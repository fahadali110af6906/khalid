<?php

namespace App\Services\Sales;

use App\Models\Customer;
use App\Models\CustomerCreditNote;
use App\Models\CustomerRefund;
use App\Models\FinancialAccount;
use App\Models\Location;
use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\User;
use App\Services\Accounting\CashAndBankService;
use App\Services\Accounting\CustomerKhataService;
use App\Services\Inventory\InventoryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class SalesReturnService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected CustomerKhataService $khataService,
        protected ?CashAndBankService $cashAndBankService = null
    ) {
        $this->cashAndBankService = $cashAndBankService ?? app(CashAndBankService::class);
    }

    /**
     * Calculate remaining returnable pairs for a SalesInvoiceItem.
     */
    public function getRemainingReturnablePairs(SalesInvoiceItem $item, ?int $excludeReturnId = null): float
    {
        $sold = (float) $item->quantity_pairs;

        $postedReturns = SalesReturnItem::where('sales_invoice_item_id', $item->id)
            ->whereHas('salesReturn', function ($q) use ($excludeReturnId) {
                $q->where('status', 'posted');
                if ($excludeReturnId) {
                    $q->where('id', '!=', $excludeReturnId);
                }
            })
            ->sum('returned_pairs');

        return max(0.0, (float) bcsub((string) $sold, (string) $postedReturns, 4));
    }

    /**
     * Create a new draft sales return without altering inventory or customer khata.
     */
    public function createDraftReturn(SalesInvoice $invoice, array $data, User $user): SalesReturn
    {
        if (!$user->hasLocationAccess($invoice->location_id)) {
            throw new AuthorizationException("User #{$user->id} does not have authorization for location #{$invoice->location_id}.");
        }

        if ($invoice->status !== 'posted') {
            throw new InvalidArgumentException("Sales returns can only be created for posted invoices. Current invoice status: {$invoice->status}.");
        }

        if (empty($data['items']) || !is_array($data['items'])) {
            throw new InvalidArgumentException("At least one item must be specified for sales return.");
        }

        if (empty($data['reason'])) {
            throw new InvalidArgumentException("Return reason is required.");
        }

        return DB::transaction(function () use ($invoice, $data, $user) {
            $returnNumber = $this->generateReturnNumber($invoice->location);
            $returnDate = $data['return_date'] ?? now()->toDateString();
            $reason = trim((string) $data['reason']);
            $notes = isset($data['notes']) ? trim((string) $data['notes']) : null;

            $invoice->loadMissing('items.variant.product');
            $invoiceItems = $invoice->items->keyBy('id');

            $itemsToProcess = [];
            $totalCartons = '0.0000';
            $totalPairs = '0.0000';
            $totalAmount = '0.00';

            foreach ($data['items'] as $itemInput) {
                $siiId = (int) ($itemInput['sales_invoice_item_id'] ?? $itemInput['item_id'] ?? 0);
                $sii = $invoiceItems->get($siiId);

                if (!$sii) {
                    throw new InvalidArgumentException("Invoice item #{$siiId} does not belong to invoice #{$invoice->invoice_number}.");
                }

                $packRatio = $sii->carton_pack_qty > 0 ? (int) $sii->carton_pack_qty : 12;

                $rawCartons = $itemInput['returned_cartons'] ?? $itemInput['return_cartons'] ?? null;
                $rawPairs = $itemInput['returned_pairs'] ?? $itemInput['return_pairs'] ?? null;

                if ($rawCartons !== null && $rawPairs !== null) {
                    $calcPairs = (float) bcmul((string) $rawCartons, (string) $packRatio, 4);
                    if (bccomp((string) $calcPairs, (string) $rawPairs, 4) !== 0) {
                        throw new InvalidArgumentException("Inconsistent carton and pair quantities provided for invoice item #{$siiId}.");
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
                    throw new InvalidArgumentException("Missing return quantity for invoice item #{$siiId}.");
                }

                if ($pairs < 0) {
                    throw new InvalidArgumentException("Return quantity cannot be negative.");
                }

                if ($pairs == 0) {
                    continue; // Skip 0-quantity rows
                }

                $remainingPairs = $this->getRemainingReturnablePairs($sii);
                if (bccomp((string) $pairs, (string) $remainingPairs, 4) > 0) {
                    throw new InvalidArgumentException("Cannot return {$pairs} pairs for invoice item #{$siiId}. Remaining returnable pairs: {$remainingPairs}. Return quantity exceeds remaining returnable.");
                }

                $unitPricePerPair = (float) $sii->unit_price_per_pair;
                $lineTotal = (string) bcmul((string) $pairs, (string) $unitPricePerPair, 2);
                $unitCost = (float) $sii->unit_cost;
                $isRestocked = isset($itemInput['is_restocked']) ? (bool) $itemInput['is_restocked'] : true;
                $condition = $itemInput['condition'] ?? 'resalable';

                $itemsToProcess[] = [
                    'sii' => $sii,
                    'product_variant_id' => $sii->product_variant_id,
                    'returned_cartons' => $cartons,
                    'returned_pairs' => $pairs,
                    'unit_price_per_pair' => $unitPricePerPair,
                    'total_amount' => $lineTotal,
                    'unit_cost' => $unitCost,
                    'is_restocked' => $isRestocked,
                    'condition' => $condition,
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

            $salesReturn = SalesReturn::create([
                'return_number' => $returnNumber,
                'sales_invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'location_id' => $invoice->location_id,
                'return_date' => $returnDate,
                'status' => 'draft',
                'total_returned_cartons' => $totalCartons,
                'total_returned_pairs' => $totalPairs,
                'total_return_amount' => $totalAmount,
                'refund_status' => 'credited_to_khata',
                'refunded_amount' => '0.00',
                'reason' => $reason,
                'notes' => $notes,
                'created_by' => $user->id,
            ]);

            foreach ($itemsToProcess as $item) {
                SalesReturnItem::create([
                    'sales_return_id' => $salesReturn->id,
                    'sales_invoice_item_id' => $item['sii']->id,
                    'product_variant_id' => $item['product_variant_id'],
                    'returned_cartons' => $item['returned_cartons'],
                    'returned_pairs' => $item['returned_pairs'],
                    'unit_price_per_pair' => $item['unit_price_per_pair'],
                    'total_amount' => $item['total_amount'],
                    'unit_cost' => $item['unit_cost'],
                    'is_restocked' => $item['is_restocked'],
                    'condition' => $item['condition'],
                    'reason' => $item['reason'],
                    'notes' => $item['notes'],
                ]);
            }

            return $salesReturn->fresh(['items.productVariant.product', 'salesInvoice', 'customer', 'location']);
        });
    }

    /**
     * Atomically post a sales return:
     * 1. Pessimistically lock return and invoice items to prevent over-returns.
     * 2. Restock inventory via InventoryService::addStock (movementType: 'sale_return') if is_restocked is true.
     * 3. Update return status to 'posted'.
     * 4. Post customer ledger CREDIT entry (credit decreases customer receivable).
     * 5. Generate linked CustomerCreditNote (does not post duplicate ledger entry).
     */
    public function postReturn(SalesReturn $return, array $data, User $user): SalesReturn
    {
        if (!$user->hasLocationAccess($return->location_id)) {
            throw new AuthorizationException("User #{$user->id} does not have authorization for location #{$return->location_id}.");
        }

        $idempotencyKey = !empty($data['idempotency_key']) ? trim((string) $data['idempotency_key']) : null;
        $payloadHash = $this->computeReturnPayloadHash($return);

        // Fast-path: Check if an idempotent return was already posted
        if ($idempotencyKey) {
            $existing = SalesReturn::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->request_hash && !hash_equals($existing->request_hash, $payloadHash)) {
                    throw new InvalidArgumentException("Idempotency key '{$idempotencyKey}' has already been used with a different request payload.");
                }
                return $existing->load(['items.productVariant.product', 'salesInvoice', 'customer', 'location', 'creditNote']);
            }
        }

        if ($return->status !== 'draft') {
            throw new RuntimeException("Only draft sales returns can be posted. Current status: {$return->status}");
        }

        try {
            return DB::transaction(function () use ($return, $user, $idempotencyKey, $payloadHash) {
                $lockedReturn = SalesReturn::where('id', $return->id)->lockForUpdate()->firstOrFail();

                if ($lockedReturn->status !== 'draft') {
                    throw new RuntimeException("Sales return #{$lockedReturn->return_number} is already posted.");
                }

                $items = $lockedReturn->items()->with('invoiceItem')->get();
                if ($items->isEmpty()) {
                    throw new InvalidArgumentException("Cannot post a sales return with no items.");
                }

                // Verify returnable quantity limits under pessimistic row locks
                foreach ($items as $item) {
                    $sii = SalesInvoiceItem::where('id', $item->sales_invoice_item_id)->lockForUpdate()->firstOrFail();
                    $remainingReturnable = $this->getRemainingReturnablePairs($sii, $lockedReturn->id);
                    if (bccomp((string) $item->returned_pairs, (string) $remainingReturnable, 4) > 0) {
                        throw new InvalidArgumentException("Cannot return {$item->returned_pairs} pairs for invoice item #{$sii->id}. Remaining returnable pairs: {$remainingReturnable}.");
                    }
                }

                // Sort items by product_variant_id ASC for deadlock-free inventory replenishment
                $sortedItems = $items->sortBy('product_variant_id');

                foreach ($sortedItems as $item) {
                    if ($item->is_restocked) {
                        $movement = $this->inventoryService->addStock(
                            locationId: $lockedReturn->location_id,
                            variantId: $item->product_variant_id,
                            quantityPairs: (float) $item->returned_pairs,
                            unitCost: (float) $item->unit_cost,
                            movementType: 'sale_return',
                            user: $user,
                            referenceType: 'sales_return',
                            referenceId: $lockedReturn->id,
                            notes: "Sales Return #{$lockedReturn->return_number} for Invoice #{$lockedReturn->salesInvoice->invoice_number}"
                        );

                        $item->stock_movement_id = $movement->id;
                        $item->save();
                    }
                }

                $lockedReturn->status = 'posted';
                $lockedReturn->posted_at = now();
                $lockedReturn->posted_by = $user->id;
                $lockedReturn->idempotency_key = $idempotencyKey;
                $lockedReturn->request_hash = $payloadHash;
                $lockedReturn->save();

                // Post Customer Khata Ledger Credit (credit decreases receivable)
                $this->khataService->recordEntry(
                    customer: $lockedReturn->customer,
                    locationId: $lockedReturn->location_id,
                    entryType: 'sale_return',
                    debitAmount: 0.00,
                    creditAmount: (float) $lockedReturn->total_return_amount,
                    entryDate: $lockedReturn->return_date->toDateString(),
                    user: $user,
                    description: "Sales Return {$lockedReturn->return_number} (Invoice: {$lockedReturn->salesInvoice->invoice_number})",
                    referenceType: 'sales_return',
                    referenceId: $lockedReturn->id,
                    referenceNumber: $lockedReturn->return_number
                );

                // Create linked stock-related CustomerCreditNote (does not post duplicate ledger entry)
                $creditNoteNumber = $this->generateCreditNoteNumber($lockedReturn->location);
                CustomerCreditNote::create([
                    'credit_note_number' => $creditNoteNumber,
                    'customer_id' => $lockedReturn->customer_id,
                    'location_id' => $lockedReturn->location_id,
                    'credit_note_type' => 'sales_return',
                    'sales_return_id' => $lockedReturn->id,
                    'sales_invoice_id' => $lockedReturn->sales_invoice_id,
                    'note_date' => $lockedReturn->return_date,
                    'amount' => $lockedReturn->total_return_amount,
                    'reason_type' => 'goods_return',
                    'reason' => $lockedReturn->reason,
                    'reference_number' => $lockedReturn->return_number,
                    'notes' => "Stock sales return #{$lockedReturn->return_number}",
                    'status' => 'posted',
                    'posted_at' => now(),
                    'posted_by' => $user->id,
                    'created_by' => $user->id,
                ]);

                return $lockedReturn->fresh(['items.productVariant.product', 'salesInvoice', 'customer', 'location', 'creditNote']);
            });
        } catch (QueryException $e) {
            if ($idempotencyKey && $this->isIdempotencyCollision($e, 'sales_returns')) {
                $existing = SalesReturn::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    if ($existing->request_hash && !hash_equals($existing->request_hash, $payloadHash)) {
                        throw new InvalidArgumentException("Idempotency key '{$idempotencyKey}' has already been used with a different request payload.");
                    }
                    return $existing->load(['items.productVariant.product', 'salesInvoice', 'customer', 'location', 'creditNote']);
                }
            }
            throw $e;
        }
    }

    /**
     * Record an approved financial-only credit note against a customer (non-stock adjustment).
     * Posts exactly one customer ledger Credit entry and does not touch inventory.
     */
    public function recordFinancialCreditNote(Customer $customer, array $data, User $user): CustomerCreditNote
    {
        $locationId = (int) ($data['location_id'] ?? $customer->primary_location_id);
        $amount = (float) $data['amount'];

        if (!$user->hasLocationAccess($locationId)) {
            throw new AuthorizationException("User #{$user->id} does not have authorization for location #{$locationId}.");
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException("Credit note amount must be greater than zero. Received: {$amount}");
        }

        if (empty($data['reason'])) {
            throw new InvalidArgumentException("Credit note reason is required.");
        }

        $location = Location::findOrFail($locationId);
        $salesInvoiceId = isset($data['sales_invoice_id']) && $data['sales_invoice_id'] ? (int) $data['sales_invoice_id'] : null;

        $idempotencyKey = !empty($data['idempotency_key']) ? trim((string) $data['idempotency_key']) : null;
        $payloadHash = $this->computeCreditNotePayloadHash($customer, $data, $locationId, $amount);

        // Fast-path: Check if an idempotent credit note was already recorded
        if ($idempotencyKey) {
            $existing = CustomerCreditNote::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->request_hash && !hash_equals($existing->request_hash, $payloadHash)) {
                    throw new InvalidArgumentException("Idempotency key '{$idempotencyKey}' has already been used with a different request payload.");
                }
                return $existing->load(['customer', 'location', 'salesInvoice']);
            }
        }

        try {
            return DB::transaction(function () use ($customer, $location, $salesInvoiceId, $amount, $data, $user, $idempotencyKey, $payloadHash) {
                $creditNoteNumber = $this->generateCreditNoteNumber($location);
                $noteDate = $data['note_date'] ?? now()->toDateString();
                $reasonType = $data['reason_type'] ?? 'other';
                $reason = trim((string) $data['reason']);
                $referenceNumber = isset($data['reference_number']) ? trim((string) $data['reference_number']) : null;
                $notes = isset($data['notes']) ? trim((string) $data['notes']) : null;

                $creditNote = CustomerCreditNote::create([
                    'credit_note_number' => $creditNoteNumber,
                    'customer_id' => $customer->id,
                    'location_id' => $location->id,
                    'credit_note_type' => 'financial_adjustment',
                    'sales_return_id' => null,
                    'sales_invoice_id' => $salesInvoiceId,
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

                // Credit Customer Khata (Financial credit reduces customer receivable)
                $this->khataService->recordEntry(
                    customer: $customer,
                    locationId: $location->id,
                    entryType: 'adjustment_credit',
                    debitAmount: 0.00,
                    creditAmount: $amount,
                    entryDate: $noteDate,
                    user: $user,
                    description: "Customer Credit Note {$creditNoteNumber} ({$reason})" . ($referenceNumber ? " [Ref: {$referenceNumber}]" : ''),
                    referenceType: 'customer_credit_note',
                    referenceId: $creditNote->id,
                    referenceNumber: $creditNoteNumber
                );

                return $creditNote->fresh(['customer', 'location', 'salesInvoice']);
            });
        } catch (QueryException $e) {
            if ($idempotencyKey && $this->isIdempotencyCollision($e, 'customer_credit_notes')) {
                $existing = CustomerCreditNote::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    if ($existing->request_hash && !hash_equals($existing->request_hash, $payloadHash)) {
                        throw new InvalidArgumentException("Idempotency key '{$idempotencyKey}' has already been used with a different request payload.");
                    }
                    return $existing->load(['customer', 'location', 'salesInvoice']);
                }
            }
            throw $e;
        }
    }

    /**
     * Record a customer cash/bank refund for a sales return or advance credit.
     * Payout from business to customer debits customer khata (reduces credit / increases balance).
     */
    public function recordCustomerRefund(SalesReturn $return, array $data, User $user): CustomerRefund
    {
        if (!$user->hasLocationAccess($return->location_id)) {
            throw new AuthorizationException("User #{$user->id} does not have authorization for location #{$return->location_id}.");
        }

        if ($return->status !== 'posted') {
            throw new InvalidArgumentException("Refunds can only be recorded against posted sales returns. Current status: {$return->status}.");
        }

        $amount = (float) $data['amount'];
        if ($amount <= 0) {
            throw new InvalidArgumentException("Refund amount must be greater than zero. Received: {$amount}");
        }

        $idempotencyKey = !empty($data['idempotency_key']) ? trim((string) $data['idempotency_key']) : null;
        $payloadHash = $this->computeRefundPayloadHash($return, $data, $amount);

        // Fast-path: Check if an idempotent refund was already recorded
        if ($idempotencyKey) {
            $existing = CustomerRefund::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->request_hash && !hash_equals($existing->request_hash, $payloadHash)) {
                    throw new InvalidArgumentException("Idempotency key '{$idempotencyKey}' has already been used with a different request payload.");
                }
                return $existing->load(['customer', 'location', 'salesReturn']);
            }
        }

        try {
            return DB::transaction(function () use ($return, $data, $amount, $user, $idempotencyKey, $payloadHash) {
                $lockedReturn = SalesReturn::where('id', $return->id)->lockForUpdate()->firstOrFail();

                $newRefundedTotal = bcadd((string) $lockedReturn->refunded_amount, (string) $amount, 2);
                if (bccomp($newRefundedTotal, (string) $lockedReturn->total_return_amount, 2) > 0) {
                    $available = bcsub((string) $lockedReturn->total_return_amount, (string) $lockedReturn->refunded_amount, 2);
                    throw new InvalidArgumentException("Refund amount {$amount} exceeds remaining refundable amount ({$available}) for return #{$lockedReturn->return_number}.");
                }

                $refundNumber = $this->generateRefundNumber($lockedReturn->location);
                $refundDate = $data['refund_date'] ?? now()->toDateString();
                $paymentMethod = $data['payment_method'] ?? 'cash';
                $referenceNumber = isset($data['reference_number']) ? trim((string) $data['reference_number']) : null;
                $notes = isset($data['notes']) ? trim((string) $data['notes']) : null;

                $financialAccountId = !empty($data['financial_account_id']) ? (int) $data['financial_account_id'] : null;

                $refund = CustomerRefund::create([
                    'refund_number' => $refundNumber,
                    'customer_id' => $lockedReturn->customer_id,
                    'sales_return_id' => $lockedReturn->id,
                    'location_id' => $lockedReturn->location_id,
                    'refund_date' => $refundDate,
                    'amount' => $amount,
                    'payment_method' => $paymentMethod,
                    'financial_account_id' => $financialAccountId,
                    'reference_number' => $referenceNumber,
                    'notes' => $notes,
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $payloadHash,
                    'created_by' => $user->id,
                ]);

                // Update sales return refund tracking
                $lockedReturn->refunded_amount = $newRefundedTotal;
                $lockedReturn->refund_status = (bccomp($newRefundedTotal, (string) $lockedReturn->total_return_amount, 2) === 0)
                    ? 'refunded'
                    : 'partially_refunded';
                $lockedReturn->save();

                // Customer Khata: Business pays cash back to customer, so customer ledger is DEBITED
                $this->khataService->recordEntry(
                    customer: $lockedReturn->customer,
                    locationId: $lockedReturn->location_id,
                    entryType: 'adjustment_debit',
                    debitAmount: $amount,
                    creditAmount: 0.00,
                    entryDate: $refundDate,
                    user: $user,
                    description: "Customer Refund {$refundNumber} for Return {$lockedReturn->return_number} via " . strtoupper($paymentMethod) . ($referenceNumber ? " [Ref: {$referenceNumber}]" : ''),
                    referenceType: 'customer_refund',
                    referenceId: $refund->id,
                    referenceNumber: $refundNumber
                );

                if ($financialAccountId) {
                    $finAccount = FinancialAccount::findOrFail($financialAccountId);
                    if ($finAccount->location_id && $finAccount->location_id !== $lockedReturn->location_id) {
                        throw new InvalidArgumentException("Selected financial account does not belong to sales return location.");
                    }
                    $this->cashAndBankService->recordTransaction(
                        account: $finAccount,
                        transactionType: 'customer_refund',
                        debitAmount: '0.00',
                        creditAmount: $amount,
                        transactionDate: $refundDate,
                        user: $user,
                        description: "Customer refund to {$lockedReturn->customer->business_name} (Refund #{$refundNumber})",
                        referenceType: CustomerRefund::class,
                        referenceId: $refund->id,
                        referenceNumber: $refundNumber,
                        idempotencyKey: $idempotencyKey ? "cbr-rf-{$idempotencyKey}" : "cbr-rf-{$refund->id}"
                    );
                }

                return $refund->fresh(['customer', 'location', 'salesReturn']);
            });
        } catch (QueryException $e) {
            if ($idempotencyKey && $this->isIdempotencyCollision($e, 'customer_refunds')) {
                $existing = CustomerRefund::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    if ($existing->request_hash && !hash_equals($existing->request_hash, $payloadHash)) {
                        throw new InvalidArgumentException("Idempotency key '{$idempotencyKey}' has already been used with a different request payload.");
                    }
                    return $existing->load(['customer', 'location', 'salesReturn']);
                }
            }
            throw $e;
        }
    }

    /**
     * Compute a deterministic canonical hash of a sales return post request.
     */
    public function computeReturnPayloadHash(SalesReturn $return): string
    {
        $items = [];
        foreach ($return->items as $item) {
            $items[$item->sales_invoice_item_id] = number_format((float) $item->returned_pairs, 4, '.', '');
        }
        ksort($items);

        $canonical = [
            'return_id' => $return->id,
            'sales_invoice_id' => $return->sales_invoice_id,
            'customer_id' => $return->customer_id,
            'location_id' => $return->location_id,
            'return_date' => $return->return_date->toDateString(),
            'total_amount' => number_format((float) $return->total_return_amount, 2, '.', ''),
            'reason' => trim((string) $return->reason),
            'items' => $items,
        ];

        return hash('sha256', json_encode($canonical));
    }

    /**
     * Compute a deterministic canonical hash of a customer credit note request.
     */
    public function computeCreditNotePayloadHash(Customer $customer, array $data, int $locationId, float $amount): string
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Credit note amount must be greater than zero.");
        }

        $canonical = [
            'customer_id' => $customer->id,
            'location_id' => $locationId,
            'sales_invoice_id' => $data['sales_invoice_id'] ?? null,
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
     * Compute a deterministic canonical hash of a customer refund request.
     */
    public function computeRefundPayloadHash(SalesReturn $return, array $data, float $amount): string
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Refund amount must be greater than zero.");
        }

        $canonical = [
            'sales_return_id' => $return->id,
            'customer_id' => $return->customer_id,
            'location_id' => $return->location_id,
            'financial_account_id' => !empty($data['financial_account_id']) ? (int) $data['financial_account_id'] : null,
            'amount' => number_format($amount, 2, '.', ''),
            'refund_date' => $data['refund_date'] ?? now()->toDateString(),
            'payment_method' => $data['payment_method'] ?? 'cash',
            'reference_number' => trim((string) ($data['reference_number'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
        ];

        return hash('sha256', json_encode($canonical));
    }

    /**
     * Generate sequential, location-coded unique sales return number.
     * Format: SRN-{LOC_CODE}-{YYYYMMDD}-{XXXX}
     */
    protected function generateReturnNumber(Location $location): string
    {
        $dateStr = now()->format('Ymd');
        $prefix = "SRN-{$location->code}-{$dateStr}";

        $latest = SalesReturn::where('return_number', 'like', "{$prefix}-%")
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
     * Generate sequential, location-coded unique customer credit note number.
     * Format: SCN-{LOC_CODE}-{YYYYMMDD}-{XXXX}
     */
    protected function generateCreditNoteNumber(Location $location): string
    {
        $dateStr = now()->format('Ymd');
        $prefix = "SCN-{$location->code}-{$dateStr}";

        $latest = CustomerCreditNote::where('credit_note_number', 'like', "{$prefix}-%")
            ->lockForUpdate()
            ->latest('id')
            ->value('credit_note_number');

        $nextSeq = 1;
        if ($latest && preg_match('/-(\d{4})$/', $latest, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }

        return sprintf('%s-%04d', $prefix, $nextSeq);
    }

    /**
     * Generate sequential, location-coded unique customer refund number.
     * Format: CRF-{LOC_CODE}-{YYYYMMDD}-{XXXX}
     */
    protected function generateRefundNumber(Location $location): string
    {
        $dateStr = now()->format('Ymd');
        $prefix = "CRF-{$location->code}-{$dateStr}";

        $latest = CustomerRefund::where('refund_number', 'like', "{$prefix}-%")
            ->lockForUpdate()
            ->latest('id')
            ->value('refund_number');

        $nextSeq = 1;
        if ($latest && preg_match('/-(\d{4})$/', $latest, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }

        return sprintf('%s-%04d', $prefix, $nextSeq);
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

        $isUniqueViolation = ($code === 23000 || $sqlState === '23000' || $driverCode === 1062 || $driverCode === 19);

        if (!$isUniqueViolation) {
            return false;
        }

        $uniqueConstraintName = "{$table}_idempotency_unique";

        return str_contains($msg, $uniqueConstraintName)
            || str_contains($msg, 'idempotency_key')
            || (str_contains($msg, '1062') && str_contains($msg, 'idempotency'));
    }
}
