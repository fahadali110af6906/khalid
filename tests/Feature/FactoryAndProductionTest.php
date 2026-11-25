<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\ProductionCompletion;
use App\Models\ProductionMaterialIssue;
use App\Models\ProductionOrder;
use App\Models\Recipe;
use App\Models\Role;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\Production\ProductionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class FactoryAndProductionTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $factory1Manager;
    protected User $factory2Manager;
    protected Location $factory1;
    protected Location $factory2;
    protected Location $shop1;

    protected UnitOfMeasure $pairUnit;
    protected UnitOfMeasure $meterUnit;
    protected UnitOfMeasure $kgUnit;

    protected ProductCategory $finishedCategory;
    protected ProductCategory $rawCategory;

    protected Product $leatherShoe;
    protected ProductVariant $leatherShoeVariant;

    protected Product $rawLeather;
    protected ProductVariant $rawLeatherVariant;

    protected Product $rawSole;
    protected ProductVariant $rawSoleVariant;

    protected Product $rawGlue;
    protected ProductVariant $rawGlueVariant;

    protected Recipe $recipe;
    protected ProductionService $productionService;
    protected InventoryService $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->productionService = app(ProductionService::class);
        $this->inventoryService = app(InventoryService::class);

        // Core Locations
        $this->factory1 = Location::where('code', 'LOC-FAC01')->firstOrFail();
        $this->factory2 = Location::where('code', 'LOC-FAC02')->firstOrFail();
        $this->shop1 = Location::where('code', 'LOC-SH01')->firstOrFail();

        // Core Users
        $this->owner = User::where('email', 'khalid.owner@khalidtraders.pk')->firstOrFail();
        $this->factory1Manager = User::where('email', 'aslam.factory1@khalidtraders.pk')->firstOrFail();

        $factoryManagerRole = Role::where('name', 'factory_manager')->firstOrFail();
        $this->factory2Manager = User::firstOrCreate(['email' => 'bashir.factory2@khalidtraders.pk'], [
            'name' => 'Bashir Ahmad (Factory 2)',
            'phone' => '+92 305 0000000',
            'password' => bcrypt('password'),
            'is_active' => true,
            'default_location_id' => $this->factory2->id,
        ]);
        $this->factory2Manager->assignRole($factoryManagerRole);
        $this->factory2Manager->locations()->sync([
            $this->factory2->id => ['is_primary' => true, 'can_view' => true, 'can_operate' => true],
        ]);

        // UOMs
        $this->pairUnit = UnitOfMeasure::firstOrCreate(['code' => 'PAIR'], ['name' => 'Pair', 'type' => 'unit', 'allow_decimals' => false]);
        $this->meterUnit = UnitOfMeasure::firstOrCreate(['code' => 'SQM'], ['name' => 'Square Meters', 'type' => 'length', 'allow_decimals' => true]);
        $this->kgUnit = UnitOfMeasure::firstOrCreate(['code' => 'KG'], ['name' => 'Kilograms', 'type' => 'weight', 'allow_decimals' => true]);

        // Categories
        $this->finishedCategory = ProductCategory::firstOrCreate(['code' => 'FF'], ['name' => 'Finished Footwear', 'type' => 'finished_good']);
        $this->rawCategory = ProductCategory::firstOrCreate(['code' => 'RM'], ['name' => 'Raw Materials', 'type' => 'raw_material']);

        // Finished Good Product & Variant
        $this->leatherShoe = Product::create([
            'category_id' => $this->finishedCategory->id,
            'base_unit_id' => $this->pairUnit->id,
            'name' => 'Classic Leather Oxford',
            'article_code' => 'CL-101',
            'is_active' => true,
        ]);
        $this->leatherShoeVariant = ProductVariant::create([
            'product_id' => $this->leatherShoe->id,
            'sku' => 'CL-101-BLK-42',
            'size' => '42',
            'color' => 'Black',
            'purchase_cost' => 0.00,
            'wholesale_price' => 2500.00,
            'retail_price' => 3000.00,
            'pairs_per_carton' => 12,
            'is_active' => true,
        ]);

        // Raw Materials
        $this->rawLeather = Product::create([
            'category_id' => $this->rawCategory->id,
            'base_unit_id' => $this->meterUnit->id,
            'name' => 'Cow Leather Upper Grade A',
            'article_code' => 'RAW-LEA-01',
            'is_active' => true,
        ]);
        $this->rawLeatherVariant = ProductVariant::create([
            'product_id' => $this->rawLeather->id,
            'sku' => 'RAW-LEA-01-BLK',
            'size' => 'Std',
            'color' => 'Black',
            'purchase_cost' => 800.00, // Rs. 800 per sq meter
            'wholesale_price' => 800.00,
            'retail_price' => 800.00,
            'pairs_per_carton' => 1,
            'is_active' => true,
        ]);

        $this->rawSole = Product::create([
            'category_id' => $this->rawCategory->id,
            'base_unit_id' => $this->pairUnit->id,
            'name' => 'PU Molded Shoe Sole 42',
            'article_code' => 'RAW-SOL-42',
            'is_active' => true,
        ]);
        $this->rawSoleVariant = ProductVariant::create([
            'product_id' => $this->rawSole->id,
            'sku' => 'RAW-SOL-42-BLK',
            'size' => '42',
            'color' => 'Black',
            'purchase_cost' => 350.00, // Rs. 350 per pair
            'wholesale_price' => 350.00,
            'retail_price' => 350.00,
            'pairs_per_carton' => 1,
            'is_active' => true,
        ]);

        $this->rawGlue = Product::create([
            'category_id' => $this->rawCategory->id,
            'base_unit_id' => $this->kgUnit->id,
            'name' => 'Industrial Bonding Glue',
            'article_code' => 'RAW-GLU-01',
            'is_active' => true,
        ]);
        $this->rawGlueVariant = ProductVariant::create([
            'product_id' => $this->rawGlue->id,
            'sku' => 'RAW-GLU-01-STD',
            'size' => 'Std',
            'color' => 'Clear',
            'purchase_cost' => 500.00, // Rs. 500 per kg
            'wholesale_price' => 500.00,
            'retail_price' => 500.00,
            'pairs_per_carton' => 1,
            'is_active' => true,
        ]);

        // Create standard BOM Recipe for Classic Leather Oxford
        $this->recipe = $this->productionService->createRecipe([
            'name' => 'Classic Oxford Leather BOM Standard',
            'product_id' => $this->leatherShoe->id,
            'product_variant_id' => $this->leatherShoeVariant->id,
            'version' => '1.0',
            'yield_pairs' => 1,
            'items' => [
                [
                    'raw_material_variant_id' => $this->rawLeatherVariant->id,
                    'quantity_per_pair' => 0.2500, // 0.25 sq meter leather
                    'wastage_percentage' => 4.00, // 4% cutting wastage
                ],
                [
                    'raw_material_variant_id' => $this->rawSoleVariant->id,
                    'quantity_per_pair' => 1.0000, // 1 pair sole
                    'wastage_percentage' => 0.00,
                ],
                [
                    'raw_material_variant_id' => $this->rawGlueVariant->id,
                    'quantity_per_pair' => 0.0500, // 0.05 kg glue
                    'wastage_percentage' => 5.00, // 5% wastage
                ],
            ],
        ], $this->owner);

        // Pre-seed Raw Material Stock at Factory 1
        $this->inventoryService->addStock(
            locationId: $this->factory1->id,
            variantId: $this->rawLeatherVariant->id,
            quantityPairs: 100.0,
            unitCost: 800.00,
            movementType: 'opening_balance',
            user: $this->owner,
            referenceType: 'initial_balance',
            notes: 'RAW-INIT-01'
        );
        $this->inventoryService->addStock(
            locationId: $this->factory1->id,
            variantId: $this->rawSoleVariant->id,
            quantityPairs: 500.0,
            unitCost: 350.00,
            movementType: 'opening_balance',
            user: $this->owner,
            referenceType: 'initial_balance',
            notes: 'RAW-INIT-02'
        );
        $this->inventoryService->addStock(
            locationId: $this->factory1->id,
            variantId: $this->rawGlueVariant->id,
            quantityPairs: 50.0,
            unitCost: 500.00,
            movementType: 'opening_balance',
            user: $this->owner,
            referenceType: 'initial_balance',
            notes: 'RAW-INIT-03'
        );
    }

    /**
     * Test 1: Recipe / BOM creation and validation.
     */
    public function test_recipe_bom_creation_and_validation(): void
    {
        $this->assertDatabaseHas('recipes', [
            'id' => $this->recipe->id,
            'name' => 'Classic Oxford Leather BOM Standard',
            'version' => '1.0',
            'product_id' => $this->leatherShoe->id,
            'is_active' => 1,
        ]);

        $this->assertCount(3, $this->recipe->items);

        // Leather gross qty = 0.25 * (1 + 0.04) = 0.2600
        $leatherItem = $this->recipe->items->where('raw_material_variant_id', $this->rawLeatherVariant->id)->first();
        $this->assertEquals(0.2600, $leatherItem->gross_quantity_per_pair);

        // Cannot create recipe without items
        $this->expectException(InvalidArgumentException::class);
        $this->productionService->createRecipe([
            'name' => 'Empty Recipe',
            'product_id' => $this->leatherShoe->id,
            'items' => [],
        ], $this->owner);
    }

    /**
     * Test 2: Production order lifecycle (draft -> planned -> in_progress).
     * Draft and planned orders must have zero stock effect.
     */
    public function test_production_order_lifecycle_zero_stock_effect_on_draft_or_planned(): void
    {
        // Initial stock of finished goods should be 0
        $initialBalance = StockBalance::where('product_variant_id', $this->leatherShoeVariant->id)
            ->where('location_id', $this->factory1->id)
            ->first();
        $this->assertNull($initialBalance);

        // Create Draft Order for 100 pairs
        $order = $this->productionService->createOrder([
            'factory_location_id' => $this->factory1->id,
            'product_id' => $this->leatherShoe->id,
            'product_variant_id' => $this->leatherShoeVariant->id,
            'recipe_id' => $this->recipe->id,
            'planned_quantity_pairs' => 100,
            'start_date' => now()->toDateString(),
            'status' => 'draft',
        ], $this->owner);

        $this->assertEquals('draft', $order->status);
        $this->assertStringStartsWith('PRD-LOC-FAC01-', $order->order_number);

        // Check that finished goods stock is still 0
        $balanceAfterDraft = StockBalance::where('product_variant_id', $this->leatherShoeVariant->id)
            ->where('location_id', $this->factory1->id)
            ->first();
        $this->assertNull($balanceAfterDraft);

        // Start Order (moves to in_progress)
        $this->productionService->startOrder($order, $this->owner);
        $order->refresh();
        $this->assertEquals('in_progress', $order->status);

        // Still no finished stock generated
        $balanceAfterStart = StockBalance::where('product_variant_id', $this->leatherShoeVariant->id)
            ->where('location_id', $this->factory1->id)
            ->first();
        $this->assertNull($balanceAfterStart);
    }

    /**
     * Test 3: Raw Material Issuance deducts inventory and accumulates actual material cost.
     */
    public function test_material_issuance_deducts_stock_and_logs_movement(): void
    {
        $order = $this->productionService->createOrder([
            'factory_location_id' => $this->factory1->id,
            'product_id' => $this->leatherShoe->id,
            'product_variant_id' => $this->leatherShoeVariant->id,
            'recipe_id' => $this->recipe->id,
            'planned_quantity_pairs' => 100,
            'start_date' => now()->toDateString(),
            'status' => 'planned',
        ], $this->factory1Manager);

        $this->productionService->startOrder($order, $this->factory1Manager);

        // Issue 26 sq meters leather (cost = 26 * 800 = 20,800) and 100 soles (cost = 100 * 350 = 35,000)
        $issue = $this->productionService->issueMaterials($order, [
            [
                'raw_material_variant_id' => $this->rawLeatherVariant->id,
                'quantity' => 26.0,
            ],
            [
                'raw_material_variant_id' => $this->rawSoleVariant->id,
                'quantity' => 100.0,
            ],
        ], $this->factory1Manager, 'Initial cutting issue');

        $this->assertEquals('issue', $issue->type);
        $this->assertEquals(55800.00, (float) $issue->total_cost);

        // Check order actual_material_cost
        $order->refresh();
        $this->assertEquals(55800.00, (float) $order->actual_material_cost);

        // Check raw material stock deduction (100 - 26 = 74 leather remaining)
        $leatherBal = StockBalance::where('product_variant_id', $this->rawLeatherVariant->id)
            ->where('location_id', $this->factory1->id)
            ->first();
        $this->assertEquals(74.0, (float) $leatherBal->pairs_quantity);

        // Check StockMovement logged
        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $this->rawLeatherVariant->id,
            'location_id' => $this->factory1->id,
            'movement_type' => 'production_consume',
            'quantity_delta_pairs' => -26.0,
            'reference_type' => ProductionMaterialIssue::class,
            'reference_id' => $issue->id,
        ]);
    }

    /**
     * Test 4: Insufficient stock prevents material issuance.
     */
    public function test_insufficient_stock_prevents_material_issuance(): void
    {
        $order = $this->productionService->createOrder([
            'factory_location_id' => $this->factory1->id,
            'product_id' => $this->leatherShoe->id,
            'product_variant_id' => $this->leatherShoeVariant->id,
            'recipe_id' => $this->recipe->id,
            'planned_quantity_pairs' => 100,
            'start_date' => now()->toDateString(),
            'status' => 'planned',
        ], $this->owner);

        // Try to issue 9999 sq meters of leather when only 100 is available
        $this->expectException(\App\Exceptions\InsufficientStockException::class);
        $this->productionService->issueMaterials($order, [
            [
                'raw_material_variant_id' => $this->rawLeatherVariant->id,
                'quantity' => 9999.0,
            ],
        ], $this->owner);
    }

    /**
     * Test 5: Unused material return restores stock and reduces actual material cost.
     */
    public function test_material_return_restores_stock_and_reduces_cost(): void
    {
        $order = $this->productionService->createOrder([
            'factory_location_id' => $this->factory1->id,
            'product_id' => $this->leatherShoe->id,
            'product_variant_id' => $this->leatherShoeVariant->id,
            'recipe_id' => $this->recipe->id,
            'planned_quantity_pairs' => 100,
            'start_date' => now()->toDateString(),
            'status' => 'planned',
        ], $this->owner);

        // Issue 100 soles (cost 35,000)
        $this->productionService->issueMaterials($order, [
            [
                'raw_material_variant_id' => $this->rawSoleVariant->id,
                'quantity' => 100.0,
            ],
        ], $this->owner);

        $order->refresh();
        $this->assertEquals(35000.00, (float) $order->actual_material_cost);

        // Return 10 unused soles to factory stock (cost 3,500)
        $return = $this->productionService->returnMaterials($order, [
            [
                'raw_material_variant_id' => $this->rawSoleVariant->id,
                'quantity' => 10.0,
            ],
        ], $this->owner, '10 unused soles returned');

        $this->assertEquals('return', $return->type);
        $this->assertEquals(3500.00, (float) $return->total_cost);

        $order->refresh();
        $this->assertEquals(31500.00, (float) $order->actual_material_cost);

        // Soles stock was 500 - 100 + 10 = 410
        $soleBal = StockBalance::where('product_variant_id', $this->rawSoleVariant->id)
            ->where('location_id', $this->factory1->id)
            ->first();
        $this->assertEquals(410.0, (float) $soleBal->pairs_quantity);

        // Check StockMovement logged
        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $this->rawSoleVariant->id,
            'location_id' => $this->factory1->id,
            'movement_type' => 'adjustment',
            'quantity_delta_pairs' => 10.0,
            'reference_type' => ProductionMaterialIssue::class,
            'reference_id' => $return->id,
        ]);
    }

    /**
     * Test 6: Finished goods completion, unit costing formula, and defect/wastage exclusion.
     *
     * Formula:
     * Unit Cost = (Allocated Material Cost + Labor Cost + Other Costs) / Good Finished Pairs
     *
     * Good pairs are added to inventory. Wastage pairs are tracked for scrap audit and NEVER added to inventory.
     */
    public function test_finished_goods_completion_unit_costing_and_wastage_exclusion(): void
    {
        $order = $this->productionService->createOrder([
            'factory_location_id' => $this->factory1->id,
            'product_id' => $this->leatherShoe->id,
            'product_variant_id' => $this->leatherShoeVariant->id,
            'recipe_id' => $this->recipe->id,
            'planned_quantity_pairs' => 100,
            'start_date' => now()->toDateString(),
            'status' => 'planned',
        ], $this->owner);

        // Issue materials: 26 sq meters leather (Rs. 20,800), 100 soles (Rs. 35,000), 5.25 kg glue (Rs. 2,625)
        // Total material cost = 20,800 + 35,000 + 2,625 = 58,425
        $this->productionService->issueMaterials($order, [
            ['raw_material_variant_id' => $this->rawLeatherVariant->id, 'quantity' => 26.0],
            ['raw_material_variant_id' => $this->rawSoleVariant->id, 'quantity' => 100.0],
            ['raw_material_variant_id' => $this->rawGlueVariant->id, 'quantity' => 5.25],
        ], $this->owner);

        $order->refresh();
        $this->assertEquals(58425.00, (float) $order->actual_material_cost);

        // Complete the order:
        // 95 Good Pairs finished
        // 5 Wastage / Scrap Pairs (defect in upper stitching)
        // Labor Cost: Rs. 15,000 (Rs. 150/pair stitcher wage)
        // Other Overhead Costs: Rs. 3,500 (Electricity & machine amortization)
        // Total Batch Cost = 58,425 + 15,000 + 3,500 = 76,925
        // Unit Cost per good pair = 76,925 / 95 = 809.7368 (Rs. 809.74)
        $completion = $this->productionService->recordCompletion($order, [
            'good_pairs' => 95,
            'wastage_pairs' => 5,
            'labor_cost' => 15000.00,
            'other_costs' => 3500.00,
            'completion_date' => now()->toDateString(),
            'mark_completed' => true,
            'idempotency_key' => (string) Str::uuid(),
        ], $this->owner);

        $this->assertEquals(95.0, (float) $completion->good_pairs);
        $this->assertEquals(5.0, (float) $completion->wastage_pairs);
        $this->assertEquals(76925.00, (float) $completion->total_batch_cost);
        $this->assertEquals(809.7368, (float) $completion->unit_cost_per_pair);

        // Verify Order status is completed
        $order->refresh();
        $this->assertEquals('completed', $order->status);
        $this->assertEquals(95.0, (float) $order->completed_good_pairs);
        $this->assertEquals(5.0, (float) $order->wastage_pairs);
        $this->assertEquals(76925.00, (float) $order->total_production_cost);
        $this->assertEquals(809.7368, (float) $order->unit_cost_per_pair);

        // Verify Finished Goods Stock: Exactly 95 pairs added to Factory 1 (NOT 100!)
        $finishedBal = StockBalance::where('product_variant_id', $this->leatherShoeVariant->id)
            ->where('location_id', $this->factory1->id)
            ->first();
        $this->assertNotNull($finishedBal);
        $this->assertEquals(95.0, (float) $finishedBal->pairs_quantity);
        // Cost per pair recorded on finished stock
        $this->assertEquals(809.7368, (float) $finishedBal->avg_unit_cost);

        // Stock movement check
        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $this->leatherShoeVariant->id,
            'location_id' => $this->factory1->id,
            'movement_type' => 'production_output',
            'quantity_delta_pairs' => 95.0,
            'unit_cost' => 809.7368,
        ]);
    }

    /**
     * Test 7: Idempotency protection prevents duplicate production completions.
     */
    public function test_idempotency_prevents_duplicate_finished_goods_completions(): void
    {
        $order = $this->productionService->createOrder([
            'factory_location_id' => $this->factory1->id,
            'product_id' => $this->leatherShoe->id,
            'product_variant_id' => $this->leatherShoeVariant->id,
            'recipe_id' => $this->recipe->id,
            'planned_quantity_pairs' => 50,
            'start_date' => now()->toDateString(),
            'status' => 'planned',
        ], $this->owner);

        $this->productionService->issueMaterials($order, [
            ['raw_material_variant_id' => $this->rawSoleVariant->id, 'quantity' => 50.0],
        ], $this->owner);

        $idempotencyKey = (string) Str::uuid();

        $payload = [
            'good_pairs' => 50,
            'wastage_pairs' => 0,
            'labor_cost' => 5000.00,
            'other_costs' => 1000.00,
            'completion_date' => now()->toDateString(),
            'mark_completed' => true,
            'idempotency_key' => $idempotencyKey,
        ];

        // First call
        $firstCompletion = $this->productionService->recordCompletion($order, $payload, $this->owner);
        $this->assertNotNull($firstCompletion->id);

        // Finished stock is 50
        $finishedBal = StockBalance::where('product_variant_id', $this->leatherShoeVariant->id)
            ->where('location_id', $this->factory1->id)
            ->first();
        $this->assertEquals(50.0, (float) $finishedBal->pairs_quantity);

        // Duplicate submission with same idempotency key
        $secondCompletion = $this->productionService->recordCompletion($order, $payload, $this->owner);
        $this->assertEquals($firstCompletion->id, $secondCompletion->id);

        // Finished stock MUST NOT be doubled (must remain exactly 50)
        $finishedBal->refresh();
        $this->assertEquals(50.0, (float) $finishedBal->pairs_quantity);
    }

    /**
     * Test 8: Order Costing and Variance Calculation.
     */
    public function test_order_costing_and_variance_analysis(): void
    {
        $order = $this->productionService->createOrder([
            'factory_location_id' => $this->factory1->id,
            'product_id' => $this->leatherShoe->id,
            'product_variant_id' => $this->leatherShoeVariant->id,
            'recipe_id' => $this->recipe->id,
            'planned_quantity_pairs' => 100,
            'start_date' => now()->toDateString(),
            'status' => 'planned',
        ], $this->owner);

        $costingBefore = $this->productionService->calculateOrderCosting($order);
        $this->assertGreaterThan(0, (float) $costingBefore['planned_material_cost']);

        // Issue materials and complete
        $this->productionService->issueMaterials($order, [
            ['raw_material_variant_id' => $this->rawLeatherVariant->id, 'quantity' => 30.0], // excess leather used
            ['raw_material_variant_id' => $this->rawSoleVariant->id, 'quantity' => 100.0],
        ], $this->owner);

        $this->productionService->recordCompletion($order, [
            'good_pairs' => 90,
            'wastage_pairs' => 10,
            'labor_cost' => 10000.00,
            'other_costs' => 2000.00,
            'completion_date' => now()->toDateString(),
            'mark_completed' => true,
        ], $this->owner);

        $order->refresh();
        $costingAfter = $this->productionService->calculateOrderCosting($order);

        $this->assertEquals(90.0, (float) $costingAfter['actual_good_pairs']);
        $this->assertEquals(10.0, (float) $costingAfter['actual_wastage_pairs']);
        $this->assertEquals(10.0, (float) $costingAfter['scrap_rate_percentage']); // 10 / (90+10) = 10%
        $this->assertGreaterThan(0, (float) $costingAfter['scrap_cost']);
    }

    /**
     * Test 9: Location Authorization Enforcement.
     * Factory 1 manager cannot access Factory 2 production order.
     */
    public function test_location_authorization_enforcement(): void
    {
        // Order at Factory 2
        $factory2Order = $this->productionService->createOrder([
            'factory_location_id' => $this->factory2->id,
            'product_id' => $this->leatherShoe->id,
            'product_variant_id' => $this->leatherShoeVariant->id,
            'recipe_id' => $this->recipe->id,
            'planned_quantity_pairs' => 50,
            'start_date' => now()->toDateString(),
            'status' => 'planned',
        ], $this->owner);

        // Factory 1 manager attempting to view Factory 2 order via web route gets 403 Forbidden
        $response = $this->actingAs($this->factory1Manager)->get(route('production.orders.show', $factory2Order));
        $response->assertStatus(403);

        // Factory 2 manager can view it
        $allowedResponse = $this->actingAs($this->factory2Manager)->get(route('production.orders.show', $factory2Order));
        $allowedResponse->assertStatus(200);

        // Owner can view it
        $ownerResponse = $this->actingAs($this->owner)->get(route('production.orders.show', $factory2Order));
        $ownerResponse->assertStatus(200);
    }

    /**
     * Test 10: Production Summary Reporting and CSV Export.
     */
    public function test_production_reports_and_csv_export(): void
    {
        // Create and complete an order
        $order = $this->productionService->createOrder([
            'factory_location_id' => $this->factory1->id,
            'product_id' => $this->leatherShoe->id,
            'product_variant_id' => $this->leatherShoeVariant->id,
            'recipe_id' => $this->recipe->id,
            'planned_quantity_pairs' => 100,
            'start_date' => now()->toDateString(),
            'status' => 'planned',
        ], $this->owner);

        $this->productionService->issueMaterials($order, [
            ['raw_material_variant_id' => $this->rawSoleVariant->id, 'quantity' => 100.0],
        ], $this->owner);

        $this->productionService->recordCompletion($order, [
            'good_pairs' => 95,
            'wastage_pairs' => 5,
            'labor_cost' => 10000.00,
            'other_costs' => 2000.00,
            'completion_date' => now()->toDateString(),
            'mark_completed' => true,
        ], $this->owner);

        // Test web report page
        $response = $this->actingAs($this->owner)->get(route('production.reports.index'));
        $response->assertStatus(200);
        $response->assertSee('Production Analytics');

        // Test CSV Export
        $csvResponse = $this->actingAs($this->owner)->get(route('production.reports.export'));
        $csvResponse->assertStatus(200);
        $csvResponse->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}
