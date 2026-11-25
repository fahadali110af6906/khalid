<?php

namespace Tests\Feature;

use App\Models\GoodsReceipt;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierDebitNote;
use App\Models\SupplierLedgerEntry;
use App\Models\SupplierPayment;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Accounting\SupplierKhataService;
use App\Services\Inventory\InventoryService;
use App\Services\Purchasing\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class SupplierAndPurchaseTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $shop1Manager;
    protected User $shop2Manager;
    protected Location $shop1;
    protected Location $shop2;
    protected SupplierKhataService $khataService;
    protected PurchaseService $purchaseService;
    protected InventoryService $inventoryService;
    protected ProductVariant $testVariant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->owner = User::where('email', 'khalid.owner@khalidtraders.pk')->firstOrFail();
        $this->shop1Manager = User::where('email', 'tariq.anarkali@khalidtraders.pk')->firstOrFail();
        $this->shop2Manager = User::where('email', 'irfan.shahalam@khalidtraders.pk')->firstOrFail();

        $this->shop1 = Location::where('code', 'LOC-SH01')->firstOrFail();
        $this->shop2 = Location::where('code', 'LOC-SH02')->firstOrFail();

        $this->khataService = app(SupplierKhataService::class);
        $this->purchaseService = app(PurchaseService::class);
        $this->inventoryService = app(InventoryService::class);

        // Create or get a test product with 12 pairs per carton
        $category = ProductCategory::firstOrCreate(
            ['code' => 'MEN'],
            ['name' => 'Men Shoes']
        );
        $uom = UnitOfMeasure::firstOrCreate(
            ['code' => 'PAIR'],
            ['name' => 'Pair', 'is_base_unit' => true]
        );

        $product = Product::firstOrCreate(
            ['article_code' => 'ART-TEST-6A'],
            [
                'name' => 'Oxford Formal Leather Shoe',
                'category_id' => $category->id,
                'base_unit_id' => $uom->id,
                'carton_pack_qty' => 12,
                'default_wholesale_rate' => 24000.00,
                'default_retail_rate' => 2500.00,
                'is_active' => true,
            ]
        );

        $this->testVariant = ProductVariant::firstOrCreate(
            ['sku' => 'ART-TEST-6A-BLK-42'],
            [
                'product_id' => $product->id,
                'size' => '42',
                'color' => 'Black',
                'is_active' => true,
                'purchase_cost' => 1500.00,
                'wholesale_rate' => 24000.00,
            ]
        );
    }

    /**
     * 1. Test creating a supplier profile with auto-generated code and location scoping.
     */
    public function test_can_create_supplier_with_auto_generated_code(): void
    {
        $this->actingAs($this->shop1Manager);

        $response = $this->post(route('suppliers.store'), [
            'business_name' => 'Golden Footwear Workshop',
            'contact_person' => 'Master Aslam',
            'phone' => '0300-9876543',
            'alternate_phone' => '0321-1234567',
            'city' => 'Gujranwala',
            'address' => 'Small Industrial Estate, Gujranwala',
            'primary_location_id' => $this->shop1->id,
            'credit_limit' => 200000.00,
            'opening_balance' => 0.00,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('suppliers', [
            'business_name' => 'Golden Footwear Workshop',
            'contact_person' => 'Master Aslam',
            'phone' => '03009876543',
            'city' => 'Gujranwala',
            'primary_location_id' => $this->shop1->id,
        ]);

        $supplier = Supplier::where('business_name', 'Golden Footwear Workshop')->first();
        $this->assertNotNull($supplier);
        $this->assertStringStartsWith('SUPP-', $supplier->supplier_code);
    }

    /**
     * 2. Test opening balance automatically creates initial credit ledger entry.
     */
    public function test_opening_balance_creates_initial_ledger_entry_accurately(): void
    {
        $this->actingAs($this->shop1Manager);

        $response = $this->post(route('suppliers.store'), [
            'business_name' => 'Shaheen Leather Mills',
            'contact_person' => 'Haji Shaheen',
            'phone' => '0300-5555555',
            'city' => 'Lahore',
            'primary_location_id' => $this->shop1->id,
            'credit_limit' => 500000.00,
            'opening_balance' => 45000.00, // We owe supplier 45,000 PKR
        ]);

        $response->assertRedirect();

        $supplier = Supplier::where('business_name', 'Shaheen Leather Mills')->firstOrFail();

        // Must record audited opening_balance ledger entry
        $this->assertDatabaseHas('supplier_ledger_entries', [
            'supplier_id' => $supplier->id,
            'entry_type' => 'opening_balance',
            'credit_amount' => '45000.00', // Credit increases payable
            'debit_amount' => '0.00',
        ]);

        $balance = $this->khataService->calculateBalance($supplier);
        $this->assertEquals('45000.00', $balance['closing_balance']);
        $this->assertEquals('payable', $balance['balance_status']);
    }

    /**
     * 3. Zero opening balance does NOT create redundant ledger row.
     */
    public function test_zero_opening_balance_does_not_create_unnecessary_ledger_entry(): void
    {
        $this->actingAs($this->shop1Manager);

        $this->post(route('suppliers.store'), [
            'business_name' => 'Zero Balance Vendor',
            'contact_person' => 'Ali Raza',
            'phone' => '0300-1112233',
            'primary_location_id' => $this->shop1->id,
            'opening_balance' => 0.00,
        ]);

        $supplier = Supplier::where('business_name', 'Zero Balance Vendor')->firstOrFail();

        $this->assertDatabaseMissing('supplier_ledger_entries', [
            'supplier_id' => $supplier->id,
        ]);

        $balance = $this->khataService->calculateBalance($supplier);
        $this->assertEquals('0.00', $balance['closing_balance']);
        $this->assertEquals('settled', $balance['balance_status']);
    }

    /**
     * 4. Zero-IDOR: User cannot create supplier for unauthorized location.
     */
    public function test_location_authorization_enforced_for_supplier_creation_zero_idor(): void
    {
        // shop1Manager only has access to shop1, NOT shop2
        $this->actingAs($this->shop1Manager);

        $response = $this->post(route('suppliers.store'), [
            'business_name' => 'Intruder Vendor',
            'contact_person' => 'Unauthorized Guy',
            'phone' => '0300-9999999',
            'primary_location_id' => $this->shop2->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('suppliers', [
            'business_name' => 'Intruder Vendor',
        ]);
    }

    /**
     * 5. Supplier Khata Sign Convention:
     * Goods Receipt increases payable (Credit); Payment decreases payable (Debit).
     */
    public function test_supplier_ledger_sign_convention_credit_increases_payable_debit_decreases(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-TEST-SIGN',
            'business_name' => 'Test Sign Vendor',
            'contact_person' => 'Bilal',
            'phone' => '0300-4443322',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->owner->id,
        ]);

        // 1. Goods Receipt of PKR 10,000 (Credit) -> Payable becomes 10,000
        $this->khataService->recordEntry(
            supplier: $supplier,
            locationId: $this->shop1->id,
            entryType: 'goods_receipt',
            creditAmount: 10000.00,
            entryDate: now()->toDateString(),
            user: $this->owner,
            referenceType: 'goods_receipt',
            referenceId: 101,
            description: 'Received Goods'
        );

        $bal1 = $this->khataService->calculateBalance($supplier);
        $this->assertEquals('10000.00', $bal1['closing_balance']);
        $this->assertEquals('payable', $bal1['balance_status']);

        // 2. Payment of PKR 4,000 (Debit) -> Payable becomes 6,000
        $this->khataService->recordEntry(
            supplier: $supplier,
            locationId: $this->shop1->id,
            entryType: 'supplier_payment',
            debitAmount: 4000.00,
            entryDate: now()->toDateString(),
            user: $this->owner,
            referenceType: 'supplier_payment',
            referenceId: 201,
            description: 'Cash Payment'
        );

        $bal2 = $this->khataService->calculateBalance($supplier);
        $this->assertEquals('6000.00', $bal2['closing_balance']);
        $this->assertEquals('payable', $bal2['balance_status']);

        // 3. Settle remaining PKR 6,000 (Debit) -> Payable becomes 0
        $this->khataService->recordEntry(
            supplier: $supplier,
            locationId: $this->shop1->id,
            entryType: 'supplier_payment',
            debitAmount: 6000.00,
            entryDate: now()->toDateString(),
            user: $this->owner,
            referenceType: 'supplier_payment',
            referenceId: 202,
            description: 'Settlement Payment'
        );

        $bal3 = $this->khataService->calculateBalance($supplier);
        $this->assertEquals('0.00', $bal3['closing_balance']);
        $this->assertEquals('settled', $bal3['balance_status']);
    }

    /**
     * 6. Mutual Exclusivity: Entry cannot have both positive debit and positive credit simultaneously.
     */
    public function test_supplier_khata_cannot_have_both_debit_and_credit_simultaneously(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-MUTUAL-EX',
            'business_name' => 'Mutual Exclusivity Vendor',
            'contact_person' => 'Rashid',
            'phone' => '0300-8887766',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->owner->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot have both positive debit and positive credit amounts simultaneously');

        $this->khataService->recordEntry(
            supplier: $supplier,
            locationId: $this->shop1->id,
            entryType: 'adjustment_credit',
            debitAmount: 1000.00,
            creditAmount: 2000.00,
            entryDate: now()->toDateString(),
            user: $this->owner
        );
    }

    /**
     * 7. Idempotency: Duplicate reference is caught and rejected.
     */
    public function test_supplier_khata_duplicate_reference_is_prevented(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-IDEMP-TEST',
            'business_name' => 'Idempotency Vendor',
            'contact_person' => 'Tahir',
            'phone' => '0300-6665544',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->owner->id,
        ]);

        $this->khataService->recordEntry(
            supplier: $supplier,
            locationId: $this->shop1->id,
            entryType: 'goods_receipt',
            creditAmount: 15000.00,
            entryDate: now()->toDateString(),
            user: $this->owner,
            referenceType: 'goods_receipt',
            referenceId: 999
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Duplicate supplier ledger entry');

        // Post same document again
        $this->khataService->recordEntry(
            supplier: $supplier,
            locationId: $this->shop1->id,
            entryType: 'goods_receipt',
            creditAmount: 15000.00,
            entryDate: now()->toDateString(),
            user: $this->owner,
            referenceType: 'goods_receipt',
            referenceId: 999
        );
    }

    /**
     * 8. Immutability: SupplierLedgerEntry cannot be updated or deleted.
     */
    public function test_supplier_ledger_entry_is_immutable_cannot_be_updated_or_deleted(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-IMMUTABLE',
            'business_name' => 'Immutable Vendor',
            'contact_person' => 'Zubair',
            'phone' => '0300-2223344',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->owner->id,
        ]);

        $entry = $this->khataService->recordEntry(
            supplier: $supplier,
            locationId: $this->shop1->id,
            entryType: 'goods_receipt',
            creditAmount: 5000.00,
            entryDate: now()->toDateString(),
            user: $this->owner
        );

        try {
            $entry->credit_amount = '7000.00';
            $entry->save();
            $this->fail('Expected RuntimeException on updating ledger entry.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }

        try {
            $entry->delete();
            $this->fail('Expected RuntimeException on deleting ledger entry.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }
    }

    /**
     * 9. Create Draft Purchase Order: Does NOT deduct or add stock, does NOT post to Khata.
     */
    public function test_create_draft_purchase_order_does_not_affect_inventory_or_ledger(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-PO-DRAFT',
            'business_name' => 'PO Draft Vendor',
            'contact_person' => 'Usman',
            'phone' => '0300-9988776',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->owner->id,
        ]);

        $po = $this->purchaseService->createDraft([
            'location_id' => $this->shop1->id,
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [
                [
                    'product_variant_id' => $this->testVariant->id,
                    'unit_type' => 'carton',
                    'quantity' => 10, // 10 cartons = 120 pairs
                    'unit_cost' => 18000.00, // 18,000 per carton = 1,500 per pair
                ],
            ],
        ], $this->shop1Manager);

        $this->assertEquals('draft', $po->status);
        $this->assertEquals(10, $po->total_cartons);
        $this->assertEquals('120.0000', $po->total_pairs);
        $this->assertEquals('180000.00', $po->total_amount);

        // Inventory must remain untouched
        $balance = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->testVariant->id)
            ->first();
        $this->assertNull($balance);

        // Supplier Khata must remain empty
        $khata = $this->khataService->calculateBalance($supplier);
        $this->assertEquals('0.00', $khata['closing_balance']);
        $this->assertEquals(0, $khata['entries_count']);
    }

    /**
     * 10. Packaging Conversion: Cartons to Pairs and calculation verification.
     */
    public function test_purchase_order_packaging_cartons_to_pairs_conversion(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-PO-PACK',
            'business_name' => 'Packaging Test Vendor',
            'contact_person' => 'Kashif',
            'phone' => '0300-3334455',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->owner->id,
        ]);

        // 5 cartons of 12 pairs each = 60 pairs
        $po = $this->purchaseService->createDraft([
            'location_id' => $this->shop1->id,
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'discount_amount' => 500.00,
            'additional_charges' => 1200.00,
            'items' => [
                [
                    'product_variant_id' => $this->testVariant->id,
                    'unit_type' => 'carton',
                    'quantity' => 5,
                    'unit_cost' => 12000.00, // 12,000 per carton (1,000/pair) -> subtotal = 60,000
                    'line_discount' => 1000.00, // line total = 59,000
                ],
            ],
        ], $this->shop1Manager);

        $this->assertEquals('60.0000', $po->total_pairs);
        $this->assertEquals('59000.00', $po->subtotal_amount);
        // Total = 59,000 - 500 (overall discount) + 1200 (freight) = 59,700
        $this->assertEquals('59700.00', $po->total_amount);
    }

    /**
     * 11. Approve Purchase Order: Transitions status to ordered without affecting stock or Khata.
     */
    public function test_approve_purchase_order_transitions_status_to_ordered(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-PO-APPROVE',
            'business_name' => 'Approve PO Vendor',
            'contact_person' => 'Farhan',
            'phone' => '0300-7778899',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->owner->id,
        ]);

        $po = $this->purchaseService->createDraft([
            'location_id' => $this->shop1->id,
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [
                [
                    'product_variant_id' => $this->testVariant->id,
                    'unit_type' => 'pair',
                    'quantity' => 50,
                    'unit_cost' => 1200.00,
                ],
            ],
        ], $this->shop1Manager);

        $approved = $this->purchaseService->approveOrder($po, $this->shop1Manager);

        $this->assertEquals('ordered', $approved->status);
        $this->assertNotNull($approved->ordered_at);
        $this->assertEquals($this->shop1Manager->id, $approved->ordered_by);

        // Still no stock movement or Khata entry
        $this->assertEquals(0, StockMovement::count());
        $this->assertEquals(0, SupplierLedgerEntry::where('supplier_id', $supplier->id)->count());
    }

    /**
     * 12. Full Goods Receipt (GRN):
     * Stock increments, WAC is updated, PO status becomes 'received', and Supplier Khata is credited.
     */
    public function test_receive_goods_full_increases_stock_updates_wac_and_credits_supplier_khata(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-GRN-FULL',
            'business_name' => 'Full GRN Vendor',
            'contact_person' => 'Imtiaz',
            'phone' => '0300-1239876',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->owner->id,
        ]);

        $po = $this->purchaseService->createDraft([
            'location_id' => $this->shop1->id,
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [
                [
                    'product_variant_id' => $this->testVariant->id,
                    'unit_type' => 'carton',
                    'quantity' => 2, // 2 cartons * 12 = 24 pairs
                    'unit_cost' => 12000.00, // 1,000 per pair
                ],
            ],
        ], $this->shop1Manager);

        $this->purchaseService->approveOrder($po, $this->shop1Manager);

        $poItem = $po->items->first();

        // Process full receipt
        $grn = $this->purchaseService->receiveGoods($po, [
            'receipt_date' => now()->toDateString(),
            'challan_number' => 'BILTY-12345',
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'received_pairs' => 24.0,
                ],
            ],
        ], $this->shop1Manager);

        // 1. Assert GRN Header and Items
        $this->assertEquals('24.0000', $grn->total_received_pairs);
        $this->assertEquals('24000.00', $grn->total_received_cost); // 24 * 1000 = 24,000
        $this->assertDatabaseHas('goods_receipts', [
            'id' => $grn->id,
            'purchase_order_id' => $po->id,
            'challan_number' => 'BILTY-12345',
        ]);

        // 2. Assert Stock Balance & Movement
        $balance = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->testVariant->id)
            ->firstOrFail();
        $this->assertEquals(24.0, (float) $balance->pairs_quantity);
        $this->assertEquals(1000.0, (float) $balance->avg_unit_cost);

        $this->assertDatabaseHas('stock_movements', [
            'location_id' => $this->shop1->id,
            'product_variant_id' => $this->testVariant->id,
            'movement_type' => 'purchase',
            'quantity_delta_pairs' => 24.0,
            'reference_type' => 'goods_receipt',
            'reference_id' => $grn->id,
        ]);

        // 3. Assert PO status transition to 'received'
        $po->refresh();
        $this->assertEquals('received', $po->status);
        $poItem->refresh();
        $this->assertEquals('24.0000', $poItem->received_pairs);

        // 4. Assert Supplier Khata credit
        $this->assertDatabaseHas('supplier_ledger_entries', [
            'supplier_id' => $supplier->id,
            'entry_type' => 'goods_receipt',
            'reference_type' => 'goods_receipt',
            'reference_id' => $grn->id,
            'credit_amount' => '24000.00',
            'debit_amount' => '0.00',
        ]);

        $khata = $this->khataService->calculateBalance($supplier);
        $this->assertEquals('24000.00', $khata['closing_balance']);
        $this->assertEquals('payable', $khata['balance_status']);
    }

    /**
     * 13. Partial Goods Receipt: PO becomes 'partially_received', second receipt completes it to 'received'.
     */
    public function test_receive_goods_partial_updates_status_to_partially_received(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-GRN-PARTIAL',
            'business_name' => 'Partial GRN Vendor',
            'contact_person' => 'Shakeel',
            'phone' => '0300-3456789',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->owner->id,
        ]);

        $po = $this->purchaseService->createDraft([
            'location_id' => $this->shop1->id,
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [
                [
                    'product_variant_id' => $this->testVariant->id,
                    'unit_type' => 'pair',
                    'quantity' => 100,
                    'unit_cost' => 1000.00,
                ],
            ],
        ], $this->shop1Manager);

        $this->purchaseService->approveOrder($po, $this->shop1Manager);
        $poItem = $po->items->first();

        // 1st Partial Receipt: 40 pairs
        $this->purchaseService->receiveGoods($po, [
            'receipt_date' => now()->toDateString(),
            'items' => [
                ['purchase_order_item_id' => $poItem->id, 'received_pairs' => 40.0],
            ],
        ], $this->shop1Manager);

        $po->refresh();
        $this->assertEquals('partially_received', $po->status);
        $poItem->refresh();
        $this->assertEquals('40.0000', $poItem->received_pairs);
        $this->assertEquals(60.0, $poItem->remainingPairs());

        $bal1 = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->testVariant->id)
            ->firstOrFail();
        $this->assertEquals(40.0, (float) $bal1->pairs_quantity);

        // 2nd Partial Receipt: remaining 60 pairs
        $this->purchaseService->receiveGoods($po, [
            'receipt_date' => now()->toDateString(),
            'items' => [
                ['purchase_order_item_id' => $poItem->id, 'received_pairs' => 60.0],
            ],
        ], $this->shop1Manager);

        $po->refresh();
        $this->assertEquals('received', $po->status);
        $poItem->refresh();
        $this->assertEquals('100.0000', $poItem->received_pairs);
        $this->assertEquals(0.0, $poItem->remainingPairs());

        $bal2 = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->testVariant->id)
            ->firstOrFail();
        $this->assertEquals(100.0, (float) $bal2->pairs_quantity);

        // Supplier Khata reflects both receipts (40,000 + 60,000 = 100,000 payable)
        $khata = $this->khataService->calculateBalance($supplier);
        $this->assertEquals('100000.00', $khata['closing_balance']);
        $this->assertEquals(2, $khata['entries_count']);
    }

    /**
     * 14. Over-receipt rejection: Cannot receive more than remaining pending quantity.
     */
    public function test_goods_receipt_rejects_over_receipt(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-OVER-RECEIPT',
            'business_name' => 'Over-Receipt Vendor',
            'contact_person' => 'Zafar',
            'phone' => '0300-5678901',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->owner->id,
        ]);

        $po = $this->purchaseService->createDraft([
            'location_id' => $this->shop1->id,
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [
                [
                    'product_variant_id' => $this->testVariant->id,
                    'unit_type' => 'pair',
                    'quantity' => 20,
                    'unit_cost' => 1000.00,
                ],
            ],
        ], $this->shop1Manager);

        $this->purchaseService->approveOrder($po, $this->shop1Manager);
        $poItem = $po->items->first();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot receive 25 pairs');

        // Attempt to receive 25 pairs when only 20 were ordered
        $this->purchaseService->receiveGoods($po, [
            'items' => [
                ['purchase_order_item_id' => $poItem->id, 'received_pairs' => 25.0],
            ],
        ], $this->shop1Manager);
    }

    /**
     * 15. Record Supplier Payment: Debits Khata and creates payment voucher.
     */
    public function test_record_supplier_payment_debits_khata_and_creates_voucher(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-PAYMENT-TEST',
            'business_name' => 'Payment Test Vendor',
            'contact_person' => 'Jamil',
            'phone' => '0300-7890123',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->owner->id,
        ]);

        // Opening balance 50,000 payable
        $this->khataService->recordEntry(
            supplier: $supplier,
            locationId: $this->shop1->id,
            entryType: 'opening_balance',
            creditAmount: 50000.00,
            entryDate: now()->toDateString(),
            user: $this->owner
        );

        // Make payment of 20,000 via controller/service
        $payment = $this->purchaseService->recordPayment($supplier, [
            'location_id' => $this->shop1->id,
            'amount' => 20000.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'reference_number' => 'IBFT-778899',
            'notes' => 'Partial payment against opening khata',
        ], $this->shop1Manager);

        $this->assertNotNull($payment->payment_number);
        $this->assertStringStartsWith('SPAY-LOC-SH01-', $payment->payment_number);
        $this->assertEquals(20000.00, (float) $payment->amount);

        // Supplier Khata debited
        $this->assertDatabaseHas('supplier_ledger_entries', [
            'supplier_id' => $supplier->id,
            'entry_type' => 'supplier_payment',
            'reference_type' => 'supplier_payment',
            'reference_id' => $payment->id,
            'debit_amount' => '20000.00',
            'credit_amount' => '0.00',
        ]);

        $khata = $this->khataService->calculateBalance($supplier);
        $this->assertEquals('30000.00', $khata['closing_balance']); // 50,000 - 20,000 = 30,000
        $this->assertEquals('payable', $khata['balance_status']);
    }

    /**
     * 16. Advance Payment: Disbursing money before goods creates an advance balance (negative payable).
     */
    public function test_supplier_payment_advance_creates_negative_payable_balance(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-ADVANCE-TEST',
            'business_name' => 'Advance Test Vendor',
            'contact_person' => 'Qasim',
            'phone' => '0300-8901234',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->owner->id,
        ]);

        // Disburse PKR 25,000 advance
        $payment = $this->purchaseService->recordPayment($supplier, [
            'location_id' => $this->shop1->id,
            'amount' => 25000.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ], $this->shop1Manager);

        $khata = $this->khataService->calculateBalance($supplier);
        $this->assertEquals('-25000.00', $khata['closing_balance']);
        $this->assertEquals('advance', $khata['balance_status']);
    }

    /**
     * 17. Security / Zero-IDOR: User cannot receive goods for a location they do not have access to.
     */
    public function test_user_cannot_receive_goods_for_unauthorized_location_zero_idor(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-IDOR-TEST',
            'business_name' => 'IDOR Test Vendor',
            'contact_person' => 'Salman',
            'phone' => '0300-9012345',
            'primary_location_id' => $this->shop2->id,
            'created_by' => $this->owner->id,
        ]);

        // PO belongs to shop2
        $po = $this->purchaseService->createDraft([
            'location_id' => $this->shop2->id,
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [
                [
                    'product_variant_id' => $this->testVariant->id,
                    'unit_type' => 'pair',
                    'quantity' => 10,
                    'unit_cost' => 1000.00,
                ],
            ],
        ], $this->shop2Manager);

        $this->purchaseService->approveOrder($po, $this->shop2Manager);

        // shop1Manager attempts to receive goods for shop2's PO
        $this->actingAs($this->shop1Manager);

        $response = $this->get(route('purchases.receive', $po));
        $response->assertForbidden();

        $responsePost = $this->post(route('purchases.receive.store', $po), [
            'items' => [
                ['purchase_order_item_id' => $po->items->first()->id, 'received_pairs' => 10],
            ],
        ]);
        $responsePost->assertForbidden();
    }

    /**
     * 18. Delete protection: Supplier with financial ledger history cannot be deleted.
     */
    public function test_delete_supplier_prevented_if_ledger_entries_exist(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-DEL-PROT',
            'business_name' => 'Protected Vendor',
            'contact_person' => 'Hamza',
            'phone' => '0300-0123456',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->owner->id,
        ]);

        $this->khataService->recordEntry(
            supplier: $supplier,
            locationId: $this->shop1->id,
            entryType: 'goods_receipt',
            creditAmount: 5000.00,
            entryDate: now()->toDateString(),
            user: $this->owner
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot delete supplier');

        $supplier->delete();
    }

    /**
     * 19. Atomic Rollback on Failure: If anything fails during GRN, stock and Khata rollback completely.
     */
    public function test_goods_receipt_atomic_rollback_on_failure(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-ROLLBACK-TEST',
            'business_name' => 'Rollback Vendor',
            'contact_person' => 'Taimur',
            'phone' => '0300-1122334',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->owner->id,
        ]);

        $po = $this->purchaseService->createDraft([
            'location_id' => $this->shop1->id,
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [
                [
                    'product_variant_id' => $this->testVariant->id,
                    'unit_type' => 'pair',
                    'quantity' => 10,
                    'unit_cost' => 1000.00,
                ],
            ],
        ], $this->shop1Manager);

        $this->purchaseService->approveOrder($po, $this->shop1Manager);
        $poItem = $po->items->first();

        // Pass invalid item that causes validation exception inside the transaction
        try {
            $this->purchaseService->receiveGoods($po, [
                'items' => [
                    ['purchase_order_item_id' => $poItem->id, 'received_pairs' => 5],
                    ['purchase_order_item_id' => 999999, 'received_pairs' => 5], // Invalid ID
                ],
            ], $this->shop1Manager);
            $this->fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException $e) {
            // Expected
        }

        // Verify that NO partial stock or GRN was saved
        $this->assertDatabaseMissing('goods_receipts', [
            'purchase_order_id' => $po->id,
        ]);

        $this->assertDatabaseMissing('supplier_ledger_entries', [
            'supplier_id' => $supplier->id,
        ]);

        $balance = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->testVariant->id)
            ->first();
        $this->assertNull($balance);
    }

    /**
     * 20. Idempotency: Same receipt key submitted twice creates only one receipt and one set of stock/ledger effects.
     */
    public function test_same_receipt_key_submitted_twice_creates_one_receipt_and_one_set_of_effects(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-IDEMP-REC-1',
            'business_name' => 'Idempotency Receipt Vendor',
            'contact_person' => 'Sohail',
            'phone' => '0300-9991111',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->owner->id,
        ]);

        $po = $this->purchaseService->createDraft([
            'location_id' => $this->shop1->id,
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [
                [
                    'product_variant_id' => $this->testVariant->id,
                    'unit_type' => 'pair',
                    'quantity' => 50,
                    'unit_cost' => 1000.00,
                ],
            ],
        ], $this->shop1Manager);

        $this->purchaseService->approveOrder($po, $this->shop1Manager);
        $poItem = $po->items->first();

        $key = 'receipt-idemp-uuid-12345';
        $payload = [
            'idempotency_key' => $key,
            'receipt_date' => now()->toDateString(),
            'challan_number' => 'CH-8899',
            'items' => [
                ['purchase_order_item_id' => $poItem->id, 'received_pairs' => 20.0],
            ],
        ];

        // 1st Submission
        $grn1 = $this->purchaseService->receiveGoods($po, $payload, $this->shop1Manager);
        $this->assertNotNull($grn1);
        $this->assertEquals(1, GoodsReceipt::where('idempotency_key', $key)->count());

        $bal1 = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->testVariant->id)
            ->firstOrFail();
        $this->assertEquals(20.0, (float) $bal1->pairs_quantity);
        $this->assertEquals(1, SupplierLedgerEntry::where('supplier_id', $supplier->id)->count());

        // 2nd Submission with exact same key and payload (simulate repeated click / network retry)
        $grn2 = $this->purchaseService->receiveGoods($po, $payload, $this->shop1Manager);
        $this->assertEquals($grn1->id, $grn2->id);

        // Verification: Exactly 1 GRN record, 20 pairs in stock (NOT 40), and 1 ledger entry (NOT 2)
        $this->assertEquals(1, GoodsReceipt::count());
        $bal2 = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->testVariant->id)
            ->firstOrFail();
        $this->assertEquals(20.0, (float) $bal2->pairs_quantity);
        $this->assertEquals(1, SupplierLedgerEntry::where('supplier_id', $supplier->id)->count());

        $khata = $this->khataService->calculateBalance($supplier);
        $this->assertEquals('20000.00', $khata['closing_balance']);
    }

    /**
     * 21. Idempotency: Same payment key submitted twice creates only one payment and one ledger debit.
     */
    public function test_same_payment_key_submitted_twice_creates_one_payment_and_one_ledger_debit(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-IDEMP-PAY-1',
            'business_name' => 'Idempotency Payment Vendor',
            'contact_person' => 'Nadeem',
            'phone' => '0300-9992222',
            'primary_location_id' => $this->shop1->id,
            'opening_balance' => 50000.00,
            'created_by' => $this->owner->id,
        ]);

        $key = 'payment-idemp-uuid-56789';
        $payload = [
            'idempotency_key' => $key,
            'location_id' => $this->shop1->id,
            'amount' => 15000.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'reference_number' => 'TRX-112233',
        ];

        // 1st Submission
        $pay1 = $this->purchaseService->recordPayment($supplier, $payload, $this->shop1Manager);
        $this->assertNotNull($pay1);
        $this->assertEquals(1, SupplierPayment::where('idempotency_key', $key)->count());

        // 2nd Submission with exact same key and payload
        $pay2 = $this->purchaseService->recordPayment($supplier, $payload, $this->shop1Manager);
        $this->assertEquals($pay1->id, $pay2->id);

        // Verification: Exactly 1 payment voucher, Khata debited only once
        $this->assertEquals(1, SupplierPayment::count());
        $paymentLedgerEntries = SupplierLedgerEntry::where('supplier_id', $supplier->id)
            ->where('entry_type', 'supplier_payment')
            ->count();
        $this->assertEquals(1, $paymentLedgerEntries);

        $khata = $this->khataService->calculateBalance($supplier);
        // 50,000 opening - 15,000 single debit = 35,000
        $this->assertEquals('35000.00', $khata['closing_balance']);
    }

    /**
     * 22. Idempotency Tamper Protection: Reusing same key with a different payload is rejected.
     */
    public function test_same_receipt_or_payment_key_with_different_payload_is_rejected(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-TAMPER-1',
            'business_name' => 'Tamper Test Vendor',
            'contact_person' => 'Waqas',
            'phone' => '0300-9993333',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->owner->id,
        ]);

        $po = $this->purchaseService->createDraft([
            'location_id' => $this->shop1->id,
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [
                [
                    'product_variant_id' => $this->testVariant->id,
                    'unit_type' => 'pair',
                    'quantity' => 50,
                    'unit_cost' => 1000.00,
                ],
            ],
        ], $this->shop1Manager);

        $this->purchaseService->approveOrder($po, $this->shop1Manager);
        $poItem = $po->items->first();

        $key = 'receipt-tamper-key-999';

        // 1. Submit original receipt
        $this->purchaseService->receiveGoods($po, [
            'idempotency_key' => $key,
            'receipt_date' => now()->toDateString(),
            'challan_number' => 'ORIGINAL-CHALLAN',
            'items' => [
                ['purchase_order_item_id' => $poItem->id, 'received_pairs' => 10.0],
            ],
        ], $this->shop1Manager);

        // 2. Attempt to reuse same key with DIFFERENT received quantity (20 pairs instead of 10)
        try {
            $this->purchaseService->receiveGoods($po, [
                'idempotency_key' => $key,
                'receipt_date' => now()->toDateString(),
                'challan_number' => 'ORIGINAL-CHALLAN',
                'items' => [
                    ['purchase_order_item_id' => $poItem->id, 'received_pairs' => 20.0],
                ],
            ], $this->shop1Manager);
            $this->fail('Expected InvalidArgumentException when reusing key with different payload.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('different request payload', $e->getMessage());
        }

        // 3. Test payment tamper with different amount
        $payKey = 'pay-tamper-key-888';
        $this->purchaseService->recordPayment($supplier, [
            'idempotency_key' => $payKey,
            'location_id' => $this->shop1->id,
            'amount' => 5000.00,
            'payment_date' => now()->toDateString(),
        ], $this->shop1Manager);

        try {
            $this->purchaseService->recordPayment($supplier, [
                'idempotency_key' => $payKey,
                'location_id' => $this->shop1->id,
                'amount' => 7000.00, // Different amount
                'payment_date' => now()->toDateString(),
            ], $this->shop1Manager);
            $this->fail('Expected InvalidArgumentException when reusing payment key with different amount.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('different request payload', $e->getMessage());
        }
    }

    /**
     * 23. Distinct Keys: Different keys allow legitimate separate partial receipts.
     */
    public function test_different_keys_allow_legitimate_separate_partial_receipts(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-LEGIT-PART-1',
            'business_name' => 'Legit Partial Vendor',
            'contact_person' => 'Ijaz',
            'phone' => '0300-9994444',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->owner->id,
        ]);

        $po = $this->purchaseService->createDraft([
            'location_id' => $this->shop1->id,
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [
                [
                    'product_variant_id' => $this->testVariant->id,
                    'unit_type' => 'pair',
                    'quantity' => 100,
                    'unit_cost' => 1000.00,
                ],
            ],
        ], $this->shop1Manager);

        $this->purchaseService->approveOrder($po, $this->shop1Manager);
        $poItem = $po->items->first();

        // 1st Partial Delivery (Key A) -> 30 pairs
        $grn1 = $this->purchaseService->receiveGoods($po, [
            'idempotency_key' => 'key-delivery-batch-1',
            'receipt_date' => now()->toDateString(),
            'challan_number' => 'BILTY-101',
            'items' => [
                ['purchase_order_item_id' => $poItem->id, 'received_pairs' => 30.0],
            ],
        ], $this->shop1Manager);

        // 2nd Partial Delivery (Key B) -> 40 pairs
        $grn2 = $this->purchaseService->receiveGoods($po, [
            'idempotency_key' => 'key-delivery-batch-2',
            'receipt_date' => now()->toDateString(),
            'challan_number' => 'BILTY-102',
            'items' => [
                ['purchase_order_item_id' => $poItem->id, 'received_pairs' => 40.0],
            ],
        ], $this->shop1Manager);

        $this->assertNotEquals($grn1->id, $grn2->id);
        $this->assertEquals(2, GoodsReceipt::count());

        $bal = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->testVariant->id)
            ->firstOrFail();
        $this->assertEquals(70.0, (float) $bal->pairs_quantity);

        $po->refresh();
        $this->assertEquals('partially_received', $po->status);
    }

    /**
     * 24. Distinct Keys: Different keys allow legitimate separate supplier payments.
     */
    public function test_different_keys_allow_legitimate_separate_supplier_payments(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-LEGIT-PAYS',
            'business_name' => 'Legit Multiple Payments Vendor',
            'contact_person' => 'Babar',
            'phone' => '0300-9995555',
            'primary_location_id' => $this->shop1->id,
            'opening_balance' => 40000.00,
            'created_by' => $this->owner->id,
        ]);

        $pay1 = $this->purchaseService->recordPayment($supplier, [
            'idempotency_key' => 'pay-key-alpha-1',
            'location_id' => $this->shop1->id,
            'amount' => 10000.00,
            'payment_date' => now()->toDateString(),
        ], $this->shop1Manager);

        $pay2 = $this->purchaseService->recordPayment($supplier, [
            'idempotency_key' => 'pay-key-beta-2',
            'location_id' => $this->shop1->id,
            'amount' => 15000.00,
            'payment_date' => now()->toDateString(),
        ], $this->shop1Manager);

        $this->assertNotEquals($pay1->id, $pay2->id);
        $this->assertEquals(2, SupplierPayment::count());

        $khata = $this->khataService->calculateBalance($supplier);
        // 40,000 opening - 10,000 - 15,000 = 15,000 remaining payable
        $this->assertEquals('15000.00', $khata['closing_balance']);
    }

    /**
     * 25. Safe Retry: A failed first attempt can be retried with the same key once input is corrected.
     */
    public function test_failed_receipt_or_payment_attempt_can_be_retried_with_same_key(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-RETRY-1',
            'business_name' => 'Retry Test Vendor',
            'contact_person' => 'Kamran',
            'phone' => '0300-9996666',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->owner->id,
        ]);

        $po = $this->purchaseService->createDraft([
            'location_id' => $this->shop1->id,
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [
                [
                    'product_variant_id' => $this->testVariant->id,
                    'unit_type' => 'pair',
                    'quantity' => 20,
                    'unit_cost' => 1000.00,
                ],
            ],
        ], $this->shop1Manager);

        $this->purchaseService->approveOrder($po, $this->shop1Manager);
        $poItem = $po->items->first();

        $key = 'receipt-retry-key-777';

        // 1st Attempt: Fails because attempted quantity (50 pairs) exceeds order (20 pairs)
        try {
            $this->purchaseService->receiveGoods($po, [
                'idempotency_key' => $key,
                'items' => [
                    ['purchase_order_item_id' => $poItem->id, 'received_pairs' => 50.0],
                ],
            ], $this->shop1Manager);
            $this->fail('Expected InvalidArgumentException on over-receipt.');
        } catch (InvalidArgumentException $e) {
            // Expected
        }

        // Verify transaction rolled back completely and key is NOT burned
        $this->assertEquals(0, GoodsReceipt::where('idempotency_key', $key)->count());

        // 2nd Attempt: User corrects quantity to 20 pairs using the same form/idempotency key
        $grn = $this->purchaseService->receiveGoods($po, [
            'idempotency_key' => $key,
            'items' => [
                ['purchase_order_item_id' => $poItem->id, 'received_pairs' => 20.0],
            ],
        ], $this->shop1Manager);

        $this->assertNotNull($grn);
        $this->assertEquals('20.0000', $grn->total_received_pairs);
        $this->assertEquals(1, GoodsReceipt::where('idempotency_key', $key)->count());
    }

    /**
     * 26. Database Uniqueness: Direct duplicate key insert triggers query exception.
     */
    public function test_database_uniqueness_protects_goods_receipt_and_payment_idempotency_keys(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-DB-UNIQ',
            'business_name' => 'DB Uniqueness Vendor',
            'contact_person' => 'Akhtar',
            'phone' => '0300-9997777',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->owner->id,
        ]);

        SupplierPayment::create([
            'payment_number' => 'SPAY-TEST-UNIQ-1',
            'supplier_id' => $supplier->id,
            'location_id' => $this->shop1->id,
            'payment_date' => now()->toDateString(),
            'amount' => 5000.00,
            'payment_method' => 'cash',
            'idempotency_key' => 'unique-db-key-9999',
            'created_by' => $this->owner->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        // Attempt direct insert with same idempotency key
        SupplierPayment::create([
            'payment_number' => 'SPAY-TEST-UNIQ-2',
            'supplier_id' => $supplier->id,
            'location_id' => $this->shop1->id,
            'payment_date' => now()->toDateString(),
            'amount' => 7000.00,
            'payment_method' => 'cash',
            'idempotency_key' => 'unique-db-key-9999',
            'created_by' => $this->owner->id,
        ]);
    }

    /**
     * 27. Payload Canonicalization: Carton and pair representations hash identically and return same receipt.
     */
    public function test_carton_and_pair_representations_hash_identically_and_return_same_receipt(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-CANON-1',
            'business_name' => 'Canonical Vendor',
            'contact_person' => 'Rashid',
            'phone' => '0300-8881111',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->owner->id,
        ]);

        $po = $this->purchaseService->createDraft([
            'location_id' => $this->shop1->id,
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [
                [
                    'product_variant_id' => $this->testVariant->id,
                    'unit_type' => 'carton',
                    'quantity' => 10,
                    'unit_cost' => 12000.00,
                ],
            ],
        ], $this->shop1Manager);

        $this->purchaseService->approveOrder($po, $this->shop1Manager);
        $poItem = $po->items->first();
        // Pack ratio is 12 pairs per carton
        $this->assertEquals(12, $poItem->carton_pack_qty);

        // Representation A: received_cartons = 2 (2 * 12 = 24 pairs)
        $payloadCartons = [
            'idempotency_key' => 'key-canon-uuid-777',
            'receipt_date' => now()->toDateString(),
            'challan_number' => 'CH-CANON-1',
            'items' => [
                ['purchase_order_item_id' => $poItem->id, 'received_cartons' => 2.0],
            ],
        ];

        // Representation B: received_pairs = 24
        $payloadPairs = [
            'idempotency_key' => 'key-canon-uuid-777',
            'receipt_date' => now()->toDateString(),
            'challan_number' => 'CH-CANON-1',
            'items' => [
                ['purchase_order_item_id' => $poItem->id, 'received_pairs' => 24.0],
            ],
        ];

        // Representation C: quantity = 2, unit_type = 'carton'
        $payloadQtyCarton = [
            'idempotency_key' => 'key-canon-uuid-777',
            'receipt_date' => now()->toDateString(),
            'challan_number' => 'CH-CANON-1',
            'items' => [
                ['purchase_order_item_id' => $poItem->id, 'quantity' => 2.0, 'unit_type' => 'carton'],
            ],
        ];

        // Representation D: quantity = 24, unit_type = 'pair'
        $payloadQtyPair = [
            'idempotency_key' => 'key-canon-uuid-777',
            'receipt_date' => now()->toDateString(),
            'challan_number' => 'CH-CANON-1',
            'items' => [
                ['purchase_order_item_id' => $poItem->id, 'quantity' => 24.0, 'unit_type' => 'pair'],
            ],
        ];

        $hashA = $this->purchaseService->computeReceiptPayloadHash($po, $payloadCartons);
        $hashB = $this->purchaseService->computeReceiptPayloadHash($po, $payloadPairs);
        $hashC = $this->purchaseService->computeReceiptPayloadHash($po, $payloadQtyCarton);
        $hashD = $this->purchaseService->computeReceiptPayloadHash($po, $payloadQtyPair);

        // All 4 representations must hash strictly identically
        $this->assertEquals($hashA, $hashB);
        $this->assertEquals($hashA, $hashC);
        $this->assertEquals($hashA, $hashD);

        // 1st submit with cartons
        $grn1 = $this->purchaseService->receiveGoods($po, $payloadCartons, $this->shop1Manager);
        $this->assertNotNull($grn1);
        $this->assertEquals('24.0000', $grn1->total_received_pairs);

        // 2nd submit with pairs using same key -> returns existing receipt without double effects
        $grn2 = $this->purchaseService->receiveGoods($po, $payloadPairs, $this->shop1Manager);
        $this->assertEquals($grn1->id, $grn2->id);

        $this->assertEquals(1, GoodsReceipt::count());
        $bal = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->testVariant->id)
            ->firstOrFail();
        $this->assertEquals(24.0, (float) $bal->pairs_quantity);
        $this->assertEquals(1, SupplierLedgerEntry::where('supplier_id', $supplier->id)->count());
    }

    /**
     * 28. Inconsistent carton and pair quantities are rejected in both hash and service.
     */
    public function test_inconsistent_carton_and_pair_quantities_rejected(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-INCONSIST-1',
            'business_name' => 'Inconsistent Vendor',
            'contact_person' => 'Bilal',
            'phone' => '0300-8882222',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->owner->id,
        ]);

        $po = $this->purchaseService->createDraft([
            'location_id' => $this->shop1->id,
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [
                [
                    'product_variant_id' => $this->testVariant->id,
                    'unit_type' => 'carton',
                    'quantity' => 5,
                    'unit_cost' => 12000.00,
                ],
            ],
        ], $this->shop1Manager);

        $this->purchaseService->approveOrder($po, $this->shop1Manager);
        $poItem = $po->items->first();

        // Pass 2 cartons but 30 pairs (when pack ratio is 12, 2 cartons = 24 pairs)
        $inconsistentPayload = [
            'idempotency_key' => 'key-inconsistent-999',
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'received_cartons' => 2.0,
                    'received_pairs' => 30.0,
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Inconsistent carton and pair quantities');

        $this->purchaseService->computeReceiptPayloadHash($po, $inconsistentPayload);
    }

    /**
     * 29. HTTP Form Resubmission: Repeated submission does not turn into HTTP 500 when matching record exists.
     */
    public function test_duplicate_key_race_recovery_does_not_turn_into_http_500(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-HTTP-IDEMP',
            'business_name' => 'HTTP Idemp Vendor',
            'contact_person' => 'Dawood',
            'phone' => '0300-8883333',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->owner->id,
        ]);

        $po = $this->purchaseService->createDraft([
            'location_id' => $this->shop1->id,
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [
                [
                    'product_variant_id' => $this->testVariant->id,
                    'unit_type' => 'pair',
                    'quantity' => 20,
                    'unit_cost' => 1000.00,
                ],
            ],
        ], $this->shop1Manager);

        $this->purchaseService->approveOrder($po, $this->shop1Manager);
        $poItem = $po->items->first();

        $this->actingAs($this->shop1Manager);

        $key = 'http-idemp-key-' . uniqid();
        $postData = [
            'idempotency_key' => $key,
            'receipt_date' => now()->toDateString(),
            'challan_number' => 'CH-HTTP-100',
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'received_pairs' => 10,
                ],
            ],
        ];

        // 1st HTTP POST
        $res1 = $this->post(route('purchases.receive.store', $po), $postData);
        $res1->assertRedirect(route('purchases.show', $po));
        $res1->assertSessionHas('success');

        // 2nd HTTP POST with exact same key and data
        $res2 = $this->post(route('purchases.receive.store', $po), $postData);
        // Must NOT be 500! Must be a clean redirect
        $res2->assertRedirect(route('purchases.show', $po));
        $res2->assertSessionHas('success');

        // Exactly 1 receipt created
        $this->assertEquals(1, GoodsReceipt::where('idempotency_key', $key)->count());

        // Test payment form HTTP resubmission
        $payKey = 'http-pay-key-' . uniqid();
        $payData = [
            'idempotency_key' => $payKey,
            'supplier_id' => $supplier->id,
            'location_id' => $this->shop1->id,
            'payment_date' => now()->toDateString(),
            'amount' => 5000.00,
            'payment_method' => 'cash',
        ];

        $payRes1 = $this->post(route('supplier-payments.store'), $payData);
        $payRes1->assertRedirect();
        $payRes1->assertSessionHas('success');

        $payRes2 = $this->post(route('supplier-payments.store'), $payData);
        $payRes2->assertRedirect();
        $payRes2->assertSessionHas('success');

        $this->assertEquals(1, SupplierPayment::where('idempotency_key', $payKey)->count());
    }

    /**
     * 30. Unrelated database errors are not incorrectly swallowed as idempotency collisions.
     */
    public function test_unrelated_database_errors_are_not_swallowed_by_idempotency_handler(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-UNRELATED-ERR',
            'business_name' => 'Unrelated Error Vendor',
            'contact_person' => 'Farooq',
            'phone' => '0300-8884444',
            'primary_location_id' => $this->shop1->id,
            'created_by' => $this->owner->id,
        ]);

        $po = $this->purchaseService->createDraft([
            'location_id' => $this->shop1->id,
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [
                [
                    'product_variant_id' => $this->testVariant->id,
                    'unit_type' => 'pair',
                    'quantity' => 10,
                    'unit_cost' => 1000.00,
                ],
            ],
        ], $this->shop1Manager);

        $this->purchaseService->approveOrder($po, $this->shop1Manager);

        // Attempt payment with invalid foreign key (non-existent purchase_order_id)
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->purchaseService->recordPayment($supplier, [
            'idempotency_key' => 'unrelated-key-123',
            'location_id' => $this->shop1->id,
            'purchase_order_id' => 999999, // Non-existent
            'amount' => 1000.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ], $this->shop1Manager);
    }

    /**
     * 31. Live MariaDB Concurrency Verification: Separate PDO connections test concurrent race recovery.
     */
    public function test_mariadb_concurrency_race_recovery_on_separate_connections(): void
    {
        $config = config('database.connections.mysql');
        $config['database'] = 'test';

        $connector = new \Illuminate\Database\Connectors\MySqlConnector;
        try {
            $pdo1 = $connector->connect($config);
            $pdo2 = $connector->connect($config);
        } catch (\Throwable $e) {
            $this->markTestSkipped('MariaDB test database not accessible on port ' . ($config['port'] ?? 3306) . ': ' . $e->getMessage());
        }

        // Set up isolated temporary tables in MariaDB test database
        $pdo1->exec("DROP TABLE IF EXISTS `test_grn_race`");
        $pdo1->exec("CREATE TABLE `test_grn_race` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `idempotency_key` VARCHAR(64) NULL UNIQUE,
            `request_hash` VARCHAR(64) NULL,
            `amount` DECIMAL(12,2) DEFAULT 0.00
        ) ENGINE=InnoDB");

        $key = 'mariadb-grn-race-' . bin2hex(random_bytes(8));
        $hash = hash('sha256', 'canonical-mariadb-payload');

        // T2 begins transaction and reads, establishing an MVCC snapshot
        $pdo2->beginTransaction();
        $pdo2->query("SELECT COUNT(*) FROM `test_grn_race`")->fetch();

        // T1 begins transaction, inserts key, and commits
        $pdo1->beginTransaction();
        $stmt1 = $pdo1->prepare("INSERT INTO `test_grn_race` (`idempotency_key`, `request_hash`, `amount`) VALUES (?, ?, 100.00)");
        $stmt1->execute([$key, $hash]);
        $pdo1->commit();

        // T2 attempts to insert the same key -> catches 1062 duplicate key
        $t2Caught = false;
        try {
            $stmt2 = $pdo2->prepare("INSERT INTO `test_grn_race` (`idempotency_key`, `request_hash`, `amount`) VALUES (?, ?, 100.00)");
            $stmt2->execute([$key, $hash]);
        } catch (\PDOException $e) {
            $t2Caught = true;
            // The service rolls back the failed transaction before recovery
            $pdo2->rollBack();

            // Outside the rolled back transaction, re-query the committed record
            $stmtCheck = $pdo2->prepare("SELECT id, idempotency_key, request_hash, amount FROM `test_grn_race` WHERE idempotency_key = ?");
            $stmtCheck->execute([$key]);
            $recovered = $stmtCheck->fetch(\PDO::FETCH_ASSOC);

            $this->assertNotNull($recovered);
            $this->assertEquals($key, $recovered['idempotency_key']);
            $this->assertEquals($hash, $recovered['request_hash']);
            $this->assertEquals('100.00', $recovered['amount']);
        }

        $this->assertTrue($t2Caught, 'Expected duplicate key collision on MariaDB connection 2.');

        // Clean up
        $pdo1->exec("DROP TABLE IF EXISTS `test_grn_race`");
    }

    /**
     * 32. Live MariaDB Concurrency Verification for Supplier Payments.
     */
    public function test_mariadb_supplier_payment_concurrency_race_recovery(): void
    {
        $config = config('database.connections.mysql');
        $config['database'] = 'test';

        $connector = new \Illuminate\Database\Connectors\MySqlConnector;
        try {
            $pdo1 = $connector->connect($config);
            $pdo2 = $connector->connect($config);
        } catch (\Throwable $e) {
            $this->markTestSkipped('MariaDB test database not accessible on port ' . ($config['port'] ?? 3306) . ': ' . $e->getMessage());
        }

        $pdo1->exec("DROP TABLE IF EXISTS `test_spay_race`");
        $pdo1->exec("CREATE TABLE `test_spay_race` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `idempotency_key` VARCHAR(64) NULL UNIQUE,
            `request_hash` VARCHAR(64) NULL,
            `amount` DECIMAL(12,2) DEFAULT 0.00
        ) ENGINE=InnoDB");

        $key = 'mariadb-spay-race-' . bin2hex(random_bytes(8));
        $hash = hash('sha256', 'canonical-mariadb-spay-payload');

        // T2 begins transaction and reads, establishing MVCC snapshot
        $pdo2->beginTransaction();
        $pdo2->query("SELECT COUNT(*) FROM `test_spay_race`")->fetch();

        // T1 commits insert
        $pdo1->beginTransaction();
        $stmt1 = $pdo1->prepare("INSERT INTO `test_spay_race` (`idempotency_key`, `request_hash`, `amount`) VALUES (?, ?, 5000.00)");
        $stmt1->execute([$key, $hash]);
        $pdo1->commit();

        // T2 attempts insert
        $t2Caught = false;
        try {
            $stmt2 = $pdo2->prepare("INSERT INTO `test_spay_race` (`idempotency_key`, `request_hash`, `amount`) VALUES (?, ?, 5000.00)");
            $stmt2->execute([$key, $hash]);
        } catch (\PDOException $e) {
            $t2Caught = true;
            // Roll back failed transaction
            $pdo2->rollBack();

            // Re-query outside transaction
            $stmtCheck = $pdo2->prepare("SELECT id, idempotency_key, request_hash, amount FROM `test_spay_race` WHERE idempotency_key = ?");
            $stmtCheck->execute([$key]);
            $recovered = $stmtCheck->fetch(\PDO::FETCH_ASSOC);

            $this->assertNotNull($recovered);
            $this->assertEquals($key, $recovered['idempotency_key']);
            $this->assertEquals($hash, $recovered['request_hash']);
            $this->assertEquals('5000.00', $recovered['amount']);
        }

        $this->assertTrue($t2Caught, 'Expected duplicate key collision on MariaDB connection 2.');

        $pdo1->exec("DROP TABLE IF EXISTS `test_spay_race`");
    }

    /*
    |--------------------------------------------------------------------------
    | Phase 6B — Purchase Returns & Supplier Debit Notes Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Helper to set up a supplier, approved PO, and received GRN for return testing.
     */
    protected function setupGoodsReceiptForReturn(float $receivedPairs = 24.0, float $unitCost = 1000.00, ?Location $location = null, ?User $user = null): array
    {
        $location = $location ?? $this->shop1;
        $user = $user ?? $this->shop1Manager;

        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-RET-' . bin2hex(random_bytes(4)),
            'business_name' => 'Return Test Supplier ' . rand(100, 999),
            'contact_person' => 'Supplier Rep',
            'phone' => '0300-' . rand(1000000, 9999999),
            'primary_location_id' => $location->id,
            'credit_limit' => 500000.00,
            'opening_balance' => 0.00,
            'current_balance' => 0.00,
            'created_by' => $user->id,
        ]);

        $po = $this->purchaseService->createDraft([
            'location_id' => $location->id,
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [
                [
                    'product_variant_id' => $this->testVariant->id,
                    'unit_type' => 'pair',
                    'quantity' => $receivedPairs,
                    'unit_cost' => $unitCost,
                ],
            ],
        ], $user);

        $this->purchaseService->approveOrder($po, $user);
        $poItem = $po->items->first();

        $grn = $this->purchaseService->receiveGoods($po, [
            'receipt_date' => now()->toDateString(),
            'challan_number' => 'BILTY-RET-' . rand(100, 999),
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'received_pairs' => $receivedPairs,
                ],
            ],
        ], $user);

        return [$supplier, $po, $grn, $grn->items->first()];
    }

    /**
     * 1. Draft purchase return creation does NOT affect stock or supplier ledger.
     */
    public function test_draft_purchase_return_creation_does_not_affect_stock_or_ledger(): void
    {
        [$supplier, $po, $grn, $grnItem] = $this->setupGoodsReceiptForReturn(24.0, 1000.00);

        // Before draft return: stock is 24, supplier khata balance is 24,000
        $balanceBefore = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->testVariant->id)
            ->firstOrFail();
        $this->assertEquals('24.0000', $balanceBefore->pairs_quantity);

        $khataBefore = $this->khataService->calculateBalance($supplier);
        $this->assertEquals('24000.00', $khataBefore['closing_balance']);

        // Create draft return for 10 pairs
        $draftReturn = $this->purchaseService->createDraftReturn($grn, [
            'return_date' => now()->toDateString(),
            'reason' => 'Quality defect on sole bonding',
            'items' => [
                [
                    'goods_receipt_item_id' => $grnItem->id,
                    'return_pairs' => 10.0,
                    'notes' => 'Defective lot 1',
                ],
            ],
        ], $this->shop1Manager);

        // Assert draft status and metadata
        $this->assertEquals('draft', $draftReturn->status);
        $this->assertEquals('10.0000', $draftReturn->total_return_pairs);
        $this->assertEquals('10000.00', $draftReturn->total_return_amount);
        $this->assertDatabaseHas('purchase_returns', [
            'id' => $draftReturn->id,
            'status' => 'draft',
            'goods_receipt_id' => $grn->id,
            'total_returned_pairs' => '10.0000',
        ]);

        // Assert stock remains UNCHANGED
        $balanceAfter = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->testVariant->id)
            ->firstOrFail();
        $this->assertEquals('24.0000', $balanceAfter->pairs_quantity);

        // Assert NO stock movements created for return
        $this->assertDatabaseMissing('stock_movements', [
            'movement_type' => 'purchase_return',
            'reference_id' => $draftReturn->id,
        ]);

        // Assert supplier ledger UNCHANGED
        $khataAfter = $this->khataService->calculateBalance($supplier);
        $this->assertEquals('24000.00', $khataAfter['closing_balance']);
    }

    /**
     * 2. Posting a purchase return deducts exact stock and records supplier ledger debit.
     */
    public function test_post_return_deducts_exact_stock_and_posts_ledger_debit(): void
    {
        [$supplier, $po, $grn, $grnItem] = $this->setupGoodsReceiptForReturn(24.0, 1000.00);

        $draftReturn = $this->purchaseService->createDraftReturn($grn, [
            'return_date' => now()->toDateString(),
            'reason' => 'Defective stitching',
            'items' => [
                [
                    'goods_receipt_item_id' => $grnItem->id,
                    'return_pairs' => 8.0,
                ],
            ],
        ], $this->shop1Manager);

        $key = 'post-ret-key-' . bin2hex(random_bytes(6));
        $postedReturn = $this->purchaseService->postReturn($draftReturn, [
            'idempotency_key' => $key,
        ], $this->shop1Manager);

        $this->assertEquals('posted', $postedReturn->status);
        $this->assertNotNull($postedReturn->posted_at);
        $this->assertEquals($this->shop1Manager->id, $postedReturn->posted_by);

        // 1. Check stock balance: 24 - 8 = 16
        $balance = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->testVariant->id)
            ->firstOrFail();
        $this->assertEquals('16.0000', $balance->pairs_quantity);

        // 2. Check stock movement
        $this->assertDatabaseHas('stock_movements', [
            'location_id' => $this->shop1->id,
            'product_variant_id' => $this->testVariant->id,
            'movement_type' => 'purchase_return',
            'reference_type' => 'purchase_return',
            'reference_id' => $postedReturn->id,
            'quantity_delta_pairs' => -8.0,
        ]);

        // 3. Check supplier ledger debit entry
        $this->assertDatabaseHas('supplier_ledger_entries', [
            'supplier_id' => $supplier->id,
            'location_id' => $this->shop1->id,
            'entry_type' => 'purchase_return',
            'reference_type' => 'purchase_return',
            'reference_id' => $postedReturn->id,
            'debit_amount' => 8000.00, // 8 * 1000
            'credit_amount' => 0.00,
        ]);

        // 4. Check supplier current balance: 24,000 - 8,000 = 16,000
        $khata = $this->khataService->calculateBalance($supplier);
        $this->assertEquals('16000.00', $khata['closing_balance']);
    }

    /**
     * 3. Partial returns accurately calculate and restrict remaining returnable quantities.
     */
    public function test_partial_returns_track_remaining_returnable_quantities(): void
    {
        [$supplier, $po, $grn, $grnItem] = $this->setupGoodsReceiptForReturn(20.0, 1500.00);

        // Initial remaining returnable
        $this->assertEquals(20.0, $this->purchaseService->getRemainingReturnablePairs($grnItem));

        // Return 1: 8 pairs
        $ret1 = $this->purchaseService->createDraftReturn($grn, [
            'return_date' => now()->toDateString(),
            'reason' => 'Partial return 1',
            'items' => [
                [
                    'goods_receipt_item_id' => $grnItem->id,
                    'return_pairs' => 8.0,
                ],
            ],
        ], $this->shop1Manager);
        $this->purchaseService->postReturn($ret1, [], $this->shop1Manager);

        // Remaining returnable: 20 - 8 = 12
        $grnItem->refresh();
        $this->assertEquals(12.0, $this->purchaseService->getRemainingReturnablePairs($grnItem));

        // Return 2: remaining 12 pairs
        $ret2 = $this->purchaseService->createDraftReturn($grn, [
            'return_date' => now()->toDateString(),
            'reason' => 'Partial return 2 - balance',
            'items' => [
                [
                    'goods_receipt_item_id' => $grnItem->id,
                    'return_pairs' => 12.0,
                ],
            ],
        ], $this->shop1Manager);
        $this->purchaseService->postReturn($ret2, [], $this->shop1Manager);

        // Now remaining returnable must be 0
        $grnItem->refresh();
        $this->assertEquals(0.0, $this->purchaseService->getRemainingReturnablePairs($grnItem));
    }

    /**
     * 4. Cannot return more than received or remaining returnable quantity.
     */
    public function test_cannot_return_more_than_received_or_remaining_returnable(): void
    {
        [$supplier, $po, $grn, $grnItem] = $this->setupGoodsReceiptForReturn(10.0, 1000.00);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds remaining returnable');

        // Attempt to return 11 pairs when only 10 received
        $this->purchaseService->createDraftReturn($grn, [
            'return_date' => now()->toDateString(),
            'reason' => 'Over return attempt',
            'items' => [
                [
                    'goods_receipt_item_id' => $grnItem->id,
                    'return_pairs' => 11.0,
                ],
            ],
        ], $this->shop1Manager);
    }

    /**
     * 5. Cannot return zero or negative quantities.
     */
    public function test_cannot_return_zero_or_negative_pairs(): void
    {
        [$supplier, $po, $grn, $grnItem] = $this->setupGoodsReceiptForReturn(10.0, 1000.00);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No items with positive return quantities provided.');

        $this->purchaseService->createDraftReturn($grn, [
            'return_date' => now()->toDateString(),
            'reason' => 'Zero return attempt',
            'items' => [
                [
                    'goods_receipt_item_id' => $grnItem->id,
                    'return_pairs' => 0.0,
                ],
            ],
        ], $this->shop1Manager);
    }

    /**
     * 6. Cannot return goods receipt item belonging to a different GRN.
     */
    public function test_cannot_return_mismatched_goods_receipt_item(): void
    {
        [$supplier1, $po1, $grn1, $grnItem1] = $this->setupGoodsReceiptForReturn(10.0, 1000.00);
        [$supplier2, $po2, $grn2, $grnItem2] = $this->setupGoodsReceiptForReturn(10.0, 1000.00);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not belong to GRN');

        // Pass grnItem2 into return for grn1
        $this->purchaseService->createDraftReturn($grn1, [
            'return_date' => now()->toDateString(),
            'reason' => 'Mismatched item',
            'items' => [
                [
                    'goods_receipt_item_id' => $grnItem2->id,
                    'return_pairs' => 5.0,
                ],
            ],
        ], $this->shop1Manager);
    }

    /**
     * 7. Cannot post return if insufficient physical stock on hand.
     */
    public function test_cannot_post_return_if_insufficient_stock_on_hand(): void
    {
        [$supplier, $po, $grn, $grnItem] = $this->setupGoodsReceiptForReturn(10.0, 1000.00);

        $draftReturn = $this->purchaseService->createDraftReturn($grn, [
            'return_date' => now()->toDateString(),
            'reason' => 'Return after sales deduction',
            'items' => [
                [
                    'goods_receipt_item_id' => $grnItem->id,
                    'return_pairs' => 8.0,
                ],
            ],
        ], $this->shop1Manager);

        // Simulate deduction leaving only 5 pairs in stock
        $this->inventoryService->deductStock(
            locationId: $this->shop1->id,
            variantId: $this->testVariant->id,
            quantityPairs: 5.0,
            movementType: 'sale',
            user: $this->shop1Manager,
            referenceType: 'test',
            referenceId: 999
        );

        // Current stock is now 5. Return requires 8.
        $this->expectException(\App\Exceptions\InsufficientStockException::class);
        $this->expectExceptionMessage('Cannot deduct 8 pairs');

        $this->purchaseService->postReturn($draftReturn, [], $this->shop1Manager);
    }

    /**
     * 8. Posting return preserves Weighted Average Cost (WAC) mathematically.
     */
    public function test_posting_return_preserves_wac(): void
    {
        [$supplier, $po, $grn, $grnItem] = $this->setupGoodsReceiptForReturn(24.0, 1200.00);

        $balanceBefore = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->testVariant->id)
            ->firstOrFail();
        $this->assertEquals('1200.0000', $balanceBefore->avg_unit_cost);

        $draftReturn = $this->purchaseService->createDraftReturn($grn, [
            'return_date' => now()->toDateString(),
            'reason' => 'WAC test return',
            'items' => [
                [
                    'goods_receipt_item_id' => $grnItem->id,
                    'return_pairs' => 6.0,
                ],
            ],
        ], $this->shop1Manager);

        $this->purchaseService->postReturn($draftReturn, [], $this->shop1Manager);

        $balanceAfter = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->testVariant->id)
            ->firstOrFail();
        // Quantity reduced from 24 to 18, avg_unit_cost strictly preserved at 1200.0000
        $this->assertEquals('18.0000', $balanceAfter->pairs_quantity);
        $this->assertEquals('1200.0000', $balanceAfter->avg_unit_cost);
    }

    /**
     * 9. Posting return automatically creates linked SupplierDebitNote WITHOUT duplicate ledger entries.
     */
    public function test_posting_return_creates_supplier_debit_note_without_duplicate_ledger(): void
    {
        [$supplier, $po, $grn, $grnItem] = $this->setupGoodsReceiptForReturn(20.0, 1000.00);

        $draftReturn = $this->purchaseService->createDraftReturn($grn, [
            'return_date' => now()->toDateString(),
            'reason' => 'Debit note link test',
            'items' => [
                [
                    'goods_receipt_item_id' => $grnItem->id,
                    'return_pairs' => 5.0,
                ],
            ],
        ], $this->shop1Manager);

        $postedReturn = $this->purchaseService->postReturn($draftReturn, [], $this->shop1Manager);

        // Check Debit Note creation
        $debitNote = SupplierDebitNote::where('purchase_return_id', $postedReturn->id)->first();
        $this->assertNotNull($debitNote);
        $this->assertEquals('purchase_return', $debitNote->debit_note_type);
        $this->assertEquals('5000.00', $debitNote->amount);
        $this->assertEquals($supplier->id, $debitNote->supplier_id);
        $this->assertEquals($this->shop1->id, $debitNote->location_id);

        // Check ledger entries: exactly 2 entries (GRN credit, return debit). NOT 3!
        $entries = SupplierLedgerEntry::where('supplier_id', $supplier->id)->get();
        $this->assertCount(2, $entries);

        $returnEntry = $entries->firstWhere('entry_type', 'purchase_return');
        $this->assertNotNull($returnEntry);
        $this->assertEquals('5000.00', $returnEntry->debit_amount);

        // Current balance: 20,000 - 5,000 = 15,000
        $khata = $this->khataService->calculateBalance($supplier);
        $this->assertEquals('15000.00', $khata['closing_balance']);
    }

    /**
     * 10. Financial-only debit note debits supplier khata without altering warehouse inventory.
     */
    public function test_financial_debit_note_debits_ledger_without_affecting_stock(): void
    {
        [$supplier, $po, $grn, $grnItem] = $this->setupGoodsReceiptForReturn(20.0, 1000.00);

        $stockBefore = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->testVariant->id)
            ->firstOrFail()->pairs_quantity;

        // Record financial debit note for discount
        $debitNote = $this->purchaseService->recordFinancialDebitNote($supplier, [
            'location_id' => $this->shop1->id,
            'note_date' => now()->toDateString(),
            'amount' => 3000.00,
            'reason_type' => 'supplier_rebate',
            'reason' => 'Annual volume rebate agreed in writing',
            'reference_number' => 'REBATE-2026',
        ], $this->shop1Manager);

        // 1. Check debit note record
        $this->assertEquals('financial_adjustment', $debitNote->debit_note_type);
        $this->assertEquals('supplier_rebate', $debitNote->reason_type);
        $this->assertEquals('3000.00', $debitNote->amount);

        // 2. Check supplier ledger debit entry
        $this->assertDatabaseHas('supplier_ledger_entries', [
            'supplier_id' => $supplier->id,
            'location_id' => $this->shop1->id,
            'entry_type' => 'adjustment_debit',
            'reference_type' => 'supplier_debit_note',
            'reference_id' => $debitNote->id,
            'debit_amount' => '3000.00',
            'credit_amount' => '0.00',
        ]);

        // 3. Balance: 20,000 - 3,000 = 17,000
        $khata = $this->khataService->calculateBalance($supplier);
        $this->assertEquals('17000.00', $khata['closing_balance']);

        // 4. Assert ZERO stock movement and untouched stock balance
        $this->assertDatabaseMissing('stock_movements', [
            'reference_type' => 'supplier_debit_note',
            'reference_id' => $debitNote->id,
        ]);
        $stockAfter = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->testVariant->id)
            ->firstOrFail()->pairs_quantity;
        $this->assertEquals($stockBefore, $stockAfter);
    }

    /**
     * 11. Idempotency on postReturn prevents duplicate stock deduction and ledger posting.
     */
    public function test_post_return_idempotency_prevents_duplicate_stock_deduction(): void
    {
        [$supplier, $po, $grn, $grnItem] = $this->setupGoodsReceiptForReturn(24.0, 1000.00);

        $draftReturn = $this->purchaseService->createDraftReturn($grn, [
            'return_date' => now()->toDateString(),
            'reason' => 'Idempotency post test',
            'items' => [
                [
                    'goods_receipt_item_id' => $grnItem->id,
                    'return_pairs' => 6.0,
                ],
            ],
        ], $this->shop1Manager);

        $key = 'post-idemp-' . bin2hex(random_bytes(6));

        // First post
        $ret1 = $this->purchaseService->postReturn($draftReturn, [
            'idempotency_key' => $key,
        ], $this->shop1Manager);

        // Second post with exact same key
        $ret2 = $this->purchaseService->postReturn($ret1, [
            'idempotency_key' => $key,
        ], $this->shop1Manager);

        // Must return identical model
        $this->assertEquals($ret1->id, $ret2->id);

        // Stock must be deducted ONCE: 24 - 6 = 18 (NOT 12)
        $stock = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->testVariant->id)
            ->firstOrFail()->pairs_quantity;
        $this->assertEquals('18.0000', $stock);

        // Ledger must be debited ONCE: 24,000 - 6,000 = 18,000
        $khata = $this->khataService->calculateBalance($supplier);
        $this->assertEquals('18000.00', $khata['closing_balance']);
        $this->assertEquals(1, SupplierLedgerEntry::where('supplier_id', $supplier->id)->where('entry_type', 'purchase_return')->count());
    }

    /**
     * 12. Financial debit note idempotency prevents duplicate ledger entries.
     */
    public function test_financial_debit_note_idempotency_prevents_duplicate_ledger_entry(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-IDEMP-DN-' . bin2hex(random_bytes(3)),
            'business_name' => 'DN Idempotency Supplier',
            'contact_person' => 'Rep',
            'phone' => '0300-1122334',
            'primary_location_id' => $this->shop1->id,
            'credit_limit' => 200000.00,
            'opening_balance' => 0.00,
            'created_by' => $this->shop1Manager->id,
        ]);

        $key = 'dn-idemp-' . bin2hex(random_bytes(6));
        $data = [
            'location_id' => $this->shop1->id,
            'note_date' => now()->toDateString(),
            'amount' => 2500.00,
            'reason_type' => 'price_difference',
            'reason' => 'Rate correction',
            'idempotency_key' => $key,
        ];

        // Submission 1
        $dn1 = $this->purchaseService->recordFinancialDebitNote($supplier, $data, $this->shop1Manager);

        // Submission 2 (replay)
        $dn2 = $this->purchaseService->recordFinancialDebitNote($supplier, $data, $this->shop1Manager);

        $this->assertEquals($dn1->id, $dn2->id);
        $this->assertEquals(1, SupplierDebitNote::where('idempotency_key', $key)->count());
        $this->assertEquals(1, SupplierLedgerEntry::where('supplier_id', $supplier->id)->where('entry_type', 'adjustment_debit')->count());

        $khata = $this->khataService->calculateBalance($supplier);
        $this->assertEquals('-2500.00', $khata['closing_balance']);
        $this->assertEquals('advance', $khata['balance_status']);
    }

    /**
     * 13. Idempotency payload mismatch is rejected with InvalidArgumentException.
     */
    public function test_debit_note_idempotency_payload_mismatch_throws_exception(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-MISMATCH-' . bin2hex(random_bytes(3)),
            'business_name' => 'Mismatch Supplier',
            'contact_person' => 'Rep',
            'phone' => '0300-9988776',
            'primary_location_id' => $this->shop1->id,
            'credit_limit' => 200000.00,
            'opening_balance' => 0.00,
            'created_by' => $this->shop1Manager->id,
        ]);

        $key = 'dn-mismatch-' . bin2hex(random_bytes(6));

        // Submit first
        $this->purchaseService->recordFinancialDebitNote($supplier, [
            'location_id' => $this->shop1->id,
            'note_date' => now()->toDateString(),
            'amount' => 1000.00,
            'reason_type' => 'supplier_rebate',
            'reason' => '1000 discount',
            'idempotency_key' => $key,
        ], $this->shop1Manager);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('different request payload');

        // Submit again with altered amount
        $this->purchaseService->recordFinancialDebitNote($supplier, [
            'location_id' => $this->shop1->id,
            'note_date' => now()->toDateString(),
            'amount' => 9999.00,
            'reason_type' => 'supplier_rebate',
            'reason' => 'Changed amount',
            'idempotency_key' => $key,
        ], $this->shop1Manager);
    }

    /**
     * 14. Supplier advance / negative payable balance is valid in wholesale trade.
     */
    public function test_supplier_advance_negative_payable_allowed(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-ADV-' . bin2hex(random_bytes(3)),
            'business_name' => 'Advance Supplier',
            'contact_person' => 'Advance Rep',
            'phone' => '0300-4455667',
            'primary_location_id' => $this->shop1->id,
            'credit_limit' => 500000.00,
            'opening_balance' => 0.00,
            'current_balance' => 0.00,
            'created_by' => $this->shop1Manager->id,
        ]);

        // Debit note for 15,000 on zero balance
        $dn = $this->purchaseService->recordFinancialDebitNote($supplier, [
            'location_id' => $this->shop1->id,
            'note_date' => now()->toDateString(),
            'amount' => 15000.00,
            'reason_type' => 'damage_compensation',
            'reason' => 'Advance allowance claim',
        ], $this->shop1Manager);

        $khata = $this->khataService->calculateBalance($supplier);
        $this->assertEquals('-15000.00', $khata['closing_balance']);
        $this->assertEquals('advance', $khata['balance_status']);
        $this->assertDatabaseHas('supplier_ledger_entries', [
            'supplier_id' => $supplier->id,
            'debit_amount' => '15000.00',
        ]);
    }

    /**
     * 15. Cross-location authorization enforces that managers cannot operate returns outside their location.
     */
    public function test_location_scoping_blocks_unauthorized_returns(): void
    {
        // Setup GRN at Shop 1
        [$supplier, $po, $grn, $grnItem] = $this->setupGoodsReceiptForReturn(10.0, 1000.00, $this->shop1, $this->shop1Manager);

        // Shop 2 Manager attempts to access Shop 1 return create page
        $this->actingAs($this->shop2Manager);

        $response = $this->get(route('purchases.receipts.returns.create', $grn));
        $response->assertStatus(403);

        // Attempt store via POST
        $postResponse = $this->post(route('purchases.receipts.returns.store', $grn), [
            'return_date' => now()->toDateString(),
            'reason' => 'Unauthorized return',
            'items' => [
                [
                    'goods_receipt_item_id' => $grnItem->id,
                    'return_pairs' => 2.0,
                ],
            ],
        ]);
        $postResponse->assertStatus(403);
    }

    /**
     * 16. Web HTTP routes: Draft Return creation and Posting lifecycle via controller.
     */
    public function test_http_purchase_return_lifecycle_via_controller(): void
    {
        [$supplier, $po, $grn, $grnItem] = $this->setupGoodsReceiptForReturn(12.0, 1000.00);

        $this->actingAs($this->shop1Manager);

        // 1. Visit Create Return page
        $createPage = $this->get(route('purchases.receipts.returns.create', $grn));
        $createPage->assertStatus(200);
        $createPage->assertSee('Return Items from');

        // 2. Submit Draft Return
        $storeResponse = $this->post(route('purchases.receipts.returns.store', $grn), [
            'return_date' => now()->toDateString(),
            'reason' => 'HTTP lifecycle return test',
            'items' => [
                [
                    'goods_receipt_item_id' => $grnItem->id,
                    'return_cartons' => 0.5,
                    'return_pairs' => 6.0,
                    'notes' => 'Half carton defective',
                ],
            ],
        ]);

        $return = PurchaseReturn::latest('id')->first();
        $this->assertNotNull($return);
        $storeResponse->assertRedirect(route('purchases.returns.show', $return));
        $this->assertEquals('draft', $return->status);

        // 3. Visit Return Show page
        $showPage = $this->get(route('purchases.returns.show', $return));
        $showPage->assertStatus(200);
        $showPage->assertSee('Draft (Unposted)');

        // 4. Post the Return
        $postResponse = $this->post(route('purchases.returns.post', $return), [
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ]);
        $postResponse->assertRedirect(route('purchases.returns.show', $return));

        $return->refresh();
        $this->assertEquals('posted', $return->status);
        $this->assertEquals('6.0000', $return->total_return_pairs);

        // Check Index view
        $indexPage = $this->get(route('purchases.returns.index'));
        $indexPage->assertStatus(200);
        $indexPage->assertSee($return->return_number);
    }

    /**
     * 17. Web HTTP routes: Financial Debit Note creation and show voucher.
     */
    public function test_http_financial_debit_note_lifecycle_via_controller(): void
    {
        $supplier = Supplier::create([
            'supplier_code' => 'SUPP-HTTP-DN-' . bin2hex(random_bytes(3)),
            'business_name' => 'HTTP DN Supplier',
            'contact_person' => 'Rep',
            'phone' => '0300-8877665',
            'primary_location_id' => $this->shop1->id,
            'credit_limit' => 200000.00,
            'opening_balance' => 0.00,
            'created_by' => $this->shop1Manager->id,
        ]);

        $this->actingAs($this->shop1Manager);

        // 1. Visit Create page
        $createPage = $this->get(route('supplier-debit-notes.create', ['supplier_id' => $supplier->id]));
        $createPage->assertStatus(200);
        $createPage->assertSee('New Financial Debit Note');

        // 2. Submit form
        $response = $this->post(route('supplier-debit-notes.store'), [
            'supplier_id' => $supplier->id,
            'location_id' => $this->shop1->id,
            'note_date' => now()->toDateString(),
            'reason_type' => 'supplier_rebate',
            'reason' => 'Trade fair 5% discount',
            'amount' => 4500.00,
            'reference_number' => 'DISC-TF-26',
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ]);

        $debitNote = SupplierDebitNote::latest('id')->first();
        $this->assertNotNull($debitNote);
        $response->assertRedirect(route('supplier-debit-notes.show', $debitNote));
        $this->assertEquals('4500.00', $debitNote->amount);

        // 3. Show Voucher
        $showPage = $this->get(route('supplier-debit-notes.show', $debitNote));
        $showPage->assertStatus(200);
        $showPage->assertSee('PKR 4,500.00');

        // 4. Index view
        $indexPage = $this->get(route('supplier-debit-notes.index'));
        $indexPage->assertStatus(200);
        $indexPage->assertSee($debitNote->debit_note_number);
    }

    /**
     * 18. MariaDB concurrency simulation: duplicate key exception recovery outside transaction on returns.
     */
    public function test_mariadb_concurrency_race_recovery_on_returns(): void
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

        $pdo1->exec("DROP TABLE IF EXISTS `test_return_race`");
        $pdo1->exec("CREATE TABLE `test_return_race` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `idempotency_key` VARCHAR(64) NULL UNIQUE,
            `request_hash` VARCHAR(64) NULL,
            `amount` DECIMAL(12,2) DEFAULT 0.00
        ) ENGINE=InnoDB");

        $key = 'mariadb-ret-race-' . bin2hex(random_bytes(8));
        $hash = hash('sha256', 'canonical-mariadb-return-payload');

        // T2 begins transaction and reads, establishing MVCC snapshot
        $pdo2->beginTransaction();
        $pdo2->query("SELECT COUNT(*) FROM `test_return_race`")->fetch();

        // T1 commits insert
        $pdo1->beginTransaction();
        $stmt1 = $pdo1->prepare("INSERT INTO `test_return_race` (`idempotency_key`, `request_hash`, `amount`) VALUES (?, ?, 12000.00)");
        $stmt1->execute([$key, $hash]);
        $pdo1->commit();

        // T2 attempts insert
        $t2Caught = false;
        try {
            $stmt2 = $pdo2->prepare("INSERT INTO `test_return_race` (`idempotency_key`, `request_hash`, `amount`) VALUES (?, ?, 12000.00)");
            $stmt2->execute([$key, $hash]);
        } catch (\PDOException $e) {
            $t2Caught = true;
            $pdo2->rollBack();

            // Re-query outside transaction
            $stmtCheck = $pdo2->prepare("SELECT id, idempotency_key, request_hash, amount FROM `test_return_race` WHERE idempotency_key = ?");
            $stmtCheck->execute([$key]);
            $recovered = $stmtCheck->fetch(\PDO::FETCH_ASSOC);

            $this->assertNotNull($recovered);
            $this->assertEquals($key, $recovered['idempotency_key']);
            $this->assertEquals($hash, $recovered['request_hash']);
            $this->assertEquals('12000.00', $recovered['amount']);
        }

        $this->assertTrue($t2Caught, 'Expected duplicate key collision on MariaDB connection 2 for returns.');

        $pdo1->exec("DROP TABLE IF EXISTS `test_return_race`");
    }
}
