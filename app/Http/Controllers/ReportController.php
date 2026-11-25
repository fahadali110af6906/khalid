<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Location;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Services\Reporting\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    /**
     * Central Reports & Analytics Dashboard.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $locationId = $request->query('location_id') ? (int) $request->query('location_id') : null;
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        $summary = $this->reportService->getDashboardSummary($user, $locationId, $fromDate, $toDate);

        $locations = $user->isOwner()
            ? Location::where('is_active', true)->orderBy('name')->get()
            : Location::whereIn('id', $user->getAuthorizedLocationIds())->orderBy('name')->get();

        return view('reports.index', [
            'summary' => $summary,
            'locations' => $locations,
            'currentLocationId' => $locationId,
            'fromDate' => $summary['date_range']['from'],
            'toDate' => $summary['date_range']['to'],
        ]);
    }

    /**
     * Sales & Wholesale Commercial Report.
     */
    public function sales(Request $request): View
    {
        $user = $request->user();
        $filters = $request->only(['from_date', 'to_date', 'location_id', 'customer_id']);

        $data = $this->reportService->getSalesReport($user, $filters);

        $locations = $user->isOwner()
            ? Location::where('is_active', true)->orderBy('name')->get()
            : Location::whereIn('id', $user->getAuthorizedLocationIds())->orderBy('name')->get();

        $customers = Customer::where('is_active', true)->orderBy('business_name')->get();

        return view('reports.sales', array_merge($data, [
            'locations' => $locations,
            'customers' => $customers,
        ]));
    }

    /**
     * Export Sales Report as CSV.
     */
    public function exportSales(Request $request): StreamedResponse
    {
        $user = $request->user();
        $filters = $request->only(['from_date', 'to_date', 'location_id', 'customer_id']);

        $data = $this->reportService->getSalesReport($user, $filters);

        $headers = [
            'Invoice #',
            'Date',
            'Customer',
            'Location',
            'Cartons',
            'Pairs',
            'Subtotal (PKR)',
            'Discount (PKR)',
            'Tax (PKR)',
            'Total Amount (PKR)',
            'Paid (PKR)',
            'Balance Due (PKR)',
            'Payment Status',
        ];

        $rows = [];
        foreach ($data['invoices'] as $inv) {
            $rows[] = [
                $inv->invoice_number,
                $inv->invoice_date->toDateString(),
                $inv->customer ? $inv->customer->business_name : 'N/A',
                $inv->location ? $inv->location->name : 'N/A',
                $inv->total_cartons,
                $inv->total_pairs,
                $inv->subtotal_amount,
                $inv->discount_amount,
                $inv->tax_amount,
                $inv->total_amount,
                $inv->paid_amount,
                $inv->balance_amount,
                ucfirst($inv->payment_status),
            ];
        }

        $filename = 'sales_report_' . ($filters['from_date'] ?? 'start') . '_to_' . ($filters['to_date'] ?? 'now') . '.csv';

        return $this->reportService->exportCsv($filename, $headers, $rows);
    }

    /**
     * Purchase & Supplier Receiving Report.
     */
    public function purchases(Request $request): View
    {
        $user = $request->user();
        $filters = $request->only(['from_date', 'to_date', 'location_id', 'supplier_id']);

        $data = $this->reportService->getPurchaseReport($user, $filters);

        $locations = $user->isOwner()
            ? Location::where('is_active', true)->orderBy('name')->get()
            : Location::whereIn('id', $user->getAuthorizedLocationIds())->orderBy('name')->get();

        $suppliers = Supplier::where('is_active', true)->orderBy('business_name')->get();

        return view('reports.purchases', array_merge($data, [
            'locations' => $locations,
            'suppliers' => $suppliers,
        ]));
    }

    /**
     * Export Purchase Report as CSV.
     */
    public function exportPurchases(Request $request): StreamedResponse
    {
        $user = $request->user();
        $filters = $request->only(['from_date', 'to_date', 'location_id', 'supplier_id']);

        $data = $this->reportService->getPurchaseReport($user, $filters);

        $headers = [
            'Receipt # (GRN)',
            'Date',
            'Supplier',
            'Location',
            'PO Reference',
            'Challan #',
            'Received Pairs',
            'Received Cost (PKR)',
        ];

        $rows = [];
        foreach ($data['receipts'] as $grn) {
            $rows[] = [
                $grn->receipt_number,
                $grn->receipt_date->toDateString(),
                $grn->supplier ? $grn->supplier->business_name : 'N/A',
                $grn->location ? $grn->location->name : 'N/A',
                $grn->purchaseOrder ? $grn->purchaseOrder->po_number : 'Direct',
                $grn->challan_number ?: 'N/A',
                $grn->total_received_pairs,
                $grn->total_received_cost,
            ];
        }

        $filename = 'purchase_report_' . ($filters['from_date'] ?? 'start') . '_to_' . ($filters['to_date'] ?? 'now') . '.csv';

        return $this->reportService->exportCsv($filename, $headers, $rows);
    }

    /**
     * Customer & Supplier Khata & Aging Reports.
     */
    public function khata(Request $request): View
    {
        $user = $request->user();
        $filters = $request->only(['from_date', 'to_date', 'location_id']);

        $customerData = $this->reportService->getCustomerKhataReport($user, $filters);
        $supplierData = $this->reportService->getSupplierKhataReport($user, $filters);

        $locations = $user->isOwner()
            ? Location::where('is_active', true)->orderBy('name')->get()
            : Location::whereIn('id', $user->getAuthorizedLocationIds())->orderBy('name')->get();

        return view('reports.khata', [
            'customerData' => $customerData,
            'supplierData' => $supplierData,
            'locations' => $locations,
            'currentLocationId' => $filters['location_id'] ?? null,
            'fromDate' => $customerData['filters']['from_date'],
            'toDate' => $customerData['filters']['to_date'],
        ]);
    }

    /**
     * Export Customer Khata & Aging as CSV.
     */
    public function exportCustomerKhata(Request $request): StreamedResponse
    {
        $user = $request->user();
        $filters = $request->only(['from_date', 'to_date', 'location_id']);

        $data = $this->reportService->getCustomerKhataReport($user, $filters);

        $headers = [
            'Customer Code',
            'Business Name',
            'Phone',
            'Assigned Location',
            'Opening Balance (PKR)',
            'Debits (Invoices/Adjustments)',
            'Credits (Payments/Credit Notes)',
            'Closing Balance (PKR)',
            'Current (0-30 Days)',
            '31-60 Days',
            '61-90 Days',
            'Over 90 Days',
        ];

        $rows = [];
        foreach ($data['customers'] as $c) {
            $rows[] = [
                $c['customer_code'],
                $c['business_name'],
                $c['phone'],
                $c['location_name'],
                $c['period_opening_balance'],
                $c['total_debit'],
                $c['total_credit'],
                $c['closing_balance'],
                $c['aging_0_30'],
                $c['aging_31_60'],
                $c['aging_61_90'],
                $c['aging_over_90'],
            ];
        }

        $filename = 'customer_khata_aging_' . ($filters['from_date'] ?? 'start') . '_to_' . ($filters['to_date'] ?? 'now') . '.csv';

        return $this->reportService->exportCsv($filename, $headers, $rows);
    }

    /**
     * Export Supplier Khata & Aging as CSV.
     */
    public function exportSupplierKhata(Request $request): StreamedResponse
    {
        $user = $request->user();
        $filters = $request->only(['from_date', 'to_date']);

        $data = $this->reportService->getSupplierKhataReport($user, $filters);

        $headers = [
            'Supplier Code',
            'Business Name',
            'Contact Person',
            'Phone',
            'Opening Balance (PKR)',
            'Credits (Receipts/Adjustments)',
            'Debits (Payments/Debit Notes)',
            'Closing Balance (PKR)',
            'Current (0-30 Days)',
            '31-60 Days',
            '61-90 Days',
            'Over 90 Days',
        ];

        $rows = [];
        foreach ($data['suppliers'] as $s) {
            $rows[] = [
                $s['supplier_code'],
                $s['business_name'],
                $s['contact_person'],
                $s['phone'],
                $s['period_opening_balance'],
                $s['total_credit'],
                $s['total_debit'],
                $s['closing_balance'],
                $s['aging_0_30'],
                $s['aging_31_60'],
                $s['aging_61_90'],
                $s['aging_over_90'],
            ];
        }

        $filename = 'supplier_khata_aging_' . ($filters['from_date'] ?? 'start') . '_to_' . ($filters['to_date'] ?? 'now') . '.csv';

        return $this->reportService->exportCsv($filename, $headers, $rows);
    }

    /**
     * Cash Book, Bank Book & Liquidity Statement.
     */
    public function cashBank(Request $request): View
    {
        $user = $request->user();
        $filters = $request->only(['from_date', 'to_date', 'location_id']);

        $data = $this->reportService->getCashBankReport($user, $filters);

        $locations = $user->isOwner()
            ? Location::where('is_active', true)->orderBy('name')->get()
            : Location::whereIn('id', $user->getAuthorizedLocationIds())->orderBy('name')->get();

        return view('reports.cash_bank', array_merge($data, [
            'locations' => $locations,
            'currentLocationId' => $filters['location_id'] ?? null,
        ]));
    }

    /**
     * Export Cash & Bank Report as CSV.
     */
    public function exportCashBank(Request $request): StreamedResponse
    {
        $user = $request->user();
        $filters = $request->only(['from_date', 'to_date', 'location_id']);

        $data = $this->reportService->getCashBankReport($user, $filters);

        $headers = [
            'Account Code',
            'Account Name',
            'Type',
            'Location',
            'Opening Balance (PKR)',
            'Total Inflows (PKR)',
            'Total Outflows (PKR)',
            'Calculated Closing (PKR)',
            'Live Stored Balance (PKR)',
            'Reconciled?',
        ];

        $rows = [];
        foreach ($data['accounts'] as $item) {
            $acc = $item['account'];
            $rows[] = [
                $acc->account_code,
                $acc->name,
                ucfirst($acc->account_type),
                $acc->location ? $acc->location->name : 'Central (All)',
                $item['opening_balance'],
                $item['total_inflow'],
                $item['total_outflow'],
                $item['closing_balance'],
                $item['live_balance'],
                $item['is_reconciled'] ? 'YES' : 'DISCREPANCY',
            ];
        }

        $filename = 'cash_bank_report_' . ($filters['from_date'] ?? 'start') . '_to_' . ($filters['to_date'] ?? 'now') . '.csv';

        return $this->reportService->exportCsv($filename, $headers, $rows);
    }

    /**
     * Expenses & Vouchers Report.
     */
    public function expenses(Request $request): View
    {
        $user = $request->user();
        $filters = $request->only(['from_date', 'to_date', 'location_id', 'expense_category_id', 'status']);

        $data = $this->reportService->getExpenseReport($user, $filters);

        $locations = $user->isOwner()
            ? Location::where('is_active', true)->orderBy('name')->get()
            : Location::whereIn('id', $user->getAuthorizedLocationIds())->orderBy('name')->get();

        $categories = ExpenseCategory::where('is_active', true)->orderBy('name')->get();

        return view('reports.expenses', array_merge($data, [
            'locations' => $locations,
            'categories' => $categories,
            'currentLocationId' => $filters['location_id'] ?? null,
            'currentCategoryId' => $filters['expense_category_id'] ?? null,
            'currentStatus' => $filters['status'] ?? null,
        ]));
    }

    /**
     * Export Expenses Report as CSV.
     */
    public function exportExpenses(Request $request): StreamedResponse
    {
        $user = $request->user();
        $filters = $request->only(['from_date', 'to_date', 'location_id', 'expense_category_id', 'status']);

        $data = $this->reportService->getExpenseReport($user, $filters);

        $headers = [
            'Voucher #',
            'Date',
            'Location',
            'Category',
            'Paid From Account',
            'Payee',
            'Description',
            'Amount (PKR)',
            'Status',
        ];

        $rows = [];
        foreach ($data['expenses'] as $exp) {
            $rows[] = [
                $exp->expense_number,
                $exp->expense_date->toDateString(),
                $exp->location ? $exp->location->name : 'N/A',
                $exp->category ? $exp->category->name : 'N/A',
                $exp->account ? $exp->account->name : 'N/A',
                $exp->payee_name ?: 'N/A',
                $exp->description,
                $exp->amount,
                strtoupper($exp->status),
            ];
        }

        $filename = 'expenses_report_' . ($filters['from_date'] ?? 'start') . '_to_' . ($filters['to_date'] ?? 'now') . '.csv';

        return $this->reportService->exportCsv($filename, $headers, $rows);
    }

    /**
     * Inventory Stock on Hand & Valuation at WAC.
     */
    public function inventory(Request $request): View
    {
        $user = $request->user();
        $filters = $request->only(['location_id', 'category_id', 'low_stock']);

        $data = $this->reportService->getInventoryReport($user, $filters);

        $locations = $user->isOwner()
            ? Location::where('is_active', true)->orderBy('name')->get()
            : Location::whereIn('id', $user->getAuthorizedLocationIds())->orderBy('name')->get();

        $categories = ProductCategory::where('is_active', true)->orderBy('name')->get();

        return view('reports.inventory', array_merge($data, [
            'locations' => $locations,
            'categories' => $categories,
            'currentLocationId' => $filters['location_id'] ?? null,
            'currentCategoryId' => $filters['category_id'] ?? null,
            'onlyLowStock' => !empty($filters['low_stock']),
        ]));
    }

    /**
     * Export Inventory Valuation as CSV.
     */
    public function exportInventory(Request $request): StreamedResponse
    {
        $user = $request->user();
        $filters = $request->only(['location_id', 'category_id', 'low_stock']);

        $data = $this->reportService->getInventoryReport($user, $filters);

        $headers = [
            'Location',
            'Article Code',
            'Product Name',
            'Category',
            'Brand',
            'Variant SKU',
            'Size',
            'Color',
            'Cartons',
            'Pairs Quantity',
            'Allocated Pairs',
            'Available Pairs',
            'WAC Unit Cost (PKR)',
            'Inventory Valuation at WAC (PKR)',
            'Wholesale Valuation (PKR)',
            'Status',
        ];

        $rows = [];
        foreach ($data['items'] as $item) {
            $rows[] = [
                $item['location_name'],
                $item['article_code'],
                $item['product_name'],
                $item['category_name'],
                $item['brand_name'],
                $item['variant_sku'],
                $item['size'] ?: '-',
                $item['color'] ?: '-',
                $item['cartons_quantity'],
                $item['pairs_quantity'],
                $item['allocated_quantity'],
                $item['available_pairs'],
                $item['avg_unit_cost'],
                $item['valuation_wac'],
                $item['valuation_wholesale'],
                $item['is_low_stock'] ? 'LOW STOCK' : 'NORMAL',
            ];
        }

        $filename = 'inventory_valuation_wac_' . now()->format('Ymd_His') . '.csv';

        return $this->reportService->exportCsv($filename, $headers, $rows);
    }

    /**
     * Operational Profit & Loss / Gross Profit Statement.
     */
    public function profitLoss(Request $request): View
    {
        $user = $request->user();
        $filters = $request->only(['from_date', 'to_date', 'location_id']);

        $data = $this->reportService->getProfitAndLossReport($user, $filters);

        $locations = $user->isOwner()
            ? Location::where('is_active', true)->orderBy('name')->get()
            : Location::whereIn('id', $user->getAuthorizedLocationIds())->orderBy('name')->get();

        return view('reports.profit_loss', array_merge($data, [
            'locations' => $locations,
            'currentLocationId' => $filters['location_id'] ?? null,
        ]));
    }

    /**
     * Export Profit & Loss Statement as CSV.
     */
    public function exportProfitLoss(Request $request): StreamedResponse
    {
        $user = $request->user();
        $filters = $request->only(['from_date', 'to_date', 'location_id']);

        $data = $this->reportService->getProfitAndLossReport($user, $filters);

        $headers = ['Accounting Line Item', 'Amount (PKR)'];

        $rows = [
            ['Gross Wholesale Sales Revenue', $data['revenue']['gross_sales']],
            ['Less: Trade Discounts', '-' . $data['revenue']['trade_discounts']],
            ['Less: Sales Returns & Allowances', '-' . $data['revenue']['sales_returns']],
            ['NET COMMERCIAL SALES REVENUE', $data['revenue']['net_sales']],
            ['', ''],
            ['Cost of Goods Sold (COGS at WAC)', $data['cogs']['gross_cogs']],
            ['Less: Restocked Returns Cost', '-' . $data['cogs']['returned_restocked_cogs']],
            ['NET COST OF GOODS SOLD (COGS)', $data['cogs']['net_cogs']],
            ['', ''],
            ['GROSS PROFIT', $data['gross_profit']['amount']],
            ['Gross Profit Margin (%)', $data['gross_profit']['margin_percent'] . '%'],
            ['', ''],
            ['OPERATING EXPENSES:', ''],
        ];

        foreach ($data['operating_expenses']['by_category'] as $cat) {
            $rows[] = ['  ' . $cat['category_name'], $cat['amount']];
        }

        $rows[] = ['TOTAL OPERATING EXPENSES', $data['operating_expenses']['total']];
        $rows[] = ['', ''];
        $rows[] = ['OPERATING NET PROFIT (EBIT)', $data['net_profit']['operating_net_profit']];
        $rows[] = ['Operating Net Profit Margin (%)', $data['net_profit']['margin_percent'] . '%'];

        $filename = 'profit_and_loss_' . ($filters['from_date'] ?? 'start') . '_to_' . ($filters['to_date'] ?? 'now') . '.csv';

        return $this->reportService->exportCsv($filename, $headers, $rows);
    }

    /**
     * Operational Cash Flow Statement.
     */
    public function cashFlow(Request $request): View
    {
        $user = $request->user();
        $filters = $request->only(['from_date', 'to_date', 'location_id']);

        $data = $this->reportService->getCashFlowReport($user, $filters);

        $locations = $user->isOwner()
            ? Location::where('is_active', true)->orderBy('name')->get()
            : Location::whereIn('id', $user->getAuthorizedLocationIds())->orderBy('name')->get();

        return view('reports.cash_flow', array_merge($data, [
            'locations' => $locations,
            'currentLocationId' => $filters['location_id'] ?? null,
        ]));
    }

    /**
     * Export Cash Flow Statement as CSV.
     */
    public function exportCashFlow(Request $request): StreamedResponse
    {
        $user = $request->user();
        $filters = $request->only(['from_date', 'to_date', 'location_id']);

        $data = $this->reportService->getCashFlowReport($user, $filters);

        $headers = ['Cash Flow Activity', 'Amount (PKR)'];

        $rows = [
            ['Opening Total Cash & Bank Liquidity', $data['opening_balance']],
            ['', ''],
            ['OPERATING CASH INFLOWS:', ''],
            ['  Customer Invoice Collections & Receipts', $data['operating_inflows']['customer_receipts']],
            ['  General Operating Income', $data['operating_inflows']['general_income']],
            ['  Counter-Reversal Inflows', $data['operating_inflows']['reversals_in']],
            ['Total Operating Inflows', $data['operating_inflows']['total']],
            ['', ''],
            ['OPERATING CASH OUTFLOWS:', ''],
            ['  Supplier Payments for Purchases', '-' . $data['operating_outflows']['supplier_payments']],
            ['  Operating Expenses & Vouchers', '-' . $data['operating_outflows']['operating_expenses']],
            ['  Customer Cash Refunds', '-' . $data['operating_outflows']['customer_refunds']],
            ['  Counter-Reversal Outflows', '-' . $data['operating_outflows']['reversals_out']],
            ['Total Operating Outflows', '-' . $data['operating_outflows']['total']],
            ['', ''],
            ['NET OPERATING CASH FLOW', $data['net_operating_cash_flow']],
            ['', ''],
            ['INTERNAL TRANSFERS (Non-Operating):', ''],
            ['  Transfers In', $data['internal_transfers']['transfers_in']],
            ['  Transfers Out', '-' . $data['internal_transfers']['transfers_out']],
            ['  Net Transfer Impact (Zero-Sum Invariant)', $data['internal_transfers']['net_effect']],
            ['', ''],
            ['Capital Setup / Opening Balances', $data['capital_injections']],
            ['CLOSING CASH & BANK LIQUIDITY', $data['closing_balance']],
        ];

        $filename = 'cash_flow_statement_' . ($filters['from_date'] ?? 'start') . '_to_' . ($filters['to_date'] ?? 'now') . '.csv';

        return $this->reportService->exportCsv($filename, $headers, $rows);
    }

    /**
     * Operational Financial Position & Accounting Blocker Disclosure Statement.
     */
    public function financialPosition(Request $request): View
    {
        $user = $request->user();
        $locationId = $request->query('location_id') ? (int) $request->query('location_id') : null;

        $data = $this->reportService->getFinancialPositionReport($user, ['location_id' => $locationId]);

        $locations = $user->isOwner()
            ? Location::where('is_active', true)->orderBy('name')->get()
            : Location::whereIn('id', $user->getAuthorizedLocationIds())->orderBy('name')->get();

        return view('reports.financial_position', array_merge($data, [
            'locations' => $locations,
            'currentLocationId' => $locationId,
        ]));
    }
}
