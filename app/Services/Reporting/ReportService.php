<?php

namespace App\Services\Reporting;

use App\Models\AccountTransaction;
use App\Models\AccountTransfer;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialAccount;
use App\Models\GoodsReceipt;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Accounting\CashAndBankService;
use App\Services\Accounting\CustomerKhataService;
use App\Services\Accounting\SupplierKhataService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportService
{
    public function __construct(
        protected CustomerKhataService $customerKhataService,
        protected SupplierKhataService $supplierKhataService,
        protected CashAndBankService $cashAndBankService
    ) {}

    /**
     * Resolve and validate location authorization for reports.
     * Returns an array of authorized location IDs, or null if owner requests global visibility.
     *
     * @throws AuthorizationException
     */
    public function resolveLocationScope(User $user, ?int $requestedLocationId = null): ?array
    {
        if ($user->isOwner()) {
            if ($requestedLocationId) {
                $location = Location::find($requestedLocationId);
                if (!$location) {
                    throw new InvalidArgumentException("Location #{$requestedLocationId} does not exist.");
                }
                return [$requestedLocationId];
            }
            return null; // Global owner scope: queries do not filter by location_id
        }

        $authorizedIds = $user->getAuthorizedLocationIds();

        if (empty($authorizedIds)) {
            throw new AuthorizationException("User #{$user->id} does not have access to any location.");
        }

        if ($requestedLocationId) {
            if (!in_array($requestedLocationId, $authorizedIds, true)) {
                throw new AuthorizationException("User #{$user->id} is not authorized to access reports for location #{$requestedLocationId}.");
            }
            return [$requestedLocationId];
        }

        return $authorizedIds;
    }

    /**
     * Parse and normalize inclusive date range.
     */
    public function resolveDateRange(?string $fromDate = null, ?string $toDate = null): array
    {
        $today = now()->toDateString();
        $startOfMonth = now()->startOfMonth()->toDateString();

        $start = !empty($fromDate) ? Carbon::parse($fromDate)->toDateString() : $startOfMonth;
        $end = !empty($toDate) ? Carbon::parse($toDate)->toDateString() : $today;

        if ($start > $end) {
            // Swap if out of order
            [$start, $end] = [$end, $start];
        }

        return [$start, $end];
    }

    /**
     * High-level Executive Dashboard Summary KPIs across modules.
     */
    public function getDashboardSummary(User $user, ?int $locationId = null, ?string $fromDate = null, ?string $toDate = null): array
    {
        $locationScope = $this->resolveLocationScope($user, $locationId);
        [$startDate, $endDate] = $this->resolveDateRange($fromDate, $toDate);

        // 1. Confirmed Sales (Posted Invoices)
        $salesQuery = SalesInvoice::where('status', 'posted')
            ->whereDate('invoice_date', '>=', $startDate)
            ->whereDate('invoice_date', '<=', $endDate)
            ->when($locationScope !== null, fn ($q) => $q->whereIn('location_id', $locationScope));

        $totalGrossSales = (string) ($salesQuery->sum('total_amount') ?: '0.00');
        $totalSalesCount = $salesQuery->count();

        // Sales Returns in range
        $salesReturnsQuery = SalesReturn::where('status', 'posted')
            ->whereDate('return_date', '>=', $startDate)
            ->whereDate('return_date', '<=', $endDate)
            ->when($locationScope !== null, fn ($q) => $q->whereIn('location_id', $locationScope));

        $totalSalesReturns = (string) ($salesReturnsQuery->sum('total_return_amount') ?: '0.00');
        $netSales = bcsub($totalGrossSales, $totalSalesReturns, 2);

        // 2. Customer Payments Received in range
        $paymentsQuery = Payment::whereDate('payment_date', '>=', $startDate)
            ->whereDate('payment_date', '<=', $endDate)
            ->when($locationScope !== null, fn ($q) => $q->whereIn('location_id', $locationScope));
        $totalCollections = (string) ($paymentsQuery->sum('amount') ?: '0.00');

        // 3. Purchases (Goods Receipts) in range
        $grnQuery = GoodsReceipt::whereDate('receipt_date', '>=', $startDate)
            ->whereDate('receipt_date', '<=', $endDate)
            ->when($locationScope !== null, fn ($q) => $q->whereIn('location_id', $locationScope));
        $totalPurchasesReceived = (string) ($grnQuery->sum('total_received_cost') ?: '0.00');
        $totalPurchasesCount = $grnQuery->count();

        // 4. Operating Expenses (Posted only) in range
        $expenseQuery = Expense::where('status', 'posted')
            ->whereDate('expense_date', '>=', $startDate)
            ->whereDate('expense_date', '<=', $endDate)
            ->when($locationScope !== null, fn ($q) => $q->whereIn('location_id', $locationScope));
        $totalExpenses = (string) ($expenseQuery->sum('amount') ?: '0.00');

        // 5. Cash & Bank Balances (Live snapshot of authorized accounts)
        $accountsQuery = FinancialAccount::where('is_active', true)
            ->when($locationScope !== null, function ($q) use ($locationScope) {
                $q->where(function ($sub) use ($locationScope) {
                    $sub->whereIn('location_id', $locationScope)
                        ->orWhereNull('location_id');
                });
            });
        $totalCashBankLiquidity = (string) ($accountsQuery->sum('current_balance') ?: '0.00');

        // 6. Stock Valuation at Weighted Average Cost (WAC)
        $stockBalancesQuery = StockBalance::when($locationScope !== null, fn ($q) => $q->whereIn('location_id', $locationScope));
        $stockBalances = $stockBalancesQuery->get();

        $totalInventoryValueWac = '0.00';
        $totalPairsInStock = '0.0000';
        foreach ($stockBalances as $sb) {
            $val = bcmul((string) $sb->pairs_quantity, (string) $sb->avg_unit_cost, 2);
            $totalInventoryValueWac = bcadd($totalInventoryValueWac, $val, 2);
            $totalPairsInStock = bcadd($totalPairsInStock, (string) $sb->pairs_quantity, 4);
        }

        // 7. Total Outstanding Customer Receivables
        $ledgerAr = CustomerLedgerEntry::when($locationScope !== null, fn ($q) => $q->whereIn('location_id', $locationScope))
            ->selectRaw('COALESCE(SUM(debit_amount) - SUM(credit_amount), 0) as bal')
            ->value('bal') ?? '0.00';

        $unpostedCustOpening = Customer::where('is_active', true)
            ->whereDoesntHave('ledgerEntries', fn ($q) => $q->where('entry_type', 'opening_balance'))
            ->when($locationScope !== null, fn ($q) => $q->whereIn('primary_location_id', $locationScope))
            ->sum('opening_balance');

        $totalReceivables = bcadd((string) $ledgerAr, (string) $unpostedCustOpening, 2);

        // 8. Total Outstanding Supplier Payables
        $ledgerAp = SupplierLedgerEntry::when($locationScope !== null, fn ($q) => $q->whereIn('location_id', $locationScope))
            ->selectRaw('COALESCE(SUM(credit_amount) - SUM(debit_amount), 0) as bal')
            ->value('bal') ?? '0.00';

        $unpostedSuppOpening = Supplier::where('is_active', true)
            ->whereDoesntHave('ledgerEntries', fn ($q) => $q->where('entry_type', 'opening_balance'))
            ->when($locationScope !== null, fn ($q) => $q->whereIn('primary_location_id', $locationScope))
            ->sum('opening_balance');

        $totalPayables = bcadd((string) $ledgerAp, (string) $unpostedSuppOpening, 2);

        // Low stock threshold count
        $lowStockCount = Product::where('is_active', true)
            ->where('reorder_threshold_cartons', '>', 0)
            ->whereHas('variants.stockBalances', function ($q) use ($locationScope) {
                $q->when($locationScope !== null, fn ($sub) => $sub->whereIn('location_id', $locationScope));
            })
            ->count();

        return [
            'date_range' => ['from' => $startDate, 'to' => $endDate],
            'location_scope' => $locationScope,
            'net_sales' => $netSales,
            'gross_sales' => $totalGrossSales,
            'sales_count' => $totalSalesCount,
            'sales_returns' => $totalSalesReturns,
            'collections' => $totalCollections,
            'purchases_received' => $totalPurchasesReceived,
            'purchases_count' => $totalPurchasesCount,
            'operating_expenses' => $totalExpenses,
            'cash_bank_liquidity' => $totalCashBankLiquidity,
            'inventory_valuation_wac' => $totalInventoryValueWac,
            'total_pairs_in_stock' => $totalPairsInStock,
            'customer_receivables' => $totalReceivables,
            'supplier_payables' => $totalPayables,
            'low_stock_count' => $lowStockCount,
        ];
    }

    /**
     * Detailed Sales Report.
     * Excludes drafts and cancelled invoices from recognized revenue totals.
     */
    public function getSalesReport(User $user, array $filters = []): array
    {
        $locationId = !empty($filters['location_id']) ? (int) $filters['location_id'] : null;
        $locationScope = $this->resolveLocationScope($user, $locationId);
        [$startDate, $endDate] = $this->resolveDateRange($filters['from_date'] ?? null, $filters['to_date'] ?? null);

        $customerId = !empty($filters['customer_id']) ? (int) $filters['customer_id'] : null;

        // Base query: strictly posted sales invoices
        $baseQuery = SalesInvoice::with(['customer', 'location'])
            ->where('status', 'posted')
            ->whereDate('invoice_date', '>=', $startDate)
            ->whereDate('invoice_date', '<=', $endDate)
            ->when($locationScope !== null, fn ($q) => $q->whereIn('location_id', $locationScope))
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId));


        // Aggregates
        $invoices = (clone $baseQuery)->orderByDesc('invoice_date')->orderByDesc('id')->get();

        $totalCartons = 0;
        $totalPairs = '0.0000';
        $subtotalAmount = '0.00';
        $discountAmount = '0.00';
        $taxAmount = '0.00';
        $totalAmount = '0.00';
        $paidAmount = '0.00';
        $balanceAmount = '0.00';

        foreach ($invoices as $inv) {
            $totalCartons += (int) $inv->total_cartons;
            $totalPairs = bcadd($totalPairs, (string) $inv->total_pairs, 4);
            $subtotalAmount = bcadd($subtotalAmount, (string) $inv->subtotal_amount, 2);
            $discountAmount = bcadd($discountAmount, (string) $inv->discount_amount, 2);
            $taxAmount = bcadd($taxAmount, (string) $inv->tax_amount, 2);
            $totalAmount = bcadd($totalAmount, (string) $inv->total_amount, 2);
            $paidAmount = bcadd($paidAmount, (string) $inv->paid_amount, 2);
            $balanceAmount = bcadd($balanceAmount, (string) $inv->balance_amount, 2);
        }

        // Returns in period
        $returnsQuery = SalesReturn::with(['customer', 'location'])
            ->where('status', 'posted')
            ->whereDate('return_date', '>=', $startDate)
            ->whereDate('return_date', '<=', $endDate)
            ->when($locationScope !== null, fn ($q) => $q->whereIn('location_id', $locationScope))
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId));

        $returns = $returnsQuery->get();
        $totalReturnsAmount = '0.00';
        $totalReturnPairs = '0.0000';
        foreach ($returns as $ret) {
            $totalReturnsAmount = bcadd($totalReturnsAmount, (string) $ret->total_return_amount, 2);
            $totalReturnPairs = bcadd($totalReturnPairs, (string) $ret->total_return_pairs, 4);
        }

        $netSalesAmount = bcsub($totalAmount, $totalReturnsAmount, 2);

        // Customer-wise breakdown
        $customerBreakdown = [];
        foreach ($invoices as $inv) {
            $cId = $inv->customer_id;
            if (!isset($customerBreakdown[$cId])) {
                $customerBreakdown[$cId] = [
                    'customer_id' => $cId,
                    'customer_code' => $inv->customer->customer_code,
                    'customer_name' => $inv->customer->business_name,
                    'invoices_count' => 0,
                    'total_pairs' => '0.0000',
                    'total_amount' => '0.00',
                    'paid_amount' => '0.00',
                    'balance_amount' => '0.00',
                ];
            }
            $customerBreakdown[$cId]['invoices_count']++;
            $customerBreakdown[$cId]['total_pairs'] = bcadd($customerBreakdown[$cId]['total_pairs'], (string) $inv->total_pairs, 4);
            $customerBreakdown[$cId]['total_amount'] = bcadd($customerBreakdown[$cId]['total_amount'], (string) $inv->total_amount, 2);
            $customerBreakdown[$cId]['paid_amount'] = bcadd($customerBreakdown[$cId]['paid_amount'], (string) $inv->paid_amount, 2);
            $customerBreakdown[$cId]['balance_amount'] = bcadd($customerBreakdown[$cId]['balance_amount'], (string) $inv->balance_amount, 2);
        }

        // Daily breakdown
        $dailyBreakdown = [];
        foreach ($invoices as $inv) {
            $date = $inv->invoice_date->toDateString();
            if (!isset($dailyBreakdown[$date])) {
                $dailyBreakdown[$date] = [
                    'date' => $date,
                    'invoices_count' => 0,
                    'total_pairs' => '0.0000',
                    'total_amount' => '0.00',
                ];
            }
            $dailyBreakdown[$date]['invoices_count']++;
            $dailyBreakdown[$date]['total_pairs'] = bcadd($dailyBreakdown[$date]['total_pairs'], (string) $inv->total_pairs, 4);
            $dailyBreakdown[$date]['total_amount'] = bcadd($dailyBreakdown[$date]['total_amount'], (string) $inv->total_amount, 2);
        }
        krsort($dailyBreakdown);

        return [
            'filters' => [
                'from_date' => $startDate,
                'to_date' => $endDate,
                'location_id' => $locationId,
                'customer_id' => $customerId,
            ],
            'summary' => [
                'invoices_count' => $invoices->count(),
                'total_cartons' => $totalCartons,
                'total_pairs' => $totalPairs,
                'subtotal_amount' => $subtotalAmount,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'balance_amount' => $balanceAmount,
                'returns_count' => $returns->count(),
                'returns_amount' => $totalReturnsAmount,
                'returns_pairs' => $totalReturnPairs,
                'net_sales_amount' => $netSalesAmount,
            ],
            'invoices' => $invoices,
            'customer_breakdown' => array_values($customerBreakdown),
            'daily_breakdown' => array_values($dailyBreakdown),
        ];
    }

    /**
     * Detailed Purchase and Receiving Report.
     * Excludes draft and cancelled POs from confirmed totals.
     */
    public function getPurchaseReport(User $user, array $filters = []): array
    {
        $locationId = !empty($filters['location_id']) ? (int) $filters['location_id'] : null;
        $locationScope = $this->resolveLocationScope($user, $locationId);
        [$startDate, $endDate] = $this->resolveDateRange($filters['from_date'] ?? null, $filters['to_date'] ?? null);

        $supplierId = !empty($filters['supplier_id']) ? (int) $filters['supplier_id'] : null;

        // 1. Goods Receipts (Confirmed inventory & supplier debt)
        $receiptsQuery = GoodsReceipt::with(['supplier', 'location', 'purchaseOrder'])
            ->whereDate('receipt_date', '>=', $startDate)
            ->whereDate('receipt_date', '<=', $endDate)
            ->when($locationScope !== null, fn ($q) => $q->whereIn('location_id', $locationScope))
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->orderByDesc('receipt_date')
            ->orderByDesc('id');

        $receipts = $receiptsQuery->get();

        $totalReceivedPairs = '0.0000';
        $totalReceivedCost = '0.00';
        foreach ($receipts as $grn) {
            $totalReceivedPairs = bcadd($totalReceivedPairs, (string) $grn->total_received_pairs, 4);
            $totalReceivedCost = bcadd($totalReceivedCost, (string) $grn->total_received_cost, 2);
        }

        // 2. Purchase Orders placed in period (Ordered/Received)
        $poQuery = PurchaseOrder::with(['supplier', 'location'])
            ->whereIn('status', ['ordered', 'partially_received', 'received'])
            ->whereDate('order_date', '>=', $startDate)
            ->whereDate('order_date', '<=', $endDate)
            ->when($locationScope !== null, fn ($q) => $q->whereIn('location_id', $locationScope))
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId));

        $orders = $poQuery->get();
        $totalOrderedAmount = '0.00';
        $totalOrderedPairs = '0.0000';
        foreach ($orders as $po) {
            $totalOrderedAmount = bcadd($totalOrderedAmount, (string) $po->total_amount, 2);
            $totalOrderedPairs = bcadd($totalOrderedPairs, (string) $po->total_pairs, 4);
        }

        // 3. Purchase Returns (Debit Notes) in period
        $returnsQuery = PurchaseReturn::with(['supplier', 'location', 'goodsReceipt'])
            ->where('status', 'posted')
            ->whereDate('return_date', '>=', $startDate)
            ->whereDate('return_date', '<=', $endDate)
            ->when($locationScope !== null, fn ($q) => $q->whereIn('location_id', $locationScope))
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId));

        $returns = $returnsQuery->get();
        $totalReturnAmount = '0.00';
        $totalReturnPairs = '0.0000';
        foreach ($returns as $pr) {
            $totalReturnAmount = bcadd($totalReturnAmount, (string) $pr->total_return_amount, 2);
            $totalReturnPairs = bcadd($totalReturnPairs, (string) $pr->total_return_pairs, 4);
        }

        // 4. Supplier Payments in period
        $paymentsQuery = SupplierPayment::with(['supplier', 'location', 'financialAccount'])
            ->whereDate('payment_date', '>=', $startDate)
            ->whereDate('payment_date', '<=', $endDate)
            ->when($locationScope !== null, fn ($q) => $q->whereIn('location_id', $locationScope))
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId));

        $payments = $paymentsQuery->get();
        $totalPaidToSuppliers = '0.00';
        foreach ($payments as $sp) {
            $totalPaidToSuppliers = bcadd($totalPaidToSuppliers, (string) $sp->amount, 2);
        }

        $netPurchasesCost = bcsub($totalReceivedCost, $totalReturnAmount, 2);

        // Supplier-wise breakdown
        $supplierBreakdown = [];
        foreach ($receipts as $grn) {
            $sId = $grn->supplier_id;
            if (!isset($supplierBreakdown[$sId])) {
                $supplierBreakdown[$sId] = [
                    'supplier_id' => $sId,
                    'supplier_code' => $grn->supplier->supplier_code,
                    'supplier_name' => $grn->supplier->business_name,
                    'receipts_count' => 0,
                    'received_pairs' => '0.0000',
                    'received_cost' => '0.00',
                    'returned_cost' => '0.00',
                    'paid_amount' => '0.00',
                    'current_khata_balance' => (string) $grn->supplier->current_balance,
                ];
            }
            $supplierBreakdown[$sId]['receipts_count']++;
            $supplierBreakdown[$sId]['received_pairs'] = bcadd($supplierBreakdown[$sId]['received_pairs'], (string) $grn->total_received_pairs, 4);
            $supplierBreakdown[$sId]['received_cost'] = bcadd($supplierBreakdown[$sId]['received_cost'], (string) $grn->total_received_cost, 2);
        }

        foreach ($returns as $pr) {
            $sId = $pr->supplier_id;
            if (isset($supplierBreakdown[$sId])) {
                $supplierBreakdown[$sId]['returned_cost'] = bcadd($supplierBreakdown[$sId]['returned_cost'], (string) $pr->total_return_amount, 2);
            }
        }

        foreach ($payments as $sp) {
            $sId = $sp->supplier_id;
            if (isset($supplierBreakdown[$sId])) {
                $supplierBreakdown[$sId]['paid_amount'] = bcadd($supplierBreakdown[$sId]['paid_amount'], (string) $sp->amount, 2);
            }
        }

        return [
            'filters' => [
                'from_date' => $startDate,
                'to_date' => $endDate,
                'location_id' => $locationId,
                'supplier_id' => $supplierId,
            ],
            'summary' => [
                'receipts_count' => $receipts->count(),
                'received_pairs' => $totalReceivedPairs,
                'received_cost' => $totalReceivedCost,
                'po_count' => $orders->count(),
                'ordered_pairs' => $totalOrderedPairs,
                'ordered_amount' => $totalOrderedAmount,
                'returns_count' => $returns->count(),
                'return_pairs' => $totalReturnPairs,
                'return_amount' => $totalReturnAmount,
                'net_purchases_cost' => $netPurchasesCost,
                'supplier_payments_total' => $totalPaidToSuppliers,
            ],
            'receipts' => $receipts,
            'supplier_breakdown' => array_values($supplierBreakdown),
        ];
    }

    /**
     * Customer Khata & Receivables Aging Report.
     */
    public function getCustomerKhataReport(User $user, array $filters = []): array
    {
        $locationId = !empty($filters['location_id']) ? (int) $filters['location_id'] : null;
        $locationScope = $this->resolveLocationScope($user, $locationId);
        [$startDate, $endDate] = $this->resolveDateRange($filters['from_date'] ?? null, $filters['to_date'] ?? null);

        $customers = Customer::with('location')
            ->where('is_active', true)
            ->when($locationScope !== null, fn ($q) => $q->whereIn('primary_location_id', $locationScope))
            ->orderBy('business_name')
            ->get();

        $rows = [];
        $totalOpening = '0.00';
        $totalDebits = '0.00';
        $totalCredits = '0.00';
        $totalClosing = '0.00';

        $totalAgingCurrent = '0.00';
        $totalAging31to60 = '0.00';
        $totalAging61to90 = '0.00';
        $totalAgingOver90 = '0.00';

        $today = Carbon::parse($endDate);

        foreach ($customers as $c) {
            $statement = $this->customerKhataService->calculateBalance($c, $locationId, $startDate, $endDate);

            $opening = (string) $statement['period_opening_balance'];
            $debit = (string) $statement['total_debit'];
            $credit = (string) $statement['total_credit'];
            $closing = (string) $statement['closing_balance'];

            $totalOpening = bcadd($totalOpening, $opening, 2);
            $totalDebits = bcadd($totalDebits, $debit, 2);
            $totalCredits = bcadd($totalCredits, $credit, 2);
            $totalClosing = bcadd($totalClosing, $closing, 2);

            // Compute Aging of outstanding posted invoices for this customer
            $unpaidInvoices = SalesInvoice::where('customer_id', $c->id)
                ->where('status', 'posted')
                ->where('balance_amount', '>', 0)
                ->whereDate('invoice_date', '<=', $endDate)
                ->get();

            $aging0to30 = '0.00';
            $aging31to60 = '0.00';
            $aging61to90 = '0.00';
            $agingOver90 = '0.00';

            foreach ($unpaidInvoices as $inv) {
                $days = $inv->invoice_date->diffInDays($today);
                $bal = (string) $inv->balance_amount;

                if ($days <= 30) {
                    $aging0to30 = bcadd($aging0to30, $bal, 2);
                } elseif ($days <= 60) {
                    $aging31to60 = bcadd($aging31to60, $bal, 2);
                } elseif ($days <= 90) {
                    $aging61to90 = bcadd($aging61to90, $bal, 2);
                } else {
                    $agingOver90 = bcadd($agingOver90, $bal, 2);
                }
            }

            $totalAgingCurrent = bcadd($totalAgingCurrent, $aging0to30, 2);
            $totalAging31to60 = bcadd($totalAging31to60, $aging31to60, 2);
            $totalAging61to90 = bcadd($totalAging61to90, $aging61to90, 2);
            $totalAgingOver90 = bcadd($totalAgingOver90, $agingOver90, 2);

            $rows[] = [
                'customer_id' => $c->id,
                'customer_code' => $c->customer_code,
                'business_name' => $c->business_name,
                'phone' => $c->phone,
                'location_name' => $c->location ? $c->location->name : 'All / Central',
                'credit_limit' => (string) $c->credit_limit,
                'period_opening_balance' => $opening,
                'total_debit' => $debit,
                'total_credit' => $credit,
                'closing_balance' => $closing,
                'aging_0_30' => $aging0to30,
                'aging_31_60' => $aging31to60,
                'aging_61_90' => $aging61to90,
                'aging_over_90' => $agingOver90,
            ];
        }

        return [
            'filters' => [
                'from_date' => $startDate,
                'to_date' => $endDate,
                'location_id' => $locationId,
            ],
            'summary' => [
                'customers_count' => count($rows),
                'total_opening' => $totalOpening,
                'total_debits' => $totalDebits,
                'total_credits' => $totalCredits,
                'total_closing' => $totalClosing,
                'aging_0_30' => $totalAgingCurrent,
                'aging_31_60' => $totalAging31to60,
                'aging_61_90' => $totalAging61to90,
                'aging_over_90' => $totalAgingOver90,
            ],
            'customers' => $rows,
        ];
    }

    /**
     * Supplier Khata & Payables Aging Report.
     */
    public function getSupplierKhataReport(User $user, array $filters = []): array
    {
        [$startDate, $endDate] = $this->resolveDateRange($filters['from_date'] ?? null, $filters['to_date'] ?? null);

        $suppliers = Supplier::where('is_active', true)->orderBy('business_name')->get();

        $rows = [];
        $totalOpening = '0.00';
        $totalCredits = '0.00';
        $totalDebits = '0.00';
        $totalClosing = '0.00';

        $totalAgingCurrent = '0.00';
        $totalAging31to60 = '0.00';
        $totalAging61to90 = '0.00';
        $totalAgingOver90 = '0.00';

        $today = Carbon::parse($endDate);

        foreach ($suppliers as $s) {
            $statement = $this->supplierKhataService->calculateBalance($s, null, $startDate, $endDate);

            $opening = (string) $statement['period_opening_balance'];
            $credit = (string) $statement['total_credit'];
            $debit = (string) $statement['total_debit'];
            $closing = (string) $statement['closing_balance'];

            $totalOpening = bcadd($totalOpening, $opening, 2);
            $totalCredits = bcadd($totalCredits, $credit, 2);
            $totalDebits = bcadd($totalDebits, $debit, 2);
            $totalClosing = bcadd($totalClosing, $closing, 2);

            // Compute Aging from ledger entries where entry_type = 'goods_receipt'
            $receiptEntries = SupplierLedgerEntry::where('supplier_id', $s->id)
                ->where('entry_type', 'goods_receipt')
                ->whereDate('entry_date', '<=', $endDate)
                ->orderByDesc('entry_date')
                ->get();

            $aging0to30 = '0.00';
            $aging31to60 = '0.00';
            $aging61to90 = '0.00';
            $agingOver90 = '0.00';

            // Aging bucket approximation based on closing liability vs recent receipt dates
            $remainingLiability = (float) $closing;
            if ($remainingLiability > 0) {
                foreach ($receiptEntries as $re) {
                    if ($remainingLiability <= 0) break;
                    $days = $re->entry_date->diffInDays($today);
                    $chunk = min($remainingLiability, (float) $re->credit_amount);
                    $chunkStr = number_format($chunk, 2, '.', '');

                    if ($days <= 30) {
                        $aging0to30 = bcadd($aging0to30, $chunkStr, 2);
                    } elseif ($days <= 60) {
                        $aging31to60 = bcadd($aging31to60, $chunkStr, 2);
                    } elseif ($days <= 90) {
                        $aging61to90 = bcadd($aging61to90, $chunkStr, 2);
                    } else {
                        $agingOver90 = bcadd($agingOver90, $chunkStr, 2);
                    }
                    $remainingLiability -= $chunk;
                }
                if ($remainingLiability > 0) {
                    $agingOver90 = bcadd($agingOver90, number_format($remainingLiability, 2, '.', ''), 2);
                }
            }

            $totalAgingCurrent = bcadd($totalAgingCurrent, $aging0to30, 2);
            $totalAging31to60 = bcadd($totalAging31to60, $aging31to60, 2);
            $totalAging61to90 = bcadd($totalAging61to90, $aging61to90, 2);
            $totalAgingOver90 = bcadd($totalAgingOver90, $agingOver90, 2);

            $rows[] = [
                'supplier_id' => $s->id,
                'supplier_code' => $s->supplier_code,
                'business_name' => $s->business_name,
                'contact_person' => $s->contact_person,
                'phone' => $s->phone,
                'period_opening_balance' => $opening,
                'total_credit' => $credit,
                'total_debit' => $debit,
                'closing_balance' => $closing,
                'aging_0_30' => $aging0to30,
                'aging_31_60' => $aging31to60,
                'aging_61_90' => $aging61to90,
                'aging_over_90' => $agingOver90,
            ];
        }

        return [
            'filters' => [
                'from_date' => $startDate,
                'to_date' => $endDate,
            ],
            'summary' => [
                'suppliers_count' => count($rows),
                'total_opening' => $totalOpening,
                'total_credits' => $totalCredits,
                'total_debits' => $totalDebits,
                'total_closing' => $totalClosing,
                'aging_0_30' => $totalAgingCurrent,
                'aging_31_60' => $totalAging31to60,
                'aging_61_90' => $totalAging61to90,
                'aging_over_90' => $totalAgingOver90,
            ],
            'suppliers' => $rows,
        ];
    }

    /**
     * Cash Book, Bank Book & Financial Account Statement.
     */
    public function getCashBankReport(User $user, array $filters = []): array
    {
        $locationId = !empty($filters['location_id']) ? (int) $filters['location_id'] : null;
        $locationScope = $this->resolveLocationScope($user, $locationId);
        [$startDate, $endDate] = $this->resolveDateRange($filters['from_date'] ?? null, $filters['to_date'] ?? null);

        $accounts = FinancialAccount::with('location')
            ->where('is_active', true)
            ->when($locationScope !== null, function ($q) use ($locationScope) {
                $q->where(function ($sub) use ($locationScope) {
                    $sub->whereIn('location_id', $locationScope)
                        ->orWhereNull('location_id');
                });
            })
            ->orderBy('account_type')
            ->orderBy('name')
            ->get();

        $accountStatements = [];
        $totalOpening = '0.00';
        $totalInflows = '0.00';
        $totalOutflows = '0.00';
        $totalClosing = '0.00';
        $totalCashBal = '0.00';
        $totalBankBal = '0.00';

        foreach ($accounts as $acc) {
            $stmt = $this->cashAndBankService->calculateBalance($acc, $startDate, $endDate);

            $op = (string) $stmt['period_opening_balance'];
            $in = (string) $stmt['total_inflow'];
            $out = (string) $stmt['total_outflow'];
            $cl = (string) $stmt['closing_balance'];
            $live = (string) $stmt['live_account_balance'];
            $reconciled = $stmt['is_reconciled'];

            $totalOpening = bcadd($totalOpening, $op, 2);
            $totalInflows = bcadd($totalInflows, $in, 2);
            $totalOutflows = bcadd($totalOutflows, $out, 2);
            $totalClosing = bcadd($totalClosing, $cl, 2);

            if ($acc->isCash()) {
                $totalCashBal = bcadd($totalCashBal, $live, 2);
            } else {
                $totalBankBal = bcadd($totalBankBal, $live, 2);
            }

            $accountStatements[] = [
                'account' => $acc,
                'opening_balance' => $op,
                'total_inflow' => $in,
                'total_outflow' => $out,
                'closing_balance' => $cl,
                'live_balance' => $live,
                'is_reconciled' => $reconciled,
                'transactions_count' => $stmt['transactions_count'],
            ];
        }

        // Account Transfers in period
        $transfers = AccountTransfer::with(['sourceAccount', 'destinationAccount', 'creator'])
            ->where('status', 'posted')
            ->whereDate('transfer_date', '>=', $startDate)
            ->whereDate('transfer_date', '<=', $endDate)
            ->when($locationScope !== null, function ($q) use ($locationScope) {
                $q->where(function ($sub) use ($locationScope) {
                    $sub->whereHas('sourceAccount', fn ($s) => $s->whereIn('location_id', $locationScope))
                        ->orWhereHas('destinationAccount', fn ($d) => $d->whereIn('location_id', $locationScope));
                });
            })
            ->orderByDesc('transfer_date')
            ->orderByDesc('id')
            ->get();

        $totalTransferredAmount = '0.00';
        foreach ($transfers as $tr) {
            $totalTransferredAmount = bcadd($totalTransferredAmount, (string) $tr->amount, 2);
        }

        return [
            'filters' => [
                'from_date' => $startDate,
                'to_date' => $endDate,
                'location_id' => $locationId,
            ],
            'summary' => [
                'accounts_count' => count($accountStatements),
                'total_opening' => $totalOpening,
                'total_inflows' => $totalInflows,
                'total_outflows' => $totalOutflows,
                'total_closing' => $totalClosing,
                'total_cash_balance' => $totalCashBal,
                'total_bank_balance' => $totalBankBal,
                'total_liquidity' => bcadd($totalCashBal, $totalBankBal, 2),
                'transfers_count' => $transfers->count(),
                'total_transfers_amount' => $totalTransferredAmount,
            ],
            'accounts' => $accountStatements,
            'transfers' => $transfers,
        ];
    }

    /**
     * Detailed Expenses Report.
     * Categorizes posted vs draft vs reversed expenses without double-counting reversals.
     */
    public function getExpenseReport(User $user, array $filters = []): array
    {
        $locationId = !empty($filters['location_id']) ? (int) $filters['location_id'] : null;
        $locationScope = $this->resolveLocationScope($user, $locationId);
        [$startDate, $endDate] = $this->resolveDateRange($filters['from_date'] ?? null, $filters['to_date'] ?? null);

        $categoryId = !empty($filters['expense_category_id']) ? (int) $filters['expense_category_id'] : null;
        $status = !empty($filters['status']) ? (string) $filters['status'] : null;

        $query = Expense::with(['category', 'account', 'location', 'creator'])
            ->whereDate('expense_date', '>=', $startDate)
            ->whereDate('expense_date', '<=', $endDate)
            ->when($locationScope !== null, fn ($q) => $q->whereIn('location_id', $locationScope))
            ->when($categoryId, fn ($q) => $q->where('expense_category_id', $categoryId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('expense_date')
            ->orderByDesc('id');

        $expenses = $query->get();

        $postedTotal = '0.00';
        $postedCount = 0;
        $draftTotal = '0.00';
        $draftCount = 0;
        $reversedTotal = '0.00';
        $reversedCount = 0;

        $categoryBreakdown = [];
        $accountBreakdown = [];

        foreach ($expenses as $exp) {
            $amt = (string) $exp->amount;
            if ($exp->status === 'posted') {
                $postedTotal = bcadd($postedTotal, $amt, 2);
                $postedCount++;

                // Group posted expenses by category
                $catId = $exp->expense_category_id;
                $catName = $exp->category ? $exp->category->name : 'Uncategorized';
                if (!isset($categoryBreakdown[$catId])) {
                    $categoryBreakdown[$catId] = [
                        'category_id' => $catId,
                        'name' => $catName,
                        'count' => 0,
                        'amount' => '0.00',
                    ];
                }
                $categoryBreakdown[$catId]['count']++;
                $categoryBreakdown[$catId]['amount'] = bcadd($categoryBreakdown[$catId]['amount'], $amt, 2);

                // Group posted expenses by account
                $accId = $exp->financial_account_id;
                $accName = $exp->account ? $exp->account->name : 'Unknown Account';
                if (!isset($accountBreakdown[$accId])) {
                    $accountBreakdown[$accId] = [
                        'account_id' => $accId,
                        'name' => $accName,
                        'count' => 0,
                        'amount' => '0.00',
                    ];
                }
                $accountBreakdown[$accId]['count']++;
                $accountBreakdown[$accId]['amount'] = bcadd($accountBreakdown[$accId]['amount'], $amt, 2);
            } elseif ($exp->status === 'draft') {
                $draftTotal = bcadd($draftTotal, $amt, 2);
                $draftCount++;
            } elseif ($exp->status === 'reversed') {
                $reversedTotal = bcadd($reversedTotal, $amt, 2);
                $reversedCount++;
            }
        }

        return [
            'filters' => [
                'from_date' => $startDate,
                'to_date' => $endDate,
                'location_id' => $locationId,
                'expense_category_id' => $categoryId,
                'status' => $status,
            ],
            'summary' => [
                'total_count' => $expenses->count(),
                'posted_count' => $postedCount,
                'posted_amount' => $postedTotal,
                'draft_count' => $draftCount,
                'draft_amount' => $draftTotal,
                'reversed_count' => $reversedCount,
                'reversed_amount' => $reversedTotal,
            ],
            'expenses' => $expenses,
            'category_breakdown' => array_values($categoryBreakdown),
            'account_breakdown' => array_values($accountBreakdown),
        ];
    }

    /**
     * Stock on Hand, Low Stock & Inventory Valuation at Weighted Average Cost (WAC).
     */
    public function getInventoryReport(User $user, array $filters = []): array
    {
        $locationId = !empty($filters['location_id']) ? (int) $filters['location_id'] : null;
        $locationScope = $this->resolveLocationScope($user, $locationId);

        $categoryId = !empty($filters['category_id']) ? (int) $filters['category_id'] : null;
        $onlyLowStock = !empty($filters['low_stock']);

        $balancesQuery = StockBalance::with([
            'location',
            'productVariant.product.category',
            'productVariant.product.brand',
        ])
        ->when($locationScope !== null, fn ($q) => $q->whereIn('location_id', $locationScope))
        ->when($categoryId, function ($q) use ($categoryId) {
            $q->whereHas('productVariant.product', fn ($p) => $p->where('category_id', $categoryId));
        });

        $stockBalances = $balancesQuery->get();

        $rows = [];
        $totalPairs = '0.0000';
        $totalAllocatedPairs = '0.0000';
        $totalCartons = 0;
        $totalValuationWac = '0.00';
        $totalValuationWholesale = '0.00';
        $lowStockItemsCount = 0;

        foreach ($stockBalances as $sb) {
            $variant = $sb->productVariant;
            if (!$variant || !$variant->product) continue;

            $product = $variant->product;
            $packRatio = $product->carton_pack_qty > 0 ? $product->carton_pack_qty : 12;

            $pairs = (string) $sb->pairs_quantity;
            $allocated = (string) $sb->allocated_quantity;
            $avgCost = (string) $sb->avg_unit_cost;

            $cartons = (float) bcdiv($pairs, (string) $packRatio, 2);

            $valWac = bcmul($pairs, $avgCost, 2);

            $wholesaleRatePerPair = bcdiv((string) $product->default_wholesale_rate, (string) $packRatio, 4);
            $valWholesale = bcmul($pairs, (string) $wholesaleRatePerPair, 2);

            $isLowStock = ($product->reorder_threshold_cartons > 0 && $cartons <= $product->reorder_threshold_cartons);
            if ($isLowStock) {
                $lowStockItemsCount++;
            }

            if ($onlyLowStock && !$isLowStock) {
                continue;
            }

            $totalPairs = bcadd($totalPairs, $pairs, 4);
            $totalAllocatedPairs = bcadd($totalAllocatedPairs, $allocated, 4);
            $totalCartons += (int) floor($cartons);
            $totalValuationWac = bcadd($totalValuationWac, $valWac, 2);
            $totalValuationWholesale = bcadd($totalValuationWholesale, $valWholesale, 2);

            $rows[] = [
                'id' => $sb->id,
                'location_id' => $sb->location_id,
                'location_name' => $sb->location ? $sb->location->name : 'N/A',
                'product_id' => $product->id,
                'article_code' => $product->article_code,
                'product_name' => $product->name,
                'category_name' => $product->category ? $product->category->name : 'N/A',
                'brand_name' => $product->brand ? $product->brand->name : 'N/A',
                'variant_sku' => $variant->sku,
                'size' => $variant->size,
                'color' => $variant->color,
                'pack_ratio' => $packRatio,
                'pairs_quantity' => $pairs,
                'cartons_quantity' => $cartons,
                'allocated_quantity' => $allocated,
                'available_pairs' => (string) $sb->available_pairs,
                'avg_unit_cost' => $avgCost,
                'valuation_wac' => $valWac,
                'wholesale_rate_carton' => (string) $product->default_wholesale_rate,
                'valuation_wholesale' => $valWholesale,
                'reorder_threshold' => $product->reorder_threshold_cartons,
                'is_low_stock' => $isLowStock,
            ];
        }

        return [
            'filters' => [
                'location_id' => $locationId,
                'category_id' => $categoryId,
                'low_stock' => $onlyLowStock,
            ],
            'summary' => [
                'variants_count' => count($rows),
                'total_pairs' => $totalPairs,
                'total_cartons' => $totalCartons,
                'total_allocated_pairs' => $totalAllocatedPairs,
                'total_valuation_wac' => $totalValuationWac,
                'total_valuation_wholesale' => $totalValuationWholesale,
                'low_stock_count' => $lowStockItemsCount,
            ],
            'items' => $rows,
        ];
    }

    /**
     * Operational Profit & Loss / Gross Profit Statement.
     * Uses actual posted sales, discounts, returns, inventory WAC at deduction, and posted expenses.
     * Clearly labels accounting scope and limitations.
     */
    public function getProfitAndLossReport(User $user, array $filters = []): array
    {
        $locationId = !empty($filters['location_id']) ? (int) $filters['location_id'] : null;
        $locationScope = $this->resolveLocationScope($user, $locationId);
        [$startDate, $endDate] = $this->resolveDateRange($filters['from_date'] ?? null, $filters['to_date'] ?? null);

        // 1. Gross Sales Revenue from posted sales invoices
        $invoices = SalesInvoice::with('items')
            ->where('status', 'posted')
            ->whereDate('invoice_date', '>=', $startDate)
            ->whereDate('invoice_date', '<=', $endDate)
            ->when($locationScope !== null, fn ($q) => $q->whereIn('location_id', $locationScope))
            ->get();

        $grossSales = '0.00';
        $tradeDiscounts = '0.00';
        $grossCogs = '0.00';

        foreach ($invoices as $inv) {
            $grossSales = bcadd($grossSales, (string) $inv->subtotal_amount, 2);
            $tradeDiscounts = bcadd($tradeDiscounts, (string) $inv->discount_amount, 2);

            foreach ($inv->items as $item) {
                // Cost of goods sold based on WAC unit cost recorded at time of deduction
                $lineCogs = bcmul((string) $item->quantity_pairs, (string) $item->unit_cost, 2);
                $grossCogs = bcadd($grossCogs, $lineCogs, 2);
            }
        }

        // 2. Sales Returns in period (Reduces recognized sales and restocks COGS)
        $returns = SalesReturn::with('items')
            ->where('status', 'posted')
            ->whereDate('return_date', '>=', $startDate)
            ->whereDate('return_date', '<=', $endDate)
            ->when($locationScope !== null, fn ($q) => $q->whereIn('location_id', $locationScope))
            ->get();

        $salesReturnsAmount = '0.00';
        $returnedCogs = '0.00';

        foreach ($returns as $ret) {
            $salesReturnsAmount = bcadd($salesReturnsAmount, (string) $ret->total_return_amount, 2);
            foreach ($ret->items as $rItem) {
                $rLineCost = bcmul((string) $rItem->quantity_pairs, (string) $rItem->unit_cost, 2);
                $returnedCogs = bcadd($returnedCogs, $rLineCost, 2);
            }
        }

        // Net Commercial Sales Revenue: Gross Sales - Trade Discounts - Sales Returns
        $netSales = bcsub(bcsub($grossSales, $tradeDiscounts, 2), $salesReturnsAmount, 2);

        // Net Cost of Goods Sold: Gross COGS - Returned Restocked COGS
        $netCogs = bcsub($grossCogs, $returnedCogs, 2);

        // Gross Profit: Net Sales - Net COGS
        $grossProfit = bcsub($netSales, $netCogs, 2);
        $grossProfitMargin = (bccomp($netSales, '0.00', 2) > 0)
            ? bcmul(bcdiv($grossProfit, $netSales, 4), '100', 2)
            : '0.00';

        // 3. Operating Expenses (Posted Vouchers Only)
        $expenses = Expense::with('category')
            ->where('status', 'posted')
            ->whereDate('expense_date', '>=', $startDate)
            ->whereDate('expense_date', '<=', $endDate)
            ->when($locationScope !== null, fn ($q) => $q->whereIn('location_id', $locationScope))
            ->get();

        $totalOperatingExpenses = '0.00';
        $expensesByCategory = [];

        foreach ($expenses as $exp) {
            $amt = (string) $exp->amount;
            $totalOperatingExpenses = bcadd($totalOperatingExpenses, $amt, 2);

            $cId = $exp->expense_category_id;
            $cName = $exp->category ? $exp->category->name : 'General Expense';
            if (!isset($expensesByCategory[$cId])) {
                $expensesByCategory[$cId] = [
                    'category_id' => $cId,
                    'category_name' => $cName,
                    'amount' => '0.00',
                ];
            }
            $expensesByCategory[$cId]['amount'] = bcadd($expensesByCategory[$cId]['amount'], $amt, 2);
        }

        // 4. Operating Net Profit (EBIT): Gross Profit - Total Operating Expenses
        $operatingNetProfit = bcsub($grossProfit, $totalOperatingExpenses, 2);
        $netProfitMargin = (bccomp($netSales, '0.00', 2) > 0)
            ? bcmul(bcdiv($operatingNetProfit, $netSales, 4), '100', 2)
            : '0.00';

        return [
            'filters' => [
                'from_date' => $startDate,
                'to_date' => $endDate,
                'location_id' => $locationId,
            ],
            'revenue' => [
                'gross_sales' => $grossSales,
                'trade_discounts' => $tradeDiscounts,
                'sales_returns' => $salesReturnsAmount,
                'net_sales' => $netSales,
            ],
            'cogs' => [
                'gross_cogs' => $grossCogs,
                'returned_restocked_cogs' => $returnedCogs,
                'net_cogs' => $netCogs,
            ],
            'gross_profit' => [
                'amount' => $grossProfit,
                'margin_percent' => $grossProfitMargin,
            ],
            'operating_expenses' => [
                'total' => $totalOperatingExpenses,
                'by_category' => array_values($expensesByCategory),
            ],
            'net_profit' => [
                'operating_net_profit' => $operatingNetProfit,
                'margin_percent' => $netProfitMargin,
            ],
            'accounting_limitations' => [
                'model' => 'Operational Trading P&L (Sub-Ledger Based)',
                'is_formal_audit' => false,
                'disclaimer' => 'This statement is an operational trading summary derived from sales invoices, sales returns, inventory WAC movements, and posted expense vouchers. Formal balance sheet depreciation, loan interest, and statutory tax provisions require a complete double-entry general ledger.',
            ],
        ];
    }

    /**
     * Operational Cash Flow Statement.
     * Categorizes verified inflows and outflows from account_transactions.
     * Excludes internal transfers from net cash flow.
     */
    public function getCashFlowReport(User $user, array $filters = []): array
    {
        $locationId = !empty($filters['location_id']) ? (int) $filters['location_id'] : null;
        $locationScope = $this->resolveLocationScope($user, $locationId);
        [$startDate, $endDate] = $this->resolveDateRange($filters['from_date'] ?? null, $filters['to_date'] ?? null);

        // Account list
        $accounts = FinancialAccount::where('is_active', true)
            ->when($locationScope !== null, function ($q) use ($locationScope) {
                $q->where(function ($sub) use ($locationScope) {
                    $sub->whereIn('location_id', $locationScope)
                        ->orWhereNull('location_id');
                });
            })
            ->pluck('id')
            ->all();

        // 1. Opening Cash & Bank Balance before startDate
        $priorTxns = AccountTransaction::whereIn('financial_account_id', $accounts)
            ->whereDate('transaction_date', '<', $startDate)
            ->get();

        $openingCashBalance = '0.00';
        foreach ($priorTxns as $pt) {
            $openingCashBalance = bcsub(bcadd($openingCashBalance, (string) $pt->debit_amount, 2), (string) $pt->credit_amount, 2);
        }

        // 2. Period Transactions
        $periodTxns = AccountTransaction::whereIn('financial_account_id', $accounts)
            ->whereDate('transaction_date', '>=', $startDate)
            ->whereDate('transaction_date', '<=', $endDate)
            ->get();

        // Operating Inflows
        $customerReceipts = '0.00';
        $generalIncome = '0.00';
        $openingBalanceCapital = '0.00';

        // Operating Outflows
        $supplierPayments = '0.00';
        $operatingExpenses = '0.00';
        $customerRefunds = '0.00';

        // Internal Transfers (Non-operating)
        $transfersIn = '0.00';
        $transfersOut = '0.00';

        // Reversals
        $reversalsIn = '0.00';
        $reversalsOut = '0.00';

        foreach ($periodTxns as $tx) {
            $debit = (string) $tx->debit_amount;
            $credit = (string) $tx->credit_amount;

            switch ($tx->transaction_type) {
                case 'customer_receipt':
                case 'cash_receipt':
                    $customerReceipts = bcadd($customerReceipts, $debit, 2);
                    break;
                case 'general_income':
                    $generalIncome = bcadd($generalIncome, $debit, 2);
                    break;
                case 'opening_balance':
                    $openingBalanceCapital = bcadd($openingBalanceCapital, $debit, 2);
                    break;
                case 'supplier_payment':
                case 'cash_payment':
                    $supplierPayments = bcadd($supplierPayments, $credit, 2);
                    break;
                case 'expense':
                    $operatingExpenses = bcadd($operatingExpenses, $credit, 2);
                    break;
                case 'customer_refund':
                    $customerRefunds = bcadd($customerRefunds, $credit, 2);
                    break;
                case 'transfer_in':
                    $transfersIn = bcadd($transfersIn, $debit, 2);
                    break;
                case 'transfer_out':
                    $transfersOut = bcadd($transfersOut, $credit, 2);
                    break;
                case 'reversal':
                    $reversalsIn = bcadd($reversalsIn, $debit, 2);
                    $reversalsOut = bcadd($reversalsOut, $credit, 2);
                    break;
            }
        }

        // Total Operating Inflows
        $totalOperatingInflows = bcadd(bcadd($customerReceipts, $generalIncome, 2), $reversalsIn, 2);

        // Total Operating Outflows
        $totalOperatingOutflows = bcadd(bcadd(bcadd($supplierPayments, $operatingExpenses, 2), $customerRefunds, 2), $reversalsOut, 2);

        // Net Operating Cash Flow
        $netOperatingCashFlow = bcsub($totalOperatingInflows, $totalOperatingOutflows, 2);

        // Net Transfer impact: transfersIn - transfersOut = 0.00 (verified)
        $netTransferImpact = bcsub($transfersIn, $transfersOut, 2);

        // Ending Cash & Bank Balance
        $closingCashBalance = bcadd(bcadd($openingCashBalance, $netOperatingCashFlow, 2), $openingBalanceCapital, 2);

        return [
            'filters' => [
                'from_date' => $startDate,
                'to_date' => $endDate,
                'location_id' => $locationId,
            ],
            'opening_balance' => $openingCashBalance,
            'operating_inflows' => [
                'customer_receipts' => $customerReceipts,
                'general_income' => $generalIncome,
                'reversals_in' => $reversalsIn,
                'total' => $totalOperatingInflows,
            ],
            'operating_outflows' => [
                'supplier_payments' => $supplierPayments,
                'operating_expenses' => $operatingExpenses,
                'customer_refunds' => $customerRefunds,
                'reversals_out' => $reversalsOut,
                'total' => $totalOperatingOutflows,
            ],
            'net_operating_cash_flow' => $netOperatingCashFlow,
            'internal_transfers' => [
                'transfers_in' => $transfersIn,
                'transfers_out' => $transfersOut,
                'net_effect' => $netTransferImpact,
                'note' => 'Internal account transfers have zero net impact on total business cash.',
            ],
            'capital_injections' => $openingBalanceCapital,
            'closing_balance' => $closingCashBalance,
        ];
    }

    /**
     * Operational Financial Position & Audit Disclosure Statement.
     * Documents verified current assets/liabilities and explains why formal
     * Trial Balance / Balance Sheet requires a full General Ledger.
     */
    public function getFinancialPositionReport(User $user, array $filters = []): array
    {
        $locationId = !empty($filters['location_id']) ? (int) $filters['location_id'] : null;
        $locationScope = $this->resolveLocationScope($user, $locationId);

        // 1. Current Assets
        // Cash & Bank
        $accounts = FinancialAccount::where('is_active', true)
            ->when($locationScope !== null, function ($q) use ($locationScope) {
                $q->where(function ($sub) use ($locationScope) {
                    $sub->whereIn('location_id', $locationScope)
                        ->orWhereNull('location_id');
                });
            })
            ->get();
        $cashBankTotal = (string) ($accounts->sum('current_balance') ?: '0.00');

        // Trade Receivables
        $ledgerAr = CustomerLedgerEntry::when($locationScope !== null, fn ($q) => $q->whereIn('location_id', $locationScope))
            ->selectRaw('COALESCE(SUM(debit_amount) - SUM(credit_amount), 0) as bal')
            ->value('bal') ?? '0.00';

        $unpostedCustOpening = Customer::where('is_active', true)
            ->whereDoesntHave('ledgerEntries', fn ($q) => $q->where('entry_type', 'opening_balance'))
            ->when($locationScope !== null, fn ($q) => $q->whereIn('primary_location_id', $locationScope))
            ->sum('opening_balance');

        $receivablesTotal = bcadd((string) $ledgerAr, (string) $unpostedCustOpening, 2);

        // Merchandise Inventory at WAC
        $stockBalances = StockBalance::when($locationScope !== null, fn ($q) => $q->whereIn('location_id', $locationScope))->get();
        $inventoryWacTotal = '0.00';
        foreach ($stockBalances as $sb) {
            $inventoryWacTotal = bcadd($inventoryWacTotal, bcmul((string) $sb->pairs_quantity, (string) $sb->avg_unit_cost, 2), 2);
        }

        $totalCurrentAssets = bcadd(bcadd($cashBankTotal, $receivablesTotal, 2), $inventoryWacTotal, 2);

        // 2. Current Liabilities
        // Trade Payables
        $ledgerAp = SupplierLedgerEntry::when($locationScope !== null, fn ($q) => $q->whereIn('location_id', $locationScope))
            ->selectRaw('COALESCE(SUM(credit_amount) - SUM(debit_amount), 0) as bal')
            ->value('bal') ?? '0.00';

        $unpostedSuppOpening = Supplier::where('is_active', true)
            ->whereDoesntHave('ledgerEntries', fn ($q) => $q->where('entry_type', 'opening_balance'))
            ->when($locationScope !== null, fn ($q) => $q->whereIn('primary_location_id', $locationScope))
            ->sum('opening_balance');

        $payablesTotal = bcadd((string) $ledgerAp, (string) $unpostedSuppOpening, 2);
        $totalCurrentLiabilities = $payablesTotal;

        // Working Capital
        $netWorkingCapital = bcsub($totalCurrentAssets, $totalCurrentLiabilities, 2);

        return [
            'assets' => [
                'cash_and_bank' => $cashBankTotal,
                'accounts_receivable' => $receivablesTotal,
                'merchandise_inventory_wac' => $inventoryWacTotal,
                'total_current_assets' => $totalCurrentAssets,
            ],
            'liabilities' => [
                'accounts_payable' => $payablesTotal,
                'total_current_liabilities' => $totalCurrentLiabilities,
            ],
            'working_capital' => $netWorkingCapital,
            'accounting_foundation_status' => [
                'has_chart_of_accounts' => false,
                'has_general_ledger' => false,
                'has_journal_entries' => false,
                'audit_blocker_reason' => 'Khalid Traders ERP is structured around dedicated operational sub-ledgers (Customer Khata, Supplier Khata, Cash/Bank Ledger, and Inventory WAC). A formal statutory Balance Sheet and Trial Balance require a double-entry General Ledger and Chart of Accounts to avoid fabricating non-cash items, fixed assets, retained earnings, and equity.',
            ],
        ];
    }

    /**
     * Generate secure, formula-injection-safe CSV export stream.
     */
    public function exportCsv(string $filename, array $headers, array $rows): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for proper Urdu/Arabic character display in Excel
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Output headers
            fputcsv($handle, $headers);

            // Output rows with sanitization against spreadsheet formula injection
            foreach ($rows as $row) {
                $sanitizedRow = array_map(function ($value) {
                    return $this->sanitizeCsvCell($value);
                }, $row);
                fputcsv($handle, $sanitizedRow);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    /**
     * Neutralize CSV formula injection attacks (=, +, -, @, tab, cr).
     */
    public function sanitizeCsvCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $str = (string) $value;

        // If cell starts with formula trigger characters, prefix with single quote
        if (strlen($str) > 0 && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $str;
        }

        return $str;
    }
}
