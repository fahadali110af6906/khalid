<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Accounting\CustomerKhataService;
use App\Services\Inventory\InventoryService;
use App\Services\Sales\SalesPostingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class SalesInvoicingAndPostingTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $shop1Manager;
    protected User $shop2Manager;
    protected Location $shop1;
    protected Location $shop2;
    protected Customer $customer1;
    protected Customer $customer2;
    protected Product $product;
    protected ProductVariant $variant1;
    protected ProductVariant $variant2;
    protected InventoryService $inventoryService;
    protected CustomerKhataService $khataService;
    protected SalesPostingService $salesService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->owner = User::where('email', 'khalid.owner@khalidtraders.pk')->firstOrFail();
        $this->shop1Manager = User::where('email', 'tariq.anarkali@khalidtraders.pk')->firstOrFail();
        $this->shop2Manager = User::where('email', 'irfan.shahalam@khalidtraders.pk')->firstOrFail();

        $this->shop1 = Location::where('code', 'LOC-SH01')->firstOrFail();
        $this->shop2 = Location::where('code', 'LOC-SH02')->firstOrFail();

        $this->inventoryService = app(InventoryService::class);
        $this->khataService = app(CustomerKhataService::class);
        $this->salesService = app(SalesPostingService::class);

        // Setup test customer
        $this->customer1 = Customer::create([
            'customer_code' => 'CUST-001',
            'business_name' => 'Bismillah Footwear',
            'contact_person' => 'Chaudhry Akram',
            'phone' => '03001234567',
            'primary_location_id' => $this->shop1->id,
            'credit_limit' => 500000.00,
            'opening_balance' => 0.00,
            'created_by' => $this->owner->id,
        ]);

        $this->customer2 = Customer::create([
            'customer_code' => 'CUST-002',
            'business_name' => 'Lahore Shoes Center',
            'contact_person' => 'Mian Tariq',
            'phone' => '03219876543',
            'primary_location_id' => $this->shop2->id,
            'credit_limit' => 300000.00,
            'opening_balance' => 0.00,
            'created_by' => $this->owner->id,
        ]);

        // Setup test product and variants
        $uom = UnitOfMeasure::firstOrCreate(['code' => 'PAIR'], ['name' => 'Pair', 'is_base_unit' => true]);
        $category = ProductCategory::firstOrCreate(['name' => 'Formal Footwear'], ['code' => 'CAT-FORMAL']);

        $this->product = Product::create([
            'category_id' => $category->id,
            'base_unit_id' => $uom->id,
            'article_code' => 'ART-OXFORD-01',
            'name' => 'Classic Oxford Leather Shoes',
            'carton_pack_qty' => 12, // 12 pairs per carton
            'default_wholesale_rate' => 24000.00, // per carton
            'default_retail_rate' => 2500.00, // per pair
            'is_active' => true,
        ]);

        $this->variant1 = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'OXF-BLK-42',
            'size' => '42',
            'color' => 'Black',
            'wholesale_rate' => 24000.00,
            'purchase_cost' => 1500.0000,
            'is_active' => true,
        ]);

        $this->variant2 = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'OXF-BRN-43',
            'size' => '43',
            'color' => 'Brown',
            'wholesale_rate' => 24000.00,
            'purchase_cost' => 1500.0000,
            'is_active' => true,
        ]);
    }

    /**
     * Helper to stock a variant at a location.
     */
    protected function stockVariant(Location $location, ProductVariant $variant, float $pairs, float $cost = 1500.00): void
    {
        $this->inventoryService->addStock(
            locationId: $location->id,
            variantId: $variant->id,
            quantityPairs: $pairs,
            unitCost: $cost,
            movementType: 'purchase',
            user: $this->owner,
            referenceType: 'SupplierReceipt',
            referenceId: 999
        );
    }

    /*
    |--------------------------------------------------------------------------
    | INVOICE CREATION & DRAFT BEHAVIOR
    |--------------------------------------------------------------------------
    */

    public function test_can_create_valid_draft_invoice(): void
    {
        $invoice = $this->salesService->createDraft([
            'customer_id' => $this->customer1->id,
            'location_id' => $this->shop1->id,
            'invoice_date' => '2026-10-23',
            'items' => [
                [
                    'product_variant_id' => $this->variant1->id,
                    'unit_type' => 'carton',
                    'quantity' => 2, // 2 cartons = 24 pairs
                    'unit_price' => 24000.00,
                    'line_discount' => 1000.00,
                ],
            ],
            'discount_amount' => 500.00,
            'tax_amount' => 0.00,
        ], $this->shop1Manager);

        $this->assertDatabaseHas('sales_invoices', [
            'id' => $invoice->id,
            'status' => 'draft',
            'customer_id' => $this->customer1->id,
            'location_id' => $this->shop1->id,
            'total_cartons' => 2,
            'total_pairs' => '24.0000',
            'subtotal_amount' => '47000.00', // (2 * 24000) - 1000 = 47000
            'discount_amount' => '500.00',
            'total_amount' => '46500.00', // 47000 - 500 = 46500
            'paid_amount' => '0.00',
            'balance_amount' => '46500.00',
            'payment_status' => 'unpaid',
        ]);

        $this->assertCount(1, $invoice->items);
        $this->assertEquals('24.0000', $invoice->items->first()->quantity_pairs);
    }

    public function test_draft_invoice_does_not_deduct_inventory_stock(): void
    {
        $this->stockVariant($this->shop1, $this->variant1, 60.0); // 60 pairs = 5 cartons

        $balanceBefore = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->variant1->id)
            ->value('pairs_quantity');

        $this->salesService->createDraft([
            'customer_id' => $this->customer1->id,
            'location_id' => $this->shop1->id,
            'invoice_date' => '2026-10-23',
            'items' => [
                [
                    'product_variant_id' => $this->variant1->id,
                    'unit_type' => 'carton',
                    'quantity' => 2,
                    'unit_price' => 24000.00,
                ],
            ],
        ], $this->shop1Manager);

        $balanceAfter = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->variant1->id)
            ->value('pairs_quantity');

        // Stock MUST remain exactly unchanged
        $this->assertEquals($balanceBefore, $balanceAfter);

        // No stock movements recorded with this type
        $this->assertEquals(0, StockMovement::where('movement_type', 'sale')->count());
    }

    public function test_draft_invoice_does_not_post_to_customer_ledger(): void
    {
        $this->salesService->createDraft([
            'customer_id' => $this->customer1->id,
            'location_id' => $this->shop1->id,
            'invoice_date' => '2026-10-23',
            'items' => [
                [
                    'product_variant_id' => $this->variant1->id,
                    'unit_type' => 'carton',
                    'quantity' => 1,
                    'unit_price' => 24000.00,
                ],
            ],
        ], $this->shop1Manager);

        // Khata ledger entries must remain 0
        $this->assertEquals(0, CustomerLedgerEntry::where('customer_id', $this->customer1->id)->count());
        $summary = $this->khataService->calculateBalance($this->customer1);
        $this->assertEquals('0.00', $summary['closing_balance']);
    }

    public function test_invalid_negative_or_zero_values_are_rejected_on_draft(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->salesService->createDraft([
            'customer_id' => $this->customer1->id,
            'location_id' => $this->shop1->id,
            'invoice_date' => '2026-10-23',
            'items' => [
                [
                    'product_variant_id' => $this->variant1->id,
                    'unit_type' => 'carton',
                    'quantity' => -5, // Negative quantity
                    'unit_price' => 24000.00,
                ],
            ],
        ], $this->shop1Manager);
    }

    public function test_inactive_variant_is_rejected(): void
    {
        $this->variant1->update(['is_active' => false]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('inactive');

        $this->salesService->createDraft([
            'customer_id' => $this->customer1->id,
            'location_id' => $this->shop1->id,
            'invoice_date' => '2026-10-23',
            'items' => [
                [
                    'product_variant_id' => $this->variant1->id,
                    'unit_type' => 'carton',
                    'quantity' => 1,
                    'unit_price' => 24000.00,
                ],
            ],
        ], $this->shop1Manager);
    }

    /*
    |--------------------------------------------------------------------------
    | INVOICE POSTING, STOCK DEDUCTION & KHATA LEDGER
    |--------------------------------------------------------------------------
    */

    public function test_posting_invoice_deducts_inventory_and_debits_khata_exactly_once(): void
    {
        // 1. Stock 48 pairs (4 cartons)
        $this->stockVariant($this->shop1, $this->variant1, 48.0, 1500.00);

        // 2. Create Draft for 2 cartons (24 pairs)
        $invoice = $this->salesService->createDraft([
            'customer_id' => $this->customer1->id,
            'location_id' => $this->shop1->id,
            'invoice_date' => '2026-10-23',
            'items' => [
                [
                    'product_variant_id' => $this->variant1->id,
                    'unit_type' => 'carton',
                    'quantity' => 2,
                    'unit_price' => 25000.00,
                ],
            ],
        ], $this->shop1Manager);

        $this->assertTrue($invoice->isDraft());

        // 3. Post Invoice (Credit sale with no initial payment)
        $postedInvoice = $this->salesService->postInvoice($invoice, $this->shop1Manager);

        $this->assertTrue($postedInvoice->isPosted());
        $this->assertNotNull($postedInvoice->posted_at);
        $this->assertEquals($this->shop1Manager->id, $postedInvoice->posted_by);

        // 4. Verify Stock Deduction: 48 - 24 = 24 pairs remaining
        $remainingStock = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->variant1->id)
            ->value('pairs_quantity');

        $this->assertEquals('24.0000', (string) $remainingStock);

        // 5. Verify Stock Movement Audit Record
        $this->assertDatabaseHas('stock_movements', [
            'location_id' => $this->shop1->id,
            'product_variant_id' => $this->variant1->id,
            'movement_type' => 'sale',
            'quantity_delta_pairs' => -24.0,
            'balance_after_pairs' => 24.0,
            'reference_type' => SalesInvoice::class,
            'reference_id' => $invoice->id,
        ]);

        // 6. Verify Customer Khata Debit Entry
        $this->assertDatabaseHas('customer_ledger_entries', [
            'customer_id' => $this->customer1->id,
            'location_id' => $this->shop1->id,
            'entry_type' => 'sale_invoice',
            'reference_type' => 'SalesInvoice',
            'reference_id' => $invoice->id,
            'debit_amount' => '50000.00',
            'credit_amount' => '0.00',
        ]);

        // 7. Verify Customer Balance = 50,000.00 PKR Receivable
        $khata = $this->khataService->calculateBalance($this->customer1);
        $this->assertEquals('50000.00', $khata['closing_balance']);
        $this->assertEquals('receivable', $khata['balance_status']);
    }

    public function test_cannot_post_the_same_invoice_twice(): void
    {
        $this->stockVariant($this->shop1, $this->variant1, 24.0);

        $invoice = $this->salesService->createDraft([
            'customer_id' => $this->customer1->id,
            'location_id' => $this->shop1->id,
            'invoice_date' => '2026-10-23',
            'items' => [
                [
                    'product_variant_id' => $this->variant1->id,
                    'unit_type' => 'carton',
                    'quantity' => 1,
                    'unit_price' => 24000.00,
                ],
            ],
        ], $this->shop1Manager);

        $this->salesService->postInvoice($invoice, $this->shop1Manager);

        // Second post attempt must be rejected
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already posted');

        $this->salesService->postInvoice($invoice, $this->shop1Manager);
    }

    public function test_insufficient_stock_rejects_posting_and_preserves_stock(): void
    {
        // Available stock is only 12 pairs (1 carton)
        $this->stockVariant($this->shop1, $this->variant1, 12.0);

        // Attempting to post invoice for 3 cartons (36 pairs)
        $invoice = $this->salesService->createDraft([
            'customer_id' => $this->customer1->id,
            'location_id' => $this->shop1->id,
            'invoice_date' => '2026-10-23',
            'items' => [
                [
                    'product_variant_id' => $this->variant1->id,
                    'unit_type' => 'carton',
                    'quantity' => 3,
                    'unit_price' => 24000.00,
                ],
            ],
        ], $this->shop1Manager);

        try {
            $this->salesService->postInvoice($invoice, $this->shop1Manager);
            $this->fail("Expected InsufficientStockException when stock is inadequate.");
        } catch (InsufficientStockException $e) {
            $this->assertStringContainsString('Cannot deduct 36 pairs', $e->getMessage());
        }

        // Invoice status remains draft
        $this->assertEquals('draft', $invoice->fresh()->status);

        // Stock balance untouched (still 12 pairs)
        $currentStock = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->variant1->id)
            ->value('pairs_quantity');
        $this->assertEquals('12.0000', (string) $currentStock);

        // No ledger entries posted
        $this->assertEquals(0, CustomerLedgerEntry::where('customer_id', $this->customer1->id)->count());
    }

    /*
    |--------------------------------------------------------------------------
    | PAYMENTS: CASH, PARTIAL, ADVANCE & LATER SETTLEMENTS
    |--------------------------------------------------------------------------
    */

    public function test_cash_sale_paid_in_full_at_posting(): void
    {
        $this->stockVariant($this->shop1, $this->variant1, 24.0);

        $invoice = $this->salesService->createDraft([
            'customer_id' => $this->customer1->id,
            'location_id' => $this->shop1->id,
            'invoice_date' => '2026-10-23',
            'items' => [
                [
                    'product_variant_id' => $this->variant1->id,
                    'unit_type' => 'carton',
                    'quantity' => 1,
                    'unit_price' => 24000.00,
                ],
            ],
        ], $this->shop1Manager);

        // Post with full cash payment of 24,000 PKR
        $posted = $this->salesService->postInvoice($invoice, $this->shop1Manager, [
            'amount' => 24000.00,
            'payment_method' => 'cash',
            'notes' => 'Full counter cash payment',
        ]);

        $this->assertEquals('24000.00', $posted->paid_amount);
        $this->assertEquals('0.00', $posted->balance_amount);
        $this->assertEquals('paid', $posted->payment_status);

        // Verify Payment record created
        $this->assertDatabaseHas('payments', [
            'sales_invoice_id' => $invoice->id,
            'customer_id' => $this->customer1->id,
            'amount' => '24000.00',
            'payment_method' => 'cash',
        ]);

        // Customer ledger has 2 entries: 1 Debit (24,000) and 1 Credit (24,000)
        $this->assertEquals(2, CustomerLedgerEntry::where('customer_id', $this->customer1->id)->count());

        // Net balance = 0.00 (Settled)
        $khata = $this->khataService->calculateBalance($this->customer1);
        $this->assertEquals('0.00', $khata['closing_balance']);
        $this->assertEquals('settled', $khata['balance_status']);
    }

    public function test_partial_initial_payment_and_later_payment_collection(): void
    {
        $this->stockVariant($this->shop1, $this->variant1, 24.0);

        // Invoice total = 24,000 PKR
        $invoice = $this->salesService->createDraft([
            'customer_id' => $this->customer1->id,
            'location_id' => $this->shop1->id,
            'invoice_date' => '2026-10-23',
            'items' => [
                [
                    'product_variant_id' => $this->variant1->id,
                    'unit_type' => 'carton',
                    'quantity' => 1,
                    'unit_price' => 24000.00,
                ],
            ],
        ], $this->shop1Manager);

        // 1. Post with partial initial payment of 10,000 PKR
        $posted = $this->salesService->postInvoice($invoice, $this->shop1Manager, [
            'amount' => 10000.00,
            'payment_method' => 'cash',
        ]);

        $this->assertEquals('10000.00', $posted->paid_amount);
        $this->assertEquals('14000.00', $posted->balance_amount);
        $this->assertEquals('partial', $posted->payment_status);

        // Party Khata balance: 24,000 Debit - 10,000 Credit = 14,000 Receivable
        $khata1 = $this->khataService->calculateBalance($this->customer1);
        $this->assertEquals('14000.00', $khata1['closing_balance']);

        // 2. Collect second payment of 9,000 PKR later via bank transfer
        $this->salesService->recordPayment($posted, [
            'amount' => 9000.00,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'HBL-TXN-98765',
        ], $this->shop1Manager);

        $posted->refresh();
        $this->assertEquals('19000.00', $posted->paid_amount);
        $this->assertEquals('5000.00', $posted->balance_amount);
        $this->assertEquals('partial', $posted->payment_status);

        // Khata balance: 14,000 - 9,000 = 5,000 Receivable
        $khata2 = $this->khataService->calculateBalance($this->customer1);
        $this->assertEquals('5000.00', $khata2['closing_balance']);

        // 3. Final payment of 5,000 PKR clears invoice completely
        $this->salesService->recordPayment($posted, [
            'amount' => 5000.00,
            'payment_method' => 'cash',
        ], $this->shop1Manager);

        $posted->refresh();
        $this->assertEquals('24000.00', $posted->paid_amount);
        $this->assertEquals('0.00', $posted->balance_amount);
        $this->assertEquals('paid', $posted->payment_status);

        // Khata balance is fully settled (0.00)
        $khata3 = $this->khataService->calculateBalance($this->customer1);
        $this->assertEquals('0.00', $khata3['closing_balance']);
        $this->assertEquals('settled', $khata3['balance_status']);
    }

    public function test_overpayment_on_invoice_is_rejected(): void
    {
        $this->stockVariant($this->shop1, $this->variant1, 24.0);

        $invoice = $this->salesService->createDraft([
            'customer_id' => $this->customer1->id,
            'location_id' => $this->shop1->id,
            'invoice_date' => '2026-10-23',
            'items' => [
                [
                    'product_variant_id' => $this->variant1->id,
                    'unit_type' => 'carton',
                    'quantity' => 1,
                    'unit_price' => 20000.00,
                ],
            ],
        ], $this->shop1Manager);

        $posted = $this->salesService->postInvoice($invoice, $this->shop1Manager);

        // Attempt to collect 25,000 PKR against 20,000 PKR invoice
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds outstanding invoice balance');

        $this->salesService->recordPayment($posted, [
            'amount' => 25000.00,
            'payment_method' => 'cash',
        ], $this->shop1Manager);
    }

    public function test_direct_on_account_advance_payment(): void
    {
        // Customer pays 50,000 PKR advance before ordering
        $payment = $this->salesService->recordDirectPayment(
            customer: $this->customer1,
            locationId: $this->shop1->id,
            data: [
                'amount' => 50000.00,
                'payment_method' => 'bank_transfer',
                'reference_number' => 'ADV-8899',
            ],
            user: $this->shop1Manager
        );

        $this->assertNull($payment->sales_invoice_id);
        $this->assertEquals('50000.00', $payment->amount);

        // Customer ledger balance: 0 Debit - 50,000 Credit = -50,000 (Advance / Payable)
        $khata = $this->khataService->calculateBalance($this->customer1);
        $this->assertEquals('-50000.00', $khata['closing_balance']);
        $this->assertEquals('payable', $khata['balance_status']);
    }

    /*
    |--------------------------------------------------------------------------
    | SECURITY & MULTI-LOCATION AUTHORIZATION
    |--------------------------------------------------------------------------
    */

    public function test_manager_cannot_create_invoice_for_unauthorized_location(): void
    {
        // Tariq (Shop 1 Manager) attempting to create invoice at Shop 2 (LOC-SH02)
        $this->expectException(AuthorizationException::class);

        $this->salesService->createDraft([
            'customer_id' => $this->customer2->id,
            'location_id' => $this->shop2->id, // Shop 2 is unauthorized for Tariq
            'invoice_date' => '2026-10-23',
            'items' => [
                [
                    'product_variant_id' => $this->variant1->id,
                    'unit_type' => 'carton',
                    'quantity' => 1,
                    'unit_price' => 24000.00,
                ],
            ],
        ], $this->shop1Manager);
    }

    public function test_manager_cannot_collect_payment_for_unauthorized_location(): void
    {
        $this->stockVariant($this->shop2, $this->variant2, 24.0);

        // Owner creates and posts invoice for Shop 2
        $invoice = $this->salesService->createDraft([
            'customer_id' => $this->customer2->id,
            'location_id' => $this->shop2->id,
            'invoice_date' => '2026-10-23',
            'items' => [
                [
                    'product_variant_id' => $this->variant2->id,
                    'unit_type' => 'carton',
                    'quantity' => 1,
                    'unit_price' => 24000.00,
                ],
            ],
        ], $this->owner);

        $posted = $this->salesService->postInvoice($invoice, $this->owner);

        // Shop 1 Manager attempting to collect payment on Shop 2 invoice
        $this->expectException(AuthorizationException::class);

        $this->salesService->recordPayment($posted, [
            'amount' => 5000.00,
            'payment_method' => 'cash',
        ], $this->shop1Manager);
    }

    public function test_owner_has_cross_location_authorization_for_all_shops(): void
    {
        $this->stockVariant($this->shop1, $this->variant1, 12.0);
        $this->stockVariant($this->shop2, $this->variant2, 12.0);

        // Owner creates for Shop 1
        $inv1 = $this->salesService->createDraft([
            'customer_id' => $this->customer1->id,
            'location_id' => $this->shop1->id,
            'items' => [['product_variant_id' => $this->variant1->id, 'unit_type' => 'pair', 'quantity' => 6, 'unit_price' => 2000.00]],
        ], $this->owner);
        $posted1 = $this->salesService->postInvoice($inv1, $this->owner);
        $this->assertTrue($posted1->isPosted());

        // Owner creates for Shop 2
        $inv2 = $this->salesService->createDraft([
            'customer_id' => $this->customer2->id,
            'location_id' => $this->shop2->id,
            'items' => [['product_variant_id' => $this->variant2->id, 'unit_type' => 'pair', 'quantity' => 6, 'unit_price' => 2000.00]],
        ], $this->owner);
        $posted2 = $this->salesService->postInvoice($inv2, $this->owner);
        $this->assertTrue($posted2->isPosted());
    }

    /*
    |--------------------------------------------------------------------------
    | MULTI-VARIANT TRANSACTIONS & DEADLOCK-SAFE LOCK ORDER
    |--------------------------------------------------------------------------
    */

    public function test_multiple_variants_handled_and_ordered_safely_in_single_invoice(): void
    {
        $this->stockVariant($this->shop1, $this->variant1, 24.0); // 2 cartons of V1
        $this->stockVariant($this->shop1, $this->variant2, 36.0); // 3 cartons of V2

        // Create invoice containing both variants in reverse ID order
        $invoice = $this->salesService->createDraft([
            'customer_id' => $this->customer1->id,
            'location_id' => $this->shop1->id,
            'items' => [
                [
                    'product_variant_id' => $this->variant2->id, // Higher ID first
                    'unit_type' => 'carton',
                    'quantity' => 1,
                    'unit_price' => 24000.00,
                ],
                [
                    'product_variant_id' => $this->variant1->id, // Lower ID second
                    'unit_type' => 'carton',
                    'quantity' => 1,
                    'unit_price' => 24000.00,
                ],
            ],
        ], $this->shop1Manager);

        $posted = $this->salesService->postInvoice($invoice, $this->shop1Manager);

        $this->assertTrue($posted->isPosted());
        $this->assertEquals('48000.00', $posted->total_amount);

        // Verify both stocks deducted
        $this->assertEquals('12.0000', (string) StockBalance::where('location_id', $this->shop1->id)->where('product_variant_id', $this->variant1->id)->value('pairs_quantity'));
        $this->assertEquals('24.0000', (string) StockBalance::where('location_id', $this->shop1->id)->where('product_variant_id', $this->variant2->id)->value('pairs_quantity'));

        // Customer ledger has single invoice debit of 48,000 PKR
        $khata = $this->khataService->calculateBalance($this->customer1);
        $this->assertEquals('48000.00', $khata['closing_balance']);
    }

    public function test_failure_on_second_item_rolls_back_entire_transaction(): void
    {
        $this->stockVariant($this->shop1, $this->variant1, 24.0); // Sufficient stock for V1
        // V2 has ZERO stock!

        $invoice = $this->salesService->createDraft([
            'customer_id' => $this->customer1->id,
            'location_id' => $this->shop1->id,
            'items' => [
                [
                    'product_variant_id' => $this->variant1->id,
                    'unit_type' => 'carton',
                    'quantity' => 1,
                    'unit_price' => 24000.00,
                ],
                [
                    'product_variant_id' => $this->variant2->id, // Will fail on insufficient stock!
                    'unit_type' => 'carton',
                    'quantity' => 1,
                    'unit_price' => 24000.00,
                ],
            ],
        ], $this->shop1Manager);

        try {
            $this->salesService->postInvoice($invoice, $this->shop1Manager);
            $this->fail("Expected InsufficientStockException.");
        } catch (InsufficientStockException $e) {
            $this->assertStringContainsString('Cannot deduct', $e->getMessage());
        }

        // V1 stock MUST NOT have been deducted (must roll back from 24)
        $v1Stock = StockBalance::where('location_id', $this->shop1->id)->where('product_variant_id', $this->variant1->id)->value('pairs_quantity');
        $this->assertEquals('24.0000', (string) $v1Stock);

        // Invoice status must remain draft
        $this->assertEquals('draft', $invoice->fresh()->status);

        // No ledger entries posted
        $this->assertEquals(0, CustomerLedgerEntry::where('customer_id', $this->customer1->id)->count());
    }

    /*
    |--------------------------------------------------------------------------
    | HTTP CONTROLLER FEATURE TESTS (ZERO-IDOR & UI FLOWS)
    |--------------------------------------------------------------------------
    */

    public function test_http_invoice_index_filters_and_forbids_unauthorized_location(): void
    {
        // 1. Shop 1 Manager viewing Shop 1 invoices succeeds
        $response = $this->actingAs($this->shop1Manager)->get(route('sales.invoices.index', ['location_id' => $this->shop1->id]));
        $response->assertStatus(200);

        // 2. Shop 1 Manager attempting to view Shop 2 invoices is 403 Forbidden
        $responseForbidden = $this->actingAs($this->shop1Manager)->get(route('sales.invoices.index', ['location_id' => $this->shop2->id]));
        $responseForbidden->assertStatus(403);
    }

    public function test_http_can_create_and_post_invoice_via_form(): void
    {
        $this->stockVariant($this->shop1, $this->variant1, 24.0);

        $response = $this->actingAs($this->shop1Manager)->post(route('sales.invoices.store'), [
            'customer_id' => $this->customer1->id,
            'location_id' => $this->shop1->id,
            'invoice_date' => '2026-10-23',
            'action' => 'post_immediately',
            'items' => [
                [
                    'product_variant_id' => $this->variant1->id,
                    'unit_type' => 'carton',
                    'quantity' => 1,
                    'unit_price' => 25000.00,
                    'line_discount' => 0.00,
                ],
            ],
            'initial_payment' => [
                'amount' => 15000.00,
                'payment_method' => 'cash',
            ],
        ]);

        $invoice = SalesInvoice::where('customer_id', $this->customer1->id)->firstOrFail();
        $this->assertTrue($invoice->isPosted());
        $this->assertEquals('15000.00', $invoice->paid_amount);
        $this->assertEquals('10000.00', $invoice->balance_amount);
        $this->assertEquals('partial', $invoice->payment_status);

        $response->assertRedirect(route('sales.invoices.show', $invoice));
    }

    public function test_http_can_collect_payment_on_posted_invoice(): void
    {
        $this->stockVariant($this->shop1, $this->variant1, 24.0);

        $invoice = $this->salesService->createDraft([
            'customer_id' => $this->customer1->id,
            'location_id' => $this->shop1->id,
            'items' => [['product_variant_id' => $this->variant1->id, 'unit_type' => 'pair', 'quantity' => 10, 'unit_price' => 2000.00]],
        ], $this->shop1Manager);

        $this->salesService->postInvoice($invoice, $this->shop1Manager);

        $response = $this->actingAs($this->shop1Manager)->post(route('sales.invoices.payments.store', $invoice), [
            'amount' => 12000.00,
            'payment_date' => '2026-10-23',
            'payment_method' => 'cash',
        ]);

        $invoice->refresh();
        $this->assertEquals('12000.00', $invoice->paid_amount);
        $this->assertEquals('8000.00', $invoice->balance_amount);
        $response->assertRedirect(route('sales.invoices.show', $invoice));
    }
}
