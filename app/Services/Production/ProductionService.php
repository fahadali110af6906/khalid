<?php

namespace App\Services\Production;

use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductionCompletion;
use App\Models\ProductionMaterialIssue;
use App\Models\ProductionMaterialIssueItem;
use App\Models\ProductionOrder;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\StockBalance;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductionService
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Create a new Bill of Materials (BOM) / Recipe.
     */
    public function createRecipe(array $data, User $user): Recipe
    {
        $productId = (int) $data['product_id'];
        $product = Product::findOrFail($productId);

        $variantId = !empty($data['product_variant_id']) ? (int) $data['product_variant_id'] : null;
        if ($variantId) {
            $variant = ProductVariant::where('product_id', $product->id)->findOrFail($variantId);
        }

        if (empty($data['items']) || !is_array($data['items'])) {
            throw new InvalidArgumentException("A recipe must have at least one raw material component.");
        }

        return DB::transaction(function () use ($product, $variantId, $data, $user) {
            $recipeNumber = $this->generateRecipeNumber();
            $yieldPairs = !empty($data['yield_pairs']) ? (float) $data['yield_pairs'] : 1.0000;

            if ($yieldPairs <= 0) {
                throw new InvalidArgumentException("Yield pairs must be greater than zero.");
            }

            $recipe = Recipe::create([
                'recipe_number' => $recipeNumber,
                'product_id' => $product->id,
                'product_variant_id' => $variantId,
                'name' => trim((string) $data['name']),
                'version' => trim((string) ($data['version'] ?? '1.0')),
                'yield_pairs' => $yieldPairs,
                'notes' => isset($data['notes']) ? trim((string) $data['notes']) : null,
                'is_active' => true,
                'created_by' => $user->id,
            ]);

            foreach ($data['items'] as $itemInput) {
                $matVariantId = (int) $itemInput['raw_material_variant_id'];
                $matVariant = ProductVariant::with('product.baseUnit')->findOrFail($matVariantId);

                $qty = (float) $itemInput['quantity_per_pair'];
                if ($qty <= 0) {
                    throw new InvalidArgumentException("Quantity per pair must be greater than zero for material {$matVariant->sku}.");
                }

                $uomId = (int) ($itemInput['unit_of_measure_id'] ?? $matVariant->product->base_unit_id);
                $wastage = isset($itemInput['wastage_percentage']) ? (float) $itemInput['wastage_percentage'] : 0.00;

                RecipeItem::create([
                    'recipe_id' => $recipe->id,
                    'raw_material_variant_id' => $matVariantId,
                    'quantity_per_pair' => $qty,
                    'unit_of_measure_id' => $uomId,
                    'wastage_percentage' => $wastage,
                    'notes' => isset($itemInput['notes']) ? trim((string) $itemInput['notes']) : null,
                ]);
            }

            return $recipe->fresh(['items.rawMaterialVariant.product.baseUnit', 'product', 'productVariant']);
        });
    }

    /**
     * Update an existing recipe and its BOM line items.
     */
    public function updateRecipe(Recipe $recipe, array $data, User $user): Recipe
    {
        return DB::transaction(function () use ($recipe, $data, $user) {
            $recipe->name = trim((string) ($data['name'] ?? $recipe->name));
            $recipe->version = trim((string) ($data['version'] ?? $recipe->version));
            if (isset($data['yield_pairs'])) {
                $yield = (float) $data['yield_pairs'];
                if ($yield <= 0) {
                    throw new InvalidArgumentException("Yield pairs must be greater than zero.");
                }
                $recipe->yield_pairs = $yield;
            }
            if (isset($data['notes'])) {
                $recipe->notes = trim((string) $data['notes']);
            }
            if (isset($data['is_active'])) {
                $recipe->is_active = (bool) $data['is_active'];
            }
            $recipe->updated_by = $user->id;
            $recipe->save();

            if (isset($data['items']) && is_array($data['items'])) {
                $recipe->items()->delete();

                foreach ($data['items'] as $itemInput) {
                    $matVariantId = (int) $itemInput['raw_material_variant_id'];
                    $matVariant = ProductVariant::with('product.baseUnit')->findOrFail($matVariantId);

                    $qty = (float) $itemInput['quantity_per_pair'];
                    if ($qty <= 0) {
                        throw new InvalidArgumentException("Quantity per pair must be greater than zero for material {$matVariant->sku}.");
                    }

                    $uomId = (int) ($itemInput['unit_of_measure_id'] ?? $matVariant->product->base_unit_id);
                    $wastage = isset($itemInput['wastage_percentage']) ? (float) $itemInput['wastage_percentage'] : 0.00;

                    RecipeItem::create([
                        'recipe_id' => $recipe->id,
                        'raw_material_variant_id' => $matVariantId,
                        'quantity_per_pair' => $qty,
                        'unit_of_measure_id' => $uomId,
                        'wastage_percentage' => $wastage,
                        'notes' => isset($itemInput['notes']) ? trim((string) $itemInput['notes']) : null,
                    ]);
                }
            }

            return $recipe->fresh(['items.rawMaterialVariant.product.baseUnit', 'product', 'productVariant']);
        });
    }

    /**
     * Create a new Production Order in draft or planned status.
     * Draft/planned orders do NOT consume inventory or produce finished goods.
     */
    public function createOrder(array $data, User $user): ProductionOrder
    {
        $locationId = (int) $data['factory_location_id'];
        $location = Location::findOrFail($locationId);

        if (!$user->hasLocationAccess($locationId, true)) {
            throw new AuthorizationException("User #{$user->id} is not authorized to create production orders for location #{$locationId}.");
        }

        $recipeId = (int) $data['recipe_id'];
        $recipe = Recipe::with('items.rawMaterialVariant')->findOrFail($recipeId);

        if (!$recipe->is_active) {
            throw new InvalidArgumentException("Recipe #{$recipe->recipe_number} is deactivated and cannot be used for new production orders.");
        }

        $productId = (int) ($data['product_id'] ?? $recipe->product_id);
        $variantId = (int) ($data['product_variant_id'] ?? $recipe->product_variant_id);

        if (!$variantId) {
            throw new InvalidArgumentException("A specific finished product variant (size & color) must be specified for the production order.");
        }

        $variant = ProductVariant::where('product_id', $productId)->findOrFail($variantId);

        $plannedQuantity = (float) $data['planned_quantity_pairs'];
        if ($plannedQuantity <= 0) {
            throw new InvalidArgumentException("Planned production quantity must be greater than zero.");
        }

        return DB::transaction(function () use ($location, $productId, $variant, $recipe, $plannedQuantity, $data, $user) {
            $orderNumber = $this->generateOrderNumber($location);
            $startDate = $data['start_date'] ?? now()->toDateString();
            $dueDate = !empty($data['due_date']) ? Carbon::parse($data['due_date'])->toDateString() : null;
            $status = in_array($data['status'] ?? 'planned', ['draft', 'planned'], true) ? ($data['status'] ?? 'planned') : 'planned';

            // Calculate planned material cost based on BOM component costs
            $plannedMaterialCost = '0.00';
            foreach ($recipe->items as $item) {
                $grossQtyPerPair = $item->gross_quantity_per_pair;
                $lineTotalQty = bcmul((string) $plannedQuantity, (string) $grossQtyPerPair, 4);
                $unitCost = (string) $item->rawMaterialVariant->purchase_cost;
                $lineCost = bcmul($lineTotalQty, $unitCost, 2);
                $plannedMaterialCost = bcadd($plannedMaterialCost, $lineCost, 2);
            }

            $order = ProductionOrder::create([
                'order_number' => $orderNumber,
                'factory_location_id' => $location->id,
                'product_id' => $productId,
                'product_variant_id' => $variant->id,
                'recipe_id' => $recipe->id,
                'planned_quantity_pairs' => $plannedQuantity,
                'completed_good_pairs' => 0.0000,
                'wastage_pairs' => 0.0000,
                'start_date' => $startDate,
                'due_date' => $dueDate,
                'status' => $status,
                'planned_material_cost' => $plannedMaterialCost,
                'actual_material_cost' => 0.00,
                'labor_cost' => 0.00,
                'other_costs' => 0.00,
                'total_production_cost' => 0.00,
                'unit_cost_per_pair' => 0.0000,
                'notes' => isset($data['notes']) ? trim((string) $data['notes']) : null,
                'created_by' => $user->id,
            ]);

            return $order->fresh(['factoryLocation', 'product', 'productVariant', 'recipe.items.rawMaterialVariant']);
        });
    }

    /**
     * Transition a production order from planned/draft to in_progress.
     */
    public function startOrder(ProductionOrder $order, User $user): ProductionOrder
    {
        if (!$user->hasLocationAccess($order->factory_location_id, true)) {
            throw new AuthorizationException("User #{$user->id} is not authorized for location #{$order->factory_location_id}.");
        }

        if (!in_array($order->status, ['draft', 'planned'], true)) {
            throw new RuntimeException("Cannot start production order #{$order->order_number} with status '{$order->status}'.");
        }

        $order->status = 'in_progress';
        $order->started_at = now();
        $order->save();

        return $order->fresh();
    }

    /**
     * Cancel a production order.
     */
    public function cancelOrder(ProductionOrder $order, string $reason, User $user): ProductionOrder
    {
        if (!$user->hasLocationAccess($order->factory_location_id, true)) {
            throw new AuthorizationException("User #{$user->id} is not authorized for location #{$order->factory_location_id}.");
        }

        if ($order->status === 'completed') {
            throw new RuntimeException("Completed production order #{$order->order_number} cannot be cancelled.");
        }

        if ($order->status === 'cancelled') {
            return $order;
        }

        // Verify if any materials were issued without being returned
        if (bccomp((string) $order->actual_material_cost, '0.00', 2) > 0) {
            throw new RuntimeException("Cannot cancel order #{$order->order_number} while issued raw materials remain unreturned (Actual Material Cost: PKR {$order->actual_material_cost}). Return materials to stock first.");
        }

        $order->status = 'cancelled';
        $order->cancellation_reason = trim($reason);
        $order->save();

        return $order->fresh();
    }

    /**
     * Issue raw materials to a production order.
     * Deducts stock exactly once via InventoryService and records audited StockMovement.
     */
    public function issueMaterials(ProductionOrder $order, array $itemsData, User $user, ?string $notes = null): ProductionMaterialIssue
    {
        if (!$user->hasLocationAccess($order->factory_location_id, true)) {
            throw new AuthorizationException("User #{$user->id} is not authorized for location #{$order->factory_location_id}.");
        }

        if ($order->status === 'completed' || $order->status === 'cancelled') {
            throw new RuntimeException("Cannot issue materials to an order with status '{$order->status}'.");
        }

        if (empty($itemsData)) {
            throw new InvalidArgumentException("At least one material item must be provided for issuance.");
        }

        return DB::transaction(function () use ($order, $itemsData, $user, $notes) {
            $lockedOrder = ProductionOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->isPlanned() || $lockedOrder->isDraft()) {
                $lockedOrder->status = 'in_progress';
                $lockedOrder->started_at = now();
                $lockedOrder->save();
            }

            $issueNumber = $this->generateIssueNumber($lockedOrder->factoryLocation, 'issue');

            $issue = ProductionMaterialIssue::create([
                'issue_number' => $issueNumber,
                'production_order_id' => $lockedOrder->id,
                'location_id' => $lockedOrder->factory_location_id,
                'issue_date' => now()->toDateString(),
                'type' => 'issue',
                'total_material_cost' => 0.00,
                'notes' => $notes ? trim($notes) : null,
                'created_by' => $user->id,
            ]);

            // Sort by variant ID ASC to prevent deadlocks
            $sortedItems = collect($itemsData)->sortBy(fn ($item) => (int) $item['raw_material_variant_id']);

            $totalIssueCost = '0.00';

            foreach ($sortedItems as $item) {
                $matVariantId = (int) $item['raw_material_variant_id'];
                $qty = (float) $item['quantity'];

                if ($qty <= 0) {
                    continue;
                }

                // Check current WAC unit cost at this factory location
                $balance = StockBalance::where('location_id', $lockedOrder->factory_location_id)
                    ->where('product_variant_id', $matVariantId)
                    ->lockForUpdate()
                    ->first();

                $unitCost = $balance && (float) $balance->avg_unit_cost > 0
                    ? (float) $balance->avg_unit_cost
                    : (float) ProductVariant::findOrFail($matVariantId)->purchase_cost;

                $lineTotalCost = (string) bcmul((string) $qty, (string) $unitCost, 2);

                // Deduct physical raw material stock
                $movement = $this->inventoryService->deductStock(
                    locationId: $lockedOrder->factory_location_id,
                    variantId: $matVariantId,
                    quantityPairs: $qty,
                    movementType: 'production_consume',
                    user: $user,
                    referenceType: ProductionMaterialIssue::class,
                    referenceId: $issue->id,
                    notes: "Raw material issue for Production Order #{$lockedOrder->order_number}"
                );

                ProductionMaterialIssueItem::create([
                    'production_material_issue_id' => $issue->id,
                    'raw_material_variant_id' => $matVariantId,
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'total_cost' => $lineTotalCost,
                    'stock_movement_id' => $movement->id,
                    'notes' => isset($item['notes']) ? trim((string) $item['notes']) : null,
                ]);

                $totalIssueCost = bcadd($totalIssueCost, $lineTotalCost, 2);
            }

            $issue->total_material_cost = $totalIssueCost;
            $issue->save();

            // Update order actual material cost
            $lockedOrder->actual_material_cost = bcadd((string) $lockedOrder->actual_material_cost, $totalIssueCost, 2);
            $lockedOrder->total_production_cost = bcadd(bcadd((string) $lockedOrder->actual_material_cost, (string) $lockedOrder->labor_cost, 2), (string) $lockedOrder->other_costs, 2);
            $lockedOrder->save();

            return $issue->fresh(['items.rawMaterialVariant.product.baseUnit']);
        });
    }

    /**
     * Return unused materials from a production order back into factory inventory.
     * Restores stock via InventoryService and reduces actual material cost.
     */
    public function returnMaterials(ProductionOrder $order, array $itemsData, User $user, ?string $notes = null): ProductionMaterialIssue
    {
        if (!$user->hasLocationAccess($order->factory_location_id, true)) {
            throw new AuthorizationException("User #{$user->id} is not authorized for location #{$order->factory_location_id}.");
        }

        if (empty($itemsData)) {
            throw new InvalidArgumentException("At least one material item must be provided for return.");
        }

        return DB::transaction(function () use ($order, $itemsData, $user, $notes) {
            $lockedOrder = ProductionOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            $returnNumber = $this->generateIssueNumber($lockedOrder->factoryLocation, 'return');

            $returnRecord = ProductionMaterialIssue::create([
                'issue_number' => $returnNumber,
                'production_order_id' => $lockedOrder->id,
                'location_id' => $lockedOrder->factory_location_id,
                'issue_date' => now()->toDateString(),
                'type' => 'return',
                'total_material_cost' => 0.00,
                'notes' => $notes ? trim($notes) : null,
                'created_by' => $user->id,
            ]);

            $totalReturnCost = '0.00';

            foreach ($itemsData as $item) {
                $matVariantId = (int) $item['raw_material_variant_id'];
                $qty = (float) $item['quantity'];

                if ($qty <= 0) {
                    continue;
                }

                // Find original issued unit cost if possible, or fallback to current WAC
                $lastIssueItem = ProductionMaterialIssueItem::whereHas('materialIssue', function ($q) use ($lockedOrder) {
                    $q->where('production_order_id', $lockedOrder->id)->where('type', 'issue');
                })->where('raw_material_variant_id', $matVariantId)->latest('id')->first();

                $unitCost = $lastIssueItem ? (float) $lastIssueItem->unit_cost : (float) ProductVariant::findOrFail($matVariantId)->purchase_cost;
                $lineTotalCost = (string) bcmul((string) $qty, (string) $unitCost, 2);

                // Add stock back into warehouse
                $movement = $this->inventoryService->addStock(
                    locationId: $lockedOrder->factory_location_id,
                    variantId: $matVariantId,
                    quantityPairs: $qty,
                    unitCost: $unitCost,
                    movementType: 'adjustment',
                    user: $user,
                    referenceType: ProductionMaterialIssue::class,
                    referenceId: $returnRecord->id,
                    notes: "Unused material return from Production Order #{$lockedOrder->order_number}"
                );

                ProductionMaterialIssueItem::create([
                    'production_material_issue_id' => $returnRecord->id,
                    'raw_material_variant_id' => $matVariantId,
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'total_cost' => $lineTotalCost,
                    'stock_movement_id' => $movement->id,
                    'notes' => isset($item['notes']) ? trim((string) $item['notes']) : null,
                ]);

                $totalReturnCost = bcadd($totalReturnCost, $lineTotalCost, 2);
            }

            $returnRecord->total_material_cost = $totalReturnCost;
            $returnRecord->save();

            // Deduct return value from actual material cost
            $newMaterialCost = bcsub((string) $lockedOrder->actual_material_cost, $totalReturnCost, 2);
            $lockedOrder->actual_material_cost = bccomp($newMaterialCost, '0.00', 2) > 0 ? $newMaterialCost : '0.00';
            $lockedOrder->total_production_cost = bcadd(bcadd((string) $lockedOrder->actual_material_cost, (string) $lockedOrder->labor_cost, 2), (string) $lockedOrder->other_costs, 2);
            $lockedOrder->save();

            return $returnRecord->fresh(['items.rawMaterialVariant.product.baseUnit']);
        });
    }

    /**
     * Record a partial or full finished goods completion for a production order.
     * Adds finished goods stock to inventory, updates WAC, records labor/overhead,
     * and accounts for defective scrap wastage separately.
     */
    public function recordCompletion(ProductionOrder $order, array $data, User $user): ProductionCompletion
    {
        if (!$user->hasLocationAccess($order->factory_location_id, true)) {
            throw new AuthorizationException("User #{$user->id} is not authorized for location #{$order->factory_location_id}.");
        }

        if ($order->status === 'completed' || $order->status === 'cancelled') {
            throw new RuntimeException("Cannot record completion for order #{$order->order_number} with status '{$order->status}'.");
        }

        $goodPairs = (float) ($data['good_pairs'] ?? 0);
        $wastagePairs = (float) ($data['wastage_pairs'] ?? 0);

        if ($goodPairs <= 0 && $wastagePairs <= 0) {
            throw new InvalidArgumentException("Completion must report at least some good pairs or wastage pairs.");
        }

        if ($goodPairs < 0 || $wastagePairs < 0) {
            throw new InvalidArgumentException("Production completion quantities cannot be negative.");
        }

        $idempotencyKey = !empty($data['idempotency_key']) ? trim((string) $data['idempotency_key']) : null;
        $payloadHash = $this->computeCompletionPayloadHash($order, $data, $goodPairs, $wastagePairs);

        // Fast-path: check if idempotent completion already recorded
        if ($idempotencyKey) {
            $existing = ProductionCompletion::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->request_hash && !hash_equals($existing->request_hash, $payloadHash)) {
                    throw new InvalidArgumentException("Idempotency key '{$idempotencyKey}' has already been used with a different request payload.");
                }
                return $existing->load(['productionOrder.productVariant.product', 'location']);
            }
        }

        try {
            return DB::transaction(function () use ($order, $data, $goodPairs, $wastagePairs, $user, $idempotencyKey, $payloadHash) {
                $lockedOrder = ProductionOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

                if ($lockedOrder->status === 'completed' || $lockedOrder->status === 'cancelled') {
                    throw new RuntimeException("Production order #{$lockedOrder->order_number} is already {$lockedOrder->status}.");
                }

                $laborCost = isset($data['labor_cost']) ? (float) $data['labor_cost'] : 0.00;
                $otherCosts = isset($data['other_costs']) ? (float) $data['other_costs'] : 0.00;

                // Allocate material cost to this batch
                $allocatedMaterialCost = '0.00';
                if ($goodPairs > 0) {
                    if (isset($data['allocated_material_cost'])) {
                        $allocatedMaterialCost = number_format((float) $data['allocated_material_cost'], 2, '.', '');
                    } else {
                        // Proportional allocation of unallocated material cost
                        $totalPairsInBatch = bcadd((string) $goodPairs, (string) $wastagePairs, 4);
                        $plannedTotal = (string) $lockedOrder->planned_quantity_pairs;
                        if (bccomp($plannedTotal, '0.00', 4) > 0 && bccomp((string) $lockedOrder->actual_material_cost, '0.00', 2) > 0) {
                            $ratio = bcdiv($totalPairsInBatch, $plannedTotal, 6);
                            $allocatedMaterialCost = bcmul((string) $lockedOrder->actual_material_cost, $ratio, 2);
                        } else {
                            // Derive from recipe component costs
                            $recipeCostPerPair = $this->calculateRecipeCostPerPair($lockedOrder->recipe);
                            $allocatedMaterialCost = bcmul($totalPairsInBatch, (string) $recipeCostPerPair, 2);
                        }
                    }
                }

                $totalBatchCost = bcadd(bcadd($allocatedMaterialCost, (string) $laborCost, 2), (string) $otherCosts, 2);

                // Finished good unit cost = total batch cost / good finished pairs
                $unitCostPerPair = ($goodPairs > 0)
                    ? (float) bcdiv($totalBatchCost, (string) $goodPairs, 4)
                    : 0.0000;

                $completionNumber = $this->generateCompletionNumber($lockedOrder->factoryLocation);

                // Add finished footwear pairs to factory inventory
                $movementId = null;
                if ($goodPairs > 0) {
                    $movement = $this->inventoryService->addStock(
                        locationId: $lockedOrder->factory_location_id,
                        variantId: $lockedOrder->product_variant_id,
                        quantityPairs: $goodPairs,
                        unitCost: $unitCostPerPair,
                        movementType: 'production_output',
                        user: $user,
                        referenceType: ProductionCompletion::class,
                        referenceId: 0, // Placeholder, updated below
                        notes: "Finished goods output from Production Order #{$lockedOrder->order_number} (Completion #{$completionNumber})"
                    );
                    $movementId = $movement->id;
                }

                $completion = ProductionCompletion::create([
                    'completion_number' => $completionNumber,
                    'production_order_id' => $lockedOrder->id,
                    'location_id' => $lockedOrder->factory_location_id,
                    'completion_date' => $data['completion_date'] ?? now()->toDateString(),
                    'good_pairs' => $goodPairs,
                    'wastage_pairs' => $wastagePairs,
                    'labor_cost' => $laborCost,
                    'other_costs' => $otherCosts,
                    'allocated_material_cost' => $allocatedMaterialCost,
                    'total_cost' => $totalBatchCost,
                    'unit_cost_per_pair' => $unitCostPerPair,
                    'stock_movement_id' => $movementId,
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $payloadHash,
                    'notes' => isset($data['notes']) ? trim((string) $data['notes']) : null,
                    'created_by' => $user->id,
                ]);

                // Update movement referenceId
                if ($movementId) {
                    $movement->reference_id = $completion->id;
                    $movement->save();
                }

                // Update production order cumulative figures
                $newCompletedGood = bcadd((string) $lockedOrder->completed_good_pairs, (string) $goodPairs, 4);
                $newWastage = bcadd((string) $lockedOrder->wastage_pairs, (string) $wastagePairs, 4);
                $newLaborCost = bcadd((string) $lockedOrder->labor_cost, (string) $laborCost, 2);
                $newOtherCosts = bcadd((string) $lockedOrder->other_costs, (string) $otherCosts, 2);

                $lockedOrder->completed_good_pairs = $newCompletedGood;
                $lockedOrder->wastage_pairs = $newWastage;
                $lockedOrder->labor_cost = $newLaborCost;
                $lockedOrder->other_costs = $newOtherCosts;
                $lockedOrder->total_production_cost = bcadd(bcadd((string) $lockedOrder->actual_material_cost, $newLaborCost, 2), $newOtherCosts, 2);

                if (bccomp($newCompletedGood, '0.00', 4) > 0) {
                    $lockedOrder->unit_cost_per_pair = bcdiv((string) $lockedOrder->total_production_cost, $newCompletedGood, 4);
                }

                // Status transition: mark completed if planned target met or explicitly requested
                $shouldComplete = !empty($data['mark_completed']) || (bccomp($newCompletedGood, (string) $lockedOrder->planned_quantity_pairs, 4) >= 0);
                if ($shouldComplete) {
                    $lockedOrder->status = 'completed';
                    $lockedOrder->completed_at = now();
                    $lockedOrder->completed_by = $user->id;
                } else {
                    $lockedOrder->status = 'in_progress';
                }

                $lockedOrder->save();

                return $completion->fresh(['productionOrder.productVariant.product', 'location']);
            });
        } catch (QueryException $e) {
            if ($idempotencyKey && $this->isIdempotencyCollision($e, 'prod_completions')) {
                $existing = ProductionCompletion::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    if ($existing->request_hash && !hash_equals($existing->request_hash, $payloadHash)) {
                        throw new InvalidArgumentException("Idempotency key '{$idempotencyKey}' has already been used with a different request payload.");
                    }
                    return $existing->load(['productionOrder.productVariant.product', 'location']);
                }
            }
            throw $e;
        }
    }

    /**
     * Compute comprehensive financial and unit costing breakdown for a production order.
     */
    public function calculateOrderCosting(ProductionOrder $order): array
    {
        $plannedPairs = (string) $order->planned_quantity_pairs;
        $goodPairs = (string) $order->completed_good_pairs;
        $wastePairs = (string) $order->wastage_pairs;

        $plannedMatCost = (string) $order->planned_material_cost;
        $actualMatCost = (string) $order->actual_material_cost;
        $laborCost = (string) $order->labor_cost;
        $otherCosts = (string) $order->other_costs;
        $totalCost = (string) $order->total_production_cost;

        $materialVariance = bcsub($actualMatCost, $plannedMatCost, 2);

        $costPerGoodPair = (bccomp($goodPairs, '0.00', 4) > 0)
            ? bcdiv($totalCost, $goodPairs, 4)
            : '0.0000';

        $totalProduced = bcadd($goodPairs, $wastePairs, 4);
        $wastageRate = (bccomp($totalProduced, '0.00', 4) > 0)
            ? bcmul(bcdiv($wastePairs, $totalProduced, 6), '100', 2)
            : '0.00';

        // Estimated cost of wastage
        $wasteCost = (bccomp($totalProduced, '0.00', 4) > 0)
            ? bcmul(bcdiv($wastePairs, $totalProduced, 6), $totalCost, 2)
            : '0.00';

        return [
            'planned_quantity_pairs' => $plannedPairs,
            'completed_good_pairs' => $goodPairs,
            'actual_good_pairs' => $goodPairs,
            'wastage_pairs' => $wastePairs,
            'actual_wastage_pairs' => $wastePairs,
            'wastage_rate_percent' => $wastageRate,
            'scrap_rate_percentage' => $wastageRate,
            'planned_material_cost' => $plannedMatCost,
            'actual_material_cost' => $actualMatCost,
            'material_variance' => $materialVariance,
            'variance_material_cost' => $materialVariance,
            'labor_cost' => $laborCost,
            'other_costs' => $otherCosts,
            'total_production_cost' => $totalCost,
            'estimated_wastage_cost' => $wasteCost,
            'scrap_cost' => $wasteCost,
            'cost_per_good_pair' => $costPerGoodPair,
        ];
    }

    /**
     * Compile production management reporting data with location authorization.
     */
    public function getProductionReport(User $user, array $filters = []): array
    {
        $locationId = !empty($filters['location_id']) ? (int) $filters['location_id'] : null;

        $authorizedLocations = $user->isOwner()
            ? Location::where('type', 'factory')->where('is_active', true)->pluck('id')->all()
            : $user->getAuthorizedLocationIds();

        if ($locationId && !in_array($locationId, $authorizedLocations, true) && !$user->isOwner()) {
            throw new AuthorizationException("Unauthorized location #{$locationId}.");
        }

        $targetLocations = $locationId ? [$locationId] : $authorizedLocations;

        $fromDate = $filters['from_date'] ?? now()->startOfMonth()->toDateString();
        $toDate = $filters['to_date'] ?? now()->toDateString();

        $query = ProductionOrder::with(['product', 'productVariant', 'factoryLocation', 'recipe'])
            ->whereIn('factory_location_id', $targetLocations)
            ->whereDate('start_date', '>=', $fromDate)
            ->whereDate('start_date', '<=', $toDate)
            ->when(!empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        $orders = $query->get();

        $totalPlannedPairs = '0.0000';
        $totalGoodPairs = '0.0000';
        $totalWastagePairs = '0.0000';
        $totalMaterialCost = '0.00';
        $totalLaborCost = '0.00';
        $totalOtherCosts = '0.00';
        $totalCost = '0.00';

        foreach ($orders as $order) {
            $totalPlannedPairs = bcadd($totalPlannedPairs, (string) $order->planned_quantity_pairs, 4);
            $totalGoodPairs = bcadd($totalGoodPairs, (string) $order->completed_good_pairs, 4);
            $totalWastagePairs = bcadd($totalWastagePairs, (string) $order->wastage_pairs, 4);
            $totalMaterialCost = bcadd($totalMaterialCost, (string) $order->actual_material_cost, 2);
            $totalLaborCost = bcadd($totalLaborCost, (string) $order->labor_cost, 2);
            $totalOtherCosts = bcadd($totalOtherCosts, (string) $order->other_costs, 2);
            $totalCost = bcadd($totalCost, (string) $order->total_production_cost, 2);
        }

        $overallCostPerPair = (bccomp($totalGoodPairs, '0.00', 4) > 0)
            ? bcdiv($totalCost, $totalGoodPairs, 4)
            : '0.0000';

        return [
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'location_id' => $locationId,
                'status' => $filters['status'] ?? null,
            ],
            'summary' => [
                'orders_count' => $orders->count(),
                'total_planned_pairs' => $totalPlannedPairs,
                'total_good_pairs' => $totalGoodPairs,
                'total_wastage_pairs' => $totalWastagePairs,
                'total_material_cost' => $totalMaterialCost,
                'total_labor_cost' => $totalLaborCost,
                'total_other_costs' => $totalOtherCosts,
                'total_cost' => $totalCost,
                'overall_cost_per_pair' => $overallCostPerPair,
            ],
            'orders' => $orders,
        ];
    }

    /**
     * Export production report as formula-injection safe CSV stream.
     */
    public function exportProductionReportCsv(array $reportData): StreamedResponse
    {
        $headers = [
            'Order #',
            'Factory',
            'Article / Product',
            'Variant SKU',
            'Recipe BOM',
            'Start Date',
            'Status',
            'Planned Pairs',
            'Completed Pairs',
            'Wastage Pairs',
            'Material Cost (PKR)',
            'Labor Cost (PKR)',
            'Other Costs (PKR)',
            'Total Cost (PKR)',
            'Cost Per Pair (PKR)',
        ];

        $rows = [];
        foreach ($reportData['orders'] as $ord) {
            $rows[] = [
                $ord->order_number,
                $ord->factoryLocation->name,
                $ord->product->article_code . ' — ' . $ord->product->name,
                $ord->productVariant->sku . ' (' . $ord->productVariant->size . '/' . $ord->productVariant->color . ')',
                $ord->recipe ? $ord->recipe->name . ' (v' . $ord->recipe->version . ')' : 'N/A',
                $ord->start_date->toDateString(),
                ucfirst($ord->status),
                $ord->planned_quantity_pairs,
                $ord->completed_good_pairs,
                $ord->wastage_pairs,
                $ord->actual_material_cost,
                $ord->labor_cost,
                $ord->other_costs,
                $ord->total_production_cost,
                $ord->unit_cost_per_pair,
            ];
        }

        $filename = 'production_report_' . ($reportData['filters']['from_date'] ?? 'all') . '_to_' . ($reportData['filters']['to_date'] ?? 'now') . '.csv';

        $response = new StreamedResponse(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                $sanitized = array_map(function ($val) {
                    if ($val === null) return '';
                    $str = (string) $val;
                    if (strlen($str) > 0 && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                        return "'" . $str;
                    }
                    return $str;
                }, $row);
                fputcsv($handle, $sanitized);
            }
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    /**
     * Calculate nominal recipe cost per pair based on standard purchase costs of components.
     */
    protected function calculateRecipeCostPerPair(Recipe $recipe): float
    {
        $totalCost = '0.00';
        foreach ($recipe->items as $item) {
            $grossQty = $item->gross_quantity_per_pair;
            $unitCost = (float) $item->rawMaterialVariant->purchase_cost;
            $line = bcmul((string) $grossQty, (string) $unitCost, 4);
            $totalCost = bcadd($totalCost, $line, 4);
        }

        return (float) $totalCost;
    }

    protected function computeCompletionPayloadHash(ProductionOrder $order, array $data, float $goodPairs, float $wastagePairs): string
    {
        $canonical = [
            'order_id' => $order->id,
            'completion_date' => $data['completion_date'] ?? now()->toDateString(),
            'good_pairs' => number_format($goodPairs, 4, '.', ''),
            'wastage_pairs' => number_format($wastagePairs, 4, '.', ''),
            'labor_cost' => number_format((float) ($data['labor_cost'] ?? 0), 2, '.', ''),
            'other_costs' => number_format((float) ($data['other_costs'] ?? 0), 2, '.', ''),
        ];

        return hash('sha256', json_encode($canonical));
    }

    protected function generateRecipeNumber(): string
    {
        $dateStr = now()->format('Ymd');
        $prefix = "BOM-{$dateStr}";

        $latest = Recipe::where('recipe_number', 'like', "{$prefix}-%")
            ->lockForUpdate()
            ->latest('id')
            ->value('recipe_number');

        $nextSeq = 1;
        if ($latest && preg_match('/-(\d{4})$/', $latest, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }

        return sprintf('%s-%04d', $prefix, $nextSeq);
    }

    protected function generateOrderNumber(Location $location): string
    {
        $dateStr = now()->format('Ymd');
        $prefix = "PRD-{$location->code}-{$dateStr}";

        $latest = ProductionOrder::where('order_number', 'like', "{$prefix}-%")
            ->lockForUpdate()
            ->latest('id')
            ->value('order_number');

        $nextSeq = 1;
        if ($latest && preg_match('/-(\d{4})$/', $latest, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }

        return sprintf('%s-%04d', $prefix, $nextSeq);
    }

    protected function generateIssueNumber(Location $location, string $type = 'issue'): string
    {
        $dateStr = now()->format('Ymd');
        $code = ($type === 'return') ? 'PMR' : 'PMI';
        $prefix = "{$code}-{$location->code}-{$dateStr}";

        $latest = ProductionMaterialIssue::where('issue_number', 'like', "{$prefix}-%")
            ->lockForUpdate()
            ->latest('id')
            ->value('issue_number');

        $nextSeq = 1;
        if ($latest && preg_match('/-(\d{4})$/', $latest, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }

        return sprintf('%s-%04d', $prefix, $nextSeq);
    }

    protected function generateCompletionNumber(Location $location): string
    {
        $dateStr = now()->format('Ymd');
        $prefix = "PMC-{$location->code}-{$dateStr}";

        $latest = ProductionCompletion::where('completion_number', 'like', "{$prefix}-%")
            ->lockForUpdate()
            ->latest('id')
            ->value('completion_number');

        $nextSeq = 1;
        if ($latest && preg_match('/-(\d{4})$/', $latest, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }

        return sprintf('%s-%04d', $prefix, $nextSeq);
    }

    protected function isIdempotencyCollision(QueryException $e, string $table): bool
    {
        $code = (int) $e->getCode();
        $sqlState = (string) ($e->errorInfo[0] ?? $code);
        $driverCode = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : null;
        $msg = $e->getMessage();

        $isUnique = ($code === 23000 || $sqlState === '23000' || $driverCode === 1062 || $driverCode === 19);

        if (!$isUnique) {
            return false;
        }

        return str_contains($msg, "{$table}_idempotency_unique")
            || str_contains($msg, 'idempotency_key')
            || (str_contains($msg, '1062') && str_contains($msg, 'idempotency'));
    }
}
