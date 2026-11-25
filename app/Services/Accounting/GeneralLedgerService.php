<?php

namespace App\Services\Accounting;

use App\Models\AccountTransaction;
use App\Models\AccountTransfer;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\CustomerRefund;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\GoodsReceipt;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Location;
use App\Models\Payment;
use App\Models\ProductionCompletion;
use App\Models\ProductionMaterialIssue;
use App\Models\ProductionOrder;
use App\Models\PurchaseReturn;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\SupplierPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GeneralLedgerService
{
    /**
     * Record a balanced double-entry Journal Entry.
     * Enforces all core accounting invariants.
     *
     * @param array $header ['entry_date', 'description', 'location_id', 'source_type', 'source_id', 'reference_number', 'idempotency_key']
     * @param array $lines Array of ['chart_of_account_id', 'debit', 'credit', 'description', 'location_id', 'reference_type', 'reference_id']
     * @throws InvalidArgumentException
     */
    public function recordJournalEntry(array $header, array $lines, User $user): JournalEntry
    {
        $idempotencyKey = $header['idempotency_key'] ?? null;

        // Idempotency check: if key already exists, return existing entry
        if ($idempotencyKey) {
            $existing = JournalEntry::where('idempotency_key', $idempotencyKey)
                ->with(['lines.account', 'location', 'creator'])
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        // Check if automatic source reference already has a posted journal
        if (!empty($header['source_type']) && !empty($header['source_id'])) {
            $existingSource = JournalEntry::where('source_type', $header['source_type'])
                ->where('source_id', $header['source_id'])
                ->where('status', 'posted')
                ->with(['lines.account', 'location', 'creator'])
                ->first();
            if ($existingSource) {
                return $existingSource;
            }
        }

        if (count($lines) < 2) {
            throw new InvalidArgumentException("A journal entry must contain at least 2 lines (debit and credit).");
        }

        $totalDebit = '0.00';
        $totalCredit = '0.00';
        $validatedLines = [];

        foreach ($lines as $idx => $line) {
            $accountId = (int) ($line['chart_of_account_id'] ?? 0);
            $account = ChartOfAccount::find($accountId);

            if (!$account) {
                throw new InvalidArgumentException("Line " . ($idx + 1) . ": Invalid account ID #{$accountId}.");
            }

            if ($account->is_group) {
                throw new InvalidArgumentException("Line " . ($idx + 1) . ": Cannot post to group account '{$account->name}' ({$account->code}). Choose a postable leaf account.");
            }

            if (!$account->is_active) {
                throw new InvalidArgumentException("Line " . ($idx + 1) . ": Account '{$account->name}' ({$account->code}) is inactive.");
            }

            $debit = (float) ($line['debit'] ?? 0.00);
            $credit = (float) ($line['credit'] ?? 0.00);

            if ($debit < 0 || $credit < 0) {
                throw new InvalidArgumentException("Line " . ($idx + 1) . ": Negative amounts are not allowed. Use opposite debit/credit column.");
            }

            if ($debit > 0 && $credit > 0) {
                throw new InvalidArgumentException("Line " . ($idx + 1) . ": A single line cannot have both positive debit and positive credit.");
            }

            if ($debit == 0 && $credit == 0) {
                continue; // Ignore 0.00 placeholder lines
            }

            $debitStr = number_format($debit, 2, '.', '');
            $creditStr = number_format($credit, 2, '.', '');

            $totalDebit = bcadd($totalDebit, $debitStr, 2);
            $totalCredit = bcadd($totalCredit, $creditStr, 2);

            $validatedLines[] = [
                'chart_of_account_id' => $account->id,
                'location_id' => !empty($line['location_id']) ? (int) $line['location_id'] : ($header['location_id'] ?? null),
                'debit' => $debitStr,
                'credit' => $creditStr,
                'description' => isset($line['description']) ? trim((string) $line['description']) : null,
                'reference_type' => $line['reference_type'] ?? null,
                'reference_id' => $line['reference_id'] ?? null,
            ];
        }

        if (count($validatedLines) < 2) {
            throw new InvalidArgumentException("A journal entry must contain at least 2 non-zero lines.");
        }

        // Invariant: Total Debits must equal Total Credits
        if (bccomp($totalDebit, $totalCredit, 2) !== 0) {
            throw new InvalidArgumentException("Unbalanced journal entry: Total Debits (Rs. {$totalDebit}) do not equal Total Credits (Rs. {$totalCredit}). Difference: Rs. " . bcsub($totalDebit, $totalCredit, 2));
        }

        if (bccomp($totalDebit, '0.00', 2) === 0) {
            throw new InvalidArgumentException("Cannot post zero-value journal entry.");
        }

        return DB::transaction(function () use ($header, $validatedLines, $totalDebit, $totalCredit, $idempotencyKey, $user) {
            $location = !empty($header['location_id']) ? Location::find($header['location_id']) : null;
            $entryNumber = $this->generateEntryNumber($location);

            $entry = JournalEntry::create([
                'entry_number' => $entryNumber,
                'entry_date' => $header['entry_date'] ?? now()->toDateString(),
                'location_id' => $location?->id,
                'source_type' => $header['source_type'] ?? null,
                'source_id' => $header['source_id'] ?? null,
                'reference_number' => $header['reference_number'] ?? null,
                'description' => trim($header['description']),
                'status' => 'posted',
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'idempotency_key' => $idempotencyKey,
                'posted_at' => now(),
                'created_by' => $user->id,
            ]);

            foreach ($validatedLines as $lineIdx => $valLine) {
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'chart_of_account_id' => $valLine['chart_of_account_id'],
                    'location_id' => $valLine['location_id'],
                    'line_number' => $lineIdx + 1,
                    'debit' => $valLine['debit'],
                    'credit' => $valLine['credit'],
                    'description' => $valLine['description'],
                    'reference_type' => $valLine['reference_type'],
                    'reference_id' => $valLine['reference_id'],
                ]);
            }

            return $entry->load(['lines.account', 'location', 'creator']);
        });
    }

    /**
     * Reverse a posted journal entry by creating a linked inverse journal entry.
     */
    public function reverseJournalEntry(JournalEntry $entry, User $user, string $reason): JournalEntry
    {
        if ($entry->status !== 'posted') {
            throw new InvalidArgumentException("Only posted journal entries can be reversed. Current status: {$entry->status}.");
        }

        if ($entry->reversed_by_entry_id !== null) {
            throw new InvalidArgumentException("This journal entry has already been reversed by entry #{$entry->reversedBy->entry_number}.");
        }

        return DB::transaction(function () use ($entry, $user, $reason) {
            $lockedEntry = JournalEntry::where('id', $entry->id)->lockForUpdate()->firstOrFail();

            $reversalLines = [];
            foreach ($lockedEntry->lines as $line) {
                $reversalLines[] = [
                    'chart_of_account_id' => $line->chart_of_account_id,
                    'location_id' => $line->location_id,
                    'debit' => $line->credit, // Swap debit and credit
                    'credit' => $line->debit,
                    'description' => "Reversal of line #{$line->line_number}: " . ($line->description ?? ''),
                    'reference_type' => $line->reference_type,
                    'reference_id' => $line->reference_id,
                ];
            }

            $reversalNumber = $this->generateEntryNumber($lockedEntry->location);

            $reversal = JournalEntry::create([
                'entry_number' => $reversalNumber,
                'entry_date' => now()->toDateString(),
                'location_id' => $lockedEntry->location_id,
                'source_type' => $lockedEntry->source_type,
                'source_id' => $lockedEntry->source_id,
                'reference_number' => $lockedEntry->reference_number,
                'description' => "Reversal of {$lockedEntry->entry_number}: " . trim($reason),
                'status' => 'posted',
                'total_debit' => $lockedEntry->total_credit,
                'total_credit' => $lockedEntry->total_debit,
                'reversal_of_entry_id' => $lockedEntry->id,
                'posted_at' => now(),
                'created_by' => $user->id,
            ]);

            foreach ($reversalLines as $lineIdx => $valLine) {
                JournalEntryLine::create([
                    'journal_entry_id' => $reversal->id,
                    'chart_of_account_id' => $valLine['chart_of_account_id'],
                    'location_id' => $valLine['location_id'],
                    'line_number' => $lineIdx + 1,
                    'debit' => $valLine['debit'],
                    'credit' => $valLine['credit'],
                    'description' => $valLine['description'],
                    'reference_type' => $valLine['reference_type'],
                    'reference_id' => $valLine['reference_id'],
                ]);
            }

            // Mark original entry as reversed and link it
            $lockedEntry->status = 'reversed';
            $lockedEntry->reversed_by_entry_id = $reversal->id;
            $lockedEntry->save();

            return $reversal->load(['lines.account', 'location', 'creator']);
        });
    }

    // =========================================================================
    // INTEGRATION WITH EXISTING OPERATIONAL MODULES
    // =========================================================================

    /**
     * Post commercial Sales Invoice into General Ledger.
     *
     * Accounting Treatment:
     * - Debit: Accounts Receivable (1130) for invoice net total
     * - Debit: Sales Discounts (4200) for trade discount (if any)
     * - Credit: Sales Revenue (4100) for gross sales amount
     * - Debit: Cost of Goods Sold (5100) for total cost of pairs sold (at WAC)
     * - Credit: Finished Goods Inventory (1140) for total cost of pairs sold
     */
    public function postSalesInvoice(SalesInvoice $invoice, User $user): JournalEntry
    {
        $arAccount = ChartOfAccount::requireSystemAccount('accounts_receivable');
        $salesAccount = ChartOfAccount::requireSystemAccount('sales_revenue');
        $discountAccount = ChartOfAccount::requireSystemAccount('sales_discounts');
        $cogsAccount = ChartOfAccount::requireSystemAccount('cogs');
        $inventoryAccount = ChartOfAccount::requireSystemAccount('merchandise_inventory');

        $netTotal = (float) $invoice->total_amount;
        $discount = (float) ($invoice->discount_amount ?? 0);
        $grossTotal = (float) bcadd((string) $netTotal, (string) $discount, 2);

        // Compute COGS at WAC from invoice line items
        $totalCogs = '0.00';
        foreach ($invoice->items as $item) {
            $lineCogs = bcmul((string) $item->quantity_pairs, (string) $item->unit_cost, 2);
            $totalCogs = bcadd($totalCogs, $lineCogs, 2);
        }

        $lines = [];

        // 1. Receivable / Cash leg
        $lines[] = [
            'chart_of_account_id' => $arAccount->id,
            'debit' => $netTotal,
            'credit' => 0.00,
            'description' => "Receivable for Invoice #{$invoice->invoice_number} ({$invoice->customer->business_name})",
            'reference_type' => Customer::class,
            'reference_id' => $invoice->customer_id,
        ];

        // 2. Discount leg (if applicable)
        if ($discount > 0) {
            $lines[] = [
                'chart_of_account_id' => $discountAccount->id,
                'debit' => $discount,
                'credit' => 0.00,
                'description' => "Trade discount allowed on Invoice #{$invoice->invoice_number}",
            ];
        }

        // 3. Sales Revenue leg
        $lines[] = [
            'chart_of_account_id' => $salesAccount->id,
            'debit' => 0.00,
            'credit' => $grossTotal,
            'description' => "Gross footwear sales for Invoice #{$invoice->invoice_number}",
        ];

        // 4. Inventory & COGS leg (if cost is tracked)
        if (bccomp($totalCogs, '0.00', 2) > 0) {
            $lines[] = [
                'chart_of_account_id' => $cogsAccount->id,
                'debit' => (float) $totalCogs,
                'credit' => 0.00,
                'description' => "Cost of Goods Sold (WAC) for Invoice #{$invoice->invoice_number}",
            ];

            $lines[] = [
                'chart_of_account_id' => $inventoryAccount->id,
                'debit' => 0.00,
                'credit' => (float) $totalCogs,
                'description' => "Stock reduction for Invoice #{$invoice->invoice_number}",
            ];
        }

        return $this->recordJournalEntry([
            'entry_date' => $invoice->invoice_date ?? now()->toDateString(),
            'location_id' => $invoice->location_id,
            'source_type' => 'sales_invoice',
            'source_id' => $invoice->id,
            'reference_number' => $invoice->invoice_number,
            'description' => "Wholesale Sales Invoice #{$invoice->invoice_number} — Customer: {$invoice->customer->business_name}",
            'idempotency_key' => 'gl_invoice_' . $invoice->id,
        ], $lines, $user);
    }

    /**
     * Post Customer Payment into General Ledger.
     *
     * Accounting Treatment:
     * - Debit: Cash in Hand (1110) or Bank Accounts (1120)
     * - Credit: Accounts Receivable (1130)
     */
    public function postCustomerPayment(Payment $payment, User $user): JournalEntry
    {
        $arAccount = ChartOfAccount::requireSystemAccount('accounts_receivable');
        $cashOrBankAccount = ($payment->payment_method === 'bank' || !empty($payment->bank_name))
            ? ChartOfAccount::requireSystemAccount('bank_accounts')
            : ChartOfAccount::requireSystemAccount('cash_in_hand');

        $amount = (float) $payment->amount;

        $lines = [
            [
                'chart_of_account_id' => $cashOrBankAccount->id,
                'debit' => $amount,
                'credit' => 0.00,
                'description' => "Cash/Bank receipt for Payment #{$payment->payment_number}",
            ],
            [
                'chart_of_account_id' => $arAccount->id,
                'debit' => 0.00,
                'credit' => $amount,
                'description' => "Customer khata credit for Payment #{$payment->payment_number} ({$payment->customer->business_name})",
                'reference_type' => Customer::class,
                'reference_id' => $payment->customer_id,
            ],
        ];

        return $this->recordJournalEntry([
            'entry_date' => $payment->payment_date ?? now()->toDateString(),
            'location_id' => $payment->location_id,
            'source_type' => 'customer_payment',
            'source_id' => $payment->id,
            'reference_number' => $payment->payment_number,
            'description' => "Customer Payment #{$payment->payment_number} — {$payment->customer->business_name}",
            'idempotency_key' => 'gl_payment_' . $payment->id,
        ], $lines, $user);
    }

    /**
     * Post Sales Return into General Ledger.
     *
     * Accounting Treatment:
     * - Debit: Sales Returns & Customer Credits (4300)
     * - Credit: Accounts Receivable (1130)
     * - Debit: Finished Goods Inventory (1140) for restocked cost at WAC
     * - Credit: Cost of Goods Sold (5100)
     */
    public function postSalesReturn(SalesReturn $salesReturn, User $user): JournalEntry
    {
        $salesReturnAccount = ChartOfAccount::requireSystemAccount('sales_returns');
        $arAccount = ChartOfAccount::requireSystemAccount('accounts_receivable');
        $inventoryAccount = ChartOfAccount::requireSystemAccount('merchandise_inventory');
        $cogsAccount = ChartOfAccount::requireSystemAccount('cogs');

        $returnTotal = (float) $salesReturn->total_return_amount;

        // Compute returned inventory cost
        $returnedCogs = '0.00';
        foreach ($salesReturn->items as $rItem) {
            $costLine = bcmul((string) $rItem->quantity_pairs, (string) $rItem->unit_cost, 2);
            $returnedCogs = bcadd($returnedCogs, $costLine, 2);
        }

        $lines = [
            [
                'chart_of_account_id' => $salesReturnAccount->id,
                'debit' => $returnTotal,
                'credit' => 0.00,
                'description' => "Sales Return #{$salesReturn->return_number}",
            ],
            [
                'chart_of_account_id' => $arAccount->id,
                'debit' => 0.00,
                'credit' => $returnTotal,
                'description' => "Credit note to customer khata for Return #{$salesReturn->return_number}",
                'reference_type' => Customer::class,
                'reference_id' => $salesReturn->customer_id,
            ],
        ];

        if (bccomp($returnedCogs, '0.00', 2) > 0) {
            $lines[] = [
                'chart_of_account_id' => $inventoryAccount->id,
                'debit' => (float) $returnedCogs,
                'credit' => 0.00,
                'description' => "Restock returned footwear for Return #{$salesReturn->return_number}",
            ];
            $lines[] = [
                'chart_of_account_id' => $cogsAccount->id,
                'debit' => 0.00,
                'credit' => (float) $returnedCogs,
                'description' => "COGS reversal for Return #{$salesReturn->return_number}",
            ];
        }

        return $this->recordJournalEntry([
            'entry_date' => $salesReturn->return_date ?? now()->toDateString(),
            'location_id' => $salesReturn->location_id,
            'source_type' => 'sales_return',
            'source_id' => $salesReturn->id,
            'reference_number' => $salesReturn->return_number,
            'description' => "Sales Return #{$salesReturn->return_number} — {$salesReturn->customer->business_name}",
            'idempotency_key' => 'gl_sales_return_' . $salesReturn->id,
        ], $lines, $user);
    }

    /**
     * Post Customer Refund into General Ledger.
     */
    public function postCustomerRefund(CustomerRefund $refund, User $user): JournalEntry
    {
        $arAccount = ChartOfAccount::requireSystemAccount('accounts_receivable');
        $cashOrBankAccount = ($refund->payment_method === 'bank')
            ? ChartOfAccount::requireSystemAccount('bank_accounts')
            : ChartOfAccount::requireSystemAccount('cash_in_hand');

        $amount = (float) $refund->amount;

        $lines = [
            [
                'chart_of_account_id' => $arAccount->id,
                'debit' => $amount,
                'credit' => 0.00,
                'description' => "Customer credit deduction for Refund #{$refund->refund_number}",
                'reference_type' => Customer::class,
                'reference_id' => $refund->customer_id,
            ],
            [
                'chart_of_account_id' => $cashOrBankAccount->id,
                'debit' => 0.00,
                'credit' => $amount,
                'description' => "Cash/Bank payout for Customer Refund #{$refund->refund_number}",
            ],
        ];

        return $this->recordJournalEntry([
            'entry_date' => $refund->refund_date ?? now()->toDateString(),
            'location_id' => $refund->location_id,
            'source_type' => 'customer_refund',
            'source_id' => $refund->id,
            'reference_number' => $refund->refund_number,
            'description' => "Customer Refund #{$refund->refund_number} — {$refund->customer->business_name}",
            'idempotency_key' => 'gl_customer_refund_' . $refund->id,
        ], $lines, $user);
    }

    /**
     * Post Goods Receipt (Purchase GRN) into General Ledger.
     *
     * Accounting Treatment:
     * - Debit: Raw Materials Inventory (1150) or Finished Goods (1140)
     * - Credit: Accounts Payable (2110)
     */
    public function postGoodsReceipt(GoodsReceipt $grn, User $user): JournalEntry
    {
        $apAccount = ChartOfAccount::requireSystemAccount('accounts_payable');

        // Check if items are raw material vs finished good
        $rawInvAccount = ChartOfAccount::requireSystemAccount('raw_materials_inventory');
        $finInvAccount = ChartOfAccount::requireSystemAccount('merchandise_inventory');

        $totalRaw = '0.00';
        $totalFin = '0.00';

        foreach ($grn->items as $item) {
            $variant = $item->productVariant;
            $unitCost = (string) ($item->unit_cost ?? $item->unit_cost_per_pair ?? 0);
            $lineTotal = bcmul((string) $item->received_pairs, $unitCost, 2);
            $catType = $variant?->product?->category?->type ?? 'finished_good';

            if ($catType === 'raw_material' || $catType === 'packaging') {
                $totalRaw = bcadd($totalRaw, $lineTotal, 2);
            } else {
                $totalFin = bcadd($totalFin, $lineTotal, 2);
            }
        }

        $lines = [];

        if (bccomp($totalRaw, '0.00', 2) > 0) {
            $lines[] = [
                'chart_of_account_id' => $rawInvAccount->id,
                'debit' => (float) $totalRaw,
                'credit' => 0.00,
                'description' => "Raw materials received under GRN #{$grn->receipt_number}",
            ];
        }

        if (bccomp($totalFin, '0.00', 2) > 0) {
            $lines[] = [
                'chart_of_account_id' => $finInvAccount->id,
                'debit' => (float) $totalFin,
                'credit' => 0.00,
                'description' => "Finished footwear received under GRN #{$grn->receipt_number}",
            ];
        }

        $grandTotal = (float) bcadd($totalRaw, $totalFin, 2);

        $lines[] = [
            'chart_of_account_id' => $apAccount->id,
            'debit' => 0.00,
            'credit' => $grandTotal,
            'description' => "Payable to supplier {$grn->supplier->business_name} for GRN #{$grn->receipt_number}",
            'reference_type' => Supplier::class,
            'reference_id' => $grn->supplier_id,
        ];

        return $this->recordJournalEntry([
            'entry_date' => $grn->receipt_date ?? now()->toDateString(),
            'location_id' => $grn->location_id,
            'source_type' => 'goods_receipt',
            'source_id' => $grn->id,
            'reference_number' => $grn->receipt_number,
            'description' => "Purchase Goods Receipt #{$grn->receipt_number} — Supplier: {$grn->supplier->business_name}",
            'idempotency_key' => 'gl_grn_' . $grn->id,
        ], $lines, $user);
    }

    /**
     * Post Supplier Payment into General Ledger.
     *
     * Accounting Treatment:
     * - Debit: Accounts Payable (2110)
     * - Credit: Cash in Hand (1110) or Bank Accounts (1120)
     */
    public function postSupplierPayment(SupplierPayment $payment, User $user): JournalEntry
    {
        $apAccount = ChartOfAccount::requireSystemAccount('accounts_payable');
        $cashOrBankAccount = ($payment->payment_method === 'bank' || !empty($payment->bank_name))
            ? ChartOfAccount::requireSystemAccount('bank_accounts')
            : ChartOfAccount::requireSystemAccount('cash_in_hand');

        $amount = (float) $payment->amount;

        $lines = [
            [
                'chart_of_account_id' => $apAccount->id,
                'debit' => $amount,
                'credit' => 0.00,
                'description' => "Debit payable for Supplier Payment #{$payment->payment_number} ({$payment->supplier->business_name})",
                'reference_type' => Supplier::class,
                'reference_id' => $payment->supplier_id,
            ],
            [
                'chart_of_account_id' => $cashOrBankAccount->id,
                'debit' => 0.00,
                'credit' => $amount,
                'description' => "Cash/Bank disbursement for Supplier Payment #{$payment->payment_number}",
            ],
        ];

        return $this->recordJournalEntry([
            'entry_date' => $payment->payment_date ?? now()->toDateString(),
            'location_id' => $payment->location_id,
            'source_type' => 'supplier_payment',
            'source_id' => $payment->id,
            'reference_number' => $payment->payment_number,
            'description' => "Supplier Payment #{$payment->payment_number} — {$payment->supplier->business_name}",
            'idempotency_key' => 'gl_supp_payment_' . $payment->id,
        ], $lines, $user);
    }

    /**
     * Post Purchase Return (Debit Note) into General Ledger.
     *
     * Accounting Treatment:
     * - Debit: Accounts Payable (2110)
     * - Credit: Raw Materials Inventory (1150) or Finished Goods (1140)
     */
    public function postPurchaseReturn(PurchaseReturn $purchaseReturn, User $user): JournalEntry
    {
        $apAccount = ChartOfAccount::requireSystemAccount('accounts_payable');
        $rawInvAccount = ChartOfAccount::requireSystemAccount('raw_materials_inventory');

        $amount = (float) $purchaseReturn->total_amount;

        $lines = [
            [
                'chart_of_account_id' => $apAccount->id,
                'debit' => $amount,
                'credit' => 0.00,
                'description' => "Debit note for Purchase Return #{$purchaseReturn->return_number} ({$purchaseReturn->supplier->business_name})",
                'reference_type' => Supplier::class,
                'reference_id' => $purchaseReturn->supplier_id,
            ],
            [
                'chart_of_account_id' => $rawInvAccount->id,
                'debit' => 0.00,
                'credit' => $amount,
                'description' => "Inventory deduction for Purchase Return #{$purchaseReturn->return_number}",
            ],
        ];

        return $this->recordJournalEntry([
            'entry_date' => $purchaseReturn->return_date ?? now()->toDateString(),
            'location_id' => $purchaseReturn->location_id,
            'source_type' => 'purchase_return',
            'source_id' => $purchaseReturn->id,
            'reference_number' => $purchaseReturn->return_number,
            'description' => "Purchase Return #{$purchaseReturn->return_number} — Supplier: {$purchaseReturn->supplier->business_name}",
            'idempotency_key' => 'gl_purch_return_' . $purchaseReturn->id,
        ], $lines, $user);
    }

    /**
     * Post Account Transfer between Cash and Bank accounts.
     *
     * Accounting Treatment:
     * - Debit: Destination Account (Cash 1110 or Bank 1120)
     * - Credit: Source Account (Cash 1110 or Bank 1120)
     * Zero income/expense effect!
     */
    public function postAccountTransfer(AccountTransfer $transfer, User $user): JournalEntry
    {
        $fromAccount = ($transfer->fromAccount->account_type === 'bank')
            ? ChartOfAccount::requireSystemAccount('bank_accounts')
            : ChartOfAccount::requireSystemAccount('cash_in_hand');

        $toAccount = ($transfer->toAccount->account_type === 'bank')
            ? ChartOfAccount::requireSystemAccount('bank_accounts')
            : ChartOfAccount::requireSystemAccount('cash_in_hand');

        $amount = (float) $transfer->amount;

        $lines = [
            [
                'chart_of_account_id' => $toAccount->id,
                'location_id' => $transfer->toAccount->location_id,
                'debit' => $amount,
                'credit' => 0.00,
                'description' => "Transfer deposit into {$transfer->toAccount->name} (Transfer #{$transfer->transfer_number})",
            ],
            [
                'chart_of_account_id' => $fromAccount->id,
                'location_id' => $transfer->fromAccount->location_id,
                'debit' => 0.00,
                'credit' => $amount,
                'description' => "Transfer withdrawal from {$transfer->fromAccount->name} (Transfer #{$transfer->transfer_number})",
            ],
        ];

        return $this->recordJournalEntry([
            'entry_date' => $transfer->transfer_date ?? now()->toDateString(),
            'location_id' => $transfer->fromAccount->location_id,
            'source_type' => 'account_transfer',
            'source_id' => $transfer->id,
            'reference_number' => $transfer->transfer_number,
            'description' => "Account Transfer #{$transfer->transfer_number}: {$transfer->fromAccount->name} -> {$transfer->toAccount->name}",
            'idempotency_key' => 'gl_transfer_' . $transfer->id,
        ], $lines, $user);
    }

    /**
     * Post Operating Expense Voucher into General Ledger.
     *
     * Accounting Treatment:
     * - Debit: Designated Operating Expense Account (Rent 6100, Utilities 6200, Salary 6300, Transport 6400, etc.)
     * - Credit: Cash in Hand (1110) or Bank Accounts (1120)
     */
    public function postExpense(Expense $expense, User $user): JournalEntry
    {
        $cashOrBankAccount = ($expense->payment_method === 'bank' || ($expense->financialAccount && $expense->financialAccount->account_type === 'bank'))
            ? ChartOfAccount::requireSystemAccount('bank_accounts')
            : ChartOfAccount::requireSystemAccount('cash_in_hand');

        // Resolve expense account from category code/name mapping
        $catCode = strtolower($expense->category?->code ?? '');
        $catName = strtolower($expense->category?->name ?? '');

        $expenseIdentifier = match (true) {
            str_contains($catCode, 'rent') || str_contains($catName, 'rent') => 'rent_expense',
            str_contains($catCode, 'util') || str_contains($catName, 'util') || str_contains($catName, 'electric') || str_contains($catName, 'power') => 'utility_expense',
            str_contains($catCode, 'sal') || str_contains($catName, 'sal') || str_contains($catName, 'wage') => 'salary_expense',
            str_contains($catCode, 'trans') || str_contains($catName, 'trans') || str_contains($catName, 'cargo') || str_contains($catName, 'carriage') => 'transport_expense',
            str_contains($catCode, 'maint') || str_contains($catName, 'repair') => 'maintenance_expense',
            str_contains($catCode, 'pack') || str_contains($catName, 'carton') => 'packaging_expense',
            default => 'general_expense',
        };

        $expenseAccount = ChartOfAccount::findBySystemIdentifier($expenseIdentifier)
            ?? ChartOfAccount::requireSystemAccount('general_expense');

        $amount = (float) $expense->amount;

        $expenseRef = $expense->expense_number ?? $expense->voucher_number ?? ('EXP-' . $expense->id);
        $expenseDesc = $expense->title ?? $expense->description ?? 'Operating Expense';

        $lines = [
            [
                'chart_of_account_id' => $expenseAccount->id,
                'debit' => $amount,
                'credit' => 0.00,
                'description' => "Operating Expense: {$expenseDesc}" . ($expense->payee_name ? " (Payee: {$expense->payee_name})" : ''),
            ],
            [
                'chart_of_account_id' => $cashOrBankAccount->id,
                'debit' => 0.00,
                'credit' => $amount,
                'description' => "Cash/Bank payout for Expense Voucher #{$expenseRef}",
            ],
        ];

        return $this->recordJournalEntry([
            'entry_date' => $expense->expense_date ?? now()->toDateString(),
            'location_id' => $expense->location_id,
            'source_type' => 'expense',
            'source_id' => $expense->id,
            'reference_number' => $expenseRef,
            'description' => "Expense Voucher #{$expenseRef} — {$expenseDesc}",
            'idempotency_key' => 'gl_expense_' . $expense->id,
        ], $lines, $user);
    }

    /**
     * Post Factory Finished Goods Completion into General Ledger (Phase 10).
     *
     * Accounting Treatment:
     * - Debit: Finished Goods Inventory (1140) for (Good Pairs * Unit Cost)
     * - Debit: Production Scrap & Wastage (5500) for scrap cost (if any)
     * - Credit: Raw Material Consumption (5200) for allocated material cost
     * - Credit: Direct Factory Labor / Accrued Wages (2130 or 5300) for labor cost
     * - Credit: Factory Manufacturing Overhead (5400) for other costs
     */
    public function postProductionCompletion(ProductionCompletion $completion, User $user): JournalEntry
    {
        $finInvAccount = ChartOfAccount::requireSystemAccount('merchandise_inventory');
        $rawConsumptionAccount = ChartOfAccount::requireSystemAccount('raw_material_consumption');
        $laborAccount = ChartOfAccount::requireSystemAccount('direct_labor');
        $overheadAccount = ChartOfAccount::requireSystemAccount('factory_overhead');
        $scrapAccount = ChartOfAccount::requireSystemAccount('production_scrap');

        $goodPairs = (float) $completion->good_pairs;
        $unitCost = (float) $completion->unit_cost_per_pair;
        $goodPairsTotalCost = (float) bcmul((string) $goodPairs, (string) $unitCost, 2);

        $matCost = (float) $completion->allocated_material_cost;
        $laborCost = (float) $completion->labor_cost;
        $otherCost = (float) $completion->other_costs;

        $batchTotal = (float) $completion->total_cost;
        $scrapDifference = (float) bcsub((string) $batchTotal, (string) $goodPairsTotalCost, 2);

        $lines = [];

        // Debit: Finished Goods Inventory
        $lines[] = [
            'chart_of_account_id' => $finInvAccount->id,
            'debit' => $goodPairsTotalCost,
            'credit' => 0.00,
            'description' => "Finished shoes output ({$goodPairs} pairs @ Rs. {$unitCost}) from Batch #{$completion->productionOrder->order_number}",
        ];

        // Debit: Scrap / Defect cost if good pairs did not absorb 100% of batch
        if ($scrapDifference > 0) {
            $lines[] = [
                'chart_of_account_id' => $scrapAccount->id,
                'debit' => $scrapDifference,
                'credit' => 0.00,
                'description' => "Defect / scrap absorption for Batch #{$completion->productionOrder->order_number}",
            ];
        }

        // Credit: Raw Material Consumption
        if ($matCost > 0) {
            $lines[] = [
                'chart_of_account_id' => $rawConsumptionAccount->id,
                'debit' => 0.00,
                'credit' => $matCost,
                'description' => "Allocated raw materials for Batch #{$completion->productionOrder->order_number}",
            ];
        }

        // Credit: Labor cost applied
        if ($laborCost > 0) {
            $lines[] = [
                'chart_of_account_id' => $laborAccount->id,
                'debit' => 0.00,
                'credit' => $laborCost,
                'description' => "Direct labor applied to Batch #{$completion->productionOrder->order_number}",
            ];
        }

        // Credit: Factory overhead applied
        if ($otherCost > 0) {
            $lines[] = [
                'chart_of_account_id' => $overheadAccount->id,
                'debit' => 0.00,
                'credit' => $otherCost,
                'description' => "Manufacturing overhead applied to Batch #{$completion->productionOrder->order_number}",
            ];
        }

        return $this->recordJournalEntry([
            'entry_date' => $completion->completion_date ?? now()->toDateString(),
            'location_id' => $completion->location_id,
            'source_type' => 'production_completion',
            'source_id' => $completion->id,
            'reference_number' => $completion->completion_number,
            'description' => "Finished Footwear Completion #{$completion->completion_number} — Order #{$completion->productionOrder->order_number}",
            'idempotency_key' => 'gl_prod_comp_' . $completion->id,
        ], $lines, $user);
    }

    /**
     * Record balanced Opening Account Balances.
     */
    public function recordOpeningBalances(array $balances, string $openingDate, User $user, ?string $notes = null): JournalEntry
    {
        $existing = JournalEntry::where('source_type', 'opening_balance')->first();
        if ($existing) {
            throw new InvalidArgumentException("Opening balance journal entry has already been recorded (#{$existing->entry_number}). Reversal is required before re-opening.");
        }

        $lines = [];
        foreach ($balances as $idx => $bal) {
            $lines[] = [
                'chart_of_account_id' => (int) $bal['chart_of_account_id'],
                'location_id' => !empty($bal['location_id']) ? (int) $bal['location_id'] : null,
                'debit' => (float) ($bal['debit'] ?? 0),
                'credit' => (float) ($bal['credit'] ?? 0),
                'description' => $bal['description'] ?? "Opening balance as of {$openingDate}",
            ];
        }

        return $this->recordJournalEntry([
            'entry_date' => $openingDate,
            'location_id' => null, // Global opening
            'source_type' => 'opening_balance',
            'source_id' => 1,
            'reference_number' => 'OB-' . Carbon::parse($openingDate)->format('Ymd'),
            'description' => "Initial Opening Balances Setup — " . ($notes ?? "Formal Accounting Inception"),
            'idempotency_key' => 'gl_opening_balance_' . Carbon::parse($openingDate)->format('Ymd'),
        ], $lines, $user);
    }

    // =========================================================================
    // FINANCIAL REPORTING ENGINES
    // =========================================================================

    /**
     * General Ledger Report: Account running balances with audit trail.
     */
    public function getGeneralLedger(ChartOfAccount $account, ?string $startDate = null, ?string $endDate = null, ?int $locationId = null, ?User $user = null): array
    {
        $startDate = $startDate ?? now()->startOfMonth()->toDateString();
        $endDate = $endDate ?? now()->toDateString();

        // Compute Opening Balance before $startDate
        $openingDebit = JournalEntryLine::where('chart_of_account_id', $account->id)
            ->whereHas('journalEntry', function ($q) use ($startDate, $locationId) {
                $q->where('status', 'posted')
                  ->whereDate('entry_date', '<', $startDate)
                  ->when($locationId, fn ($lq) => $lq->where('location_id', $locationId));
            })
            ->sum('debit');

        $openingCredit = JournalEntryLine::where('chart_of_account_id', $account->id)
            ->whereHas('journalEntry', function ($q) use ($startDate, $locationId) {
                $q->where('status', 'posted')
                  ->whereDate('entry_date', '<', $startDate)
                  ->when($locationId, fn ($lq) => $lq->where('location_id', $locationId));
            })
            ->sum('credit');

        $openingBalance = ($account->normal_balance === 'debit')
            ? bcsub((string) $openingDebit, (string) $openingCredit, 2)
            : bcsub((string) $openingCredit, (string) $openingDebit, 2);

        // Fetch period lines
        $lines = JournalEntryLine::with(['journalEntry.location', 'location'])
            ->where('chart_of_account_id', $account->id)
            ->whereHas('journalEntry', function ($q) use ($startDate, $endDate, $locationId) {
                $q->where('status', 'posted')
                  ->whereDate('entry_date', '>=', $startDate)
                  ->whereDate('entry_date', '<=', $endDate)
                  ->when($locationId, fn ($lq) => $lq->where('location_id', $locationId));
            })
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_entry_lines.line_number')
            ->select('journal_entry_lines.*')
            ->get();

        $running = $openingBalance;
        $totalPeriodDebit = '0.00';
        $totalPeriodCredit = '0.00';
        $entries = [];

        foreach ($lines as $line) {
            $d = (string) $line->debit;
            $c = (string) $line->credit;

            $totalPeriodDebit = bcadd($totalPeriodDebit, $d, 2);
            $totalPeriodCredit = bcadd($totalPeriodCredit, $c, 2);

            if ($account->normal_balance === 'debit') {
                $running = bcadd(bcsub($running, $c, 2), $d, 2);
            } else {
                $running = bcadd(bcsub($running, $d, 2), $c, 2);
            }

            $entries[] = [
                'line_id' => $line->id,
                'entry_id' => $line->journalEntry->id,
                'entry_number' => $line->journalEntry->entry_number,
                'entry_date' => $line->journalEntry->entry_date->toDateString(),
                'reference_number' => $line->journalEntry->reference_number,
                'description' => $line->description ?: $line->journalEntry->description,
                'location' => $line->location ? $line->location->name : ($line->journalEntry->location ? $line->journalEntry->location->name : 'Global'),
                'debit' => $d,
                'credit' => $c,
                'running_balance' => $running,
            ];
        }

        return [
            'account' => $account,
            'filters' => ['from_date' => $startDate, 'to_date' => $endDate, 'location_id' => $locationId],
            'opening_balance' => $openingBalance,
            'closing_balance' => $running,
            'total_debit' => $totalPeriodDebit,
            'total_credit' => $totalPeriodCredit,
            'entries' => $entries,
        ];
    }

    /**
     * Trial Balance: Verifies debits == credits across all active accounts.
     */
    public function getTrialBalance(?string $startDate = null, ?string $endDate = null, ?int $locationId = null, ?User $user = null): array
    {
        $startDate = $startDate ?? now()->startOfYear()->toDateString();
        $endDate = $endDate ?? now()->toDateString();

        $accounts = ChartOfAccount::postable()->orderBy('code')->get();

        $rows = [];
        $grandOpeningDebit = '0.00';
        $grandOpeningCredit = '0.00';
        $grandPeriodDebit = '0.00';
        $grandPeriodCredit = '0.00';
        $grandClosingDebit = '0.00';
        $grandClosingCredit = '0.00';

        foreach ($accounts as $acc) {
            // Opening balance before $startDate
            $opDeb = JournalEntryLine::where('chart_of_account_id', $acc->id)
                ->whereHas('journalEntry', function ($q) use ($startDate, $locationId) {
                    $q->where('status', 'posted')
                      ->whereDate('entry_date', '<', $startDate)
                      ->when($locationId, fn ($lq) => $lq->where('location_id', $locationId));
                })
                ->sum('debit');

            $opCred = JournalEntryLine::where('chart_of_account_id', $acc->id)
                ->whereHas('journalEntry', function ($q) use ($startDate, $locationId) {
                    $q->where('status', 'posted')
                      ->whereDate('entry_date', '<', $startDate)
                      ->when($locationId, fn ($lq) => $lq->where('location_id', $locationId));
                })
                ->sum('credit');

            // Period movements
            $perDeb = JournalEntryLine::where('chart_of_account_id', $acc->id)
                ->whereHas('journalEntry', function ($q) use ($startDate, $endDate, $locationId) {
                    $q->where('status', 'posted')
                      ->whereDate('entry_date', '>=', $startDate)
                      ->whereDate('entry_date', '<=', $endDate)
                      ->when($locationId, fn ($lq) => $lq->where('location_id', $locationId));
                })
                ->sum('debit');

            $perCred = JournalEntryLine::where('chart_of_account_id', $acc->id)
                ->whereHas('journalEntry', function ($q) use ($startDate, $endDate, $locationId) {
                    $q->where('status', 'posted')
                      ->whereDate('entry_date', '>=', $startDate)
                      ->whereDate('entry_date', '<=', $endDate)
                      ->when($locationId, fn ($lq) => $lq->where('location_id', $locationId));
                })
                ->sum('credit');

            $totDeb = bcadd((string) $opDeb, (string) $perDeb, 2);
            $totCred = bcadd((string) $opCred, (string) $perCred, 2);

            // Skip accounts with completely zero activity
            if (bccomp($totDeb, '0.00', 2) === 0 && bccomp($totCred, '0.00', 2) === 0) {
                continue;
            }

            // Net Closing
            $closingNet = bcsub($totDeb, $totCred, 2);
            $closingDebit = '0.00';
            $closingCredit = '0.00';
            if (bccomp($closingNet, '0.00', 2) > 0) {
                $closingDebit = $closingNet;
            } else {
                $closingCredit = bcmul($closingNet, '-1', 2);
            }

            $rows[] = [
                'account_id' => $acc->id,
                'code' => $acc->code,
                'name' => $acc->name,
                'type' => $acc->type,
                'normal_balance' => $acc->normal_balance,
                'opening_debit' => number_format((float) $opDeb, 2, '.', ''),
                'opening_credit' => number_format((float) $opCred, 2, '.', ''),
                'period_debit' => number_format((float) $perDeb, 2, '.', ''),
                'period_credit' => number_format((float) $perCred, 2, '.', ''),
                'closing_debit' => $closingDebit,
                'closing_credit' => $closingCredit,
            ];

            $grandOpeningDebit = bcadd($grandOpeningDebit, (string) $opDeb, 2);
            $grandOpeningCredit = bcadd($grandOpeningCredit, (string) $opCred, 2);
            $grandPeriodDebit = bcadd($grandPeriodDebit, (string) $perDeb, 2);
            $grandPeriodCredit = bcadd($grandPeriodCredit, (string) $perCred, 2);
            $grandClosingDebit = bcadd($grandClosingDebit, $closingDebit, 2);
            $grandClosingCredit = bcadd($grandClosingCredit, $closingCredit, 2);
        }

        $isBalanced = bccomp($grandClosingDebit, $grandClosingCredit, 2) === 0;

        return [
            'filters' => ['from_date' => $startDate, 'to_date' => $endDate, 'location_id' => $locationId],
            'rows' => $rows,
            'totals' => [
                'opening_debit' => $grandOpeningDebit,
                'opening_credit' => $grandOpeningCredit,
                'period_debit' => $grandPeriodDebit,
                'period_credit' => $grandPeriodCredit,
                'closing_debit' => $grandClosingDebit,
                'closing_credit' => $grandClosingCredit,
                'is_balanced' => $isBalanced,
                'discrepancy' => bcsub($grandClosingDebit, $grandClosingCredit, 2),
            ],
        ];
    }

    /**
     * Profit & Loss Statement based on posted General Ledger revenue and expense accounts.
     */
    public function getProfitAndLoss(?string $startDate = null, ?string $endDate = null, ?int $locationId = null, ?User $user = null): array
    {
        $startDate = $startDate ?? now()->startOfMonth()->toDateString();
        $endDate = $endDate ?? now()->toDateString();

        // 1. Revenue accounts
        $revenueAccounts = ChartOfAccount::postable()->where('type', 'revenue')->orderBy('code')->get();
        $revenueRows = [];
        $totalRevenue = '0.00';

        foreach ($revenueAccounts as $revAcc) {
            $deb = JournalEntryLine::where('chart_of_account_id', $revAcc->id)
                ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')->whereDate('entry_date', '>=', $startDate)->whereDate('entry_date', '<=', $endDate)->when($locationId, fn ($lq) => $lq->where('location_id', $locationId)))
                ->sum('debit');
            $cred = JournalEntryLine::where('chart_of_account_id', $revAcc->id)
                ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')->whereDate('entry_date', '>=', $startDate)->whereDate('entry_date', '<=', $endDate)->when($locationId, fn ($lq) => $lq->where('location_id', $locationId)))
                ->sum('credit');

            // Credit normal for revenue
            $net = bcsub((string) $cred, (string) $deb, 2);
            $totalRevenue = bcadd($totalRevenue, $net, 2);

            $revenueRows[] = [
                'account' => $revAcc,
                'amount' => $net,
            ];
        }

        // 2. COGS & Production accounts (code 5000-5999)
        $cogsAccounts = ChartOfAccount::postable()->where('type', 'expense')->where('code', 'like', '5%')->orderBy('code')->get();
        $cogsRows = [];
        $totalCogs = '0.00';

        foreach ($cogsAccounts as $cogsAcc) {
            $deb = JournalEntryLine::where('chart_of_account_id', $cogsAcc->id)
                ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')->whereDate('entry_date', '>=', $startDate)->whereDate('entry_date', '<=', $endDate)->when($locationId, fn ($lq) => $lq->where('location_id', $locationId)))
                ->sum('debit');
            $cred = JournalEntryLine::where('chart_of_account_id', $cogsAcc->id)
                ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')->whereDate('entry_date', '>=', $startDate)->whereDate('entry_date', '<=', $endDate)->when($locationId, fn ($lq) => $lq->where('location_id', $locationId)))
                ->sum('credit');

            // Debit normal for expense
            $net = bcsub((string) $deb, (string) $cred, 2);
            $totalCogs = bcadd($totalCogs, $net, 2);

            $cogsRows[] = [
                'account' => $cogsAcc,
                'amount' => $net,
            ];
        }

        // Gross Profit: Revenue - COGS
        $grossProfit = bcsub($totalRevenue, $totalCogs, 2);

        // 3. Operating & Admin Expenses (code 6000-6999)
        $opExpensesAccounts = ChartOfAccount::postable()->where('type', 'expense')->where('code', 'like', '6%')->orderBy('code')->get();
        $operatingRows = [];
        $totalOperatingExpenses = '0.00';

        foreach ($opExpensesAccounts as $opAcc) {
            $deb = JournalEntryLine::where('chart_of_account_id', $opAcc->id)
                ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')->whereDate('entry_date', '>=', $startDate)->whereDate('entry_date', '<=', $endDate)->when($locationId, fn ($lq) => $lq->where('location_id', $locationId)))
                ->sum('debit');
            $cred = JournalEntryLine::where('chart_of_account_id', $opAcc->id)
                ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')->whereDate('entry_date', '>=', $startDate)->whereDate('entry_date', '<=', $endDate)->when($locationId, fn ($lq) => $lq->where('location_id', $locationId)))
                ->sum('credit');

            $net = bcsub((string) $deb, (string) $cred, 2);
            $totalOperatingExpenses = bcadd($totalOperatingExpenses, $net, 2);

            $operatingRows[] = [
                'account' => $opAcc,
                'amount' => $net,
            ];
        }

        // Net Operating Profit: Gross Profit - Operating Expenses
        $netOperatingProfit = bcsub($grossProfit, $totalOperatingExpenses, 2);

        return [
            'filters' => ['from_date' => $startDate, 'to_date' => $endDate, 'location_id' => $locationId],
            'revenues' => $revenueRows,
            'total_revenue' => $totalRevenue,
            'cogs' => $cogsRows,
            'total_cogs' => $totalCogs,
            'gross_profit' => $grossProfit,
            'operating_expenses' => $operatingRows,
            'total_operating_expenses' => $totalOperatingExpenses,
            'net_profit' => $netOperatingProfit,
        ];
    }

    /**
     * Balance Sheet: Assets = Liabilities + Equity as of a given date.
     */
    public function getBalanceSheet(?string $asOfDate = null, ?int $locationId = null, ?User $user = null): array
    {
        $asOfDate = $asOfDate ?? now()->toDateString();

        // 1. Assets (Debit normal)
        $assetAccounts = ChartOfAccount::postable()->where('type', 'asset')->orderBy('code')->get();
        $assetRows = [];
        $totalAssets = '0.00';

        foreach ($assetAccounts as $acc) {
            $deb = JournalEntryLine::where('chart_of_account_id', $acc->id)
                ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')->whereDate('entry_date', '<=', $asOfDate)->when($locationId, fn ($lq) => $lq->where('location_id', $locationId)))
                ->sum('debit');
            $cred = JournalEntryLine::where('chart_of_account_id', $acc->id)
                ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')->whereDate('entry_date', '<=', $asOfDate)->when($locationId, fn ($lq) => $lq->where('location_id', $locationId)))
                ->sum('credit');

            $balance = bcsub((string) $deb, (string) $cred, 2);
            $totalAssets = bcadd($totalAssets, $balance, 2);

            $assetRows[] = [
                'account' => $acc,
                'balance' => $balance,
            ];
        }

        // 2. Liabilities (Credit normal)
        $liabilityAccounts = ChartOfAccount::postable()->where('type', 'liability')->orderBy('code')->get();
        $liabilityRows = [];
        $totalLiabilities = '0.00';

        foreach ($liabilityAccounts as $acc) {
            $deb = JournalEntryLine::where('chart_of_account_id', $acc->id)
                ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')->whereDate('entry_date', '<=', $asOfDate)->when($locationId, fn ($lq) => $lq->where('location_id', $locationId)))
                ->sum('debit');
            $cred = JournalEntryLine::where('chart_of_account_id', $acc->id)
                ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')->whereDate('entry_date', '<=', $asOfDate)->when($locationId, fn ($lq) => $lq->where('location_id', $locationId)))
                ->sum('credit');

            $balance = bcsub((string) $cred, (string) $deb, 2);
            $totalLiabilities = bcadd($totalLiabilities, $balance, 2);

            $liabilityRows[] = [
                'account' => $acc,
                'balance' => $balance,
            ];
        }

        // 3. Equity Accounts
        $equityAccounts = ChartOfAccount::postable()->where('type', 'equity')->orderBy('code')->get();
        $equityRows = [];
        $totalBaseEquity = '0.00';

        foreach ($equityAccounts as $acc) {
            $deb = JournalEntryLine::where('chart_of_account_id', $acc->id)
                ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')->whereDate('entry_date', '<=', $asOfDate)->when($locationId, fn ($lq) => $lq->where('location_id', $locationId)))
                ->sum('debit');
            $cred = JournalEntryLine::where('chart_of_account_id', $acc->id)
                ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')->whereDate('entry_date', '<=', $asOfDate)->when($locationId, fn ($lq) => $lq->where('location_id', $locationId)))
                ->sum('credit');

            $balance = ($acc->normal_balance === 'debit')
                ? bcsub((string) $deb, (string) $cred, 2)
                : bcsub((string) $cred, (string) $deb, 2);

            if ($acc->normal_balance === 'debit') {
                $totalBaseEquity = bcsub($totalBaseEquity, $balance, 2); // e.g. drawings reduce equity
            } else {
                $totalBaseEquity = bcadd($totalBaseEquity, $balance, 2);
            }

            $equityRows[] = [
                'account' => $acc,
                'balance' => $balance,
            ];
        }

        // 4. Net Cumulative Earnings to date (Revenues - Expenses)
        $totalAllRev = JournalEntryLine::whereHas('account', fn ($q) => $q->where('type', 'revenue'))
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')->whereDate('entry_date', '<=', $asOfDate)->when($locationId, fn ($lq) => $lq->where('location_id', $locationId)))
            ->selectRaw('SUM(credit) - SUM(debit) as net_rev')
            ->value('net_rev') ?? 0;

        $totalAllExp = JournalEntryLine::whereHas('account', fn ($q) => $q->where('type', 'expense'))
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')->whereDate('entry_date', '<=', $asOfDate)->when($locationId, fn ($lq) => $lq->where('location_id', $locationId)))
            ->selectRaw('SUM(debit) - SUM(credit) as net_exp')
            ->value('net_exp') ?? 0;

        $retainedEarningsCumulative = bcsub((string) $totalAllRev, (string) $totalAllExp, 2);

        $totalEquity = bcadd($totalBaseEquity, $retainedEarningsCumulative, 2);
        $totalLiabilitiesAndEquity = bcadd($totalLiabilities, $totalEquity, 2);

        $isBalanced = bccomp($totalAssets, $totalLiabilitiesAndEquity, 2) === 0;

        return [
            'filters' => ['as_of_date' => $asOfDate, 'location_id' => $locationId],
            'assets' => $assetRows,
            'total_assets' => $totalAssets,
            'liabilities' => $liabilityRows,
            'total_liabilities' => $totalLiabilities,
            'equity' => $equityRows,
            'retained_earnings_cumulative' => $retainedEarningsCumulative,
            'total_equity' => $totalEquity,
            'total_liabilities_and_equity' => $totalLiabilitiesAndEquity,
            'is_balanced' => $isBalanced,
            'discrepancy' => bcsub($totalAssets, $totalLiabilitiesAndEquity, 2),
        ];
    }

    /**
     * Reconciliation report: Compares General Ledger control accounts against sub-ledger totals.
     */
    public function getReconciliationReport(User $user, ?int $locationId = null): array
    {
        // 1. Cash & Bank: GL vs FinancialAccounts
        $cashGl = ChartOfAccount::requireSystemAccount('cash_in_hand');
        $bankGl = ChartOfAccount::requireSystemAccount('bank_accounts');

        $glCashBalance = JournalEntryLine::where('chart_of_account_id', $cashGl->id)
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')->when($locationId, fn ($lq) => $lq->where('location_id', $locationId)))
            ->selectRaw('SUM(debit) - SUM(credit) as bal')->value('bal') ?? '0.00';

        $glBankBalance = JournalEntryLine::where('chart_of_account_id', $bankGl->id)
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')->when($locationId, fn ($lq) => $lq->where('location_id', $locationId)))
            ->selectRaw('SUM(debit) - SUM(credit) as bal')->value('bal') ?? '0.00';

        $subCashBalance = FinancialAccount::where('account_type', 'cash')
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->sum('current_balance');

        $subBankBalance = FinancialAccount::where('account_type', 'bank')
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->sum('current_balance');

        // 2. Accounts Receivable: GL 1130 vs Customer Balances
        $arGl = ChartOfAccount::requireSystemAccount('accounts_receivable');
        $glArBalance = JournalEntryLine::where('chart_of_account_id', $arGl->id)
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')->when($locationId, fn ($lq) => $lq->where('location_id', $locationId)))
            ->selectRaw('SUM(debit) - SUM(credit) as bal')->value('bal') ?? '0.00';

        $ledgerAr = CustomerLedgerEntry::when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->selectRaw('COALESCE(SUM(debit_amount) - SUM(credit_amount), 0) as bal')
            ->value('bal') ?? '0.00';

        $unpostedCustOpening = Customer::whereDoesntHave('ledgerEntries', fn ($q) => $q->where('entry_type', 'opening_balance'))
            ->when($locationId, fn ($q) => $q->where('primary_location_id', $locationId))
            ->sum('opening_balance');

        $subArBalance = bcadd((string) $ledgerAr, (string) $unpostedCustOpening, 2);

        // 3. Accounts Payable: GL 2110 vs Supplier Balances
        $apGl = ChartOfAccount::requireSystemAccount('accounts_payable');
        $glApBalance = JournalEntryLine::where('chart_of_account_id', $apGl->id)
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')->when($locationId, fn ($lq) => $lq->where('location_id', $locationId)))
            ->selectRaw('SUM(credit) - SUM(debit) as bal')->value('bal') ?? '0.00';

        $ledgerAp = SupplierLedgerEntry::when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->selectRaw('COALESCE(SUM(credit_amount) - SUM(debit_amount), 0) as bal')
            ->value('bal') ?? '0.00';

        $unpostedSuppOpening = Supplier::whereDoesntHave('ledgerEntries', fn ($q) => $q->where('entry_type', 'opening_balance'))
            ->when($locationId, fn ($q) => $q->where('primary_location_id', $locationId))
            ->sum('opening_balance');

        $subApBalance = bcadd((string) $ledgerAp, (string) $unpostedSuppOpening, 2);

        // 4. Inventory: GL 1140 & 1150 vs StockBalances (WAC)
        $invFinGl = ChartOfAccount::requireSystemAccount('merchandise_inventory');
        $invRawGl = ChartOfAccount::requireSystemAccount('raw_materials_inventory');

        $glFinInv = JournalEntryLine::where('chart_of_account_id', $invFinGl->id)
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')->when($locationId, fn ($lq) => $lq->where('location_id', $locationId)))
            ->selectRaw('SUM(debit) - SUM(credit) as bal')->value('bal') ?? '0.00';

        $glRawInv = JournalEntryLine::where('chart_of_account_id', $invRawGl->id)
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')->when($locationId, fn ($lq) => $lq->where('location_id', $locationId)))
            ->selectRaw('SUM(debit) - SUM(credit) as bal')->value('bal') ?? '0.00';

        $subFinInv = StockBalance::whereHas('productVariant.product.category', fn ($c) => $c->where('type', 'finished_good'))
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->selectRaw('SUM(pairs_quantity * avg_unit_cost) as total_val')
            ->value('total_val') ?? '0.00';

        $subRawInv = StockBalance::whereHas('productVariant.product.category', fn ($c) => $c->whereIn('type', ['raw_material', 'packaging']))
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->selectRaw('SUM(pairs_quantity * avg_unit_cost) as total_val')
            ->value('total_val') ?? '0.00';

        return [
            'location_id' => $locationId,
            'items' => [
                [
                    'name' => 'Cash in Hand',
                    'gl_account' => $cashGl->code . ' — ' . $cashGl->name,
                    'gl_balance' => (string) $glCashBalance,
                    'subledger_source' => 'Cash Drawers (Financial Accounts)',
                    'subledger_balance' => (string) $subCashBalance,
                    'variance' => bcsub((string) $glCashBalance, (string) $subCashBalance, 2),
                ],
                [
                    'name' => 'Bank Accounts',
                    'gl_account' => $bankGl->code . ' — ' . $bankGl->name,
                    'gl_balance' => (string) $glBankBalance,
                    'subledger_source' => 'Bank Books (Financial Accounts)',
                    'subledger_balance' => (string) $subBankBalance,
                    'variance' => bcsub((string) $glBankBalance, (string) $subBankBalance, 2),
                ],
                [
                    'name' => 'Accounts Receivable',
                    'gl_account' => $arGl->code . ' — ' . $arGl->name,
                    'gl_balance' => (string) $glArBalance,
                    'subledger_source' => 'Customer Khata (Detailed Receivables)',
                    'subledger_balance' => (string) $subArBalance,
                    'variance' => bcsub((string) $glArBalance, (string) $subArBalance, 2),
                ],
                [
                    'name' => 'Accounts Payable',
                    'gl_account' => $apGl->code . ' — ' . $apGl->name,
                    'gl_balance' => (string) $glApBalance,
                    'subledger_source' => 'Supplier Khata (Detailed Payables)',
                    'subledger_balance' => (string) $subApBalance,
                    'variance' => bcsub((string) $glApBalance, (string) $subApBalance, 2),
                ],
                [
                    'name' => 'Finished Goods Inventory',
                    'gl_account' => $invFinGl->code . ' — ' . $invFinGl->name,
                    'gl_balance' => (string) $glFinInv,
                    'subledger_source' => 'Stock Balances (WAC)',
                    'subledger_balance' => (string) $subFinInv,
                    'variance' => bcsub((string) $glFinInv, (string) $subFinInv, 2),
                ],
                [
                    'name' => 'Raw Materials Inventory',
                    'gl_account' => $invRawGl->code . ' — ' . $invRawGl->name,
                    'gl_balance' => (string) $glRawInv,
                    'subledger_source' => 'Stock Balances (WAC)',
                    'subledger_balance' => (string) $subRawInv,
                    'variance' => bcsub((string) $glRawInv, (string) $subRawInv, 2),
                ],
            ],
        ];
    }

    /**
     * Export Trial Balance as formula-injection safe CSV stream.
     */
    public function exportTrialBalanceCsv(array $data): StreamedResponse
    {
        $headers = [
            'Account Code',
            'Account Name',
            'Type',
            'Opening Debit (PKR)',
            'Opening Credit (PKR)',
            'Period Debit (PKR)',
            'Period Credit (PKR)',
            'Closing Debit (PKR)',
            'Closing Credit (PKR)',
        ];

        $rows = [];
        foreach ($data['rows'] as $r) {
            $rows[] = [
                $r['code'],
                $r['name'],
                ucfirst($r['type']),
                $r['opening_debit'],
                $r['opening_credit'],
                $r['period_debit'],
                $r['period_credit'],
                $r['closing_debit'],
                $r['closing_credit'],
            ];
        }

        $rows[] = [
            'TOTALS',
            'Grand Total',
            '',
            $data['totals']['opening_debit'],
            $data['totals']['opening_credit'],
            $data['totals']['period_debit'],
            $data['totals']['period_credit'],
            $data['totals']['closing_debit'],
            $data['totals']['closing_credit'],
        ];

        $filename = 'trial_balance_' . ($data['filters']['from_date'] ?? 'start') . '_to_' . ($data['filters']['to_date'] ?? 'now') . '.csv';

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

    protected function generateEntryNumber(?Location $location = null): string
    {
        $dateStr = now()->format('Ymd');
        $code = $location ? $location->code : 'HQ';
        $prefix = "JV-{$code}-{$dateStr}";

        $latest = JournalEntry::where('entry_number', 'like', "{$prefix}-%")
            ->lockForUpdate()
            ->latest('id')
            ->value('entry_number');

        $nextSeq = 1;
        if ($latest && preg_match('/-(\d{4})$/', $latest, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }

        return sprintf('%s-%04d', $prefix, $nextSeq);
    }
}
