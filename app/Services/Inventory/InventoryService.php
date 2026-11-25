<?php

namespace App\Services\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Models\Location;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Services\Administration\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class InventoryService
{
    public function __construct(
        protected ?AuditService $auditService = null
    ) {
        $this->auditService = $auditService ?? app(AuditService::class);
    }
    /**
     * Add stock to a location (e.g. from approved adjustment, supplier receipt, or production output).
     * Automatically updates weighted-average cost (WAC) and logs audited StockMovement.
     */
    public function addStock(
        int $locationId,
        int $variantId,
        float $quantityPairs,
        float $unitCost,
        string $movementType,
        User $user,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null
    ): StockMovement {
        if ($quantityPairs <= 0) {
            throw new InvalidArgumentException("Quantity to add must be positive. Received: {$quantityPairs}");
        }

        if ($unitCost < 0) {
            throw new InvalidArgumentException("Unit cost cannot be negative. Received: {$unitCost}");
        }

        return DB::transaction(function () use (
            $locationId,
            $variantId,
            $quantityPairs,
            $unitCost,
            $movementType,
            $user,
            $referenceType,
            $referenceId,
            $notes
        ) {
            // Pessimistic row locking for concurrency safety
            $balance = StockBalance::where('location_id', $locationId)
                ->where('product_variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if (!$balance) {
                $balance = new StockBalance([
                    'location_id' => $locationId,
                    'product_variant_id' => $variantId,
                    'pairs_quantity' => 0.0000,
                    'allocated_quantity' => 0.0000,
                    'avg_unit_cost' => $unitCost,
                ]);
            }

            $currentQty = (float) $balance->pairs_quantity;
            $currentAvgCost = (float) $balance->avg_unit_cost;

            $newQty = (float) bcadd((string) $currentQty, (string) $quantityPairs, 4);

            // Calculate new Weighted Average Cost (WAC)
            $newAvgCost = $unitCost;
            if ($newQty > 0) {
                $totalExistingValue = (float) bcmul((string) $currentQty, (string) $currentAvgCost, 4);
                $totalIncomingValue = (float) bcmul((string) $quantityPairs, (string) $unitCost, 4);
                $newAvgCost = (float) bcdiv((string) ($totalExistingValue + $totalIncomingValue), (string) $newQty, 4);
            }

            $balance->pairs_quantity = $newQty;
            $balance->avg_unit_cost = $newAvgCost;
            $balance->save();

            // Record audited stock movement
            return StockMovement::create([
                'location_id' => $locationId,
                'product_variant_id' => $variantId,
                'movement_type' => $movementType,
                'quantity_delta_pairs' => $quantityPairs,
                'balance_after_pairs' => $newQty,
                'unit_cost' => $unitCost,
                'total_cost' => (float) bcmul((string) $quantityPairs, (string) $unitCost, 4),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $notes,
                'created_by' => $user->id,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Deduct stock from a location (e.g. for wholesale sale, approved adjustment, or transfer dispatch).
     * Enforces strict non-negative stock invariant with pessimistic row locking.
     */
    public function deductStock(
        int $locationId,
        int $variantId,
        float $quantityPairs,
        string $movementType,
        User $user,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null
    ): StockMovement {
        if ($quantityPairs <= 0) {
            throw new InvalidArgumentException("Quantity to deduct must be positive. Received: {$quantityPairs}");
        }

        return DB::transaction(function () use (
            $locationId,
            $variantId,
            $quantityPairs,
            $movementType,
            $user,
            $referenceType,
            $referenceId,
            $notes
        ) {
            // Pessimistic row locking
            $balance = StockBalance::where('location_id', $locationId)
                ->where('product_variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            $availableQty = $balance ? (float) $balance->pairs_quantity : 0.0;

            if ($availableQty < $quantityPairs) {
                $sku = ProductVariant::find($variantId)?->sku ?? "ID #{$variantId}";
                $locationName = Location::find($locationId)?->name ?? "Location #{$locationId}";

                throw new InsufficientStockException(
                    "Cannot deduct {$quantityPairs} pairs for {$sku} at {$locationName}. Current balance is {$availableQty} pairs."
                );
            }

            $newQty = (float) bcsub((string) $availableQty, (string) $quantityPairs, 4);
            $balance->pairs_quantity = $newQty;
            $balance->save();

            $unitCost = (float) $balance->avg_unit_cost;

            // Record audited stock movement
            return StockMovement::create([
                'location_id' => $locationId,
                'product_variant_id' => $variantId,
                'movement_type' => $movementType,
                'quantity_delta_pairs' => -$quantityPairs,
                'balance_after_pairs' => $newQty,
                'unit_cost' => $unitCost,
                'total_cost' => (float) bcmul((string) $quantityPairs, (string) $unitCost, 4),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $notes,
                'created_by' => $user->id,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Approve and post an inventory adjustment.
     */
    public function postAdjustment(StockAdjustment $adjustment, User $approver): void
    {
        if ($adjustment->status !== 'draft') {
            throw new InvalidArgumentException("Only draft adjustments can be approved.");
        }

        DB::transaction(function () use ($adjustment, $approver) {
            foreach ($adjustment->items as $item) {
                $delta = (float) $item->quantity_delta_pairs;

                if ($delta > 0) {
                    $this->addStock(
                        $adjustment->location_id,
                        $item->product_variant_id,
                        $delta,
                        (float) $item->unit_cost,
                        'adjustment',
                        $approver,
                        StockAdjustment::class,
                        $adjustment->id,
                        "Stock Adjustment: {$adjustment->reason}"
                    );
                } elseif ($delta < 0) {
                    $this->deductStock(
                        $adjustment->location_id,
                        $item->product_variant_id,
                        abs($delta),
                        'adjustment',
                        $approver,
                        StockAdjustment::class,
                        $adjustment->id,
                        "Stock Adjustment: {$adjustment->reason}"
                    );
                }
            }

            $adjustment->status = 'approved';
            $adjustment->approved_by = $approver->id;
            $adjustment->approved_at = now();
            $adjustment->save();
        });
    }

    /**
     * Create a draft inter-location stock transfer.
     */
    public function createTransferDraft(array $data, User $user): StockTransfer
    {
        $sourceLocationId = (int) $data['source_location_id'];
        $destinationLocationId = (int) $data['destination_location_id'];

        if ($sourceLocationId === $destinationLocationId) {
            throw new InvalidArgumentException("Source and destination locations cannot be identical.");
        }

        if (!$user->hasLocationAccess($sourceLocationId, true)) {
            throw new RuntimeException("User does not have operational access to source location #{$sourceLocationId}.");
        }

        if (empty($data['items']) || !is_array($data['items'])) {
            throw new InvalidArgumentException("At least one line item is required for stock transfer.");
        }

        return DB::transaction(function () use ($data, $user, $sourceLocationId, $destinationLocationId) {
            $prefix = 'ST-' . date('Ymd') . '-';
            $transferNumber = $prefix . strtoupper(Str::random(4));

            $transfer = StockTransfer::create([
                'transfer_number' => $transferNumber,
                'source_location_id' => $sourceLocationId,
                'destination_location_id' => $destinationLocationId,
                'status' => 'draft',
                'transfer_date' => $data['transfer_date'] ?? now()->toDateString(),
                'dispatch_notes' => $data['dispatch_notes'] ?? null,
                'total_cartons' => 0,
                'total_pairs' => '0.0000',
                'total_cost' => '0.00',
                'created_by' => $user->id,
            ]);

            $totalCartons = 0;
            $totalPairs = '0.0000';
            $totalCost = '0.00';

            foreach ($data['items'] as $itemData) {
                $variantId = (int) $itemData['product_variant_id'];
                $variant = ProductVariant::with('product')->findOrFail($variantId);
                $packRatio = $variant->product->carton_pack_qty ?? 12;

                $cartons = (int) ($itemData['quantity_cartons'] ?? 0);
                $pairs = isset($itemData['quantity_pairs']) ? (float) $itemData['quantity_pairs'] : 0.0;

                if ($pairs <= 0 && $cartons > 0) {
                    $pairs = (float) bcmul((string) $cartons, (string) $packRatio, 4);
                } elseif ($cartons <= 0 && $pairs > 0) {
                    $cartons = (int) floor($pairs / $packRatio);
                }

                if ($pairs <= 0) {
                    throw new InvalidArgumentException("Item quantity must be greater than zero.");
                }

                // Fetch current source average unit cost
                $balance = StockBalance::where('location_id', $sourceLocationId)
                    ->where('product_variant_id', $variantId)
                    ->first();
                $unitCost = $balance ? (float) $balance->avg_unit_cost : (float) ($variant->purchase_cost ?? 0.0);
                $lineTotal = (float) bcmul((string) $pairs, (string) $unitCost, 2);

                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_variant_id' => $variantId,
                    'quantity_cartons' => $cartons,
                    'carton_pack_qty' => $packRatio,
                    'quantity_pairs' => $pairs,
                    'unit_cost' => $unitCost,
                    'total_cost' => $lineTotal,
                    'notes' => $itemData['notes'] ?? null,
                ]);

                $totalCartons += $cartons;
                $totalPairs = bcadd($totalPairs, (string) $pairs, 4);
                $totalCost = bcadd($totalCost, (string) $lineTotal, 2);
            }

            $transfer->update([
                'total_cartons' => $totalCartons,
                'total_pairs' => $totalPairs,
                'total_cost' => $totalCost,
            ]);

            $this->auditService->log(
                action: 'stock_transfer_create',
                model: $transfer,
                description: "Created draft stock transfer {$transfer->transfer_number} from location #{$sourceLocationId} to #{$destinationLocationId}",
                actor: $user,
                locationId: $sourceLocationId
            );

            return $transfer->fresh(['items.productVariant.product', 'sourceLocation', 'destinationLocation']);
        });
    }

    /**
     * Dispatch stock transfer: deducts inventory from source location and puts into transit.
     */
    public function dispatchTransfer(StockTransfer $transfer, User $user, ?string $notes = null): StockTransfer
    {
        if ($transfer->status !== 'draft') {
            throw new RuntimeException("Only draft transfers can be dispatched. Current status: {$transfer->status}");
        }

        if (!$user->hasLocationAccess($transfer->source_location_id, true)) {
            throw new RuntimeException("User does not have operational authorization to dispatch from source location.");
        }

        return DB::transaction(function () use ($transfer, $user, $notes) {
            $lockedTransfer = StockTransfer::where('id', $transfer->id)->lockForUpdate()->firstOrFail();

            if ($lockedTransfer->status !== 'draft') {
                throw new RuntimeException("Transfer is already dispatched or processed.");
            }

            $items = $lockedTransfer->items()->with('productVariant')->get();
            if ($items->isEmpty()) {
                throw new InvalidArgumentException("Transfer has no line items.");
            }

            // DEADLOCK PREVENTION: sort items by product_variant_id ascending
            $sortedItems = $items->sortBy('product_variant_id');

            foreach ($sortedItems as $item) {
                $movement = $this->deductStock(
                    locationId: $lockedTransfer->source_location_id,
                    variantId: $item->product_variant_id,
                    quantityPairs: (float) $item->quantity_pairs,
                    movementType: 'transfer_out',
                    user: $user,
                    referenceType: StockTransfer::class,
                    referenceId: $lockedTransfer->id,
                    notes: "Stock Transfer Dispatch #{$lockedTransfer->transfer_number} to {$lockedTransfer->destinationLocation->name}"
                );

                // Update item unit cost with actual deducted WAC
                $item->unit_cost = $movement->unit_cost;
                $item->total_cost = (float) bcmul((string) $item->quantity_pairs, (string) $movement->unit_cost, 2);
                $item->save();
            }

            $lockedTransfer->status = 'in_transit';
            $lockedTransfer->dispatched_by = $user->id;
            $lockedTransfer->dispatched_at = now();
            if ($notes) {
                $lockedTransfer->dispatch_notes = $notes;
            }
            $lockedTransfer->save();

            $this->auditService->log(
                action: 'stock_transfer_dispatch',
                model: $lockedTransfer,
                description: "Dispatched stock transfer {$lockedTransfer->transfer_number} ({$lockedTransfer->total_pairs} pairs in transit)",
                actor: $user,
                locationId: $lockedTransfer->source_location_id
            );

            return $lockedTransfer->fresh(['items.productVariant.product', 'sourceLocation', 'destinationLocation']);
        });
    }

    /**
     * Receive stock transfer at destination location: adds stock into destination inventory.
     */
    public function receiveTransfer(StockTransfer $transfer, User $user, ?string $notes = null): StockTransfer
    {
        if ($transfer->status !== 'in_transit') {
            throw new RuntimeException("Only in-transit transfers can be received. Current status: {$transfer->status}");
        }

        if (!$user->hasLocationAccess($transfer->destination_location_id, true)) {
            throw new RuntimeException("User does not have operational authorization to receive at destination location.");
        }

        return DB::transaction(function () use ($transfer, $user, $notes) {
            $lockedTransfer = StockTransfer::where('id', $transfer->id)->lockForUpdate()->firstOrFail();

            if ($lockedTransfer->status !== 'in_transit') {
                throw new RuntimeException("Transfer is not in transit.");
            }

            $items = $lockedTransfer->items()->with('productVariant')->get();

            // DEADLOCK PREVENTION: sort items by product_variant_id ascending
            $sortedItems = $items->sortBy('product_variant_id');

            foreach ($sortedItems as $item) {
                $recvPairs = (float) $item->quantity_pairs;

                $this->addStock(
                    locationId: $lockedTransfer->destination_location_id,
                    variantId: $item->product_variant_id,
                    quantityPairs: $recvPairs,
                    unitCost: (float) $item->unit_cost,
                    movementType: 'transfer_in',
                    user: $user,
                    referenceType: StockTransfer::class,
                    referenceId: $lockedTransfer->id,
                    notes: "Stock Transfer Receipt #{$lockedTransfer->transfer_number} from {$lockedTransfer->sourceLocation->name}"
                );

                $item->received_quantity_pairs = $recvPairs;
                $item->save();
            }

            $lockedTransfer->status = 'completed';
            $lockedTransfer->received_by = $user->id;
            $lockedTransfer->received_at = now();
            if ($notes) {
                $lockedTransfer->receive_notes = $notes;
            }
            $lockedTransfer->save();

            $this->auditService->log(
                action: 'stock_transfer_receive',
                model: $lockedTransfer,
                description: "Received stock transfer {$lockedTransfer->transfer_number} at {$lockedTransfer->destinationLocation->name}",
                actor: $user,
                locationId: $lockedTransfer->destination_location_id
            );

            return $lockedTransfer->fresh(['items.productVariant.product', 'sourceLocation', 'destinationLocation']);
        });
    }

    /**
     * Cancel a stock transfer (if in transit, safely reverses stock back to source location).
     */
    public function cancelTransfer(StockTransfer $transfer, User $user, string $reason): StockTransfer
    {
        if (in_array($transfer->status, ['completed', 'cancelled'])) {
            throw new RuntimeException("Completed or already cancelled transfers cannot be cancelled.");
        }

        if (!$user->isOwner() && !$user->hasLocationAccess($transfer->source_location_id, true)) {
            throw new RuntimeException("Only the business owner or source location manager can cancel this transfer.");
        }

        return DB::transaction(function () use ($transfer, $user, $reason) {
            $lockedTransfer = StockTransfer::where('id', $transfer->id)->lockForUpdate()->firstOrFail();

            // If it was already dispatched and in transit, reverse stock back to source location
            if ($lockedTransfer->status === 'in_transit') {
                $items = $lockedTransfer->items()->get()->sortBy('product_variant_id');

                foreach ($items as $item) {
                    $this->addStock(
                        locationId: $lockedTransfer->source_location_id,
                        variantId: $item->product_variant_id,
                        quantityPairs: (float) $item->quantity_pairs,
                        unitCost: (float) $item->unit_cost,
                        movementType: 'transfer_in',
                        user: $user,
                        referenceType: StockTransfer::class,
                        referenceId: $lockedTransfer->id,
                        notes: "Transfer #{$lockedTransfer->transfer_number} cancelled: Reversing to source. Reason: {$reason}"
                    );
                }
            }

            $lockedTransfer->status = 'cancelled';
            $lockedTransfer->cancellation_reason = $reason;
            $lockedTransfer->cancelled_by = $user->id;
            $lockedTransfer->cancelled_at = now();
            $lockedTransfer->save();

            $this->auditService->log(
                action: 'stock_transfer_cancel',
                model: $lockedTransfer,
                description: "Cancelled stock transfer {$lockedTransfer->transfer_number}. Reason: {$reason}",
                actor: $user,
                locationId: $lockedTransfer->source_location_id
            );

            return $lockedTransfer->fresh();
        });
    }
}
