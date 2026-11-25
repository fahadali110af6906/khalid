<?php

namespace App\Services\Purchasing;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\FinancialAccount;
use App\Models\Location;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Supplier;
use App\Models\SupplierDebitNote;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Accounting\CashAndBankService;
use App\Services\Accounting\SupplierKhataService;
use App\Services\Inventory\InventoryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class PurchaseService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected SupplierKhataService $khataService,
        protected ?CashAndBankService $cashAndBankService = null
    ) {
        $this->cashAndBankService = $cashAndBankService ?? app(CashAndBankService::class);
    }

    /**
     * Create a new draft purchase order without affecting inventory or supplier ledger.
     */
    public function createDraft(array $data, User $user): PurchaseOrder
    {
        $locationId = (int) $data['location_id'];
        $supplierId = (int) $data['supplier_id'];

        if (!$user->hasLocationAccess($locationId)) {
            throw new AuthorizationException("User #{$user->id} does not have authorization for location #{$locationId}.");
        }

        $supplier = Supplier::findOrFail($supplierId);
        $location = Location::findOrFail($locationId);

        if (empty($data['items']) || !is_array($data['items'])) {
            throw new InvalidArgumentException("At least one purchase order item is required.");
        }

        return DB::transaction(function () use ($data, $user, $supplier, $location) {
            $poNumber = $this->generatePurchaseNumber($location);
            $orderDate = $data['order_date'] ?? now()->toDateString();
            $expectedDeliveryDate = $data['expected_delivery_date'] ?? null;
            $notes = $data['notes'] ?? null;

            $overallDiscount = isset($data['discount_amount']) ? number_format((float) $data['discount_amount'], 2, '.', '') : '0.00';
            $additionalCharges = isset($data['additional_charges']) ? number_format((float) $data['additional_charges'], 2, '.', '') : '0.00';

            if (bccomp($overallDiscount, '0.00', 2) < 0) {
                throw new InvalidArgumentException("Purchase order discount cannot be negative.");
            }

            if (bccomp($additionalCharges, '0.00', 2) < 0) {
                throw new InvalidArgumentException("Additional charges cannot be negative.");
            }

            $order = PurchaseOrder::create([
                'purchase_number' => $poNumber,
                'supplier_id' => $supplier->id,
                'location_id' => $location->id,
                'order_date' => $orderDate,
                'expected_delivery_date' => $expectedDeliveryDate,
                'status' => 'draft',
                'total_cartons' => 0,
                'total_pairs' => '0.0000',
                'subtotal_amount' => '0.00',
                'discount_amount' => $overallDiscount,
                'additional_charges' => $additionalCharges,
                'total_amount' => '0.00',
                'notes' => $notes,
                'created_by' => $user->id,
            ]);

            $this->syncOrderItemsAndTotals($order, $data['items'], $overallDiscount, $additionalCharges);

            return $order->fresh(['items.productVariant.product', 'supplier', 'location']);
        });
    }

    /**
     * Update an existing draft purchase order.
     */
    public function updateDraft(PurchaseOrder $order, array $data, User $user): PurchaseOrder
    {
        if ($order->status !== 'draft') {
            throw new RuntimeException("Only draft purchase orders can be updated. Current status: {$order->status}");
        }

        $locationId = (int) ($data['location_id'] ?? $order->location_id);
        if (!$user->hasLocationAccess($locationId)) {
            throw new AuthorizationException("User #{$user->id} does not have authorization for location #{$locationId}.");
        }

        return DB::transaction(function () use ($order, $data, $locationId) {
            if (isset($data['supplier_id'])) {
                $supplier = Supplier::findOrFail((int) $data['supplier_id']);
                $order->supplier_id = $supplier->id;
            }

            $order->location_id = $locationId;
            if (isset($data['order_date'])) {
                $order->order_date = $data['order_date'];
            }
            if (array_key_exists('expected_delivery_date', $data)) {
                $order->expected_delivery_date = $data['expected_delivery_date'];
            }
            if (array_key_exists('notes', $data)) {
                $order->notes = $data['notes'];
            }

            $overallDiscount = isset($data['discount_amount']) ? number_format((float) $data['discount_amount'], 2, '.', '') : (string) $order->discount_amount;
            $additionalCharges = isset($data['additional_charges']) ? number_format((float) $data['additional_charges'], 2, '.', '') : (string) $order->additional_charges;

            if (bccomp($overallDiscount, '0.00', 2) < 0) {
                throw new InvalidArgumentException("Purchase order discount cannot be negative.");
            }

            if (bccomp($additionalCharges, '0.00', 2) < 0) {
                throw new InvalidArgumentException("Additional charges cannot be negative.");
            }

            if (!empty($data['items']) && is_array($data['items'])) {
                $order->items()->delete();
                $this->syncOrderItemsAndTotals($order, $data['items'], $overallDiscount, $additionalCharges);
            } else {
                $order->discount_amount = $overallDiscount;
                $order->additional_charges = $additionalCharges;
                $order->total_amount = bcadd(bcsub((string) $order->subtotal_amount, $overallDiscount, 2), $additionalCharges, 2);
                $order->save();
            }

            return $order->fresh(['items.productVariant.product', 'supplier', 'location']);
        });
    }

    /**
     * Approve and place a draft purchase order (status -> 'ordered').
     * Neither affects stock nor affects supplier khata ledger.
     */
    public function approveOrder(PurchaseOrder $order, User $user): PurchaseOrder
    {
        if (!$user->hasLocationAccess($order->location_id)) {
            throw new AuthorizationException("User #{$user->id} does not have authorization for location #{$order->location_id}.");
        }

        if ($order->status !== 'draft') {
            throw new RuntimeException("Only draft purchase orders can be approved. Current status: {$order->status}");
        }

        if ($order->items()->count() === 0) {
            throw new RuntimeException("Cannot approve a purchase order with no items.");
        }

        $order->status = 'ordered';
        $order->ordered_at = now();
        $order->ordered_by = $user->id;
        $order->save();

        return $order->fresh(['items', 'supplier', 'location']);
    }

    /**
     * Cancel a purchase order if not yet received.
     */
    public function cancelOrder(PurchaseOrder $order, User $user, ?string $reason = null): PurchaseOrder
    {
        if (!$user->hasLocationAccess($order->location_id)) {
            throw new AuthorizationException("User #{$user->id} does not have authorization for location #{$order->location_id}.");
        }

        if (in_array($order->status, ['partially_received', 'received', 'cancelled'])) {
            throw new RuntimeException("Purchase order in status '{$order->status}' cannot be cancelled.");
        }

        $order->status = 'cancelled';
        if ($reason) {
            $order->notes = ($order->notes ? $order->notes . "\n" : '') . "Cancelled by {$user->name}: {$reason}";
        }
        $order->save();

        return $order;
    }

    /**
     * Receive goods (full or partial) against an approved purchase order.
     * Atomically:
     * 1. Validates quantities against remaining pending items.
     * 2. Sorts items by product_variant_id ASC to guarantee deadlock-free execution.
     * 3. Increases physical stock & updates WAC via InventoryService::addStock().
     * 4. Updates PurchaseOrderItem received_pairs and overall PO status.
     * 5. Credits Supplier Khata ledger (goods received increases payable).
     */
    public function receiveGoods(PurchaseOrder $order, array $data, User $user): GoodsReceipt
    {
        if (!$user->hasLocationAccess($order->location_id)) {
            throw new AuthorizationException("User #{$user->id} does not have authorization for location #{$order->location_id}.");
        }

        if (!in_array($order->status, ['ordered', 'partially_received'])) {
            throw new RuntimeException("Goods can only be received for orders in 'ordered' or 'partially_received' status. Current: {$order->status}");
        }

        if (empty($data['items']) || !is_array($data['items'])) {
            throw new InvalidArgumentException("At least one item must be received.");
        }

        $idempotencyKey = !empty($data['idempotency_key']) ? trim((string) $data['idempotency_key']) : null;
        $payloadHash = $this->computeReceiptPayloadHash($order, $data);

        // Fast-path: Check if an idempotent receipt was already created
        if ($idempotencyKey) {
            $existing = GoodsReceipt::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->request_hash && !hash_equals($existing->request_hash, $payloadHash)) {
                    throw new InvalidArgumentException("Idempotency key '{$idempotencyKey}' has already been used with a different request payload.");
                }
                return $existing->load(['items.productVariant.product', 'purchaseOrder', 'supplier', 'location']);
            }
        }

        try {
            return DB::transaction(function () use ($order, $data, $user, $idempotencyKey, $payloadHash) {
                $receiptNumber = $this->generateReceiptNumber($order->location);
                $receiptDate = $data['receipt_date'] ?? now()->toDateString();
                $challanNumber = $data['challan_number'] ?? null;
                $notes = $data['notes'] ?? null;

                // Preload and lock existing PO items
                $poItems = PurchaseOrderItem::where('purchase_order_id', $order->id)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                // Parse and validate items to receive
                $itemsToProcess = [];
                foreach ($data['items'] as $itemInput) {
                    $poItemId = (int) ($itemInput['purchase_order_item_id'] ?? $itemInput['item_id'] ?? 0);
                    $poItem = $poItems->get($poItemId);

                    if (!$poItem) {
                        throw new InvalidArgumentException("Purchase order item #{$poItemId} does not belong to PO #{$order->purchase_number}.");
                    }

                    $packRatio = $poItem->carton_pack_qty > 0 ? (int) $poItem->carton_pack_qty : 12;

                    // Determine quantity in pairs to receive and validate consistency
                    if (isset($itemInput['received_cartons']) && isset($itemInput['received_pairs'])) {
                        $calcPairs = (float) bcmul((string) $itemInput['received_cartons'], (string) $packRatio, 4);
                        if (bccomp((string) $calcPairs, (string) $itemInput['received_pairs'], 4) !== 0) {
                            throw new InvalidArgumentException("Inconsistent carton and pair quantities provided for item #{$poItemId}.");
                        }
                        $pairs = (float) $itemInput['received_pairs'];
                        $cartons = (float) $itemInput['received_cartons'];
                    } elseif (isset($itemInput['received_cartons'])) {
                        $cartons = (float) $itemInput['received_cartons'];
                        $pairs = (float) bcmul((string) $cartons, (string) $packRatio, 4);
                    } elseif (isset($itemInput['received_pairs'])) {
                        $pairs = (float) $itemInput['received_pairs'];
                        $cartons = (float) bcdiv((string) $pairs, (string) $packRatio, 4);
                    } elseif (isset($itemInput['quantity'])) {
                        $unitType = $itemInput['unit_type'] ?? $poItem->unit_type;
                        $qty = (float) $itemInput['quantity'];
                        if ($unitType === 'carton') {
                            $cartons = $qty;
                            $pairs = (float) bcmul((string) $cartons, (string) $packRatio, 4);
                        } else {
                            $pairs = $qty;
                            $cartons = (float) bcdiv((string) $pairs, (string) $packRatio, 4);
                        }
                    } else {
                        throw new InvalidArgumentException("Missing quantity for received item #{$poItemId}.");
                    }

                    if ($pairs < 0) {
                        throw new InvalidArgumentException("Received quantity cannot be negative.");
                    }

                    if ($pairs == 0) {
                        continue; // Skip 0-quantity rows
                    }

                    $remainingPairs = (float) bcsub((string) $poItem->ordered_pairs, (string) $poItem->received_pairs, 4);
                    if (bccomp((string) $pairs, (string) $remainingPairs, 4) > 0) {
                        throw new InvalidArgumentException(
                            "Cannot receive {$pairs} pairs for variant #{$poItem->product_variant_id}. Remaining pending pairs: {$remainingPairs}."
                        );
                    }

                    $itemsToProcess[] = [
                        'po_item' => $poItem,
                        'variant_id' => $poItem->product_variant_id,
                        'received_cartons' => $cartons,
                        'received_pairs' => $pairs,
                        'unit_cost_per_pair' => (float) $poItem->unit_cost_per_pair,
                        'notes' => $itemInput['notes'] ?? null,
                    ];
                }

                if (empty($itemsToProcess)) {
                    throw new InvalidArgumentException("No valid items with positive quantity provided for receipt.");
                }

                // Create Goods Receipt header
                $goodsReceipt = GoodsReceipt::create([
                    'receipt_number' => $receiptNumber,
                    'purchase_order_id' => $order->id,
                    'supplier_id' => $order->supplier_id,
                    'location_id' => $order->location_id,
                    'receipt_date' => $receiptDate,
                    'challan_number' => $challanNumber,
                    'total_received_pairs' => '0.0000',
                    'total_received_cost' => '0.00',
                    'notes' => $notes,
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $payloadHash,
                    'received_by' => $user->id,
                ]);

                // Sort items by product_variant_id ASC for deadlock prevention
                usort($itemsToProcess, fn($a, $b) => $a['variant_id'] <=> $b['variant_id']);

                $totalReceivedPairs = '0.0000';
                $totalReceivedCost = '0.00';

                foreach ($itemsToProcess as $item) {
                    /** @var PurchaseOrderItem $poItem */
                    $poItem = $item['po_item'];
                    $variantId = $item['variant_id'];
                    $recvPairs = $item['received_pairs'];
                    $recvCartons = $item['received_cartons'];
                    $unitCostPerPair = $item['unit_cost_per_pair'];

                    $lineTotalCost = (string) bcmul((string) $recvPairs, (string) $unitCostPerPair, 2);

                    // Add physical inventory via InventoryService (automatically updates WAC and logs StockMovement)
                    $movement = $this->inventoryService->addStock(
                        locationId: $order->location_id,
                        variantId: $variantId,
                        quantityPairs: $recvPairs,
                        unitCost: $unitCostPerPair,
                        movementType: 'purchase',
                        user: $user,
                        referenceType: 'goods_receipt',
                        referenceId: $goodsReceipt->id,
                        notes: "GRN #{$receiptNumber} for PO #{$order->purchase_number}"
                    );

                    // Record Goods Receipt Item
                    GoodsReceiptItem::create([
                        'goods_receipt_id' => $goodsReceipt->id,
                        'purchase_order_item_id' => $poItem->id,
                        'product_variant_id' => $variantId,
                        'received_cartons' => $recvCartons,
                        'received_pairs' => $recvPairs,
                        'unit_cost_per_pair' => $unitCostPerPair,
                        'total_cost' => $lineTotalCost,
                        'stock_movement_id' => $movement->id,
                        'notes' => $item['notes'],
                    ]);

                    // Update PO Item received pairs
                    $poItem->received_pairs = bcadd((string) $poItem->received_pairs, (string) $recvPairs, 4);
                    $poItem->save();

                    $totalReceivedPairs = bcadd($totalReceivedPairs, (string) $recvPairs, 4);
                    $totalReceivedCost = bcadd($totalReceivedCost, $lineTotalCost, 2);
                }

                // Update Goods Receipt totals
                $goodsReceipt->total_received_pairs = $totalReceivedPairs;
                $goodsReceipt->total_received_cost = $totalReceivedCost;
                $goodsReceipt->save();

                // Credit Supplier Khata Ledger (Goods receipt increases payable to supplier)
                $this->khataService->recordEntry(
                    supplier: $order->supplier,
                    locationId: $order->location_id,
                    entryType: 'goods_receipt',
                    creditAmount: (float) $totalReceivedCost,
                    entryDate: $receiptDate,
                    referenceType: 'goods_receipt',
                    referenceId: $goodsReceipt->id,
                    referenceNumber: $receiptNumber,
                    description: "Goods Receipt {$receiptNumber} against PO {$order->purchase_number}" . ($challanNumber ? " (Challan: {$challanNumber})" : ''),
                    user: $user
                );

                // Re-check overall PO completion
                $allReceived = true;
                $poItems = PurchaseOrderItem::where('purchase_order_id', $order->id)->get();
                foreach ($poItems as $pItem) {
                    if (bccomp((string) $pItem->received_pairs, (string) $pItem->ordered_pairs, 4) < 0) {
                        $allReceived = false;
                        break;
                    }
                }

                $order->status = $allReceived ? 'received' : 'partially_received';
                $order->save();

                return $goodsReceipt->fresh(['items.productVariant.product', 'purchaseOrder', 'supplier', 'location']);
            });
        } catch (QueryException $e) {
            if ($idempotencyKey && $this->isIdempotencyCollision($e, 'goods_receipts')) {
                // The failed transaction was already rolled back cleanly by DB::transaction.
                // We can safely re-query the existing committed record outside the failed transaction.
                $existing = GoodsReceipt::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    if ($existing->request_hash && !hash_equals($existing->request_hash, $payloadHash)) {
                        throw new InvalidArgumentException("Idempotency key '{$idempotencyKey}' has already been used with a different request payload.");
                    }
                    return $existing->load(['items.productVariant.product', 'purchaseOrder', 'supplier', 'location']);
                }
            }
            throw $e;
        }
    }

    /**
     * Record a payment made to a supplier.
     * Creates SupplierPayment record and debits Supplier Khata ledger (debit reduces payable).
     */
    public function recordPayment(Supplier $supplier, array $data, User $user): SupplierPayment
    {
        $locationId = (int) ($data['location_id'] ?? $supplier->primary_location_id);
        $amount = (float) $data['amount'];

        if (!$user->hasLocationAccess($locationId)) {
            throw new AuthorizationException("User #{$user->id} does not have authorization for location #{$locationId}.");
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException("Payment amount must be greater than zero. Received: {$amount}");
        }

        $location = Location::findOrFail($locationId);
        $purchaseOrderId = isset($data['purchase_order_id']) && $data['purchase_order_id'] ? (int) $data['purchase_order_id'] : null;

        if ($purchaseOrderId) {
            $po = PurchaseOrder::where('id', $purchaseOrderId)->where('supplier_id', $supplier->id)->firstOrFail();
        }

        $idempotencyKey = !empty($data['idempotency_key']) ? trim((string) $data['idempotency_key']) : null;
        $payloadHash = $this->computePaymentPayloadHash($supplier, $data, $locationId, $purchaseOrderId, $amount);

        // Fast-path: Check if an idempotent payment was already created
        if ($idempotencyKey) {
            $existing = SupplierPayment::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->request_hash && !hash_equals($existing->request_hash, $payloadHash)) {
                    throw new InvalidArgumentException("Idempotency key '{$idempotencyKey}' has already been used with a different request payload.");
                }
                return $existing->load(['supplier', 'location', 'purchaseOrder']);
            }
        }

        try {
            return DB::transaction(function () use ($supplier, $location, $purchaseOrderId, $amount, $data, $user, $idempotencyKey, $payloadHash) {
                $paymentNumber = $this->generatePaymentNumber($location);
                $paymentDate = $data['payment_date'] ?? now()->toDateString();
                $paymentMethod = $data['payment_method'] ?? 'cash';
                $referenceNumber = $data['reference_number'] ?? null;
                $notes = $data['notes'] ?? null;
                $financialAccountId = !empty($data['financial_account_id']) ? (int) $data['financial_account_id'] : null;

                $payment = SupplierPayment::create([
                    'payment_number' => $paymentNumber,
                    'supplier_id' => $supplier->id,
                    'purchase_order_id' => $purchaseOrderId,
                    'location_id' => $location->id,
                    'payment_date' => $paymentDate,
                    'amount' => $amount,
                    'payment_method' => $paymentMethod,
                    'financial_account_id' => $financialAccountId,
                    'reference_number' => $referenceNumber,
                    'notes' => $notes,
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $payloadHash,
                    'created_by' => $user->id,
                ]);

                // Debit Supplier Khata (Debit payment reduces payable)
                $this->khataService->recordEntry(
                    supplier: $supplier,
                    locationId: $location->id,
                    entryType: 'supplier_payment',
                    debitAmount: $amount,
                    entryDate: $paymentDate,
                    referenceType: 'supplier_payment',
                    referenceId: $payment->id,
                    referenceNumber: $paymentNumber,
                    description: "Supplier Payment {$paymentNumber}" . ($referenceNumber ? " (Ref: {$referenceNumber})" : ''),
                    user: $user
                );

                if ($financialAccountId) {
                    $finAccount = FinancialAccount::findOrFail($financialAccountId);
                    if ($finAccount->location_id && $finAccount->location_id !== $location->id) {
                        throw new InvalidArgumentException("Selected financial account does not belong to payment location.");
                    }
                    $this->cashAndBankService->recordTransaction(
                        account: $finAccount,
                        transactionType: 'supplier_payment',
                        debitAmount: '0.00',
                        creditAmount: $amount,
                        transactionDate: $paymentDate,
                        user: $user,
                        description: "Supplier payment to {$supplier->business_name} (Payment #{$paymentNumber})",
                        referenceType: SupplierPayment::class,
                        referenceId: $payment->id,
                        referenceNumber: $paymentNumber,
                        idempotencyKey: $idempotencyKey ? "cbr-sp-{$idempotencyKey}" : "cbr-sp-{$payment->id}"
                    );
                }

                return $payment->fresh(['supplier', 'location', 'purchaseOrder']);
            });
        } catch (QueryException $e) {
            if ($idempotencyKey && $this->isIdempotencyCollision($e, 'supplier_payments')) {
                // The failed transaction was already rolled back cleanly by DB::transaction.
                // We re-query the existing record cleanly outside the failed transaction.
                $existing = SupplierPayment::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    if ($existing->request_hash && !hash_equals($existing->request_hash, $payloadHash)) {
                        throw new InvalidArgumentException("Idempotency key '{$idempotencyKey}' has already been used with a different request payload.");
                    }
                    return $existing->load(['supplier', 'location', 'purchaseOrder']);
                }
            }
            throw $e;
        }
    }

    /**
     * Synchronize items and calculate totals for a purchase order.
     */
    protected function syncOrderItemsAndTotals(PurchaseOrder $order, array $itemsData, string $overallDiscount, string $additionalCharges): void
    {
        $totalCartons = 0;
        $totalPairs = '0.0000';
        $subtotalAmount = '0.00';

        foreach ($itemsData as $itemData) {
            $variantId = (int) $itemData['product_variant_id'];
            $variant = ProductVariant::with('product')->findOrFail($variantId);

            if (!$variant->is_active || !$variant->product->is_active) {
                throw new InvalidArgumentException("Product variant {$variant->sku} is inactive and cannot be ordered.");
            }

            $unitType = $itemData['unit_type'] ?? 'carton';
            $packRatio = $variant->product->carton_pack_qty > 0 ? $variant->product->carton_pack_qty : 12;

            if ($unitType === 'carton') {
                $qtyCartons = (float) $itemData['quantity'];
                if ($qtyCartons <= 0) {
                    throw new InvalidArgumentException("Quantity in cartons must be greater than zero.");
                }
                $qtyPairs = (float) bcmul((string) $qtyCartons, (string) $packRatio, 4);
                $unitCost = (float) $itemData['unit_cost'];
                if ($unitCost < 0) {
                    throw new InvalidArgumentException("Unit cost cannot be negative.");
                }
                $unitCostPerPair = (float) bcdiv((string) $unitCost, (string) $packRatio, 4);
                $lineSubtotal = (string) bcmul((string) $qtyCartons, (string) $unitCost, 2);
                $totalCartons += (int) ceil($qtyCartons);
            } else {
                $qtyPairs = (float) $itemData['quantity'];
                if ($qtyPairs <= 0) {
                    throw new InvalidArgumentException("Quantity in pairs must be greater than zero.");
                }
                $qtyCartons = (float) bcdiv((string) $qtyPairs, (string) $packRatio, 4);
                $unitCost = (float) $itemData['unit_cost'];
                if ($unitCost < 0) {
                    throw new InvalidArgumentException("Unit cost cannot be negative.");
                }
                $unitCostPerPair = $unitCost;
                $lineSubtotal = (string) bcmul((string) $qtyPairs, (string) $unitCost, 2);
                $totalCartons += (int) ceil($qtyCartons);
            }

            $lineDiscount = isset($itemData['line_discount']) ? number_format((float) $itemData['line_discount'], 2, '.', '') : '0.00';
            if (bccomp($lineDiscount, '0.00', 2) < 0) {
                throw new InvalidArgumentException("Line discount cannot be negative.");
            }

            $lineTotal = bcsub($lineSubtotal, $lineDiscount, 2);
            if (bccomp($lineTotal, '0.00', 2) < 0) {
                throw new InvalidArgumentException("Line total cannot be negative after line discount.");
            }

            PurchaseOrderItem::create([
                'purchase_order_id' => $order->id,
                'product_variant_id' => $variant->id,
                'unit_type' => $unitType,
                'quantity_cartons' => $qtyCartons,
                'carton_pack_qty' => $packRatio,
                'ordered_pairs' => $qtyPairs,
                'received_pairs' => '0.0000',
                'unit_cost' => $unitCost,
                'unit_cost_per_pair' => $unitCostPerPair,
                'line_subtotal' => $lineSubtotal,
                'line_discount' => $lineDiscount,
                'line_total' => $lineTotal,
                'notes' => $itemData['notes'] ?? null,
            ]);

            $totalPairs = bcadd($totalPairs, (string) $qtyPairs, 4);
            $subtotalAmount = bcadd($subtotalAmount, $lineTotal, 2);
        }

        $totalAmount = bcadd(bcsub($subtotalAmount, $overallDiscount, 2), $additionalCharges, 2);
        if (bccomp($totalAmount, '0.00', 2) < 0) {
            $totalAmount = '0.00';
        }

        $order->total_cartons = $totalCartons;
        $order->total_pairs = $totalPairs;
        $order->subtotal_amount = $subtotalAmount;
        $order->discount_amount = $overallDiscount;
        $order->additional_charges = $additionalCharges;
        $order->total_amount = $totalAmount;
        $order->save();
    }

    /**
     * Generate sequential, location-coded unique purchase order number.
     * Format: PO-{LOC_CODE}-{YYYYMMDD}-{XXXX}
     */
    protected function generatePurchaseNumber(Location $location): string
    {
        $dateStr = now()->format('Ymd');
        $prefix = "PO-{$location->code}-{$dateStr}";

        $latest = PurchaseOrder::where('purchase_number', 'like', "{$prefix}-%")
            ->lockForUpdate()
            ->latest('id')
            ->value('purchase_number');

        $nextSeq = 1;
        if ($latest && preg_match('/-(\d{4})$/', $latest, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }

        return sprintf('%s-%04d', $prefix, $nextSeq);
    }

    /**
     * Generate sequential, location-coded unique goods receipt number.
     * Format: GRN-{LOC_CODE}-{YYYYMMDD}-{XXXX}
     */
    protected function generateReceiptNumber(Location $location): string
    {
        $dateStr = now()->format('Ymd');
        $prefix = "GRN-{$location->code}-{$dateStr}";

        $latest = GoodsReceipt::where('receipt_number', 'like', "{$prefix}-%")
            ->lockForUpdate()
            ->latest('id')
            ->value('receipt_number');

        $nextSeq = 1;
        if ($latest && preg_match('/-(\d{4})$/', $latest, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }

        return sprintf('%s-%04d', $prefix, $nextSeq);
    }

    /**
     * Generate sequential, location-coded unique supplier payment number.
     * Format: SPAY-{LOC_CODE}-{YYYYMMDD}-{XXXX}
     */
    protected function generatePaymentNumber(Location $location): string
    {
        $dateStr = now()->format('Ymd');
        $prefix = "SPAY-{$location->code}-{$dateStr}";

        $latest = SupplierPayment::where('payment_number', 'like', "{$prefix}-%")
            ->lockForUpdate()
            ->latest('id')
            ->value('payment_number');

        $nextSeq = 1;
        if ($latest && preg_match('/-(\d{4})$/', $latest, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }

        return sprintf('%s-%04d', $prefix, $nextSeq);
    }

    /**
     * Compute a deterministic canonical hash of a goods receipt request payload.
     * Normalizes carton and pair quantities to equivalent pairs using the PO item pack ratio.
     */
    public function computeReceiptPayloadHash(PurchaseOrder $order, array $data): string
    {
        if (empty($data['items']) || !is_array($data['items'])) {
            throw new InvalidArgumentException("At least one item must be received.");
        }

        $order->loadMissing('items');
        $poItems = $order->items->keyBy('id');

        $items = [];
        foreach ($data['items'] as $item) {
            $poItemId = (int) ($item['purchase_order_item_id'] ?? $item['item_id'] ?? 0);
            if ($poItemId <= 0) {
                throw new InvalidArgumentException("Invalid or missing purchase order item ID in receipt payload.");
            }

            $poItem = $poItems->get($poItemId);
            if (!$poItem || $poItem->purchase_order_id !== $order->id) {
                throw new InvalidArgumentException("Purchase order item #{$poItemId} does not belong to PO #{$order->purchase_number}.");
            }

            $packRatio = $poItem->carton_pack_qty > 0 ? (int) $poItem->carton_pack_qty : 12;

            // Determine canonical quantity in pairs and validate consistency
            if (isset($item['received_cartons']) && isset($item['received_pairs'])) {
                $calcPairs = (float) bcmul((string) $item['received_cartons'], (string) $packRatio, 4);
                if (bccomp((string) $calcPairs, (string) $item['received_pairs'], 4) !== 0) {
                    throw new InvalidArgumentException("Inconsistent carton and pair quantities provided for PO item #{$poItemId}.");
                }
                $pairs = (float) $item['received_pairs'];
            } elseif (isset($item['received_cartons'])) {
                $cartons = (float) $item['received_cartons'];
                $pairs = (float) bcmul((string) $cartons, (string) $packRatio, 4);
            } elseif (isset($item['received_pairs'])) {
                $pairs = (float) $item['received_pairs'];
            } elseif (isset($item['quantity'])) {
                $unitType = $item['unit_type'] ?? $poItem->unit_type;
                $qty = (float) $item['quantity'];
                if ($unitType === 'carton') {
                    $pairs = (float) bcmul((string) $qty, (string) $packRatio, 4);
                } else {
                    $pairs = $qty;
                }
            } else {
                throw new InvalidArgumentException("Missing quantity for received item #{$poItemId}.");
            }

            if ($pairs < 0) {
                throw new InvalidArgumentException("Received quantity cannot be negative.");
            }

            if ($pairs == 0) {
                continue; // Skip 0-quantity rows
            }

            $items[$poItemId] = number_format($pairs, 4, '.', '');
        }

        ksort($items);

        $canonical = [
            'po_id' => $order->id,
            'location_id' => $order->location_id,
            'supplier_id' => $order->supplier_id,
            'receipt_date' => $data['receipt_date'] ?? now()->toDateString(),
            'challan_number' => trim((string) ($data['challan_number'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
            'items' => $items,
        ];

        return hash('sha256', json_encode($canonical));
    }

    /**
     * Compute a deterministic canonical hash of a supplier payment request payload.
     */
    public function computePaymentPayloadHash(Supplier $supplier, array $data, int $locationId, ?int $purchaseOrderId, float $amount): string
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Payment amount must be greater than zero. Received: {$amount}");
        }

        $canonical = [
            'supplier_id' => $supplier->id,
            'location_id' => $locationId,
            'purchase_order_id' => $purchaseOrderId,
            'financial_account_id' => !empty($data['financial_account_id']) ? (int) $data['financial_account_id'] : null,
            'amount' => number_format($amount, 2, '.', ''),
            'payment_date' => $data['payment_date'] ?? now()->toDateString(),
            'payment_method' => $data['payment_method'] ?? 'cash',
            'reference_number' => trim((string) ($data['reference_number'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
        ];

        return hash('sha256', json_encode($canonical));
    }

    /**
     * Determine if a QueryException was caused by an idempotency_key unique constraint violation.
     */
    protected function isIdempotencyCollision(QueryException $e, string $table): bool
    {
        $code = (int) $e->getCode();
        $sqlState = (string) ($e->errorInfo[0] ?? $code);
        $driverCode = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : null;
        $msg = $e->getMessage();

        // Unique constraint violation: SQLSTATE 23000, MySQL/MariaDB driver error 1062, SQLite driver error 19
        $isUniqueViolation = ($code === 23000 || $sqlState === '23000' || $driverCode === 1062 || $driverCode === 19);

        if (!$isUniqueViolation) {
            return false;
        }

        $uniqueConstraintName = "{$table}_idempotency_unique";

        return str_contains($msg, $uniqueConstraintName)
            || str_contains($msg, 'idempotency_key')
            || (str_contains($msg, '1062') && str_contains($msg, 'idempotency'));
    }

    /**
     * Calculate remaining returnable pairs for a GoodsReceiptItem.
     */
    public function getRemainingReturnablePairs(GoodsReceiptItem $item, ?int $excludeReturnId = null): float
    {
        $recv = (float) $item->received_pairs;

        $postedReturns = PurchaseReturnItem::where('goods_receipt_item_id', $item->id)
            ->whereHas('purchaseReturn', function ($q) use ($excludeReturnId) {
                $q->where('status', 'posted');
                if ($excludeReturnId) {
                    $q->where('id', '!=', $excludeReturnId);
                }
            })
            ->sum('returned_pairs');

        return max(0.0, (float) bcsub((string) $recv, (string) $postedReturns, 4));
    }

    /**
     * Create a new draft purchase return without altering inventory or supplier khata.
     */
    public function createDraftReturn(GoodsReceipt $receipt, array $data, User $user): PurchaseReturn
    {
        if (!$user->hasLocationAccess($receipt->location_id)) {
            throw new AuthorizationException("User #{$user->id} does not have authorization for location #{$receipt->location_id}.");
        }

        if (empty($data['items']) || !is_array($data['items'])) {
            throw new InvalidArgumentException("At least one item must be specified for purchase return.");
        }

        if (empty($data['reason'])) {
            throw new InvalidArgumentException("Return reason is required.");
        }

        return DB::transaction(function () use ($receipt, $data, $user) {
            $returnNumber = $this->generateReturnNumber($receipt->location);
            $returnDate = $data['return_date'] ?? now()->toDateString();
            $reason = trim((string) $data['reason']);
            $notes = isset($data['notes']) ? trim((string) $data['notes']) : null;

            $receipt->loadMissing('items.orderItem');
            $receiptItems = $receipt->items->keyBy('id');

            $itemsToProcess = [];
            $totalCartons = '0.0000';
            $totalPairs = '0.0000';
            $totalAmount = '0.00';

            foreach ($data['items'] as $itemInput) {
                $griId = (int) ($itemInput['goods_receipt_item_id'] ?? $itemInput['item_id'] ?? 0);
                $gri = $receiptItems->get($griId);

                if (!$gri) {
                    throw new InvalidArgumentException("Goods receipt item #{$griId} does not belong to GRN #{$receipt->receipt_number}.");
                }

                $packRatio = $gri->orderItem?->carton_pack_qty > 0 ? (int) $gri->orderItem->carton_pack_qty : 12;

                $rawCartons = $itemInput['returned_cartons'] ?? $itemInput['return_cartons'] ?? null;
                $rawPairs = $itemInput['returned_pairs'] ?? $itemInput['return_pairs'] ?? null;

                // Determine returned pairs and validate representation consistency
                if ($rawCartons !== null && $rawPairs !== null) {
                    $calcPairs = (float) bcmul((string) $rawCartons, (string) $packRatio, 4);
                    if (bccomp((string) $calcPairs, (string) $rawPairs, 4) !== 0) {
                        throw new InvalidArgumentException("Inconsistent carton and pair quantities provided for receipt item #{$griId}.");
                    }
                    $pairs = (float) $rawPairs;
                    $cartons = (float) $rawCartons;
                } elseif ($rawCartons !== null) {
                    $cartons = (float) $rawCartons;
                    $pairs = (float) bcmul((string) $cartons, (string) $packRatio, 4);
                } elseif ($rawPairs !== null) {
                    $pairs = (float) $rawPairs;
                    $cartons = (float) bcdiv((string) $pairs, (string) $packRatio, 4);
                } elseif (isset($itemInput['quantity'])) {
                    $unitType = $itemInput['unit_type'] ?? 'pair';
                    $qty = (float) $itemInput['quantity'];
                    if ($unitType === 'carton') {
                        $cartons = $qty;
                        $pairs = (float) bcmul((string) $cartons, (string) $packRatio, 4);
                    } else {
                        $pairs = $qty;
                        $cartons = (float) bcdiv((string) $pairs, (string) $packRatio, 4);
                    }
                } else {
                    throw new InvalidArgumentException("Missing return quantity for receipt item #{$griId}.");
                }

                if ($pairs < 0) {
                    throw new InvalidArgumentException("Return quantity cannot be negative.");
                }

                if ($pairs == 0) {
                    continue; // Skip 0-quantity rows
                }

                $remainingPairs = $this->getRemainingReturnablePairs($gri);
                if (bccomp((string) $pairs, (string) $remainingPairs, 4) > 0) {
                    throw new InvalidArgumentException("Cannot return {$pairs} pairs for receipt item #{$griId}. Remaining returnable pairs: {$remainingPairs}. Return quantity exceeds remaining returnable.");
                }

                $unitCostPerPair = (float) $gri->unit_cost_per_pair;
                $lineTotal = (string) bcmul((string) $pairs, (string) $unitCostPerPair, 2);

                $itemsToProcess[] = [
                    'gri' => $gri,
                    'product_variant_id' => $gri->product_variant_id,
                    'purchase_order_item_id' => $gri->purchase_order_item_id,
                    'returned_cartons' => $cartons,
                    'returned_pairs' => $pairs,
                    'unit_cost_per_pair' => $unitCostPerPair,
                    'total_cost' => $lineTotal,
                    'reason' => $itemInput['reason'] ?? $reason,
                    'notes' => $itemInput['notes'] ?? null,
                ];

                $totalCartons = bcadd($totalCartons, (string) $cartons, 4);
                $totalPairs = bcadd($totalPairs, (string) $pairs, 4);
                $totalAmount = bcadd($totalAmount, $lineTotal, 2);
            }

            if (empty($itemsToProcess)) {
                throw new InvalidArgumentException("No items with positive return quantities provided.");
            }

            $purchaseReturn = PurchaseReturn::create([
                'return_number' => $returnNumber,
                'goods_receipt_id' => $receipt->id,
                'purchase_order_id' => $receipt->purchase_order_id,
                'supplier_id' => $receipt->supplier_id,
                'location_id' => $receipt->location_id,
                'return_date' => $returnDate,
                'status' => 'draft',
                'total_returned_cartons' => $totalCartons,
                'total_returned_pairs' => $totalPairs,
                'total_return_amount' => $totalAmount,
                'reason' => $reason,
                'notes' => $notes,
                'created_by' => $user->id,
            ]);

            foreach ($itemsToProcess as $item) {
                PurchaseReturnItem::create([
                    'purchase_return_id' => $purchaseReturn->id,
                    'goods_receipt_item_id' => $item['gri']->id,
                    'purchase_order_item_id' => $item['purchase_order_item_id'],
                    'product_variant_id' => $item['product_variant_id'],
                    'returned_cartons' => $item['returned_cartons'],
                    'returned_pairs' => $item['returned_pairs'],
                    'unit_cost_per_pair' => $item['unit_cost_per_pair'],
                    'total_cost' => $item['total_cost'],
                    'reason' => $item['reason'],
                    'notes' => $item['notes'],
                ]);
            }

            return $purchaseReturn->fresh(['items.productVariant.product', 'goodsReceipt', 'supplier', 'location']);
        });
    }

    /**
     * Atomically post a purchase return:
     * 1. Pessimistically lock return and receipt items to prevent over-returns.
     * 2. Deduct inventory via InventoryService (throws InsufficientStockException if stock < returned pairs).
     * 3. Update return status to 'posted'.
     * 4. Post supplier ledger DEBIT entry (reduces payable).
     * 5. Generate linked stock-related SupplierDebitNote (does not post duplicate ledger entry).
     */
    public function postReturn(PurchaseReturn $return, array $data, User $user): PurchaseReturn
    {
        if (!$user->hasLocationAccess($return->location_id)) {
            throw new AuthorizationException("User #{$user->id} does not have authorization for location #{$return->location_id}.");
        }

        $idempotencyKey = !empty($data['idempotency_key']) ? trim((string) $data['idempotency_key']) : null;
        $payloadHash = $this->computeReturnPayloadHash($return);

        // Fast-path: Check if an idempotent return was already posted
        if ($idempotencyKey) {
            $existing = PurchaseReturn::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->request_hash && !hash_equals($existing->request_hash, $payloadHash)) {
                    throw new InvalidArgumentException("Idempotency key '{$idempotencyKey}' has already been used with a different request payload.");
                }
                return $existing->load(['items.productVariant.product', 'goodsReceipt', 'supplier', 'location', 'debitNote']);
            }
        }

        if ($return->status !== 'draft') {
            throw new RuntimeException("Only draft purchase returns can be posted. Current status: {$return->status}");
        }

        try {
            return DB::transaction(function () use ($return, $user, $idempotencyKey, $payloadHash) {
                $lockedReturn = PurchaseReturn::where('id', $return->id)->lockForUpdate()->firstOrFail();

                if ($lockedReturn->status !== 'draft') {
                    throw new RuntimeException("Purchase return #{$lockedReturn->return_number} is already posted.");
                }

                $items = $lockedReturn->items()->with('goodsReceiptItem')->get();
                if ($items->isEmpty()) {
                    throw new InvalidArgumentException("Cannot post a purchase return with no items.");
                }

                // Verify returnable quantity limits under pessimistic row locks
                foreach ($items as $item) {
                    $gri = GoodsReceiptItem::where('id', $item->goods_receipt_item_id)->lockForUpdate()->firstOrFail();
                    $remainingReturnable = $this->getRemainingReturnablePairs($gri, $lockedReturn->id);
                    if (bccomp((string) $item->returned_pairs, (string) $remainingReturnable, 4) > 0) {
                        throw new InvalidArgumentException("Cannot return {$item->returned_pairs} pairs for receipt item #{$gri->id}. Remaining returnable pairs: {$remainingReturnable}.");
                    }
                }

                // Sort items by product_variant_id ASC for deadlock-free inventory deduction
                $sortedItems = $items->sortBy('product_variant_id');

                foreach ($sortedItems as $item) {
                    $movement = $this->inventoryService->deductStock(
                        locationId: $lockedReturn->location_id,
                        variantId: $item->product_variant_id,
                        quantityPairs: (float) $item->returned_pairs,
                        movementType: 'purchase_return',
                        user: $user,
                        referenceType: 'purchase_return',
                        referenceId: $lockedReturn->id,
                        notes: "Purchase Return #{$lockedReturn->return_number} for GRN #{$lockedReturn->goodsReceipt->receipt_number}"
                    );

                    $item->stock_movement_id = $movement->id;
                    $item->save();
                }

                $lockedReturn->status = 'posted';
                $lockedReturn->posted_at = now();
                $lockedReturn->posted_by = $user->id;
                $lockedReturn->idempotency_key = $idempotencyKey;
                $lockedReturn->request_hash = $payloadHash;
                $lockedReturn->save();

                // Post Supplier Khata Ledger Debit (debit decreases payable)
                $this->khataService->recordEntry(
                    supplier: $lockedReturn->supplier,
                    locationId: $lockedReturn->location_id,
                    entryType: 'purchase_return',
                    debitAmount: (float) $lockedReturn->total_return_amount,
                    entryDate: $lockedReturn->return_date->toDateString(),
                    referenceType: 'purchase_return',
                    referenceId: $lockedReturn->id,
                    referenceNumber: $lockedReturn->return_number,
                    description: "Purchase Return {$lockedReturn->return_number} (GRN: {$lockedReturn->goodsReceipt->receipt_number})",
                    user: $user
                );

                // Create linked stock-related SupplierDebitNote (does not post duplicate ledger entry)
                $debitNoteNumber = $this->generateDebitNoteNumber($lockedReturn->location);
                SupplierDebitNote::create([
                    'debit_note_number' => $debitNoteNumber,
                    'supplier_id' => $lockedReturn->supplier_id,
                    'location_id' => $lockedReturn->location_id,
                    'debit_note_type' => 'purchase_return',
                    'purchase_return_id' => $lockedReturn->id,
                    'purchase_order_id' => $lockedReturn->purchase_order_id,
                    'note_date' => $lockedReturn->return_date,
                    'amount' => $lockedReturn->total_return_amount,
                    'reason_type' => 'goods_return',
                    'reason' => $lockedReturn->reason,
                    'reference_number' => $lockedReturn->return_number,
                    'notes' => "Stock purchase return #{$lockedReturn->return_number}",
                    'status' => 'posted',
                    'posted_at' => now(),
                    'posted_by' => $user->id,
                    'created_by' => $user->id,
                ]);

                return $lockedReturn->fresh(['items.productVariant.product', 'goodsReceipt', 'supplier', 'location', 'debitNote']);
            });
        } catch (QueryException $e) {
            if ($idempotencyKey && $this->isIdempotencyCollision($e, 'purchase_returns')) {
                $existing = PurchaseReturn::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    if ($existing->request_hash && !hash_equals($existing->request_hash, $payloadHash)) {
                        throw new InvalidArgumentException("Idempotency key '{$idempotencyKey}' has already been used with a different request payload.");
                    }
                    return $existing->load(['items.productVariant.product', 'goodsReceipt', 'supplier', 'location', 'debitNote']);
                }
            }
            throw $e;
        }
    }

    /**
     * Record an approved financial-only debit note against a supplier (non-stock adjustment).
     * Posts exactly one supplier ledger Debit entry and does not touch inventory.
     */
    public function recordFinancialDebitNote(Supplier $supplier, array $data, User $user): SupplierDebitNote
    {
        $locationId = (int) ($data['location_id'] ?? $supplier->primary_location_id);
        $amount = (float) $data['amount'];

        if (!$user->hasLocationAccess($locationId)) {
            throw new AuthorizationException("User #{$user->id} does not have authorization for location #{$locationId}.");
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException("Debit note amount must be greater than zero. Received: {$amount}");
        }

        if (empty($data['reason'])) {
            throw new InvalidArgumentException("Debit note reason is required.");
        }

        $location = Location::findOrFail($locationId);
        $purchaseOrderId = isset($data['purchase_order_id']) && $data['purchase_order_id'] ? (int) $data['purchase_order_id'] : null;

        $idempotencyKey = !empty($data['idempotency_key']) ? trim((string) $data['idempotency_key']) : null;
        $payloadHash = $this->computeDebitNotePayloadHash($supplier, $data, $locationId, $amount);

        // Fast-path: Check if an idempotent debit note was already recorded
        if ($idempotencyKey) {
            $existing = SupplierDebitNote::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->request_hash && !hash_equals($existing->request_hash, $payloadHash)) {
                    throw new InvalidArgumentException("Idempotency key '{$idempotencyKey}' has already been used with a different request payload.");
                }
                return $existing->load(['supplier', 'location', 'purchaseOrder']);
            }
        }

        try {
            return DB::transaction(function () use ($supplier, $location, $purchaseOrderId, $amount, $data, $user, $idempotencyKey, $payloadHash) {
                $debitNoteNumber = $this->generateDebitNoteNumber($location);
                $noteDate = $data['note_date'] ?? now()->toDateString();
                $reasonType = $data['reason_type'] ?? 'other';
                $reason = trim((string) $data['reason']);
                $referenceNumber = isset($data['reference_number']) ? trim((string) $data['reference_number']) : null;
                $notes = isset($data['notes']) ? trim((string) $data['notes']) : null;

                $debitNote = SupplierDebitNote::create([
                    'debit_note_number' => $debitNoteNumber,
                    'supplier_id' => $supplier->id,
                    'location_id' => $location->id,
                    'debit_note_type' => 'financial_adjustment',
                    'purchase_return_id' => null,
                    'purchase_order_id' => $purchaseOrderId,
                    'note_date' => $noteDate,
                    'amount' => $amount,
                    'reason_type' => $reasonType,
                    'reason' => $reason,
                    'reference_number' => $referenceNumber,
                    'notes' => $notes,
                    'status' => 'posted',
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $payloadHash,
                    'posted_at' => now(),
                    'posted_by' => $user->id,
                    'created_by' => $user->id,
                ]);

                // Debit Supplier Khata (Financial debit reduces payable)
                $this->khataService->recordEntry(
                    supplier: $supplier,
                    locationId: $location->id,
                    entryType: 'adjustment_debit',
                    debitAmount: $amount,
                    entryDate: $noteDate,
                    referenceType: 'supplier_debit_note',
                    referenceId: $debitNote->id,
                    referenceNumber: $debitNoteNumber,
                    description: "Supplier Debit Note {$debitNoteNumber} ({$reason})" . ($referenceNumber ? " [Ref: {$referenceNumber}]" : ''),
                    user: $user
                );

                return $debitNote->fresh(['supplier', 'location', 'purchaseOrder']);
            });
        } catch (QueryException $e) {
            if ($idempotencyKey && $this->isIdempotencyCollision($e, 'supplier_debit_notes')) {
                $existing = SupplierDebitNote::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    if ($existing->request_hash && !hash_equals($existing->request_hash, $payloadHash)) {
                        throw new InvalidArgumentException("Idempotency key '{$idempotencyKey}' has already been used with a different request payload.");
                    }
                    return $existing->load(['supplier', 'location', 'purchaseOrder']);
                }
            }
            throw $e;
        }
    }

    /**
     * Compute a deterministic canonical hash of a purchase return post request.
     */
    public function computeReturnPayloadHash(PurchaseReturn $return): string
    {
        $items = [];
        foreach ($return->items as $item) {
            $items[$item->goods_receipt_item_id] = number_format((float) $item->returned_pairs, 4, '.', '');
        }
        ksort($items);

        $canonical = [
            'return_id' => $return->id,
            'goods_receipt_id' => $return->goods_receipt_id,
            'supplier_id' => $return->supplier_id,
            'location_id' => $return->location_id,
            'return_date' => $return->return_date->toDateString(),
            'total_amount' => number_format((float) $return->total_return_amount, 2, '.', ''),
            'reason' => trim((string) $return->reason),
            'items' => $items,
        ];

        return hash('sha256', json_encode($canonical));
    }

    /**
     * Compute a deterministic canonical hash of a financial-only supplier debit note request.
     */
    public function computeDebitNotePayloadHash(Supplier $supplier, array $data, int $locationId, float $amount): string
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Debit note amount must be greater than zero.");
        }

        $canonical = [
            'supplier_id' => $supplier->id,
            'location_id' => $locationId,
            'purchase_order_id' => $data['purchase_order_id'] ?? null,
            'amount' => number_format($amount, 2, '.', ''),
            'note_date' => $data['note_date'] ?? now()->toDateString(),
            'reason_type' => $data['reason_type'] ?? 'other',
            'reason' => trim((string) ($data['reason'] ?? '')),
            'reference_number' => trim((string) ($data['reference_number'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
        ];

        return hash('sha256', json_encode($canonical));
    }

    /**
     * Generate sequential, location-coded unique purchase return number.
     * Format: PRET-{LOC_CODE}-{YYYYMMDD}-{XXXX}
     */
    protected function generateReturnNumber(Location $location): string
    {
        $dateStr = now()->format('Ymd');
        $prefix = "PRET-{$location->code}-{$dateStr}";

        $latest = PurchaseReturn::where('return_number', 'like', "{$prefix}-%")
            ->lockForUpdate()
            ->latest('id')
            ->value('return_number');

        $nextSeq = 1;
        if ($latest && preg_match('/-(\d{4})$/', $latest, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }

        return sprintf('%s-%04d', $prefix, $nextSeq);
    }

    /**
     * Generate sequential, location-coded unique supplier debit note number.
     * Format: SDN-{LOC_CODE}-{YYYYMMDD}-{XXXX}
     */
    protected function generateDebitNoteNumber(Location $location): string
    {
        $dateStr = now()->format('Ymd');
        $prefix = "SDN-{$location->code}-{$dateStr}";

        $latest = SupplierDebitNote::where('debit_note_number', 'like', "{$prefix}-%")
            ->lockForUpdate()
            ->latest('id')
            ->value('debit_note_number');

        $nextSeq = 1;
        if ($latest && preg_match('/-(\d{4})$/', $latest, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }

        return sprintf('%s-%04d', $prefix, $nextSeq);
    }
}
