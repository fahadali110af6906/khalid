<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ProductAndInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $shop1Manager;
    protected User $shop2Manager;
    protected Location $shop1;
    protected Location $shop2;
    protected ProductCategory $category;
    protected UnitOfMeasure $pairUnit;
    protected InventoryService $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->owner = User::where('email', 'khalid.owner@khalidtraders.pk')->firstOrFail();
        $this->shop1Manager = User::where('email', 'tariq.anarkali@khalidtraders.pk')->firstOrFail();
        $this->shop2Manager = User::where('email', 'irfan.shahalam@khalidtraders.pk')->firstOrFail();

        $this->shop1 = Location::where('code', 'LOC-SH01')->firstOrFail();
        $this->shop2 = Location::where('code', 'LOC-SH02')->firstOrFail();

        $this->category = ProductCategory::where('code', 'MEN-FORMAL')->firstOrFail();
        $this->pairUnit = UnitOfMeasure::where('code', 'PR')->firstOrFail();

        $this->inventoryService = app(InventoryService::class);
    }

    /**
     * Test product and variant matrix creation with validation.
     */
    public function test_can_create_product_with_variants_and_skus(): void
    {
        $this->actingAs($this->owner);

        $response = $this->post(route('products.store'), [
            'article_code' => 'ART-5500',
            'name' => 'Classic Oxford Leather Shoe',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->pairUnit->id,
            'carton_pack_qty' => 12,
            'default_wholesale_rate' => 2400.00,
            'default_retail_rate' => 3000.00,
            'reorder_threshold_cartons' => 5,
            'description' => 'Genuine cow leather formal shoe',
            'variants' => [
                ['size' => '40', 'color' => 'Black', 'barcode' => '896400010040', 'purchase_cost' => 1600.00],
                ['size' => '41', 'color' => 'Black', 'barcode' => '896400010041', 'purchase_cost' => 1600.00],
                ['size' => '42', 'color' => 'Brown', 'barcode' => '896400010042', 'purchase_cost' => 1600.00],
            ],
        ]);

        $response->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', [
            'article_code' => 'ART-5500',
            'carton_pack_qty' => 12,
        ]);

        $product = Product::where('article_code', 'ART-5500')->firstOrFail();
        $this->assertCount(3, $product->variants);

        // Verify generated SKUs
        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'sku' => 'ART-5500-BLA-40',
            'size' => '40',
            'color' => 'Black',
        ]);
    }

    /**
     * Test duplicate article code is rejected.
     */
    public function test_duplicate_article_code_is_rejected(): void
    {
        $this->actingAs($this->owner);

        Product::create([
            'article_code' => 'ART-9999',
            'name' => 'Existing Model',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->pairUnit->id,
            'carton_pack_qty' => 12,
            'default_wholesale_rate' => 2000,
        ]);

        $response = $this->post(route('products.store'), [
            'article_code' => 'ART-9999',
            'name' => 'Another Model',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->pairUnit->id,
            'carton_pack_qty' => 12,
            'default_wholesale_rate' => 2000,
            'reorder_threshold_cartons' => 5,
            'variants' => [
                ['size' => '40', 'color' => 'Black'],
            ],
        ]);

        $response->assertSessionHasErrors('article_code');
    }

    /**
     * Test carton to pair packaging conversions.
     */
    public function test_carton_to_pair_packaging_conversions(): void
    {
        $product12 = Product::create([
            'article_code' => 'ART-TEST-12',
            'name' => 'Standard Pack',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->pairUnit->id,
            'carton_pack_qty' => 12,
            'default_wholesale_rate' => 1500,
        ]);

        // 10 cartons = 120 pairs
        $this->assertEquals(120.0, $product12->convertCartonsToPairs(10));
        $this->assertEquals(18.0, $product12->convertCartonsToPairs(1.5));

        // 125 pairs = 10 cartons + 5 loose pairs
        $breakdown = $product12->convertPairsToCartons(125);
        $this->assertEquals(10, $breakdown['cartons']);
        $this->assertEquals(5.0, $breakdown['loose_pairs']);
        $this->assertEquals(125.0, $breakdown['total_pairs']);

        // Test non-standard 24-pair carton packaging
        $product24 = Product::create([
            'article_code' => 'ART-TEST-24',
            'name' => 'Bulk Pack',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->pairUnit->id,
            'carton_pack_qty' => 24,
            'default_wholesale_rate' => 1200,
        ]);

        $this->assertEquals(240.0, $product24->convertCartonsToPairs(10));

        // Negative conversions must throw InvalidArgumentException
        $this->expectException(InvalidArgumentException::class);
        $product12->convertCartonsToPairs(-5);
    }

    /**
     * Test stock addition and weighted average cost calculation.
     */
    public function test_stock_addition_and_wac_cost_calculation(): void
    {
        $product = Product::create([
            'article_code' => 'ART-WAC',
            'name' => 'WAC Shoe',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->pairUnit->id,
            'carton_pack_qty' => 12,
            'default_wholesale_rate' => 2000,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'ART-WAC-BLK-42',
            'size' => '42',
            'color' => 'Black',
            'purchase_cost' => 1000.00,
        ]);

        // Batch 1: 100 pairs @ PKR 1,000
        $movement1 = $this->inventoryService->addStock(
            $this->shop1->id,
            $variant->id,
            100.0,
            1000.0,
            'opening_balance',
            $this->owner
        );

        $balance = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $variant->id)
            ->firstOrFail();

        $this->assertEquals(100.0, (float) $balance->pairs_quantity);
        $this->assertEquals(1000.0, (float) $balance->avg_unit_cost);

        // Batch 2: 100 pairs @ PKR 1,200
        // New WAC = (100 * 1000 + 100 * 1200) / 200 = 220,000 / 200 = 1,100
        $this->inventoryService->addStock(
            $this->shop1->id,
            $variant->id,
            100.0,
            1200.0,
            'purchase',
            $this->owner
        );

        $balance->refresh();
        $this->assertEquals(200.0, (float) $balance->pairs_quantity);
        $this->assertEquals(1100.0, (float) $balance->avg_unit_cost);

        // Check movements recorded
        $this->assertCount(2, StockMovement::where('product_variant_id', $variant->id)->get());
    }

    /**
     * Test negative stock prevention throws InsufficientStockException.
     */
    public function test_negative_stock_prevention(): void
    {
        $product = Product::create([
            'article_code' => 'ART-NEG',
            'name' => 'Negative Stock Test',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->pairUnit->id,
            'carton_pack_qty' => 12,
            'default_wholesale_rate' => 2000,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'ART-NEG-BLK-40',
            'size' => '40',
            'color' => 'Black',
        ]);

        // Add 50 pairs
        $this->inventoryService->addStock(
            $this->shop1->id,
            $variant->id,
            50.0,
            1000.0,
            'opening_balance',
            $this->owner
        );

        // Attempt to deduct 60 pairs must throw InsufficientStockException
        $this->expectException(InsufficientStockException::class);
        $this->inventoryService->deductStock(
            $this->shop1->id,
            $variant->id,
            60.0,
            'sale',
            $this->owner
        );
    }

    /**
     * Test location isolation on stock ledger (Zero IDOR).
     */
    public function test_location_isolation_on_stock_ledger(): void
    {
        $product = Product::create([
            'article_code' => 'ART-ISO',
            'name' => 'Isolation Test Shoe',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->pairUnit->id,
            'carton_pack_qty' => 12,
            'default_wholesale_rate' => 2000,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'ART-ISO-BLK-40',
            'size' => '40',
            'color' => 'Black',
        ]);

        // Add stock at Shop 1
        $this->inventoryService->addStock(
            $this->shop1->id,
            $variant->id,
            120.0,
            1500.0,
            'opening_balance',
            $this->owner
        );

        // Add stock at Shop 2
        $this->inventoryService->addStock(
            $this->shop2->id,
            $variant->id,
            240.0,
            1500.0,
            'opening_balance',
            $this->owner
        );

        // Shop 1 Manager accesses Shop 1 stock -> 200 OK
        $responseShop1 = $this->actingAs($this->shop1Manager)
            ->get(route('inventory.index', ['location_id' => $this->shop1->id]));
        $responseShop1->assertStatus(200);
        $responseShop1->assertSee('ART-ISO-BLK-40');
        $responseShop1->assertSee('10 CTN');

        // Shop 1 Manager attempts to access Shop 2 stock -> 403 Forbidden!
        $responseShop2 = $this->actingAs($this->shop1Manager)
            ->get(route('inventory.index', ['location_id' => $this->shop2->id]));
        $responseShop2->assertStatus(403);

        // Shop 1 Manager attempts consolidated view -> 403 Forbidden!
        $responseAll = $this->actingAs($this->shop1Manager)
            ->get(route('inventory.index', ['location_id' => 'all']));
        $responseAll->assertStatus(403);
    }

    /**
     * Test inventory adjustment draft and approval lifecycle.
     */
    public function test_inventory_adjustment_workflow(): void
    {
        $product = Product::create([
            'article_code' => 'ART-ADJ',
            'name' => 'Adjustment Test',
            'category_id' => $this->category->id,
            'base_unit_id' => $this->pairUnit->id,
            'carton_pack_qty' => 12,
            'default_wholesale_rate' => 2000,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'ART-ADJ-BLK-41',
            'size' => '41',
            'color' => 'Black',
        ]);

        // Shop 1 Manager creates draft adjustment
        $this->actingAs($this->shop1Manager);
        $response = $this->post(route('inventory.adjustments.store'), [
            'location_id' => $this->shop1->id,
            'reason' => 'Stock physical count discrepancy',
            'items' => [
                [
                    'product_variant_id' => $variant->id,
                    'quantity_delta_pairs' => 24.0,
                    'unit_cost' => 1500.00,
                    'notes' => 'Found 2 extra cartons',
                ],
            ],
        ]);
        $response->assertRedirect(route('inventory.adjustments'));

        $adjustment = StockAdjustment::where('location_id', $this->shop1->id)->firstOrFail();
        $this->assertEquals('draft', $adjustment->status);

        // Shop 1 Manager cannot approve adjustment -> 403 Forbidden
        $responseUnauthApprove = $this->actingAs($this->shop1Manager)
            ->post(route('inventory.adjustments.approve', $adjustment));
        $responseUnauthApprove->assertStatus(403);

        // Owner approves adjustment -> Success
        $responseApprove = $this->actingAs($this->owner)
            ->post(route('inventory.adjustments.approve', $adjustment));
        $responseApprove->assertRedirect(route('inventory.adjustments'));

        $adjustment->refresh();
        $this->assertEquals('approved', $adjustment->status);

        // Stock balance must now be 24 pairs
        $balance = StockBalance::where('location_id', $this->shop1->id)
            ->where('product_variant_id', $variant->id)
            ->firstOrFail();
        $this->assertEquals(24.0, (float) $balance->pairs_quantity);
    }
}
