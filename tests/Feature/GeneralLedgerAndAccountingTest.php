<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialAccount;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\JournalEntry;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\ProductionCompletion;
use App\Models\ProductionOrder;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Recipe;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Accounting\GeneralLedgerService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class GeneralLedgerAndAccountingTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $manager;
    protected Location $shop1;
    protected Location $factory1;
    protected GeneralLedgerService $glService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seed(ChartOfAccountsSeeder::class);

        $this->glService = app(GeneralLedgerService::class);

        $this->shop1 = Location::where('code', 'LOC-SH01')->firstOrFail();
        $this->factory1 = Location::where('code', 'LOC-FAC01')->firstOrFail();
        $this->owner = User::where('email', 'khalid.owner@khalidtraders.pk')->firstOrFail();
        $this->manager = User::where('email', 'tariq.anarkali@khalidtraders.pk')->firstOrFail();
    }

    /**
     * Test Chart of Accounts seeding, hierarchy, and system accounts.
     */
    public function test_chart_of_accounts_seeded_with_correct_system_identifiers(): void
    {
        $cashAcc = ChartOfAccount::requireSystemAccount('cash_in_hand');
        $this->assertEquals('1110', $cashAcc->code);
        $this->assertEquals('asset', $cashAcc->type);
        $this->assertEquals('debit', $cashAcc->normal_balance);
        $this->assertFalse($cashAcc->is_group);

        $apAcc = ChartOfAccount::requireSystemAccount('accounts_payable');
        $this->assertEquals('2110', $apAcc->code);
        $this->assertEquals('liability', $apAcc->type);
        $this->assertEquals('credit', $apAcc->normal_balance);

        $salesAcc = ChartOfAccount::requireSystemAccount('sales_revenue');
        $this->assertEquals('4100', $salesAcc->code);
        $this->assertEquals('revenue', $salesAcc->type);
        $this->assertEquals('credit', $salesAcc->normal_balance);

        $cogsAcc = ChartOfAccount::requireSystemAccount('cogs');
        $this->assertEquals('5100', $cogsAcc->code);
        $this->assertEquals('expense', $cogsAcc->type);
        $this->assertEquals('debit', $cogsAcc->normal_balance);

        // Group accounts should exist
        $currentAssets = ChartOfAccount::where('code', '1100')->firstOrFail();
        $this->assertTrue($currentAssets->is_group);
    }

    /**
     * Test cannot post journal entries to group accounts.
     */
    public function test_cannot_post_to_group_account(): void
    {
        $groupAcc = ChartOfAccount::where('code', '1100')->firstOrFail();
        $cashAcc = ChartOfAccount::requireSystemAccount('cash_in_hand');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Cannot post to group account");

        $this->glService->recordJournalEntry([
            'entry_date' => now()->toDateString(),
            'description' => 'Test invalid group post',
        ], [
            ['chart_of_account_id' => $groupAcc->id, 'debit' => 1000, 'credit' => 0],
            ['chart_of_account_id' => $cashAcc->id, 'debit' => 0, 'credit' => 1000],
        ], $this->owner);
    }

    /**
     * Test balanced journal entry posting and exact arithmetic balance.
     */
    public function test_balanced_journal_entry_posts_successfully(): void
    {
        $cashAcc = ChartOfAccount::requireSystemAccount('cash_in_hand');
        $equityAcc = ChartOfAccount::requireSystemAccount('owner_capital');

        $entry = $this->glService->recordJournalEntry([
            'entry_date' => now()->toDateString(),
            'location_id' => $this->shop1->id,
            'description' => 'Owner Capital Injection in Cash',
            'reference_number' => 'JV-TEST-001',
        ], [
            ['chart_of_account_id' => $cashAcc->id, 'debit' => 50000.00, 'credit' => 0.00, 'description' => 'Cash received'],
            ['chart_of_account_id' => $equityAcc->id, 'debit' => 0.00, 'credit' => 50000.00, 'description' => 'Capital credited'],
        ], $this->owner);

        $this->assertDatabaseHas('journal_entries', [
            'id' => $entry->id,
            'status' => 'posted',
            'total_debit' => 50000.00,
            'total_credit' => 50000.00,
            'location_id' => $this->shop1->id,
        ]);

        $this->assertCount(2, $entry->lines);
        $this->assertTrue($entry->isBalanced());
    }

    /**
     * Test unbalanced journal entry throws InvalidArgumentException and does not persist.
     */
    public function test_unbalanced_journal_entry_is_rejected(): void
    {
        $cashAcc = ChartOfAccount::requireSystemAccount('cash_in_hand');
        $equityAcc = ChartOfAccount::requireSystemAccount('owner_capital');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unbalanced journal entry");

        $this->glService->recordJournalEntry([
            'entry_date' => now()->toDateString(),
            'description' => 'Unbalanced Attempt',
        ], [
            ['chart_of_account_id' => $cashAcc->id, 'debit' => 50000.00, 'credit' => 0.00],
            ['chart_of_account_id' => $equityAcc->id, 'debit' => 0.00, 'credit' => 40000.00],
        ], $this->owner);

        $this->assertDatabaseMissing('journal_entries', [
            'description' => 'Unbalanced Attempt',
        ]);
    }

    /**
     * Test invalid lines (e.g. negative amounts or both debit and credit) are rejected.
     */
    public function test_journal_entry_with_invalid_amounts_is_rejected(): void
    {
        $cashAcc = ChartOfAccount::requireSystemAccount('cash_in_hand');
        $equityAcc = ChartOfAccount::requireSystemAccount('owner_capital');

        $this->expectException(InvalidArgumentException::class);

        $this->glService->recordJournalEntry([
            'entry_date' => now()->toDateString(),
            'description' => 'Negative amount test',
        ], [
            ['chart_of_account_id' => $cashAcc->id, 'debit' => -100.00, 'credit' => 0.00],
            ['chart_of_account_id' => $equityAcc->id, 'debit' => 0.00, 'credit' => -100.00],
        ], $this->owner);
    }

    /**
     * Test idempotency key prevents duplicate journal entries for the same automated transaction.
     */
    public function test_idempotency_key_prevents_duplicate_journal_entry(): void
    {
        $cashAcc = ChartOfAccount::requireSystemAccount('cash_in_hand');
        $bankAcc = ChartOfAccount::requireSystemAccount('bank_accounts');

        $payload = [
            'entry_date' => now()->toDateString(),
            'location_id' => $this->shop1->id,
            'description' => 'Cash to Bank Transfer',
            'idempotency_key' => 'transfer_key_unique_123',
        ];

        $lines = [
            ['chart_of_account_id' => $bankAcc->id, 'debit' => 10000.00, 'credit' => 0.00],
            ['chart_of_account_id' => $cashAcc->id, 'debit' => 0.00, 'credit' => 10000.00],
        ];

        $first = $this->glService->recordJournalEntry($payload, $lines, $this->owner);
        $second = $this->glService->recordJournalEntry($payload, $lines, $this->owner);

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, JournalEntry::where('idempotency_key', 'transfer_key_unique_123')->count());
    }

    /**
     * Test journal entry reversal creates a linked inverse voucher and marks original as reversed.
     */
    public function test_journal_entry_reversal_creates_linked_inverse_entry(): void
    {
        $cashAcc = ChartOfAccount::requireSystemAccount('cash_in_hand');
        $rentAcc = ChartOfAccount::requireSystemAccount('rent_expense');

        $entry = $this->glService->recordJournalEntry([
            'entry_date' => now()->toDateString(),
            'location_id' => $this->shop1->id,
            'description' => 'Rent Payment Voucher',
        ], [
            ['chart_of_account_id' => $rentAcc->id, 'debit' => 25000.00, 'credit' => 0.00],
            ['chart_of_account_id' => $cashAcc->id, 'debit' => 0.00, 'credit' => 25000.00],
        ], $this->owner);

        $this->assertEquals('posted', $entry->status);

        $reversal = $this->glService->reverseJournalEntry($entry, $this->owner, 'Erroneous rent entry duplicate');

        $entry->refresh();
        $this->assertEquals('reversed', $entry->status);
        $this->assertEquals($reversal->id, $entry->reversed_by_entry_id);
        $this->assertEquals($entry->id, $reversal->reversal_of_entry_id);

        // Reversal debits and credits must be inverted
        $rentLine = $reversal->lines()->where('chart_of_account_id', $rentAcc->id)->firstOrFail();
        $cashLine = $reversal->lines()->where('chart_of_account_id', $cashAcc->id)->firstOrFail();

        $this->assertEquals('0.00', $rentLine->debit);
        $this->assertEquals('25000.00', $rentLine->credit);
        $this->assertEquals('25000.00', $cashLine->debit);
        $this->assertEquals('0.00', $cashLine->credit);
    }

    /**
     * Test Sales Invoice Posting into General Ledger (AR, Sales Revenue, COGS, Finished Goods Inventory).
     */
    public function test_post_sales_invoice_integration(): void
    {
        $uom = UnitOfMeasure::firstOrCreate(['code' => 'PAIR'], ['name' => 'Pair', 'type' => 'unit']);
        $cat = ProductCategory::firstOrCreate(['code' => 'FF'], ['name' => 'Finished Footwear', 'type' => 'finished_good']);
        $prod = Product::create([
            'category_id' => $cat->id,
            'base_unit_id' => $uom->id,
            'name' => 'Leather Oxford Shoes',
            'article_code' => 'LO-01',
            'is_active' => true,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $prod->id,
            'sku' => 'LO-01-42',
            'size' => '42',
            'color' => 'Black',
            'pairs_per_carton' => 12,
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'customer_code' => 'CUST-001',
            'business_name' => 'Haji Footwear Mart',
            'contact_person' => 'Haji Muhammad',
            'phone' => '+92 300 1234567',
            'primary_location_id' => $this->shop1->id,
            'current_balance' => 0,
            'created_by' => $this->owner->id,
        ]);

        $invoice = SalesInvoice::create([
            'invoice_number' => 'INV-TEST-GL-01',
            'customer_id' => $customer->id,
            'location_id' => $this->shop1->id,
            'invoice_date' => now()->toDateString(),
            'subtotal_amount' => 12000.00,
            'discount_amount' => 500.00,
            'total_amount' => 11500.00,
            'status' => 'posted',
            'created_by' => $this->owner->id,
        ]);

        SalesInvoiceItem::create([
            'sales_invoice_id' => $invoice->id,
            'product_variant_id' => $variant->id,
            'quantity_pairs' => 10,
            'unit_price' => 1200.00,
            'unit_price_per_pair' => 1200.00,
            'line_subtotal' => 12000.00,
            'line_total' => 12000.00,
            'unit_cost' => 800.00, // COGS = 10 * 800 = 8000
        ]);

        $invoice->load(['items.productVariant', 'customer']);

        $entry = $this->glService->postSalesInvoice($invoice, $this->owner);

        $this->assertInstanceOf(JournalEntry::class, $entry);
        $this->assertEquals('sales_invoice', $entry->source_type);
        $this->assertEquals($invoice->id, $entry->source_id);

        $arAcc = ChartOfAccount::requireSystemAccount('accounts_receivable');
        $revAcc = ChartOfAccount::requireSystemAccount('sales_revenue');
        $discAcc = ChartOfAccount::requireSystemAccount('sales_discounts');
        $cogsAcc = ChartOfAccount::requireSystemAccount('cogs');
        $invAcc = ChartOfAccount::requireSystemAccount('merchandise_inventory');

        // Check AR debit = net amount (11500)
        $arLine = $entry->lines()->where('chart_of_account_id', $arAcc->id)->firstOrFail();
        $this->assertEquals('11500.00', $arLine->debit);

        // Check Discount debit = 500
        $discLine = $entry->lines()->where('chart_of_account_id', $discAcc->id)->firstOrFail();
        $this->assertEquals('500.00', $discLine->debit);

        // Check Sales credit = gross total (12000)
        $revLine = $entry->lines()->where('chart_of_account_id', $revAcc->id)->firstOrFail();
        $this->assertEquals('12000.00', $revLine->credit);

        // Check COGS debit = 8000
        $cogsLine = $entry->lines()->where('chart_of_account_id', $cogsAcc->id)->firstOrFail();
        $this->assertEquals('8000.00', $cogsLine->debit);

        // Check Finished Goods Inventory credit = 8000
        $invLine = $entry->lines()->where('chart_of_account_id', $invAcc->id)->firstOrFail();
        $this->assertEquals('8000.00', $invLine->credit);

        $this->assertTrue($entry->isBalanced());
    }

    /**
     * Test Purchase Goods Receipt (GRN) posting into General Ledger.
     */
    public function test_post_goods_receipt_grn_integration(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUP-001',
            'business_name' => 'Royal Sole Supplies',
            'contact_person' => 'Nawaz Sharif',
            'phone' => '+92 300 9999999',
            'primary_location_id' => $this->shop1->id,
            'current_balance' => 0,
            'created_by' => $this->owner->id,
        ]);

        $uom = UnitOfMeasure::firstOrCreate(['code' => 'PAIR'], ['name' => 'Pair', 'type' => 'unit']);
        $cat = ProductCategory::firstOrCreate(['code' => 'FF'], ['name' => 'Finished Goods', 'type' => 'finished_good']);
        $prod = Product::create([
            'category_id' => $cat->id,
            'base_unit_id' => $uom->id,
            'name' => 'Finished Leather Boot',
            'article_code' => 'FB-01',
            'is_active' => true,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $prod->id,
            'sku' => 'FB-01-40',
            'size' => '40',
            'color' => 'Brown',
            'pairs_per_carton' => 10,
            'is_active' => true,
        ]);

        $po = PurchaseOrder::create([
            'purchase_number' => 'PO-SUP-001',
            'supplier_id' => $supplier->id,
            'location_id' => $this->shop1->id,
            'order_date' => now()->toDateString(),
            'status' => 'ordered',
            'total_amount' => 16000.00,
            'created_by' => $this->owner->id,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_variant_id' => $variant->id,
            'ordered_cartons' => 2,
            'ordered_pairs' => 20,
            'unit_cost' => 800.00,
            'unit_cost_per_pair' => 800.00,
            'line_subtotal' => 16000.00,
            'line_total' => 16000.00,
        ]);

        $grn = GoodsReceipt::create([
            'receipt_number' => 'GRN-TEST-001',
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'location_id' => $this->shop1->id,
            'receipt_date' => now()->toDateString(),
            'total_received_pairs' => 20,
            'total_received_cost' => 16000.00,
            'received_by' => $this->owner->id,
        ]);

        GoodsReceiptItem::create([
            'goods_receipt_id' => $grn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_variant_id' => $variant->id,
            'received_cartons' => 2,
            'received_pairs' => 20,
            'unit_cost_per_pair' => 800.00,
            'total_cost' => 16000.00,
        ]);

        $grn->load(['items.productVariant.product.category', 'supplier']);

        $entry = $this->glService->postGoodsReceipt($grn, $this->owner);

        $apAcc = ChartOfAccount::requireSystemAccount('accounts_payable');
        $invAcc = ChartOfAccount::requireSystemAccount('merchandise_inventory');

        // Check Inventory debit = 16000
        $invLine = $entry->lines()->where('chart_of_account_id', $invAcc->id)->firstOrFail();
        $this->assertEquals('16000.00', $invLine->debit);

        // Check AP credit = 16000
        $apLine = $entry->lines()->where('chart_of_account_id', $apAcc->id)->firstOrFail();
        $this->assertEquals('16000.00', $apLine->credit);

        $this->assertTrue($entry->isBalanced());
    }

    /**
     * Test Supplier Payment posting into General Ledger.
     */
    public function test_post_supplier_payment_integration(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUP-002',
            'business_name' => 'Packages Ltd Raw Packaging',
            'contact_person' => 'Hamza',
            'phone' => '+92 300 8888888',
            'primary_location_id' => $this->shop1->id,
            'current_balance' => 10000.00,
            'created_by' => $this->owner->id,
        ]);

        $payment = SupplierPayment::create([
            'payment_number' => 'SP-TEST-001',
            'supplier_id' => $supplier->id,
            'location_id' => $this->shop1->id,
            'payment_date' => now()->toDateString(),
            'amount' => 10000.00,
            'payment_method' => 'cash',
            'created_by' => $this->owner->id,
        ]);

        $entry = $this->glService->postSupplierPayment($payment, $this->owner);

        $apAcc = ChartOfAccount::requireSystemAccount('accounts_payable');
        $cashAcc = ChartOfAccount::requireSystemAccount('cash_in_hand');

        $apLine = $entry->lines()->where('chart_of_account_id', $apAcc->id)->firstOrFail();
        $cashLine = $entry->lines()->where('chart_of_account_id', $cashAcc->id)->firstOrFail();

        $this->assertEquals('10000.00', $apLine->debit);
        $this->assertEquals('10000.00', $cashLine->credit);
        $this->assertTrue($entry->isBalanced());
    }

    /**
     * Test Operating Expense Voucher posting into General Ledger.
     */
    public function test_post_operating_expense_integration(): void
    {
        $cat = ExpenseCategory::create([
            'name' => 'Shop Electricity & Power',
            'code' => 'UTIL-01',
            'is_active' => true,
        ]);

        $finAcc = FinancialAccount::create([
            'account_code' => 'CASH-01',
            'location_id' => $this->shop1->id,
            'name' => 'Cash Counter Shop 1',
            'account_type' => 'cash',
            'currency' => 'PKR',
            'current_balance' => 50000.00,
            'is_active' => true,
            'created_by' => $this->owner->id,
        ]);

        $expense = Expense::create([
            'expense_number' => 'EXP-TEST-001',
            'expense_category_id' => $cat->id,
            'financial_account_id' => $finAcc->id,
            'location_id' => $this->shop1->id,
            'expense_date' => now()->toDateString(),
            'amount' => 15000.00,
            'description' => 'Monthly Electricity Bill LESCO',
            'payment_method' => 'cash',
            'status' => 'posted',
            'created_by' => $this->owner->id,
        ]);

        $entry = $this->glService->postExpense($expense, $this->owner);

        $utilAcc = ChartOfAccount::requireSystemAccount('utility_expense');
        $cashAcc = ChartOfAccount::requireSystemAccount('cash_in_hand');

        $utilLine = $entry->lines()->where('chart_of_account_id', $utilAcc->id)->firstOrFail();
        $cashLine = $entry->lines()->where('chart_of_account_id', $cashAcc->id)->firstOrFail();

        $this->assertEquals('15000.00', $utilLine->debit);
        $this->assertEquals('15000.00', $cashLine->credit);
        $this->assertTrue($entry->isBalanced());
    }

    /**
     * Test Factory Production Completion posting into General Ledger (Phase 10 integration).
     */
    public function test_post_production_completion_integration(): void
    {
        $uom = UnitOfMeasure::firstOrCreate(['code' => 'PAIR'], ['name' => 'Pair', 'type' => 'unit']);
        $cat = ProductCategory::firstOrCreate(['code' => 'FF'], ['name' => 'Finished Footwear', 'type' => 'finished_good']);
        $prod = Product::create([
            'category_id' => $cat->id,
            'base_unit_id' => $uom->id,
            'name' => 'Handmade Leather Derby',
            'article_code' => 'HLD-01',
            'is_active' => true,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $prod->id,
            'sku' => 'HLD-01-41',
            'size' => '41',
            'color' => 'Black',
            'pairs_per_carton' => 10,
            'is_active' => true,
        ]);

        $recipe = Recipe::create([
            'recipe_number' => 'REC-001',
            'product_id' => $prod->id,
            'product_variant_id' => $variant->id,
            'name' => 'Derby Standard Recipe',
            'yield_pairs' => 1.0,
            'is_active' => true,
            'created_by' => $this->owner->id,
        ]);

        $order = ProductionOrder::create([
            'order_number' => 'PO-TEST-001',
            'factory_location_id' => $this->factory1->id,
            'product_id' => $prod->id,
            'product_variant_id' => $variant->id,
            'recipe_id' => $recipe->id,
            'planned_quantity_pairs' => 100,
            'status' => 'completed',
            'start_date' => now()->toDateString(),
            'created_by' => $this->owner->id,
        ]);

        $completion = ProductionCompletion::create([
            'completion_number' => 'PC-TEST-001',
            'production_order_id' => $order->id,
            'location_id' => $this->factory1->id,
            'completion_date' => now()->toDateString(),
            'good_pairs' => 95,
            'wastage_pairs' => 5,
            'allocated_material_cost' => 60000.00,
            'labor_cost' => 20000.00,
            'other_costs' => 10000.00,
            'total_cost' => 90000.00, // Total = 90,000
            'unit_cost_per_pair' => 900.00, // 95 * 900 = 85,500; scrap difference = 4,500
            'created_by' => $this->owner->id,
        ]);

        $completion->load(['productionOrder']);

        $entry = $this->glService->postProductionCompletion($completion, $this->owner);

        $finInvAcc = ChartOfAccount::requireSystemAccount('merchandise_inventory');
        $rawConsumptionAcc = ChartOfAccount::requireSystemAccount('raw_material_consumption');
        $laborAcc = ChartOfAccount::requireSystemAccount('direct_labor');
        $overheadAcc = ChartOfAccount::requireSystemAccount('factory_overhead');
        $scrapAcc = ChartOfAccount::requireSystemAccount('production_scrap');

        // Debit: Finished Goods Inventory = 95 pairs * 900 = 85,500.00
        $finLine = $entry->lines()->where('chart_of_account_id', $finInvAcc->id)->firstOrFail();
        $this->assertEquals('85500.00', $finLine->debit);

        // Debit: Scrap = 4,500.00
        $scrapLine = $entry->lines()->where('chart_of_account_id', $scrapAcc->id)->firstOrFail();
        $this->assertEquals('4500.00', $scrapLine->debit);

        // Credit: Raw Material Consumption = 60,000.00
        $rawLine = $entry->lines()->where('chart_of_account_id', $rawConsumptionAcc->id)->firstOrFail();
        $this->assertEquals('60000.00', $rawLine->credit);

        // Credit: Direct Labor = 20,000.00
        $laborLine = $entry->lines()->where('chart_of_account_id', $laborAcc->id)->firstOrFail();
        $this->assertEquals('20000.00', $laborLine->credit);

        // Credit: Overhead = 10,000.00
        $overheadLine = $entry->lines()->where('chart_of_account_id', $overheadAcc->id)->firstOrFail();
        $this->assertEquals('10000.00', $overheadLine->credit);

        $this->assertTrue($entry->isBalanced());
    }

    /**
     * Test Trial Balance accurately balances Debits == Credits and handles CSV export.
     */
    public function test_trial_balance_and_csv_export(): void
    {
        $cashAcc = ChartOfAccount::requireSystemAccount('cash_in_hand');
        $equityAcc = ChartOfAccount::requireSystemAccount('owner_capital');

        $this->glService->recordJournalEntry([
            'entry_date' => now()->toDateString(),
            'description' => 'Capital Deposit',
        ], [
            ['chart_of_account_id' => $cashAcc->id, 'debit' => 100000.00, 'credit' => 0.00],
            ['chart_of_account_id' => $equityAcc->id, 'debit' => 0.00, 'credit' => 100000.00],
        ], $this->owner);

        $tb = $this->glService->getTrialBalance(now()->startOfYear()->toDateString(), now()->toDateString());

        $this->assertTrue($tb['totals']['is_balanced']);
        $this->assertEquals('100000.00', $tb['totals']['closing_debit']);
        $this->assertEquals('100000.00', $tb['totals']['closing_credit']);

        // Test web route for Trial Balance CSV
        $response = $this->actingAs($this->owner)->get(route('accounting.trial-balance.csv'));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    /**
     * Test Profit & Loss and Balance Sheet integrity equations.
     */
    public function test_profit_and_loss_and_balance_sheet_accounting_equations(): void
    {
        $cashAcc = ChartOfAccount::requireSystemAccount('cash_in_hand');
        $revAcc = ChartOfAccount::requireSystemAccount('sales_revenue');
        $rentAcc = ChartOfAccount::requireSystemAccount('rent_expense');
        $equityAcc = ChartOfAccount::requireSystemAccount('owner_capital');

        // 1. Initial Capital: 200,000
        $this->glService->recordJournalEntry([
            'entry_date' => now()->toDateString(),
            'description' => 'Initial Capital',
        ], [
            ['chart_of_account_id' => $cashAcc->id, 'debit' => 200000.00, 'credit' => 0.00],
            ['chart_of_account_id' => $equityAcc->id, 'debit' => 0.00, 'credit' => 200000.00],
        ], $this->owner);

        // 2. Cash Sale: 50,000 revenue
        $this->glService->recordJournalEntry([
            'entry_date' => now()->toDateString(),
            'description' => 'Cash Sale',
        ], [
            ['chart_of_account_id' => $cashAcc->id, 'debit' => 50000.00, 'credit' => 0.00],
            ['chart_of_account_id' => $revAcc->id, 'debit' => 0.00, 'credit' => 50000.00],
        ], $this->owner);

        // 3. Rent Expense: 20,000 cash paid
        $this->glService->recordJournalEntry([
            'entry_date' => now()->toDateString(),
            'description' => 'Rent Expense',
        ], [
            ['chart_of_account_id' => $rentAcc->id, 'debit' => 20000.00, 'credit' => 0.00],
            ['chart_of_account_id' => $cashAcc->id, 'debit' => 0.00, 'credit' => 20000.00],
        ], $this->owner);

        // Net Operating Profit should be 50,000 - 20,000 = 30,000
        $pnl = $this->glService->getProfitAndLoss(now()->startOfYear()->toDateString(), now()->toDateString());
        $this->assertEquals('50000.00', $pnl['total_revenue']);
        $this->assertEquals('20000.00', $pnl['total_operating_expenses']);
        $this->assertEquals('30000.00', $pnl['net_profit']);

        // Balance Sheet:
        // Cash = 200,000 + 50,000 - 20,000 = 230,000 (Assets)
        // Capital = 200,000 (Equity)
        // Retained Earnings = 30,000 (Equity)
        // Total Equity = 230,000
        // Total Liabilities & Equity = 230,000
        // Assets = Liabilities + Equity
        $bs = $this->glService->getBalanceSheet(now()->toDateString());
        $this->assertTrue($bs['is_balanced']);
        $this->assertEquals('230000.00', $bs['total_assets']);
        $this->assertEquals('230000.00', $bs['total_liabilities_and_equity']);
        $this->assertEquals('30000.00', $bs['retained_earnings_cumulative']);
    }

    /**
     * Test Opening Balances record and rejection of second opening balance attempt.
     */
    public function test_opening_balances_flow(): void
    {
        $cashAcc = ChartOfAccount::requireSystemAccount('cash_in_hand');
        $equityAcc = ChartOfAccount::requireSystemAccount('opening_balance_equity');

        $lines = [
            ['chart_of_account_id' => $cashAcc->id, 'debit' => 75000.00, 'credit' => 0.00],
            ['chart_of_account_id' => $equityAcc->id, 'debit' => 0.00, 'credit' => 75000.00],
        ];

        $entry = $this->glService->recordOpeningBalances($lines, '2026-01-01', $this->owner, 'ERP Opening Balances');

        $this->assertEquals('opening_balance', $entry->source_type);
        $this->assertTrue($entry->isBalanced());

        // Attempting to record opening balance again must throw InvalidArgumentException
        $this->expectException(InvalidArgumentException::class);
        $this->glService->recordOpeningBalances($lines, '2026-01-01', $this->owner, 'Duplicate Opening Balances');
    }

    /**
     * Test web routes for Chart of Accounts and General Ledger reports.
     */
    public function test_accounting_web_routes_accessible_to_authorized_users(): void
    {
        $this->actingAs($this->owner)->get(route('accounting.chart-of-accounts.index'))->assertOk();
        $this->actingAs($this->owner)->get(route('accounting.journals.index'))->assertOk();
        $this->actingAs($this->owner)->get(route('accounting.ledger'))->assertOk();
        $this->actingAs($this->owner)->get(route('accounting.trial-balance'))->assertOk();
        $this->actingAs($this->owner)->get(route('accounting.profit-loss'))->assertOk();
        $this->actingAs($this->owner)->get(route('accounting.balance-sheet'))->assertOk();
        $this->actingAs($this->owner)->get(route('accounting.reconciliation'))->assertOk();
        $this->actingAs($this->owner)->get(route('accounting.opening-balances.index'))->assertOk();
    }
}
