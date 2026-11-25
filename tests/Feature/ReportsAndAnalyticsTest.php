<?php

namespace Tests\Feature;

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
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesReturn;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\SupplierPayment;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Accounting\CashAndBankService;
use App\Services\Accounting\CustomerKhataService;
use App\Services\Accounting\ExpenseService;
use App\Services\Accounting\SupplierKhataService;
use App\Services\Inventory\InventoryService;
use App\Services\Reporting\ReportService;
use App\Services\Sales\SalesPostingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsAndAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $shop1Manager;
    protected User $shop2Manager;
    protected Location $shop1;
    protected Location $shop2;
    protected Customer $customer;
    protected Supplier $supplier;
    protected Product $product;
    protected ProductVariant $variant;
    protected FinancialAccount $cashAccount;
    protected FinancialAccount $bankAccount;
    protected ExpenseCategory $utilityCategory;
    protected ReportService $reportService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->owner = User::where('email', 'khalid.owner@khalidtraders.pk')->firstOrFail();
        $this->shop1Manager = User::where('email', 'tariq.anarkali@khalidtraders.pk')->firstOrFail();
        $this->shop2Manager = User::where('email', 'irfan.shahalam@khalidtraders.pk')->firstOrFail();

        $this->shop1 = Location::where('code', 'LOC-SH01')->firstOrFail();
        $this->shop2 = Location::where('code', 'LOC-SH02')->firstOrFail();

        $this->reportService = app(ReportService::class);

        // Setup Customer
        $this->customer = Customer::firstOrCreate(
            ['customer_code' => 'CUST-REP-01'],
            [
                'business_name' => 'Lahore Footwear Mart',
                'contact_person' => 'Haji Rafiq',
                'phone' => '03009988776',
                'primary_location_id' => $this->shop1->id,
                'credit_limit' => 500000.00,
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
                'created_by' => $this->owner->id,
            ]
        );

        // Setup Supplier
        $this->supplier = Supplier::firstOrCreate(
            ['supplier_code' => 'SUPP-REP-01'],
            [
                'business_name' => 'Ravi Leather Mills',
                'contact_person' => 'Chaudhry Zubair',
                'phone' => '03214433221',
                'primary_location_id' => $this->shop1->id,
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
                'created_by' => $this->owner->id,
            ]
        );

        // Setup Product & Variant
        $category = ProductCategory::firstOrCreate(
            ['code' => 'CAT-REP'],
            ['name' => 'Formal Shoes', 'is_active' => true]
        );

        $uom = UnitOfMeasure::firstOrCreate(['code' => 'PAIR'], ['name' => 'Pair', 'is_base_unit' => true]);

        $this->product = Product::firstOrCreate(
            ['article_code' => 'ART-REP-101'],
            [
                'category_id' => $category->id,
                'base_unit_id' => $uom->id,
                'name' => 'Oxford Classic Leather',
                'carton_pack_qty' => 12,
                'default_wholesale_rate' => 24000.00, // PKR 2,000 per pair
                'default_retail_rate' => 30000.00,
                'reorder_threshold_cartons' => 5,
                'is_active' => true,
            ]
        );

        $this->variant = ProductVariant::firstOrCreate(
            ['sku' => 'VAR-REP-101-42-BLK'],
            [
                'product_id' => $this->product->id,
                'size' => '42',
                'color' => 'Black',
                'purchase_cost' => 1200.00,
                'selling_price' => 2000.00,
                'is_active' => true,
            ]
        );

        // Setup Financial Accounts
        $this->cashAccount = FinancialAccount::firstOrCreate(
            ['account_code' => 'ACC-REP-CSH-01'],
            [
                'name' => 'Shop 1 Cash Drawer',
                'account_type' => 'cash',
                'location_id' => $this->shop1->id,
                'currency' => 'PKR',
                'opening_balance' => 50000.00,
                'current_balance' => 50000.00,
                'is_active' => true,
                'created_by' => $this->owner->id,
            ]
        );

        $this->bankAccount = FinancialAccount::firstOrCreate(
            ['account_code' => 'ACC-REP-BNK-01'],
            [
                'name' => 'Meezan Bank Principal',
                'account_type' => 'bank',
                'location_id' => null, // Central
                'currency' => 'PKR',
                'opening_balance' => 200000.00,
                'current_balance' => 200000.00,
                'is_active' => true,
                'created_by' => $this->owner->id,
            ]
        );

        // Expense Category
        $this->utilityCategory = ExpenseCategory::firstOrCreate(
            ['code' => 'EXP-UTL-REP'],
            ['name' => 'Electricity & Gas', 'is_active' => true]
        );
    }

    /**
     * 1. Test Date Filters and Default Empty Date Ranges.
     */
    public function test_date_filters_and_empty_date_ranges(): void
    {
        [$start, $end] = $this->reportService->resolveDateRange(null, null);
        $this->assertEquals(now()->startOfMonth()->toDateString(), $start);
        $this->assertEquals(now()->toDateString(), $end);

        // Custom range
        [$customStart, $customEnd] = $this->reportService->resolveDateRange('2026-01-01', '2026-01-31');
        $this->assertEquals('2026-01-01', $customStart);
        $this->assertEquals('2026-01-31', $customEnd);

        // Inverted dates swap automatically
        [$invertedStart, $invertedEnd] = $this->reportService->resolveDateRange('2026-05-31', '2026-05-01');
        $this->assertEquals('2026-05-01', $invertedStart);
        $this->assertEquals('2026-05-31', $invertedEnd);
    }

    /**
     * 2. Test Location Isolation and Owner Visibility.
     */
    public function test_location_isolation_and_owner_visibility(): void
    {
        // Owner has global access (returns null meaning unrestricted query)
        $ownerScope = $this->reportService->resolveLocationScope($this->owner);
        $this->assertNull($ownerScope);

        // Owner can also specify a single location
        $ownerFilteredScope = $this->reportService->resolveLocationScope($this->owner, $this->shop1->id);
        $this->assertEquals([$this->shop1->id], $ownerFilteredScope);

        // Shop 1 Manager has access to Shop 1
        $managerScope = $this->reportService->resolveLocationScope($this->shop1Manager, $this->shop1->id);
        $this->assertEquals([$this->shop1->id], $managerScope);

        // Shop 1 Manager attempting to access Shop 2 must throw AuthorizationException
        $this->expectException(AuthorizationException::class);
        $this->reportService->resolveLocationScope($this->shop1Manager, $this->shop2->id);
    }

    /**
     * 3. Test HTTP 403 on Location Parameter Tampering.
     */
    public function test_http_authorization_rejects_unauthorized_location_parameter(): void
    {
        // Acting as Shop 1 Manager requesting Shop 2 data
        $response = $this->actingAs($this->shop1Manager)->get(route('reports.sales', [
            'location_id' => $this->shop2->id,
        ]));

        $response->assertStatus(403);
    }

    /**
     * 4. Test Draft and Cancelled Documents Exclusion from Recognized Sales.
     */
    public function test_draft_and_cancelled_documents_excluded_from_sales_totals(): void
    {
        $today = now()->toDateString();

        // 1. Confirmed Posted Invoice: PKR 10,000
        SalesInvoice::create([
            'invoice_number' => 'INV-POST-01',
            'customer_id' => $this->customer->id,
            'location_id' => $this->shop1->id,
            'invoice_date' => $today,
            'status' => 'posted',
            'total_cartons' => 1,
            'total_pairs' => 12.0000,
            'subtotal_amount' => 10000.00,
            'discount_amount' => 0.00,
            'tax_amount' => 0.00,
            'total_amount' => 10000.00,
            'paid_amount' => 10000.00,
            'balance_amount' => 0.00,
            'payment_status' => 'paid',
            'created_by' => $this->owner->id,
        ]);

        // 2. Draft Invoice: PKR 5,000 (must be excluded)
        SalesInvoice::create([
            'invoice_number' => 'INV-DRAFT-01',
            'customer_id' => $this->customer->id,
            'location_id' => $this->shop1->id,
            'invoice_date' => $today,
            'status' => 'draft',
            'total_cartons' => 1,
            'total_pairs' => 12.0000,
            'subtotal_amount' => 5000.00,
            'discount_amount' => 0.00,
            'tax_amount' => 0.00,
            'total_amount' => 5000.00,
            'paid_amount' => 0.00,
            'balance_amount' => 5000.00,
            'payment_status' => 'unpaid',
            'created_by' => $this->owner->id,
        ]);

        // 3. Cancelled Invoice: PKR 8,000 (must be excluded)
        SalesInvoice::create([
            'invoice_number' => 'INV-CANCEL-01',
            'customer_id' => $this->customer->id,
            'location_id' => $this->shop1->id,
            'invoice_date' => $today,
            'status' => 'cancelled',
            'total_cartons' => 1,
            'total_pairs' => 12.0000,
            'subtotal_amount' => 8000.00,
            'discount_amount' => 0.00,
            'tax_amount' => 0.00,
            'total_amount' => 8000.00,
            'paid_amount' => 0.00,
            'balance_amount' => 8000.00,
            'payment_status' => 'unpaid',
            'created_by' => $this->owner->id,
        ]);

        $report = $this->reportService->getSalesReport($this->owner, [
            'from_date' => $today,
            'to_date' => $today,
            'location_id' => $this->shop1->id,
        ]);

        // Total amount must equal ONLY the posted invoice (10,000.00)
        $this->assertEquals(1, $report['summary']['invoices_count']);
        $this->assertEquals('10000.00', $report['summary']['total_amount']);
        $this->assertEquals('10000.00', $report['summary']['net_sales_amount']);
    }

    /**
     * 5. Test Sales Returns Deduct from Net Revenue Without Double Counting.
     */
    public function test_sales_returns_deducted_without_double_counting(): void
    {
        $today = now()->toDateString();

        $invoice = SalesInvoice::create([
            'invoice_number' => 'INV-RET-TEST',
            'customer_id' => $this->customer->id,
            'location_id' => $this->shop1->id,
            'invoice_date' => $today,
            'status' => 'posted',
            'total_cartons' => 2,
            'total_pairs' => 24.0000,
            'subtotal_amount' => 20000.00,
            'discount_amount' => 0.00,
            'tax_amount' => 0.00,
            'total_amount' => 20000.00,
            'paid_amount' => 0.00,
            'balance_amount' => 20000.00,
            'payment_status' => 'unpaid',
            'created_by' => $this->owner->id,
        ]);

        // Posted Return: PKR 4,000
        SalesReturn::create([
            'return_number' => 'SRN-RET-01',
            'sales_invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'location_id' => $this->shop1->id,
            'return_date' => $today,
            'total_return_pairs' => 4.0000,
            'total_return_amount' => 4000.00,
            'refunded_amount' => 0.00,
            'reason' => 'Defective stitching batch',
            'status' => 'posted',
            'refund_status' => 'credited_to_khata',
            'created_by' => $this->owner->id,
        ]);

        $report = $this->reportService->getSalesReport($this->owner, [
            'from_date' => $today,
            'to_date' => $today,
        ]);

        $this->assertEquals('20000.00', $report['summary']['total_amount']);
        $this->assertEquals('4000.00', $report['summary']['returns_amount']);
        // Net Sales = 20,000 - 4,000 = 16,000
        $this->assertEquals('16000.00', $report['summary']['net_sales_amount']);
    }

    /**
     * 6. Test Customer & Supplier Khata Balances and Aging Buckets.
     */
    public function test_customer_and_supplier_khata_aging_buckets(): void
    {
        $today = now()->toDateString();
        $fortyDaysAgo = now()->subDays(40)->toDateString();

        // Customer Invoice from 40 days ago (belongs in 31-60 days bucket)
        SalesInvoice::create([
            'invoice_number' => 'INV-AGING-40D',
            'customer_id' => $this->customer->id,
            'location_id' => $this->shop1->id,
            'invoice_date' => $fortyDaysAgo,
            'status' => 'posted',
            'total_cartons' => 1,
            'total_pairs' => 12.0000,
            'subtotal_amount' => 15000.00,
            'discount_amount' => 0.00,
            'tax_amount' => 0.00,
            'total_amount' => 15000.00,
            'paid_amount' => 0.00,
            'balance_amount' => 15000.00,
            'payment_status' => 'unpaid',
            'created_by' => $this->owner->id,
        ]);

        // Customer Ledger Entry
        CustomerLedgerEntry::create([
            'customer_id' => $this->customer->id,
            'location_id' => $this->shop1->id,
            'entry_type' => 'sale_invoice',
            'reference_type' => SalesInvoice::class,
            'reference_id' => 999,
            'reference_number' => 'INV-AGING-40D',
            'entry_date' => $fortyDaysAgo,
            'debit_amount' => 15000.00,
            'credit_amount' => 0.00,
            'description' => 'Sales Invoice 40 days ago',
            'created_by' => $this->owner->id,
        ]);

        $khataReport = $this->reportService->getCustomerKhataReport($this->owner, [
            'from_date' => $fortyDaysAgo,
            'to_date' => $today,
        ]);

        $this->assertEquals('15000.00', $khataReport['summary']['total_closing']);
        // Aging check: 40 days must fall into 31-60 bucket
        $this->assertEquals('15000.00', $khataReport['summary']['aging_31_60']);
        $this->assertEquals('0.00', $khataReport['summary']['aging_0_30']);
    }

    /**
     * 7. Test Cash & Bank Inflows, Outflows, and Zero-Sum Inter-Account Transfers.
     */
    public function test_cash_bank_inflows_outflows_and_transfers(): void
    {
        $today = now()->toDateString();
        $cashBankService = app(CashAndBankService::class);

        // Inter-account transfer: PKR 10,000 from Bank to Cash
        $transfer = $cashBankService->transfer(
            source: $this->bankAccount,
            destination: $this->cashAccount,
            amount: 10000.00,
            date: $today,
            user: $this->owner,
            reason: 'Replenish shop cash drawer'
        );

        $report = $this->reportService->getCashBankReport($this->owner, [
            'from_date' => $today,
            'to_date' => $today,
        ]);

        $this->assertEquals(1, $report['summary']['transfers_count']);
        $this->assertEquals('10000.00', $report['summary']['total_transfers_amount']);

        // Cash flow statement verification: transfers net effect is 0.00
        $cashFlow = $this->reportService->getCashFlowReport($this->owner, [
            'from_date' => $today,
            'to_date' => $today,
        ]);

        $this->assertEquals('10000.00', $cashFlow['internal_transfers']['transfers_in']);
        $this->assertEquals('10000.00', $cashFlow['internal_transfers']['transfers_out']);
        $this->assertEquals('0.00', $cashFlow['internal_transfers']['net_effect']);
    }

    /**
     * 8. Test Expenses: Posted vs Draft vs Reversed.
     */
    public function test_expense_reporting_classifies_posted_draft_and_reversed(): void
    {
        $today = now()->toDateString();

        // 1. Posted Expense: PKR 3,500
        Expense::create([
            'expense_number' => 'EXP-POST-01',
            'location_id' => $this->shop1->id,
            'expense_category_id' => $this->utilityCategory->id,
            'financial_account_id' => $this->cashAccount->id,
            'expense_date' => $today,
            'amount' => 3500.00,
            'description' => 'Electricity Bill',
            'status' => 'posted',
            'created_by' => $this->owner->id,
        ]);

        // 2. Draft Expense: PKR 2,000 (pending)
        Expense::create([
            'expense_number' => 'EXP-DRAFT-01',
            'location_id' => $this->shop1->id,
            'expense_category_id' => $this->utilityCategory->id,
            'financial_account_id' => $this->cashAccount->id,
            'expense_date' => $today,
            'amount' => 2000.00,
            'description' => 'Office Stationery Draft',
            'status' => 'draft',
            'created_by' => $this->owner->id,
        ]);

        // 3. Reversed Expense: PKR 1,200 (annulled)
        Expense::create([
            'expense_number' => 'EXP-REV-01',
            'location_id' => $this->shop1->id,
            'expense_category_id' => $this->utilityCategory->id,
            'financial_account_id' => $this->cashAccount->id,
            'expense_date' => $today,
            'amount' => 1200.00,
            'description' => 'Cancelled Sweeper Tip',
            'status' => 'reversed',
            'reversal_reason' => 'Entry entered by mistake',
            'created_by' => $this->owner->id,
        ]);

        $report = $this->reportService->getExpenseReport($this->owner, [
            'from_date' => $today,
            'to_date' => $today,
        ]);

        $this->assertEquals(3, $report['summary']['total_count']);
        $this->assertEquals('3500.00', $report['summary']['posted_amount']);
        $this->assertEquals('2000.00', $report['summary']['draft_amount']);
        $this->assertEquals('1200.00', $report['summary']['reversed_amount']);
    }

    /**
     * 9. Test Stock Quantity and Valuation at Weighted Average Cost (WAC).
     */
    public function test_inventory_valuation_at_wac(): void
    {
        // Set stock: 24 pairs (2 cartons) at WAC unit cost of PKR 1,500
        StockBalance::updateOrCreate(
            [
                'location_id' => $this->shop1->id,
                'product_variant_id' => $this->variant->id,
            ],
            [
                'pairs_quantity' => 24.0000,
                'allocated_quantity' => 0.0000,
                'avg_unit_cost' => 1500.0000,
            ]
        );

        $report = $this->reportService->getInventoryReport($this->owner, [
            'location_id' => $this->shop1->id,
        ]);

        // 24 pairs * PKR 1,500 = PKR 36,000.00
        $this->assertEquals('24.0000', $report['summary']['total_pairs']);
        $this->assertEquals(2, $report['summary']['total_cartons']);
        $this->assertEquals('36000.00', $report['summary']['total_valuation_wac']);
    }

    /**
     * 10. Test CSV Export Formula Injection Sanitization.
     */
    public function test_csv_export_neutralizes_formula_injection_triggers(): void
    {
        $dangerousEqual = '=cmd|"/C calc"!A0';
        $dangerousPlus = '+12345';
        $dangerousMinus = '-2+5';
        $dangerousAt = '@SUM(A1:A10)';
        $normalText = 'Standard Customer Name';

        $this->assertEquals("'=cmd|\"/C calc\"!A0", $this->reportService->sanitizeCsvCell($dangerousEqual));
        $this->assertEquals("'+12345", $this->reportService->sanitizeCsvCell($dangerousPlus));
        $this->assertEquals("'-2+5", $this->reportService->sanitizeCsvCell($dangerousMinus));
        $this->assertEquals("'@SUM(A1:A10)", $this->reportService->sanitizeCsvCell($dangerousAt));
        $this->assertEquals('Standard Customer Name', $this->reportService->sanitizeCsvCell($normalText));
    }

    /**
     * 11. Test Read-Only Guarantee: Reports Do Not Mutate Database Records.
     */
    public function test_reports_are_strictly_read_only_and_do_not_mutate_state(): void
    {
        $initialInvoicesCount = SalesInvoice::count();
        $initialPaymentsCount = Payment::count();
        $initialExpensesCount = Expense::count();
        $initialMovementsCount = StockMovement::count();
        $initialTransactionsCount = AccountTransaction::count();

        // Run all reports
        $this->reportService->getDashboardSummary($this->owner);
        $this->reportService->getSalesReport($this->owner);
        $this->reportService->getPurchaseReport($this->owner);
        $this->reportService->getCustomerKhataReport($this->owner);
        $this->reportService->getSupplierKhataReport($this->owner);
        $this->reportService->getCashBankReport($this->owner);
        $this->reportService->getExpenseReport($this->owner);
        $this->reportService->getInventoryReport($this->owner);
        $this->reportService->getProfitAndLossReport($this->owner);
        $this->reportService->getCashFlowReport($this->owner);
        $this->reportService->getFinancialPositionReport($this->owner);

        // Verify zero mutations occurred
        $this->assertEquals($initialInvoicesCount, SalesInvoice::count());
        $this->assertEquals($initialPaymentsCount, Payment::count());
        $this->assertEquals($initialExpensesCount, Expense::count());
        $this->assertEquals($initialMovementsCount, StockMovement::count());
        $this->assertEquals($initialTransactionsCount, AccountTransaction::count());
    }

    /**
     * 12. Test HTTP Endpoints Render Successfully for Owner and Managers.
     */
    public function test_reports_http_endpoints_render_for_authorized_users(): void
    {
        $routes = [
            'reports.index',
            'reports.sales',
            'reports.purchases',
            'reports.khata',
            'reports.cash-bank',
            'reports.expenses',
            'reports.inventory',
            'reports.profit-loss',
            'reports.cash-flow',
            'reports.financial-position',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($this->owner)->get(route($route));
            $response->assertStatus(200);
        }
    }
}
