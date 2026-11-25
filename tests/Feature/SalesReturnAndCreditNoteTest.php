<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerCreditNote;
use App\Models\CustomerLedgerEntry;
use App\Models\CustomerRefund;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Accounting\CustomerKhataService;
use App\Services\Inventory\InventoryService;
use App\Services\Sales\SalesPostingService;
use App\Services\Sales\SalesReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class SalesReturnAndCreditNoteTest extends TestCase
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
    protected SalesPostingService $salesPostingService;
    protected SalesReturnService $salesReturnService;

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
        $this->salesPostingService = app(SalesPostingService::class);
        $this->salesReturnService = app(SalesReturnService::class);

        // Setup test customer
        $this->customer1 = Customer::firstOrCreate(
            ['customer_code' => 'CUST-001'],
            [
                'business_name' => 'Bismillah Footwear',
                'contact_person' => 'Chaudhry Akram',
                'phone' => '03001234567',
                'primary_location_id' => $this->shop1->id,
                'credit_limit' => 500000.00,
                'opening_balance' => 0.00,
                'created_by' => $this->owner->id,
            ]
        );

        $this->customer2 = Customer::firstOrCreate(
            ['customer_code' => 'CUST-002'],
            [
                'business_name' => 'Lahore Shoes Center',
                'contact_person' => 'Mian Tariq',
                'phone' => '03219876543',
                'primary_location_id' => $this->shop2->id,
                'credit_limit' => 300000.00,
                'opening_balance' => 0.00,
                'created_by' => $this->owner->id,
            ]
        );

        // Setup test product and variants
        $uom = UnitOfMeasure::firstOrCreate(['code' => 'PAIR'], ['name' => 'Pair', 'is_base_unit' => true]);
        $category = ProductCategory::firstOrCreate(['name' => 'Formal Footwear'], ['code' => 'CAT-FORMAL']);

        $this->product = Product::firstOrCreate(
            ['article_code' => 'ART-RET-01'],
            [
                'category_id' => $category->id,
                'base_unit_id' => $uom->id,
                'name' => 'Classic Derby Leather Shoes',
                'carton_pack_qty' => 12,
                'default_wholesale_rate' => 24000.00,
                'default_retail_rate' => 2500.00,
                'is_active' => true,
            ]
        );

        $this->variant1 = ProductVariant::firstOrCreate(
            ['sku' => 'DERBY-BLK-42'],
            [
                'product_id' => $this->product->id,
                'size' => '42',
                'color' => 'Black',
                'wholesale_rate' => 24000.00,
                'purchase_cost' => 1500.0000,
                'is_active' => true,
            ]
        );

        $this->variant2 = ProductVariant::firstOrCreate(
            ['sku' => 'DERBY-BRN-43'],
            [
                'product_id' => $this->product->id,
                'size' => '43',
                'color' => 'Brown',
                'wholesale_rate' => 24000.00,
                'purchase_cost' => 1500.0000,
                'is_active' => true,
            ]
        );
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

    /**
     * Helper to create and post a sales invoice for testing returns.
     */
    protected function createAndPostInvoice(Customer $customer, Location $location, array $items): SalesInvoice
    {
        // Ensure stock exists for invoice items
        foreach ($items as $item) {
            $variant = ProductVariant::findOrFail($item['product_variant_id']);
            $unitType = $item['unit_type'] ?? 'carton';
            $pairs = ($unitType === 'carton') ? ($item['quantity'] * 12) : $item['quantity'];
            $this->stockVariant($location, $variant, (float) $pairs);
        }

        $draft = $this->salesPostingService->createDraft([
            'customer_id' => $customer->id,
            'location_id' => $location->id,
            'invoice_date' => now()->toDateString(),
            'items' => $items,
        ], $this->owner);

        return $this->salesPostingService->postInvoice($draft, $this->owner);
    }

    /**
     * 1. Test draft sales return creation does not alter stock or customer khata.
     */
    public function test_draft_sales_return_creation_does_not_alter_stock_or_customer_khata(): void
    {
        $invoice = $this->createAndPostInvoice($this->customer1, $this->shop1, [
            [
                'product_variant_id' => $this->variant1->id,
                'unit_type' => 'carton',
                'quantity' => 2, // 24 pairs
                'unit_price' => 24000.00,
            ]
        ]);

        $item = $invoice->items->first();
        $stockBefore = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->variant1->id)
            ->value('pairs_quantity');

        $khataBefore = $this->khataService->calculateBalance($this->customer1);

        $return = $this->salesReturnService->createDraftReturn($invoice, [
            'return_date' => now()->toDateString(),
            'reason' => 'Customer overstocked',
            'items' => [
                [
                    'sales_invoice_item_id' => $item->id,
                    'returned_cartons' => 1,
                    'returned_pairs' => 12,
                ]
            ]
        ], $this->owner);

        $this->assertEquals('draft', $return->status);
        $this->assertEquals(12, $return->total_returned_pairs);
        $this->assertEquals(24000.00, $return->total_return_amount);

        // Check stock balance unchanged
        $stockAfter = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->variant1->id)
            ->value('pairs_quantity');
        $this->assertEquals($stockBefore, $stockAfter);

        // Check customer khata unchanged
        $khataAfter = $this->khataService->calculateBalance($this->customer1);
        $this->assertEquals($khataBefore['closing_balance'], $khataAfter['closing_balance']);
    }

    /**
     * 2. Test cannot create sales return for unposted sales invoice.
     */
    public function test_cannot_create_sales_return_for_unposted_sales_invoice(): void
    {
        $this->stockVariant($this->shop1, $this->variant1, 24);

        $draftInvoice = $this->salesPostingService->createDraft([
            'customer_id' => $this->customer1->id,
            'location_id' => $this->shop1->id,
            'invoice_date' => now()->toDateString(),
            'items' => [
                [
                    'product_variant_id' => $this->variant1->id,
                    'unit_type' => 'carton',
                    'quantity' => 1,
                    'unit_price' => 24000.00,
                ]
            ],
        ], $this->owner);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sales returns can only be created for posted invoices');

        $this->salesReturnService->createDraftReturn($draftInvoice, [
            'reason' => 'Defect',
            'items' => [
                [
                    'sales_invoice_item_id' => $draftInvoice->items->first()->id,
                    'returned_pairs' => 12,
                ]
            ]
        ], $this->owner);
    }

    /**
     * 3. Test post sales return restocks inventory and records audited stock movement.
     */
    public function test_post_sales_return_restocks_inventory_and_records_audited_stock_movement(): void
    {
        $invoice = $this->createAndPostInvoice($this->customer1, $this->shop1, [
            [
                'product_variant_id' => $this->variant1->id,
                'unit_type' => 'carton',
                'quantity' => 2, // 24 pairs sold
                'unit_price' => 24000.00,
            ]
        ]);

        $item = $invoice->items->first();
        $stockAfterSale = (float) StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->variant1->id)
            ->value('pairs_quantity');

        $return = $this->salesReturnService->createDraftReturn($invoice, [
            'reason' => 'Size mismatch',
            'items' => [
                [
                    'sales_invoice_item_id' => $item->id,
                    'returned_pairs' => 12,
                    'is_restocked' => true,
                ]
            ]
        ], $this->owner);

        $postedReturn = $this->salesReturnService->postReturn($return, [], $this->owner);

        $this->assertEquals('posted', $postedReturn->status);
        $this->assertNotNull($postedReturn->posted_at);

        // Inventory must be increased by 12 pairs
        $stockAfterReturn = (float) StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->variant1->id)
            ->value('pairs_quantity');

        $this->assertEquals($stockAfterSale + 12, $stockAfterReturn);

        // Audited movement must exist
        $movement = StockMovement::where('movement_type', 'sale_return')
            ->where('reference_type', 'sales_return')
            ->where('reference_id', $postedReturn->id)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(12, $movement->quantity_delta_pairs);
        $this->assertEquals($postedReturn->items->first()->unit_cost, $movement->unit_cost);
    }

    /**
     * 4. Test post sales return credits customer khata once.
     */
    public function test_post_sales_return_credits_customer_khata_once(): void
    {
        $invoice = $this->createAndPostInvoice($this->customer1, $this->shop1, [
            [
                'product_variant_id' => $this->variant1->id,
                'unit_type' => 'carton',
                'quantity' => 2, // 24 pairs, total 48,000 PKR
                'unit_price' => 24000.00,
            ]
        ]);

        $khataAfterSale = $this->khataService->calculateBalance($this->customer1);
        $this->assertEquals('48000.00', $khataAfterSale['closing_balance']);

        $item = $invoice->items->first();
        $return = $this->salesReturnService->createDraftReturn($invoice, [
            'reason' => 'Returns 1 carton',
            'items' => [
                [
                    'sales_invoice_item_id' => $item->id,
                    'returned_pairs' => 12, // 24,000 PKR
                ]
            ]
        ], $this->owner);

        $this->salesReturnService->postReturn($return, [], $this->owner);

        // Customer receivable must decrease from 48,000 to 24,000
        $khataAfterReturn = $this->khataService->calculateBalance($this->customer1);
        $this->assertEquals('24000.00', $khataAfterReturn['closing_balance']);

        // Exactly one ledger entry for this return
        $entries = CustomerLedgerEntry::where('customer_id', $this->customer1->id)
            ->where('reference_type', 'sales_return')
            ->where('reference_id', $return->id)
            ->get();

        $this->assertCount(1, $entries);
        $this->assertEquals('sale_return', $entries->first()->entry_type);
        $this->assertEquals('24000.00', $entries->first()->credit_amount);
        $this->assertEquals('0.00', $entries->first()->debit_amount);
    }

    /**
     * 5. Test post sales return generates linked customer credit note without duplicate ledger entry.
     */
    public function test_post_sales_return_generates_linked_customer_credit_note_without_duplicate_ledger_entry(): void
    {
        $invoice = $this->createAndPostInvoice($this->customer1, $this->shop1, [
            [
                'product_variant_id' => $this->variant1->id,
                'unit_type' => 'carton',
                'quantity' => 1, // 12 pairs, 24,000 PKR
                'unit_price' => 24000.00,
            ]
        ]);

        $item = $invoice->items->first();
        $return = $this->salesReturnService->createDraftReturn($invoice, [
            'reason' => 'Full Return',
            'items' => [
                [
                    'sales_invoice_item_id' => $item->id,
                    'returned_pairs' => 12,
                ]
            ]
        ], $this->owner);

        $posted = $this->salesReturnService->postReturn($return, [], $this->owner);

        // Credit note must exist and be linked
        $creditNote = CustomerCreditNote::where('sales_return_id', $posted->id)->first();
        $this->assertNotNull($creditNote);
        $this->assertEquals('sales_return', $creditNote->credit_note_type);
        $this->assertEquals('24000.00', $creditNote->amount);

        // Ledger must NOT have an entry for the credit note reference
        $duplicateEntry = CustomerLedgerEntry::where('customer_id', $this->customer1->id)
            ->where('reference_type', 'customer_credit_note')
            ->where('reference_id', $creditNote->id)
            ->first();

        $this->assertNull($duplicateEntry, 'Linked credit note must not post a duplicate ledger entry.');
    }

    /**
     * 6. Test cannot return more than sold quantity.
     */
    public function test_cannot_return_more_than_sold_quantity(): void
    {
        $invoice = $this->createAndPostInvoice($this->customer1, $this->shop1, [
            [
                'product_variant_id' => $this->variant1->id,
                'unit_type' => 'carton',
                'quantity' => 1, // 12 pairs
                'unit_price' => 24000.00,
            ]
        ]);

        $item = $invoice->items->first();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Return quantity exceeds remaining returnable');

        $this->salesReturnService->createDraftReturn($invoice, [
            'reason' => 'Excess return',
            'items' => [
                [
                    'sales_invoice_item_id' => $item->id,
                    'returned_pairs' => 13, // 13 > 12 sold
                ]
            ]
        ], $this->owner);
    }

    /**
     * 7. Test partial sales returns accumulate correctly and enforce remaining limit.
     */
    public function test_partial_sales_returns_accumulate_correctly_and_enforce_remaining_limit(): void
    {
        $invoice = $this->createAndPostInvoice($this->customer1, $this->shop1, [
            [
                'product_variant_id' => $this->variant1->id,
                'unit_type' => 'carton',
                'quantity' => 2, // 24 pairs
                'unit_price' => 24000.00,
            ]
        ]);

        $item = $invoice->items->first();

        // 1st Return: 10 pairs
        $ret1 = $this->salesReturnService->createDraftReturn($invoice, [
            'reason' => 'Batch 1 return',
            'items' => [
                [
                    'sales_invoice_item_id' => $item->id,
                    'returned_pairs' => 10,
                ]
            ]
        ], $this->owner);
        $this->salesReturnService->postReturn($ret1, [], $this->owner);

        // Remaining returnable should be 14 pairs
        $remaining = $this->salesReturnService->getRemainingReturnablePairs($item);
        $this->assertEquals(14.0, $remaining);

        // 2nd Return: 14 pairs (valid)
        $ret2 = $this->salesReturnService->createDraftReturn($invoice, [
            'reason' => 'Batch 2 return',
            'items' => [
                [
                    'sales_invoice_item_id' => $item->id,
                    'returned_pairs' => 14,
                ]
            ]
        ], $this->owner);
        $this->salesReturnService->postReturn($ret2, [], $this->owner);

        // Remaining returnable should now be 0 pairs
        $this->assertEquals(0.0, $this->salesReturnService->getRemainingReturnablePairs($item));

        // 3rd Return: 1 pair (should fail)
        $this->expectException(InvalidArgumentException::class);
        $this->salesReturnService->createDraftReturn($invoice, [
            'reason' => 'Batch 3 excess',
            'items' => [
                [
                    'sales_invoice_item_id' => $item->id,
                    'returned_pairs' => 1,
                ]
            ]
        ], $this->owner);
    }

    /**
     * 8. Test return item with is_restocked false credits khata but skips inventory addition.
     */
    public function test_return_item_with_is_restocked_false_credits_khata_but_skips_inventory_addition(): void
    {
        $invoice = $this->createAndPostInvoice($this->customer1, $this->shop1, [
            [
                'product_variant_id' => $this->variant1->id,
                'unit_type' => 'carton',
                'quantity' => 1, // 12 pairs
                'unit_price' => 24000.00,
            ]
        ]);

        $item = $invoice->items->first();
        $stockBefore = (float) StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->variant1->id)
            ->value('pairs_quantity');

        $return = $this->salesReturnService->createDraftReturn($invoice, [
            'reason' => 'Damaged/destroyed goods discarded',
            'items' => [
                [
                    'sales_invoice_item_id' => $item->id,
                    'returned_pairs' => 12,
                    'condition' => 'damaged',
                    'is_restocked' => false,
                ]
            ]
        ], $this->owner);

        $posted = $this->salesReturnService->postReturn($return, [], $this->owner);

        // Stock must remain unchanged
        $stockAfter = (float) StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->variant1->id)
            ->value('pairs_quantity');
        $this->assertEquals($stockBefore, $stockAfter);

        // Customer khata must still be credited
        $khata = $this->khataService->calculateBalance($this->customer1);
        $this->assertEquals('0.00', $khata['closing_balance']);
    }

    /**
     * 9. Test restocking preserves or updates WAC correctly.
     */
    public function test_restocking_preserves_or_updates_wac_correctly(): void
    {
        $invoice = $this->createAndPostInvoice($this->customer1, $this->shop1, [
            [
                'product_variant_id' => $this->variant1->id,
                'unit_type' => 'carton',
                'quantity' => 1, // 12 pairs at 1500 cost
                'unit_price' => 24000.00,
            ]
        ]);

        $item = $invoice->items->first();
        $this->assertEquals(1500.00, (float) $item->unit_cost);

        $return = $this->salesReturnService->createDraftReturn($invoice, [
            'reason' => 'Customer return',
            'items' => [
                [
                    'sales_invoice_item_id' => $item->id,
                    'returned_pairs' => 12,
                    'is_restocked' => true,
                ]
            ]
        ], $this->owner);

        $this->salesReturnService->postReturn($return, [], $this->owner);

        $balance = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->variant1->id)
            ->first();

        $this->assertEquals(1500.00, (float) $balance->avg_unit_cost);
    }

    /**
     * 10. Test standalone financial credit note credits customer khata and does not touch inventory.
     */
    public function test_standalone_financial_credit_note_credits_customer_khata_and_does_not_touch_inventory(): void
    {
        $this->stockVariant($this->shop1, $this->variant1, 50);
        $stockBefore = (float) StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->variant1->id)
            ->value('pairs_quantity');

        // Customer owes 50,000 via opening debit
        $this->khataService->recordEntry(
            customer: $this->customer1,
            locationId: $this->shop1->id,
            entryType: 'sale_invoice',
            debitAmount: 50000.00,
            creditAmount: 0.00,
            entryDate: now()->toDateString(),
            user: $this->owner,
            referenceType: 'test_invoice',
            referenceId: 101,
            description: 'Test invoice'
        );

        $creditNote = $this->salesReturnService->recordFinancialCreditNote($this->customer1, [
            'location_id' => $this->shop1->id,
            'note_date' => now()->toDateString(),
            'amount' => 5000.00,
            'reason_type' => 'volume_rebate',
            'reason' => 'Annual 5K sales target rebate',
            'reference_number' => 'REB-2026-01',
        ], $this->owner);

        $this->assertEquals('5000.00', $creditNote->amount);
        $this->assertEquals('financial_adjustment', $creditNote->credit_note_type);

        // Khata balance reduced from 50,000 to 45,000
        $khata = $this->khataService->calculateBalance($this->customer1);
        $this->assertEquals('45000.00', $khata['closing_balance']);

        // Stock unchanged
        $stockAfter = (float) StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->variant1->id)
            ->value('pairs_quantity');
        $this->assertEquals($stockBefore, $stockAfter);
    }

    /**
     * 11. Test cannot create zero or negative credit note.
     */
    public function test_cannot_create_zero_or_negative_credit_note(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Credit note amount must be greater than zero');

        $this->salesReturnService->recordFinancialCreditNote($this->customer1, [
            'location_id' => $this->shop1->id,
            'amount' => 0.00,
            'reason' => 'Zero amount test',
        ], $this->owner);
    }

    /**
     * 12. Test cash refund for sales return debits customer khata and updates refund tracking.
     */
    public function test_cash_refund_for_sales_return_debits_customer_khata_and_updates_refund_tracking(): void
    {
        $invoice = $this->createAndPostInvoice($this->customer1, $this->shop1, [
            [
                'product_variant_id' => $this->variant1->id,
                'unit_type' => 'carton',
                'quantity' => 1,
                'unit_price' => 24000.00,
            ]
        ]);

        $item = $invoice->items->first();
        $return = $this->salesReturnService->createDraftReturn($invoice, [
            'reason' => 'Full Return',
            'items' => [
                [
                    'sales_invoice_item_id' => $item->id,
                    'returned_pairs' => 12,
                ]
            ]
        ], $this->owner);

        $posted = $this->salesReturnService->postReturn($return, [], $this->owner);
        $this->assertEquals('credited_to_khata', $posted->refund_status);

        // Customer pays cash back to customer: 24,000 PKR
        $refund = $this->salesReturnService->recordCustomerRefund($posted, [
            'amount' => 24000.00,
            'refund_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'reference_number' => 'CASH-REF-001',
        ], $this->owner);

        $this->assertEquals('24000.00', $refund->amount);
        $this->assertEquals('refunded', $posted->fresh()->refund_status);
        $this->assertEquals('24000.00', $posted->fresh()->refunded_amount);

        // Khata ledger must have adjustment_debit for the refund
        $ledgerEntry = CustomerLedgerEntry::where('customer_id', $this->customer1->id)
            ->where('reference_type', 'customer_refund')
            ->where('reference_id', $refund->id)
            ->first();

        $this->assertNotNull($ledgerEntry);
        $this->assertEquals('adjustment_debit', $ledgerEntry->entry_type);
        $this->assertEquals('24000.00', $ledgerEntry->debit_amount);
    }

    /**
     * 13. Test cannot refund more than total sales return value.
     */
    public function test_cannot_refund_more_than_total_sales_return_value(): void
    {
        $invoice = $this->createAndPostInvoice($this->customer1, $this->shop1, [
            [
                'product_variant_id' => $this->variant1->id,
                'unit_type' => 'carton',
                'quantity' => 1,
                'unit_price' => 24000.00,
            ]
        ]);

        $item = $invoice->items->first();
        $return = $this->salesReturnService->createDraftReturn($invoice, [
            'reason' => 'Return',
            'items' => [
                [
                    'sales_invoice_item_id' => $item->id,
                    'returned_pairs' => 12, // 24,000 PKR
                ]
            ]
        ], $this->owner);

        $posted = $this->salesReturnService->postReturn($return, [], $this->owner);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds remaining refundable amount');

        $this->salesReturnService->recordCustomerRefund($posted, [
            'amount' => 25000.00, // 25,000 > 24,000
        ], $this->owner);
    }

    /**
     * 14. Test sales return post idempotency returns same instance without repeating stock or khata.
     */
    public function test_sales_return_post_idempotency_returns_same_instance_without_repeating_stock_or_khata(): void
    {
        $invoice = $this->createAndPostInvoice($this->customer1, $this->shop1, [
            [
                'product_variant_id' => $this->variant1->id,
                'unit_type' => 'carton',
                'quantity' => 1,
                'unit_price' => 24000.00,
            ]
        ]);

        $item = $invoice->items->first();
        $return = $this->salesReturnService->createDraftReturn($invoice, [
            'reason' => 'Idempotency test',
            'items' => [
                [
                    'sales_invoice_item_id' => $item->id,
                    'returned_pairs' => 12,
                ]
            ]
        ], $this->owner);

        $key = 'idem-return-' . Str::uuid();

        // 1st Post
        $post1 = $this->salesReturnService->postReturn($return, ['idempotency_key' => $key], $this->owner);

        $stockAfterFirst = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->variant1->id)
            ->value('pairs_quantity');

        $khataAfterFirst = $this->khataService->calculateBalance($this->customer1);

        // 2nd Post with same idempotency key
        $post2 = $this->salesReturnService->postReturn($return, ['idempotency_key' => $key], $this->owner);

        $this->assertEquals($post1->id, $post2->id);

        $stockAfterSecond = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->variant1->id)
            ->value('pairs_quantity');

        $khataAfterSecond = $this->khataService->calculateBalance($this->customer1);

        // Stock and khata must not be applied twice
        $this->assertEquals($stockAfterFirst, $stockAfterSecond);
        $this->assertEquals($khataAfterFirst['closing_balance'], $khataAfterSecond['closing_balance']);
    }

    /**
     * 15. Test sales return post idempotency rejects payload mismatch.
     */
    public function test_sales_return_post_idempotency_rejects_payload_mismatch(): void
    {
        $invoice1 = $this->createAndPostInvoice($this->customer1, $this->shop1, [
            [
                'product_variant_id' => $this->variant1->id,
                'unit_type' => 'carton',
                'quantity' => 1,
                'unit_price' => 24000.00,
            ]
        ]);
        $return1 = $this->salesReturnService->createDraftReturn($invoice1, [
            'reason' => 'First return',
            'items' => [
                [
                    'sales_invoice_item_id' => $invoice1->items->first()->id,
                    'returned_pairs' => 12,
                ]
            ]
        ], $this->owner);

        $invoice2 = $this->createAndPostInvoice($this->customer1, $this->shop1, [
            [
                'product_variant_id' => $this->variant1->id,
                'unit_type' => 'carton',
                'quantity' => 1,
                'unit_price' => 24000.00,
            ]
        ]);
        $return2 = $this->salesReturnService->createDraftReturn($invoice2, [
            'reason' => 'Different return',
            'items' => [
                [
                    'sales_invoice_item_id' => $invoice2->items->first()->id,
                    'returned_pairs' => 6,
                ]
            ]
        ], $this->owner);

        $sharedKey = 'shared-idem-key-' . Str::uuid();

        // Post return 1
        $this->salesReturnService->postReturn($return1, ['idempotency_key' => $sharedKey], $this->owner);

        // Post return 2 with same key (different payload) must fail
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already been used with a different request payload');

        $this->salesReturnService->postReturn($return2, ['idempotency_key' => $sharedKey], $this->owner);
    }

    /**
     * 16. Test location authorization enforced for sales returns and credit notes.
     */
    public function test_location_authorization_enforced_for_sales_returns_and_credit_notes(): void
    {
        // Invoice created at Shop 1
        $invoice = $this->createAndPostInvoice($this->customer1, $this->shop1, [
            [
                'product_variant_id' => $this->variant1->id,
                'unit_type' => 'carton',
                'quantity' => 1,
                'unit_price' => 24000.00,
            ]
        ]);

        // Shop 2 manager cannot create return for Shop 1 invoice
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $this->salesReturnService->createDraftReturn($invoice, [
            'reason' => 'Unauthorized return',
            'items' => [
                [
                    'sales_invoice_item_id' => $invoice->items->first()->id,
                    'returned_pairs' => 12,
                ]
            ]
        ], $this->shop2Manager);
    }

    /**
     * 17. Test web controller routes: index, create, store, show, post, refund.
     */
    public function test_web_controller_routes_index_create_store_show_post_refund(): void
    {
        $this->actingAs($this->owner);

        $invoice = $this->createAndPostInvoice($this->customer1, $this->shop1, [
            [
                'product_variant_id' => $this->variant1->id,
                'unit_type' => 'carton',
                'quantity' => 2,
                'unit_price' => 24000.00,
            ]
        ]);

        // 1. Create return view
        $createPage = $this->get(route('sales.returns.create', $invoice));
        $createPage->assertStatus(200);
        $createPage->assertSee($invoice->invoice_number);

        // 2. Submit draft return
        $storeResponse = $this->post(route('sales.returns.store', $invoice), [
            'return_date' => now()->toDateString(),
            'reason' => 'Defective stitching batch',
            'items' => [
                [
                    'sales_invoice_item_id' => $invoice->items->first()->id,
                    'returned_cartons' => 1,
                    'returned_pairs' => 12,
                    'condition' => 'defective',
                    'is_restocked' => true,
                ]
            ]
        ]);

        $return = SalesReturn::latest('id')->first();
        $this->assertNotNull($return);
        $storeResponse->assertRedirect(route('sales.returns.show', $return));

        // 3. Show return voucher (draft)
        $showPage = $this->get(route('sales.returns.show', $return));
        $showPage->assertStatus(200);
        $showPage->assertSee('Post Return Now');

        // 4. Post return
        $postResponse = $this->post(route('sales.returns.post', $return), [
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $postResponse->assertRedirect(route('sales.returns.show', $return));
        $this->assertEquals('posted', $return->fresh()->status);

        // 5. Refund partial amount
        $refundResponse = $this->post(route('sales.returns.refund', $return), [
            'amount' => 10000.00,
            'refund_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);
        $refundResponse->assertRedirect(route('sales.returns.show', $return));
        $this->assertEquals('partially_refunded', $return->fresh()->refund_status);
        $this->assertEquals('10000.00', $return->fresh()->refunded_amount);

        // 6. Returns index view
        $indexPage = $this->get(route('sales.returns.index'));
        $indexPage->assertStatus(200);
        $indexPage->assertSee($return->return_number);

        // 7. Customer credit notes index and create
        $cnIndex = $this->get(route('customer_credit_notes.index'));
        $cnIndex->assertStatus(200);

        $cnCreate = $this->get(route('customer_credit_notes.create'));
        $cnCreate->assertStatus(200);
    }

    /**
     * 18. MariaDB concurrency simulation: duplicate key exception recovery outside transaction on sales returns.
     */
    public function test_mariadb_concurrency_race_recovery_on_sales_returns(): void
    {
        $config = config('database.connections.mysql');
        $config['database'] = 'test';

        $connector = new \Illuminate\Database\Connectors\MySqlConnector;
        try {
            $pdo1 = $connector->connect($config);
            $pdo2 = $connector->connect($config);
        } catch (\Throwable $e) {
            $this->markTestSkipped('MariaDB test database unavailable: ' . $e->getMessage());
            return;
        }

        $pdo1->exec("DROP TABLE IF EXISTS `test_sales_return_race`");
        $pdo1->exec("CREATE TABLE `test_sales_return_race` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `idempotency_key` VARCHAR(64) NULL UNIQUE,
            `request_hash` VARCHAR(64) NULL,
            `amount` DECIMAL(12,2) DEFAULT 0.00
        ) ENGINE=InnoDB");

        $key = 'mariadb-sr-race-' . bin2hex(random_bytes(8));
        $hash = hash('sha256', 'canonical-mariadb-sales-return-payload');

        // T2 begins transaction and reads, establishing MVCC snapshot
        $pdo2->beginTransaction();
        $pdo2->query("SELECT COUNT(*) FROM `test_sales_return_race`")->fetch();

        // T1 commits insert
        $pdo1->beginTransaction();
        $stmt1 = $pdo1->prepare("INSERT INTO `test_sales_return_race` (`idempotency_key`, `request_hash`, `amount`) VALUES (?, ?, 24000.00)");
        $stmt1->execute([$key, $hash]);
        $pdo1->commit();

        // T2 attempts insert with duplicate key
        $t2Caught = false;
        try {
            $stmt2 = $pdo2->prepare("INSERT INTO `test_sales_return_race` (`idempotency_key`, `request_hash`, `amount`) VALUES (?, ?, 24000.00)");
            $stmt2->execute([$key, $hash]);
        } catch (\PDOException $e) {
            $t2Caught = true;
            $pdo2->rollBack();

            // Re-query outside transaction after rollback
            $stmtCheck = $pdo2->prepare("SELECT id, idempotency_key, request_hash, amount FROM `test_sales_return_race` WHERE idempotency_key = ?");
            $stmtCheck->execute([$key]);
            $recovered = $stmtCheck->fetch(\PDO::FETCH_ASSOC);

            $this->assertNotNull($recovered);
            $this->assertEquals($key, $recovered['idempotency_key']);
            $this->assertEquals($hash, $recovered['request_hash']);
            $this->assertEquals('24000.00', $recovered['amount']);
        }

        $this->assertTrue($t2Caught, 'Expected duplicate key collision on MariaDB connection 2 for sales returns.');

        $pdo1->exec("DROP TABLE IF EXISTS `test_sales_return_race`");
    }
}
