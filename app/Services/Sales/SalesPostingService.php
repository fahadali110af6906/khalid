<?php

namespace App\Services\Sales;

use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\FinancialAccount;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\User;
use App\Services\Accounting\CashAndBankService;
use App\Services\Accounting\CustomerKhataService;
use App\Services\Inventory\InventoryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class SalesPostingService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected CustomerKhataService $khataService,
        protected ?CashAndBankService $cashAndBankService = null
    ) {
        $this->cashAndBankService = $cashAndBankService ?? app(CashAndBankService::class);
    }

    /**
     * Create a new draft sales invoice without deducting inventory or affecting customer ledger.
     */
    public function createDraft(array $data, User $user): SalesInvoice
    {
        $locationId = (int) $data['location_id'];
        $customerId = (int) $data['customer_id'];

        if (!$user->hasLocationAccess($locationId)) {
            throw new AuthorizationException("User #{$user->id} does not have authorization to create invoices for location #{$locationId}.");
        }

        $customer = Customer::findOrFail($customerId);
        $location = Location::findOrFail($locationId);

        if (empty($data['items']) || !is_array($data['items'])) {
            throw new InvalidArgumentException("At least one invoice line item is required.");
        }

        return DB::transaction(function () use ($data, $user, $customer, $location) {
            $invoiceNumber = $this->generateInvoiceNumber($location);

            $invoiceDate = $data['invoice_date'] ?? now()->toDateString();
            $dueDate = $data['due_date'] ?? null;
            $notes = $data['notes'] ?? null;

            $totalCartons = 0;
            $totalPairs = '0.0000';
            $subtotalAmount = '0.00';
            $overallDiscount = isset($data['discount_amount']) ? number_format((float) $data['discount_amount'], 2, '.', '') : '0.00';
            $taxAmount = isset($data['tax_amount']) ? number_format((float) $data['tax_amount'], 2, '.', '') : '0.00';

            if (bccomp($overallDiscount, '0.00', 2) < 0) {
                throw new InvalidArgumentException("Invoice discount cannot be negative.");
            }

            if (bccomp($taxAmount, '0.00', 2) < 0) {
                throw new InvalidArgumentException("Tax amount cannot be negative.");
            }

            $invoice = SalesInvoice::create([
                'invoice_number' => $invoiceNumber,
                'customer_id' => $customer->id,
                'location_id' => $location->id,
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'status' => 'draft',
                'total_cartons' => 0,
                'total_pairs' => '0.0000',
                'subtotal_amount' => '0.00',
                'discount_amount' => $overallDiscount,
                'tax_amount' => $taxAmount,
                'total_amount' => '0.00',
                'paid_amount' => '0.00',
                'balance_amount' => '0.00',
                'payment_status' => 'unpaid',
                'notes' => $notes,
                'created_by' => $user->id,
            ]);

            foreach ($data['items'] as $itemData) {
                $variantId = (int) $itemData['product_variant_id'];
                $variant = ProductVariant::with('product')->findOrFail($variantId);

                if (!$variant->is_active || !$variant->product->is_active) {
                    throw new InvalidArgumentException("Product variant {$variant->sku} is inactive and cannot be sold.");
                }

                $unitType = $itemData['unit_type'] ?? 'carton';
                $packRatio = $variant->product->carton_pack_qty > 0 ? $variant->product->carton_pack_qty : 12;

                if ($unitType === 'carton') {
                    $qtyCartons = (float) $itemData['quantity'];
                    if ($qtyCartons <= 0) {
                        throw new InvalidArgumentException("Quantity in cartons must be greater than zero.");
                    }
                    $qtyPairs = (float) bcmul((string) $qtyCartons, (string) $packRatio, 4);
                    $unitPricePerCarton = (float) $itemData['unit_price'];
                    if ($unitPricePerCarton <= 0) {
                        throw new InvalidArgumentException("Unit price must be greater than zero.");
                    }
                    $unitPricePerPair = (float) bcdiv((string) $unitPricePerCarton, (string) $packRatio, 4);
                    $lineSubtotal = (string) bcmul((string) $qtyCartons, (string) $unitPricePerCarton, 2);
                    $totalCartons += (int) ceil($qtyCartons);
                } else {
                    $qtyPairs = (float) $itemData['quantity'];
                    if ($qtyPairs <= 0) {
                        throw new InvalidArgumentException("Quantity in pairs must be greater than zero.");
                    }
                    $qtyCartons = (float) bcdiv((string) $qtyPairs, (string) $packRatio, 4);
                    $unitPricePerPair = (float) $itemData['unit_price'];
                    if ($unitPricePerPair <= 0) {
                        throw new InvalidArgumentException("Unit price must be greater than zero.");
                    }
                    $unitPricePerCarton = (float) bcmul((string) $unitPricePerPair, (string) $packRatio, 2);
                    $lineSubtotal = (string) bcmul((string) $qtyPairs, (string) $unitPricePerPair, 2);
                }

                $lineDiscount = isset($itemData['line_discount']) ? number_format((float) $itemData['line_discount'], 2, '.', '') : '0.00';
                if (bccomp($lineDiscount, '0.00', 2) < 0) {
                    throw new InvalidArgumentException("Line discount cannot be negative.");
                }
                if (bccomp($lineDiscount, $lineSubtotal, 2) > 0) {
                    throw new InvalidArgumentException("Line discount cannot exceed line subtotal.");
                }

                $lineTotal = bcsub($lineSubtotal, $lineDiscount, 2);

                SalesInvoiceItem::create([
                    'sales_invoice_id' => $invoice->id,
                    'product_variant_id' => $variant->id,
                    'unit_type' => $unitType,
                    'quantity_cartons' => $qtyCartons,
                    'carton_pack_qty' => $packRatio,
                    'quantity_pairs' => $qtyPairs,
                    'unit_price' => $itemData['unit_price'],
                    'unit_price_per_pair' => $unitPricePerPair,
                    'line_subtotal' => $lineSubtotal,
                    'line_discount' => $lineDiscount,
                    'line_total' => $lineTotal,
                    'unit_cost' => $variant->purchase_cost ?? '0.0000',
                    'notes' => $itemData['notes'] ?? null,
                ]);

                $totalPairs = bcadd($totalPairs, (string) $qtyPairs, 4);
                $subtotalAmount = bcadd($subtotalAmount, $lineTotal, 2);
            }

            // Final Total = Subtotal - Overall Discount + Tax
            $netTotal = bcadd(bcsub($subtotalAmount, $overallDiscount, 2), $taxAmount, 2);
            if (bccomp($netTotal, '0.00', 2) < 0) {
                throw new InvalidArgumentException("Final invoice total cannot be negative.");
            }

            $invoice->update([
                'total_cartons' => $totalCartons,
                'total_pairs' => $totalPairs,
                'subtotal_amount' => $subtotalAmount,
                'total_amount' => $netTotal,
                'balance_amount' => $netTotal,
            ]);

            return $invoice->fresh(['items.variant.product', 'customer', 'location']);
        });
    }

    /**
     * Atomically post a sales invoice:
     * 1. Pessimistically lock stock-balances in ASCENDING variant ID order (deadlock prevention).
     * 2. Verify sufficient stock and deduct inventory via InventoryService.
     * 3. Update invoice status to 'posted'.
     * 4. Post customer ledger debit entry via CustomerKhataService.
     * 5. Process initial payment if present (record Payment, credit Khata, update balance).
     */
    public function postInvoice(SalesInvoice $invoice, User $user, ?array $initialPayment = null): SalesInvoice
    {
        if ($invoice->status !== 'draft') {
            throw new RuntimeException("Invoice {$invoice->invoice_number} cannot be posted because its current status is '{$invoice->status}'.");
        }

        if (!$user->hasLocationAccess($invoice->location_id)) {
            throw new AuthorizationException("User #{$user->id} is not authorized to post invoices for location #{$invoice->location_id}.");
        }

        return DB::transaction(function () use ($invoice, $user, $initialPayment) {
            // Re-fetch invoice with lock
            $lockedInvoice = SalesInvoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            if ($lockedInvoice->status !== 'draft') {
                throw new RuntimeException("Concurrent post rejected: Invoice {$lockedInvoice->invoice_number} is already posted.");
            }

            $items = $lockedInvoice->items()->with('variant.product')->get();
            if ($items->isEmpty()) {
                throw new InvalidArgumentException("Cannot post invoice {$lockedInvoice->invoice_number} without any line items.");
            }

            // DEADLOCK PREVENTION: sort items by product_variant_id ascending
            $sortedItems = $items->sortBy('product_variant_id');

            // 1. Deduct inventory for each line item
            foreach ($sortedItems as $item) {
                if (!$item->variant->is_active || !$item->variant->product->is_active) {
                    throw new InvalidArgumentException("Product variant {$item->variant->sku} is inactive and cannot be sold.");
                }

                $movement = $this->inventoryService->deductStock(
                    locationId: $lockedInvoice->location_id,
                    variantId: $item->product_variant_id,
                    quantityPairs: (float) $item->quantity_pairs,
                    movementType: 'sale',
                    user: $user,
                    referenceType: SalesInvoice::class,
                    referenceId: $lockedInvoice->id,
                    notes: "Wholesale Sale Invoice #{$lockedInvoice->invoice_number}"
                );

                // Snapshot latest WAC unit cost onto the line item
                $item->unit_cost = $movement->unit_cost;
                $item->save();
            }

            // 2. Transition invoice to posted
            $lockedInvoice->status = 'posted';
            $lockedInvoice->posted_at = now();
            $lockedInvoice->posted_by = $user->id;
            $lockedInvoice->save();

            // 3. Post Customer Ledger Debit entry (Sale increases customer receivable)
            $this->khataService->recordEntry(
                customer: $lockedInvoice->customer,
                locationId: $lockedInvoice->location_id,
                entryType: 'sale_invoice',
                debitAmount: $lockedInvoice->total_amount,
                creditAmount: '0.00',
                entryDate: $lockedInvoice->invoice_date->format('Y-m-d'),
                user: $user,
                description: "Wholesale Sales Invoice #{$lockedInvoice->invoice_number}",
                referenceType: 'SalesInvoice',
                referenceId: $lockedInvoice->id,
                referenceNumber: $lockedInvoice->invoice_number
            );

            // 4. Handle initial payment if provided
            if ($initialPayment && isset($initialPayment['amount']) && (float) $initialPayment['amount'] > 0) {
                $paymentAmount = number_format((float) $initialPayment['amount'], 2, '.', '');

                if (bccomp($paymentAmount, (string) $lockedInvoice->total_amount, 2) > 0) {
                    throw new InvalidArgumentException("Initial payment amount (PKR {$paymentAmount}) cannot exceed invoice total (PKR {$lockedInvoice->total_amount}).");
                }

                $paymentNumber = $this->generatePaymentNumber($lockedInvoice->location);
                $paymentDate = $initialPayment['payment_date'] ?? $lockedInvoice->invoice_date->format('Y-m-d');
                $paymentMethod = $initialPayment['payment_method'] ?? 'cash';
                $referenceNo = $initialPayment['reference_number'] ?? null;
                $paymentNotes = $initialPayment['notes'] ?? "Initial payment for invoice #{$lockedInvoice->invoice_number}";
                $financialAccountId = !empty($initialPayment['financial_account_id']) ? (int) $initialPayment['financial_account_id'] : null;

                $payment = Payment::create([
                    'payment_number' => $paymentNumber,
                    'customer_id' => $lockedInvoice->customer_id,
                    'sales_invoice_id' => $lockedInvoice->id,
                    'location_id' => $lockedInvoice->location_id,
                    'payment_date' => $paymentDate,
                    'amount' => $paymentAmount,
                    'payment_method' => $paymentMethod,
                    'financial_account_id' => $financialAccountId,
                    'reference_number' => $referenceNo,
                    'notes' => $paymentNotes,
                    'created_by' => $user->id,
                ]);

                // Update invoice paid and balance amounts
                $newPaid = $paymentAmount;
                $newBalance = bcsub((string) $lockedInvoice->total_amount, $newPaid, 2);
                $paymentStatus = bccomp($newBalance, '0.00', 2) === 0 ? 'paid' : 'partial';

                $lockedInvoice->paid_amount = $newPaid;
                $lockedInvoice->balance_amount = $newBalance;
                $lockedInvoice->payment_status = $paymentStatus;
                $lockedInvoice->save();

                // Post Customer Ledger Credit entry (Payment decreases customer receivable)
                $this->khataService->recordEntry(
                    customer: $lockedInvoice->customer,
                    locationId: $lockedInvoice->location_id,
                    entryType: 'payment_received',
                    debitAmount: '0.00',
                    creditAmount: $paymentAmount,
                    entryDate: $paymentDate,
                    user: $user,
                    description: "Payment received for Invoice #{$lockedInvoice->invoice_number} via " . ucfirst(str_replace('_', ' ', $paymentMethod)),
                    referenceType: 'Payment',
                    referenceId: $payment->id,
                    referenceNumber: $payment->payment_number
                );

                if ($financialAccountId) {
                    $finAccount = FinancialAccount::findOrFail($financialAccountId);
                    if ($finAccount->location_id && $finAccount->location_id !== $lockedInvoice->location_id) {
                        throw new InvalidArgumentException("Selected financial account does not belong to invoice location.");
                    }
                    $this->cashAndBankService->recordTransaction(
                        account: $finAccount,
                        transactionType: 'customer_receipt',
                        debitAmount: $paymentAmount,
                        creditAmount: '0.00',
                        transactionDate: $paymentDate,
                        user: $user,
                        description: "Payment received for Invoice #{$lockedInvoice->invoice_number} via " . ucfirst(str_replace('_', ' ', $paymentMethod)),
                        referenceType: Payment::class,
                        referenceId: $payment->id,
                        referenceNumber: $payment->payment_number,
                        idempotencyKey: !empty($initialPayment['idempotency_key']) ? "cbr-init-{$initialPayment['idempotency_key']}" : "cbr-pmt-{$payment->id}"
                    );
                }
            }

            return $lockedInvoice->fresh(['items.variant.product', 'payments', 'customer', 'location']);
        });
    }

    /**
     * Record a subsequent payment against an outstanding posted invoice.
     */
    public function recordPayment(SalesInvoice $invoice, array $data, User $user): Payment
    {
        if ($invoice->status !== 'posted') {
            throw new RuntimeException("Payments can only be collected against posted invoices.");
        }

        if (!$user->hasLocationAccess($invoice->location_id)) {
            throw new AuthorizationException("User #{$user->id} is not authorized to collect payments for location #{$invoice->location_id}.");
        }

        $amount = number_format((float) ($data['amount'] ?? 0), 2, '.', '');
        if (bccomp($amount, '0.00', 2) <= 0) {
            throw new InvalidArgumentException("Payment amount must be greater than zero.");
        }

        return DB::transaction(function () use ($invoice, $data, $amount, $user) {
            $lockedInvoice = SalesInvoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            if (bccomp($amount, (string) $lockedInvoice->balance_amount, 2) > 0) {
                throw new InvalidArgumentException("Payment amount (PKR {$amount}) exceeds outstanding invoice balance (PKR {$lockedInvoice->balance_amount}). Overpayments must be handled as customer advances.");
            }

            $paymentNumber = $this->generatePaymentNumber($lockedInvoice->location);
            $paymentDate = $data['payment_date'] ?? now()->toDateString();
            $paymentMethod = $data['payment_method'] ?? 'cash';
            $referenceNo = $data['reference_number'] ?? null;
            $notes = $data['notes'] ?? "Payment collected for invoice #{$lockedInvoice->invoice_number}";
            $financialAccountId = !empty($data['financial_account_id']) ? (int) $data['financial_account_id'] : null;

            $payment = Payment::create([
                'payment_number' => $paymentNumber,
                'customer_id' => $lockedInvoice->customer_id,
                'sales_invoice_id' => $lockedInvoice->id,
                'location_id' => $lockedInvoice->location_id,
                'payment_date' => $paymentDate,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'financial_account_id' => $financialAccountId,
                'reference_number' => $referenceNo,
                'notes' => $notes,
                'created_by' => $user->id,
            ]);

            // Update invoice balances
            $newPaid = bcadd((string) $lockedInvoice->paid_amount, $amount, 2);
            $newBalance = bcsub((string) $lockedInvoice->total_amount, $newPaid, 2);
            $paymentStatus = bccomp($newBalance, '0.00', 2) === 0 ? 'paid' : 'partial';

            $lockedInvoice->paid_amount = $newPaid;
            $lockedInvoice->balance_amount = $newBalance;
            $lockedInvoice->payment_status = $paymentStatus;
            $lockedInvoice->save();

            // Post Customer Ledger Credit entry
            $this->khataService->recordEntry(
                customer: $lockedInvoice->customer,
                locationId: $lockedInvoice->location_id,
                entryType: 'payment_received',
                debitAmount: '0.00',
                creditAmount: $amount,
                entryDate: $paymentDate,
                user: $user,
                description: "Payment collected for Invoice #{$lockedInvoice->invoice_number} via " . ucfirst(str_replace('_', ' ', $paymentMethod)),
                referenceType: 'Payment',
                referenceId: $payment->id,
                referenceNumber: $payment->payment_number
            );

            if ($financialAccountId) {
                $finAccount = FinancialAccount::findOrFail($financialAccountId);
                if ($finAccount->location_id && $finAccount->location_id !== $lockedInvoice->location_id) {
                    throw new InvalidArgumentException("Selected financial account does not belong to invoice location.");
                }
                $this->cashAndBankService->recordTransaction(
                    account: $finAccount,
                    transactionType: 'customer_receipt',
                    debitAmount: $amount,
                    creditAmount: '0.00',
                    transactionDate: $paymentDate,
                    user: $user,
                    description: "Payment collected for Invoice #{$lockedInvoice->invoice_number} via " . ucfirst(str_replace('_', ' ', $paymentMethod)),
                    referenceType: Payment::class,
                    referenceId: $payment->id,
                    referenceNumber: $payment->payment_number,
                    idempotencyKey: !empty($data['idempotency_key']) ? "cbr-pmt-{$data['idempotency_key']}" : "cbr-pmt-{$payment->id}"
                );
            }

            return $payment;
        });
    }

    /**
     * Record an on-account customer payment / advance (without direct invoice linkage).
     */
    public function recordDirectPayment(Customer $customer, int $locationId, array $data, User $user): Payment
    {
        if (!$user->hasLocationAccess($locationId)) {
            throw new AuthorizationException("User #{$user->id} is not authorized to collect payments for location #{$locationId}.");
        }

        $location = Location::findOrFail($locationId);
        $amount = number_format((float) ($data['amount'] ?? 0), 2, '.', '');
        if (bccomp($amount, '0.00', 2) <= 0) {
            throw new InvalidArgumentException("Payment amount must be greater than zero.");
        }

        return DB::transaction(function () use ($customer, $location, $data, $amount, $user) {
            $paymentNumber = $this->generatePaymentNumber($location);
            $paymentDate = $data['payment_date'] ?? now()->toDateString();
            $paymentMethod = $data['payment_method'] ?? 'cash';
            $referenceNo = $data['reference_number'] ?? null;
            $notes = $data['notes'] ?? "On-account / advance payment from {$customer->business_name}";
            $financialAccountId = !empty($data['financial_account_id']) ? (int) $data['financial_account_id'] : null;

            $payment = Payment::create([
                'payment_number' => $paymentNumber,
                'customer_id' => $customer->id,
                'sales_invoice_id' => null,
                'location_id' => $location->id,
                'payment_date' => $paymentDate,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'financial_account_id' => $financialAccountId,
                'reference_number' => $referenceNo,
                'notes' => $notes,
                'created_by' => $user->id,
            ]);

            // Post Customer Ledger Credit entry
            $this->khataService->recordEntry(
                customer: $customer,
                locationId: $location->id,
                entryType: 'payment_received',
                debitAmount: '0.00',
                creditAmount: $amount,
                entryDate: $paymentDate,
                user: $user,
                description: "On-account payment received via " . ucfirst(str_replace('_', ' ', $paymentMethod)),
                referenceType: 'Payment',
                referenceId: $payment->id,
                referenceNumber: $payment->payment_number
            );

            if ($financialAccountId) {
                $finAccount = FinancialAccount::findOrFail($financialAccountId);
                if ($finAccount->location_id && $finAccount->location_id !== $location->id) {
                    throw new InvalidArgumentException("Selected financial account does not belong to payment location.");
                }
                $this->cashAndBankService->recordTransaction(
                    account: $finAccount,
                    transactionType: 'customer_receipt',
                    debitAmount: $amount,
                    creditAmount: '0.00',
                    transactionDate: $paymentDate,
                    user: $user,
                    description: "On-account payment received from {$customer->business_name} via " . ucfirst(str_replace('_', ' ', $paymentMethod)),
                    referenceType: Payment::class,
                    referenceId: $payment->id,
                    referenceNumber: $payment->payment_number,
                    idempotencyKey: !empty($data['idempotency_key']) ? "cbr-dir-{$data['idempotency_key']}" : "cbr-pmt-{$payment->id}"
                );
            }

            return $payment;
        });
    }

    /**
     * Generate sequential, location-coded unique invoice number.
     * Format: INV-{LOC_CODE}-{YYYYMMDD}-{XXXX}
     */
    protected function generateInvoiceNumber(Location $location): string
    {
        $dateStr = now()->format('Ymd');
        $prefix = "INV-{$location->code}-{$dateStr}";

        $latest = SalesInvoice::where('invoice_number', 'like', "{$prefix}-%")
            ->lockForUpdate()
            ->latest('id')
            ->value('invoice_number');

        $nextSeq = 1;
        if ($latest && preg_match('/-(\d{4})$/', $latest, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }

        return sprintf('%s-%04d', $prefix, $nextSeq);
    }

    /**
     * Generate sequential, location-coded unique payment number.
     * Format: PAY-{LOC_CODE}-{YYYYMMDD}-{XXXX}
     */
    protected function generatePaymentNumber(Location $location): string
    {
        $dateStr = now()->format('Ymd');
        $prefix = "PAY-{$location->code}-{$dateStr}";

        $latest = Payment::where('payment_number', 'like', "{$prefix}-%")
            ->lockForUpdate()
            ->latest('id')
            ->value('payment_number');

        $nextSeq = 1;
        if ($latest && preg_match('/-(\d{4})$/', $latest, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }

        return sprintf('%s-%04d', $prefix, $nextSeq);
    }
}
