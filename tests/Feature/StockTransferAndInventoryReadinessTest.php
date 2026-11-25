<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Models\StockAdjustment;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\Sales\SalesPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransferAndInventoryReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $shopManager;
    protected User $factoryManager;
    protected Location $factory;
    protected Location $shop1;
    protected Location $warehouse;
    protected Product $product;
    protected ProductVariant $variant;
    protected InventoryService $inventoryService;
    protected SalesPostingService $salesService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->inventoryService = app(InventoryService::class);
        $this->salesService = app(SalesPostingService::class);

        $this->owner = User::where('email', 'khalid.owner@khalidtraders.pk')->firstOrFail();
        $this->shopManager = User::where('email', 'tariq.anarkali@khalidtraders.pk')->firstOrFail();
        $this->factoryManager = User::where('email', 'aslam.factory1@khalidtraders.pk')->firstOrFail();

        $this->factory = Location::where('code', 'LOC-FAC01')->firstOrFail();
        $this->shop1 = Location::where('code', 'LOC-SH01')->firstOrFail();
        $this->warehouse = Location::where('code', 'LOC-WH01')->firstOrFail();

        $category = ProductCategory::firstOrCreate(
            ['slug' => 'formal-shoes'],
            ['name' => 'Formal Shoes', 'code' => 'CAT-FORMAL', 'is_active' => true]
        );

        $unit = UnitOfMeasure::firstOrCreate(
            ['code' => 'PRS'],
            ['name' => 'Pairs', 'allow_decimal' => false, 'is_active' => true]
        );

        $this->product = Product::create([
            'article_code' => 'KT-ART-8899',
            'name' => 'Oxford Executive Derby',
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'carton_pack_qty' => 12,
            'default_wholesale_rate' => 2800.00,
            'default_retail_rate' => 3500.00,
            'reorder_threshold_cartons' => 10,
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'KT-ART-8899-BLK-42',
            'size' => '42',
            'color' => 'Black',
            'purchase_cost' => 1800.00,
            'is_active' => true,
        ]);
    }

    /**
     * Test draft stock transfer creation without premature stock movements.
     */
    public function test_can_create_draft_stock_transfer(): void
    {
        // 1. Initial stock at factory: 120 pairs
        $this->inventoryService->addStock(
            locationId: $this->factory->id,
            variantId: $this->variant->id,
            quantityPairs: 120.0,
            unitCost: 1800.00,
            movementType: 'opening_balance',
            user: $this->owner
        );

        $transferData = [
            'source_location_id' => $this->factory->id,
            'destination_location_id' => $this->shop1->id,
            'transfer_date' => now()->toDateString(),
            'dispatch_notes' => 'Transfer 5 cartons for weekend wholesale demand',
            'action' => 'save_draft',
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'quantity_cartons' => 5,
                    'quantity_pairs' => 60,
                    'notes' => '5 cartons of size 42 Black',
                ],
            ],
        ];

        $response = $this->actingAs($this->owner)->post(route('inventory.transfers.store'), $transferData);
        $response->assertRedirect();

        $transfer = StockTransfer::where('source_location_id', $this->factory->id)
            ->where('destination_location_id', $this->shop1->id)
            ->firstOrFail();

        $this->assertEquals('draft', $transfer->status);
        $this->assertEquals(5, $transfer->total_cartons);
        $this->assertEquals(60.0, (float) $transfer->total_pairs);

        // Factory stock should NOT be deducted while draft
        $factoryBalance = StockBalance::where('location_id', $this->factory->id)
            ->where('product_variant_id', $this->variant->id)
            ->first();
        $this->assertEquals(120.0, (float) $factoryBalance->pairs_quantity);
    }

    /**
     * Test transfer dispatch deducts source inventory and sets status in_transit.
     */
    public function test_dispatch_transfer_deducts_source_inventory_and_marks_in_transit(): void
    {
        $this->inventoryService->addStock(
            locationId: $this->factory->id,
            variantId: $this->variant->id,
            quantityPairs: 120.0,
            unitCost: 1800.00,
            movementType: 'opening_balance',
            user: $this->owner
        );

        $transfer = $this->inventoryService->createTransferDraft([
            'source_location_id' => $this->factory->id,
            'destination_location_id' => $this->shop1->id,
            'transfer_date' => now()->toDateString(),
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'quantity_cartons' => 3,
                    'quantity_pairs' => 36,
                ],
            ],
        ], $this->owner);

        // Dispatch transfer
        $dispatched = $this->inventoryService->dispatchTransfer($transfer, $this->owner, 'Dispatched via Van 4412');

        $this->assertEquals('in_transit', $dispatched->status);
        $this->assertNotNull($dispatched->dispatched_at);

        // Factory stock deducted: 120 - 36 = 84
        $factoryBalance = StockBalance::where('location_id', $this->factory->id)
            ->where('product_variant_id', $this->variant->id)
            ->first();
        $this->assertEquals(84.0, (float) $factoryBalance->pairs_quantity);

        // Verify movement logged
        $movement = StockMovement::where('reference_type', StockTransfer::class)
            ->where('reference_id', $transfer->id)
            ->where('movement_type', 'transfer_out')
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals(-36.0, (float) $movement->quantity_delta_pairs);

        // Destination shop balance still 0
        $shopBalance = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->variant->id)
            ->first();
        $this->assertTrue(!$shopBalance || (float) $shopBalance->pairs_quantity === 0.0);
    }

    /**
     * Test transfer receipt credits destination inventory and completes transfer.
     */
    public function test_receive_transfer_adds_destination_inventory_and_completes_transfer(): void
    {
        $this->inventoryService->addStock(
            locationId: $this->factory->id,
            variantId: $this->variant->id,
            quantityPairs: 100.0,
            unitCost: 1800.00,
            movementType: 'opening_balance',
            user: $this->owner
        );

        $transfer = $this->inventoryService->createTransferDraft([
            'source_location_id' => $this->factory->id,
            'destination_location_id' => $this->shop1->id,
            'transfer_date' => now()->toDateString(),
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'quantity_cartons' => 2,
                    'quantity_pairs' => 24,
                ],
            ],
        ], $this->owner);

        $transfer = $this->inventoryService->dispatchTransfer($transfer, $this->owner);

        // Receive at destination shop
        $completed = $this->inventoryService->receiveTransfer($transfer, $this->owner, 'All 2 cartons received in pristine condition');

        $this->assertEquals('completed', $completed->status);
        $this->assertNotNull($completed->received_at);

        // Destination shop stock increased: +24
        $shopBalance = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->variant->id)
            ->first();
        $this->assertNotNull($shopBalance);
        $this->assertEquals(24.0, (float) $shopBalance->pairs_quantity);
        $this->assertEquals(1800.00, (float) $shopBalance->avg_unit_cost);

        // Receipt movement logged
        $receiptMovement = StockMovement::where('reference_type', StockTransfer::class)
            ->where('reference_id', $transfer->id)
            ->where('movement_type', 'transfer_in')
            ->first();
        $this->assertNotNull($receiptMovement);
        $this->assertEquals(24.0, (float) $receiptMovement->quantity_delta_pairs);
    }

    /**
     * Test cancelling an in-transit transfer safely reverses stock back to origin location.
     */
    public function test_cancel_in_transit_transfer_reverses_stock_back_to_source(): void
    {
        $this->inventoryService->addStock(
            locationId: $this->factory->id,
            variantId: $this->variant->id,
            quantityPairs: 50.0,
            unitCost: 1800.00,
            movementType: 'opening_balance',
            user: $this->owner
        );

        $transfer = $this->inventoryService->createTransferDraft([
            'source_location_id' => $this->factory->id,
            'destination_location_id' => $this->shop1->id,
            'transfer_date' => now()->toDateString(),
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'quantity_cartons' => 1,
                    'quantity_pairs' => 12,
                ],
            ],
        ], $this->owner);

        $this->inventoryService->dispatchTransfer($transfer, $this->owner);

        // Factory stock currently 38
        $this->assertEquals(38.0, (float) StockBalance::where('location_id', $this->factory->id)->where('product_variant_id', $this->variant->id)->value('pairs_quantity'));

        // Driver turned back / vehicle breakdown -> Cancel transfer
        $cancelled = $this->inventoryService->cancelTransfer($transfer, $this->owner, 'Van breakdown; stock returned to factory warehouse');

        $this->assertEquals('cancelled', $cancelled->status);

        // Factory stock restored back to 50
        $factoryRestored = StockBalance::where('location_id', $this->factory->id)
            ->where('product_variant_id', $this->variant->id)
            ->first();
        $this->assertEquals(50.0, (float) $factoryRestored->pairs_quantity);

        // Reversal movement logged
        $reversal = StockMovement::where('reference_type', StockTransfer::class)
            ->where('reference_id', $transfer->id)
            ->where('movement_type', 'transfer_in')
            ->first();
        $this->assertNotNull($reversal);
        $this->assertEquals(12.0, (float) $reversal->quantity_delta_pairs);
    }

    /**
     * Test transfer dispatch is prevented when source location has insufficient stock.
     */
    public function test_cannot_dispatch_transfer_with_insufficient_stock(): void
    {
        // Source location has only 10 pairs
        $this->inventoryService->addStock(
            locationId: $this->factory->id,
            variantId: $this->variant->id,
            quantityPairs: 10.0,
            unitCost: 1800.00,
            movementType: 'opening_balance',
            user: $this->owner
        );

        $transfer = $this->inventoryService->createTransferDraft([
            'source_location_id' => $this->factory->id,
            'destination_location_id' => $this->shop1->id,
            'transfer_date' => now()->toDateString(),
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'quantity_cartons' => 2,
                    'quantity_pairs' => 24, // Requesting 24, but only 10 available
                ],
            ],
        ], $this->owner);

        $this->expectException(InsufficientStockException::class);
        $this->inventoryService->dispatchTransfer($transfer, $this->owner);
    }

    /**
     * Test product article edit and update.
     */
    public function test_product_article_edit_and_update(): void
    {
        $editResp = $this->actingAs($this->owner)->get(route('products.edit', $this->product));
        $editResp->assertOk();
        $editResp->assertSee('Edit Article');
        $editResp->assertSee($this->product->article_code);

        $updateData = [
            'name' => 'Oxford Executive Derby Premium',
            'category_id' => $this->product->category_id,
            'carton_pack_qty' => 12,
            'default_wholesale_rate' => 3100.00,
            'default_retail_rate' => 3900.00,
            'reorder_threshold_cartons' => 15,
            'description' => 'Updated full-grain leather oxford',
            'is_active' => true,
        ];

        $putResp = $this->actingAs($this->owner)->put(route('products.update', $this->product), $updateData);
        $putResp->assertRedirect(route('products.show', $this->product));

        $this->product->refresh();
        $this->assertEquals('Oxford Executive Derby Premium', $this->product->name);
        $this->assertEquals(3100.00, (float) $this->product->default_wholesale_rate);
        $this->assertEquals(15, $this->product->reorder_threshold_cartons);
    }

    /**
     * Test stock count adjustment draft creation and approval.
     */
    public function test_stock_adjustment_draft_creation_and_approval(): void
    {
        // 1. Initial stock 50 pairs
        $this->inventoryService->addStock(
            locationId: $this->shop1->id,
            variantId: $this->variant->id,
            quantityPairs: 50.0,
            unitCost: 1800.00,
            movementType: 'opening_balance',
            user: $this->owner
        );

        $createViewResp = $this->actingAs($this->owner)->get(route('inventory.adjustments.create'));
        $createViewResp->assertOk();
        $createViewResp->assertSee('Physical Stock Count');

        // Submit physical count: found 5 excess pairs (+5)
        $adjData = [
            'location_id' => $this->shop1->id,
            'reason' => 'Physical Stock Count Reconciliation',
            'notes' => 'Found 5 extra pairs during weekly count',
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'quantity_delta_pairs' => 5,
                    'unit_cost' => 1800.00,
                    'notes' => 'Excess stock found in rear storage',
                ],
            ],
        ];

        $postResp = $this->actingAs($this->owner)->post(route('inventory.adjustments.store'), $adjData);
        $postResp->assertRedirect(route('inventory.adjustments'));

        $adjustment = StockAdjustment::where('location_id', $this->shop1->id)->latest()->firstOrFail();
        $this->assertEquals('draft', $adjustment->status);

        // Approve and post adjustment
        $approveResp = $this->actingAs($this->owner)->post(route('inventory.adjustments.approve', $adjustment));
        $approveResp->assertRedirect(route('inventory.adjustments'));

        $adjustment->refresh();
        $this->assertEquals('approved', $adjustment->status);

        // Stock balance updated from 50 to 55
        $shopBalance = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $this->variant->id)
            ->first();
        $this->assertEquals(55.0, (float) $shopBalance->pairs_quantity);
    }

    /**
     * Test cancelling a draft sales invoice.
     */
    public function test_can_cancel_draft_sales_invoice(): void
    {
        $customer = Customer::firstOrCreate(
            ['customer_code' => 'CUST-TEST-001'],
            [
                'business_name' => 'Al-Madina Shoes',
                'contact_person' => 'Haji Madina',
                'phone' => '+92 300 1122334',
                'primary_location_id' => $this->shop1->id,
                'credit_limit' => 500000.00,
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
                'created_by' => $this->owner->id,
                'is_active' => true,
            ]
        );

        $draftInvoice = $this->salesService->createDraft([
            'customer_id' => $customer->id,
            'location_id' => $this->shop1->id,
            'invoice_date' => now()->toDateString(),
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'unit_type' => 'carton',
                    'quantity' => 1,
                    'unit_price' => 3000.00,
                ],
            ],
        ], $this->owner);

        $this->assertEquals('draft', $draftInvoice->status);

        // Cancel draft invoice
        $cancelResp = $this->actingAs($this->owner)->post(route('sales.invoices.cancel', $draftInvoice));
        $cancelResp->assertRedirect(route('sales.invoices.index'));

        $draftInvoice->refresh();
        $this->assertEquals('cancelled', $draftInvoice->status);
    }
}
