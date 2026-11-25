<?php

namespace App\Services\Accounting;

use App\Models\AccountTransaction;
use App\Models\AccountTransfer;
use App\Models\FinancialAccount;
use App\Models\Location;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CashAndBankService
{
    /**
     * Create a new cash or bank financial account.
     */
    public function createAccount(array $data, User $user): FinancialAccount
    {
        $locationId = isset($data['location_id']) && $data['location_id'] ? (int) $data['location_id'] : null;

        if ($locationId && !$user->hasLocationAccess($locationId, true)) {
            throw new AuthorizationException("User #{$user->id} does not have authorization for location #{$locationId}.");
        }

        return DB::transaction(function () use ($data, $user, $locationId) {
            $accountType = $data['account_type'] ?? $data['type'] ?? 'cash';
            $accountCode = !empty($data['account_code'])
                ? trim((string) $data['account_code'])
                : $this->generateAccountCode($accountType, $locationId);

            $openingBalance = isset($data['opening_balance'])
                ? number_format((float) $data['opening_balance'], 2, '.', '')
                : '0.00';

            $account = FinancialAccount::create([
                'account_code' => $accountCode,
                'name' => trim((string) $data['name']),
                'account_type' => $accountType,
                'location_id' => $locationId,
                'bank_name' => isset($data['bank_name']) ? trim((string) $data['bank_name']) : null,
                'account_number' => isset($data['account_number']) ? trim((string) $data['account_number']) : null,
                'iban' => isset($data['iban']) ? trim((string) $data['iban']) : null,
                'branch_code' => isset($data['branch_code']) ? trim((string) $data['branch_code']) : null,
                'currency' => $data['currency'] ?? 'PKR',
                'opening_balance' => $openingBalance,
                'current_balance' => '0.00',
                'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
                'notes' => isset($data['notes']) ? trim((string) $data['notes']) : null,
                'created_by' => $user->id,
            ]);

            // If opening balance > 0, post controlled opening balance entry
            if (bccomp($openingBalance, '0.00', 2) > 0) {
                $this->postOpeningBalance(
                    account: $account,
                    amount: (float) $openingBalance,
                    date: $data['opening_date'] ?? now()->toDateString(),
                    user: $user,
                    notes: "Initial opening balance on account setup"
                );
            }

            return $account->fresh(['location', 'creator']);
        });
    }

    /**
     * Update account details (name, bank details, active status).
     * Opening balance cannot be re-applied or altered through normal update.
     */
    public function updateAccount(FinancialAccount $account, array $data, User $user): FinancialAccount
    {
        if ($account->location_id && !$user->hasLocationAccess($account->location_id, true)) {
            throw new AuthorizationException("User #{$user->id} does not have authorization for location #{$account->location_id}.");
        }

        $account->update([
            'name' => trim((string) ($data['name'] ?? $account->name)),
            'bank_name' => isset($data['bank_name']) ? trim((string) $data['bank_name']) : $account->bank_name,
            'account_number' => isset($data['account_number']) ? trim((string) $data['account_number']) : $account->account_number,
            'iban' => isset($data['iban']) ? trim((string) $data['iban']) : $account->iban,
            'branch_code' => isset($data['branch_code']) ? trim((string) $data['branch_code']) : $account->branch_code,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : $account->is_active,
            'notes' => isset($data['notes']) ? trim((string) $data['notes']) : $account->notes,
        ]);

        return $account->fresh(['location', 'creator']);
    }

    /**
     * Post a controlled opening balance entry to an account.
     * Guaranteed to execute at most once per account.
     */
    public function postOpeningBalance(
        FinancialAccount $account,
        float $amount,
        string $date,
        User $user,
        ?string $notes = null
    ): AccountTransaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Opening balance amount must be positive. Received: {$amount}");
        }

        return DB::transaction(function () use ($account, $amount, $date, $user, $notes) {
            $lockedAccount = FinancialAccount::where('id', $account->id)->lockForUpdate()->firstOrFail();

            $alreadyPosted = AccountTransaction::where('financial_account_id', $lockedAccount->id)
                ->where('transaction_type', 'opening_balance')
                ->exists();

            if ($alreadyPosted) {
                throw new RuntimeException("Opening balance has already been posted for account {$lockedAccount->account_code}.");
            }

            $amountStr = number_format($amount, 2, '.', '');
            $newBalance = bcadd((string) $lockedAccount->current_balance, $amountStr, 2);

            $lockedAccount->opening_balance = $amountStr;
            $lockedAccount->current_balance = $newBalance;
            $lockedAccount->save();

            $txnNumber = $this->generateTransactionNumber();

            return AccountTransaction::create([
                'transaction_number' => $txnNumber,
                'financial_account_id' => $lockedAccount->id,
                'transaction_type' => 'opening_balance',
                'transaction_date' => $date,
                'debit_amount' => $amountStr,
                'credit_amount' => '0.00',
                'balance_after' => $newBalance,
                'reference_type' => 'opening_balance',
                'reference_id' => $lockedAccount->id,
                'description' => $notes ?: "Opening balance for {$lockedAccount->name}",
                'created_by' => $user->id,
            ]);
        });
    }

    /**
     * Alias for postOpeningBalance.
     */
    public function recordOpeningBalance(
        FinancialAccount $account,
        float $amount,
        string $date,
        User $user,
        ?string $notes = null
    ): AccountTransaction {
        return $this->postOpeningBalance($account, $amount, $date, $user, $notes);
    }

    /**
     * Record an atomic, validated cash or bank ledger transaction.
     *
     * Asset Accounting Rule:
     * - Debit increases cash/bank asset balance.
     * - Credit decreases cash/bank asset balance.
     */
    public function recordTransaction(
        FinancialAccount $account,
        string $transactionType,
        float|string $debitAmount,
        float|string $creditAmount,
        string $transactionDate,
        User $user,
        ?string $description = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $referenceNumber = null,
        ?string $idempotencyKey = null,
        ?int $transferId = null
    ): AccountTransaction {
        $debitStr = number_format((float) $debitAmount, 2, '.', '');
        $creditStr = number_format((float) $creditAmount, 2, '.', '');

        if (bccomp($debitStr, '0.00', 2) < 0 || bccomp($creditStr, '0.00', 2) < 0) {
            throw new InvalidArgumentException("Debit and Credit amounts must be non-negative.");
        }

        if (bccomp($debitStr, '0.00', 2) === 0 && bccomp($creditStr, '0.00', 2) === 0) {
            throw new InvalidArgumentException("A financial transaction must have a non-zero debit or credit amount.");
        }

        if (bccomp($debitStr, '0.00', 2) > 0 && bccomp($creditStr, '0.00', 2) > 0) {
            throw new InvalidArgumentException("A transaction cannot have both positive debit and positive credit simultaneously. Exactly one must be positive.");
        }

        $payloadHash = $idempotencyKey
            ? hash('sha256', json_encode([
                'account_id' => $account->id,
                'type' => $transactionType,
                'debit' => $debitStr,
                'credit' => $creditStr,
                'date' => $transactionDate,
                'ref_type' => $referenceType,
                'ref_id' => $referenceId,
            ]))
            : null;

        // Fast-path: Check existing idempotent transaction
        if ($idempotencyKey) {
            $existing = AccountTransaction::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->request_hash && !hash_equals($existing->request_hash, $payloadHash)) {
                    throw new InvalidArgumentException("Idempotency key '{$idempotencyKey}' has already been used with a different request payload.");
                }
                return $existing->load(['account', 'creator']);
            }
        }

        try {
            return DB::transaction(function () use (
                $account,
                $transactionType,
                $debitStr,
                $creditStr,
                $transactionDate,
                $user,
                $description,
                $referenceType,
                $referenceId,
                $referenceNumber,
                $idempotencyKey,
                $payloadHash,
                $transferId
            ) {
                $lockedAccount = FinancialAccount::where('id', $account->id)->lockForUpdate()->firstOrFail();

                if (!$lockedAccount->is_active) {
                    throw new RuntimeException("Cannot post transaction to inactive account #{$lockedAccount->account_code}.");
                }

                // Balance calculation: Balance + Debit - Credit
                $currentBal = (string) $lockedAccount->current_balance;
                $newBalance = bcsub(bcadd($currentBal, $debitStr, 2), $creditStr, 2);

                // Disallow negative cash / overdraft unless explicit credit account
                if (bccomp($newBalance, '0.00', 2) < 0) {
                    throw new InvalidArgumentException("Insufficient account balance in {$lockedAccount->name} (Balance: PKR {$currentBal}). Transaction would cause negative balance (PKR {$newBalance}).");
                }

                $lockedAccount->current_balance = $newBalance;
                $lockedAccount->save();

                $txnNumber = $this->generateTransactionNumber();

                return AccountTransaction::create([
                    'transaction_number' => $txnNumber,
                    'financial_account_id' => $lockedAccount->id,
                    'transaction_type' => $transactionType,
                    'transaction_date' => $transactionDate,
                    'debit_amount' => $debitStr,
                    'credit_amount' => $creditStr,
                    'balance_after' => $newBalance,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'reference_number' => $referenceNumber,
                    'description' => $description ?: ucfirst(str_replace('_', ' ', $transactionType)),
                    'transfer_id' => $transferId,
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $payloadHash,
                    'created_by' => $user->id,
                ]);
            });
        } catch (QueryException $e) {
            if ($idempotencyKey && $this->isIdempotencyCollision($e, 'acc_txns')) {
                $existing = AccountTransaction::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    if ($existing->request_hash && !hash_equals($existing->request_hash, $payloadHash)) {
                        throw new InvalidArgumentException("Idempotency key '{$idempotencyKey}' has already been used with a different request payload.");
                    }
                    return $existing->load(['account', 'creator']);
                }
            }
            throw $e;
        }
    }

    /**
     * Atomically transfer funds between two financial accounts.
     * Uses strict deadlock-free locking order and guarantees zero net system effect.
     */
    public function transfer(
        FinancialAccount $source,
        FinancialAccount $destination,
        float $amount,
        string $date,
        User $user,
        string $reason,
        ?string $referenceNumber = null,
        ?string $notes = null,
        ?string $idempotencyKey = null
    ): AccountTransfer {
        if ($source->id === $destination->id) {
            throw new InvalidArgumentException("Source and destination accounts must be different.");
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException("Transfer amount must be greater than zero. Received: {$amount}");
        }

        $amountStr = number_format($amount, 2, '.', '');

        $payloadHash = $idempotencyKey
            ? hash('sha256', json_encode([
                'src' => $source->id,
                'dst' => $destination->id,
                'amt' => $amountStr,
                'date' => $date,
            ]))
            : null;

        if ($idempotencyKey) {
            $existing = AccountTransfer::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->request_hash && !hash_equals($existing->request_hash, $payloadHash)) {
                    throw new InvalidArgumentException("Idempotency key '{$idempotencyKey}' has already been used with a different request payload.");
                }
                return $existing->load(['sourceAccount', 'destinationAccount', 'transactions']);
            }
        }

        try {
            return DB::transaction(function () use (
                $source,
                $destination,
                $amountStr,
                $date,
                $user,
                $reason,
                $referenceNumber,
                $notes,
                $idempotencyKey,
                $payloadHash
            ) {
                // Deadlock prevention: lock rows in consistent ASC order by ID
                $firstId = min($source->id, $destination->id);
                $secondId = max($source->id, $destination->id);

                FinancialAccount::where('id', $firstId)->lockForUpdate()->firstOrFail();
                FinancialAccount::where('id', $secondId)->lockForUpdate()->firstOrFail();

                $lockedSource = FinancialAccount::findOrFail($source->id);
                $lockedDest = FinancialAccount::findOrFail($destination->id);

                if (!$lockedSource->is_active || !$lockedDest->is_active) {
                    throw new RuntimeException("Both source and destination accounts must be active.");
                }

                if (bccomp((string) $lockedSource->current_balance, $amountStr, 2) < 0) {
                    throw new InvalidArgumentException("Insufficient balance in {$lockedSource->name}. Available: PKR {$lockedSource->current_balance}, Required: PKR {$amountStr}");
                }

                $transferNumber = $this->generateTransferNumber();

                $transfer = AccountTransfer::create([
                    'transfer_number' => $transferNumber,
                    'source_account_id' => $lockedSource->id,
                    'destination_account_id' => $lockedDest->id,
                    'transfer_date' => $date,
                    'amount' => $amountStr,
                    'reason' => $reason,
                    'reference_number' => $referenceNumber,
                    'notes' => $notes,
                    'status' => 'posted',
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $payloadHash,
                    'created_by' => $user->id,
                ]);

                // 1. Source Account: Outflow (Credit decreases source balance)
                $this->recordTransaction(
                    account: $lockedSource,
                    transactionType: 'transfer_out',
                    debitAmount: 0.00,
                    creditAmount: $amountStr,
                    transactionDate: $date,
                    user: $user,
                    description: "Transfer to {$lockedDest->name}: {$reason}",
                    referenceType: 'AccountTransfer',
                    referenceId: $transfer->id,
                    referenceNumber: $transferNumber,
                    transferId: $transfer->id
                );

                // 2. Destination Account: Inflow (Debit increases destination balance)
                $this->recordTransaction(
                    account: $lockedDest,
                    transactionType: 'transfer_in',
                    debitAmount: $amountStr,
                    creditAmount: 0.00,
                    transactionDate: $date,
                    user: $user,
                    description: "Transfer from {$lockedSource->name}: {$reason}",
                    referenceType: 'AccountTransfer',
                    referenceId: $transfer->id,
                    referenceNumber: $transferNumber,
                    transferId: $transfer->id
                );

                return $transfer->fresh(['sourceAccount', 'destinationAccount', 'transactions']);
            });
        } catch (QueryException $e) {
            if ($idempotencyKey && $this->isIdempotencyCollision($e, 'acc_transfers')) {
                $existing = AccountTransfer::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    if ($existing->request_hash && !hash_equals($existing->request_hash, $payloadHash)) {
                        throw new InvalidArgumentException("Idempotency key '{$idempotencyKey}' has already been used with a different request payload.");
                    }
                    return $existing->load(['sourceAccount', 'destinationAccount', 'transactions']);
                }
            }
            throw $e;
        }
    }

    /**
     * Alias for transfer with compatible parameter order.
     */
    public function recordTransfer(
        FinancialAccount $fromAccount,
        FinancialAccount $toAccount,
        float $amount,
        string $transferDate,
        User $user,
        ?string $notes = null,
        ?string $referenceNumber = null,
        ?string $idempotencyKey = null,
        ?string $reason = 'Inter-account transfer'
    ): AccountTransfer {
        return $this->transfer(
            source: $fromAccount,
            destination: $toAccount,
            amount: $amount,
            date: $transferDate,
            user: $user,
            reason: $reason ?? 'Inter-account transfer',
            referenceNumber: $referenceNumber,
            notes: $notes,
            idempotencyKey: $idempotencyKey
        );
    }

    /**
     * Reverse an auditable financial transaction.
     * Restores account balance and records an explicit counter-entry.
     */
    public function reverseTransaction(
        AccountTransaction $transaction,
        string $reason,
        User $user
    ): AccountTransaction {
        if ($transaction->is_reversal) {
            throw new InvalidArgumentException("Cannot reverse a transaction that is already a reversal.");
        }

        return DB::transaction(function () use ($transaction, $reason, $user) {
            // Check if already reversed
            $alreadyReversed = AccountTransaction::where('reversed_transaction_id', $transaction->id)->exists();
            if ($alreadyReversed) {
                throw new RuntimeException("Transaction #{$transaction->transaction_number} has already been reversed.");
            }

            $account = FinancialAccount::where('id', $transaction->financial_account_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Reverse sign: If original was debit (inflow), counter is credit (outflow).
            $counterDebit = $transaction->credit_amount;
            $counterCredit = $transaction->debit_amount;

            $revNumber = $this->generateTransactionNumber();
            $currentBal = (string) $account->current_balance;
            $newBalance = bcsub(bcadd($currentBal, (string) $counterDebit, 2), (string) $counterCredit, 2);

            if (bccomp($newBalance, '0.00', 2) < 0) {
                throw new InvalidArgumentException("Reversal would result in negative balance in {$account->name}.");
            }

            $account->current_balance = $newBalance;
            $account->save();

            return AccountTransaction::create([
                'transaction_number' => $revNumber,
                'financial_account_id' => $account->id,
                'transaction_type' => 'reversal',
                'transaction_date' => now()->toDateString(),
                'debit_amount' => $counterDebit,
                'credit_amount' => $counterCredit,
                'balance_after' => $newBalance,
                'reference_type' => $transaction->reference_type,
                'reference_id' => $transaction->reference_id,
                'reference_number' => $transaction->transaction_number,
                'description' => "Reversal of TXN #{$transaction->transaction_number}: {$reason}",
                'is_reversal' => true,
                'reversed_transaction_id' => $transaction->id,
                'created_by' => $user->id,
            ]);
        });
    }

    /**
     * Compute comprehensive financial account statement metrics using exact BCMath arithmetic.
     */
    public function calculateBalance(
        FinancialAccount $account,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        $priorDebit = '0.00';
        $priorCredit = '0.00';

        if ($fromDate) {
            $priorTransactions = AccountTransaction::where('financial_account_id', $account->id)
                ->where('transaction_date', '<', $fromDate)
                ->get();

            foreach ($priorTransactions as $tx) {
                $priorDebit = bcadd($priorDebit, (string) $tx->debit_amount, 2);
                $priorCredit = bcadd($priorCredit, (string) $tx->credit_amount, 2);
            }
        }

        $periodOpening = bcsub($priorDebit, $priorCredit, 2);

        $periodQuery = AccountTransaction::where('financial_account_id', $account->id)
            ->when($fromDate, fn ($q) => $q->where('transaction_date', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->where('transaction_date', '<=', $toDate))
            ->orderBy('transaction_date')
            ->orderBy('id');

        $periodTransactions = $periodQuery->get();

        $periodDebit = '0.00';
        $periodCredit = '0.00';

        foreach ($periodTransactions as $tx) {
            $periodDebit = bcadd($periodDebit, (string) $tx->debit_amount, 2);
            $periodCredit = bcadd($periodCredit, (string) $tx->credit_amount, 2);
        }

        $closingBalance = bcsub(bcadd($periodOpening, $periodDebit, 2), $periodCredit, 2);

        return [
            'period_opening_balance' => $periodOpening,
            'total_inflow' => $periodDebit,
            'total_outflow' => $periodCredit,
            'closing_balance' => $closingBalance,
            'live_account_balance' => (string) $account->current_balance,
            'is_reconciled' => (bccomp($closingBalance, (string) $account->current_balance, 2) === 0),
            'transactions_count' => $periodTransactions->count(),
        ];
    }

    /**
     * Reconcile account live balance against transaction ledger history.
     */
    public function reconcile(FinancialAccount $account): bool
    {
        return $this->reconcileBalance($account)['reconciled'];
    }

    /**
     * Detailed balance reconciliation metrics.
     */
    public function reconcileBalance(FinancialAccount $account): array
    {
        $allTxns = AccountTransaction::where('financial_account_id', $account->id)->get();

        $totalDebit = '0.00';
        $totalCredit = '0.00';

        foreach ($allTxns as $t) {
            $totalDebit = bcadd($totalDebit, (string) $t->debit_amount, 2);
            $totalCredit = bcadd($totalCredit, (string) $t->credit_amount, 2);
        }

        $expectedBalance = bcsub($totalDebit, $totalCredit, 2);
        $diff = bcsub($expectedBalance, (string) $account->current_balance, 2);
        $reconciled = (bccomp($diff, '0.00', 2) === 0);

        return [
            'reconciled' => $reconciled,
            'is_reconciled' => $reconciled,
            'stored_balance' => (string) $account->current_balance,
            'calculated_balance' => $expectedBalance,
            'difference' => $diff,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'transactions_count' => $allTxns->count(),
        ];
    }

    /**
     * Generate sequential unique account code.
     */
    protected function generateAccountCode(string $type, ?int $locationId = null): string
    {
        $prefix = ($type === 'cash') ? 'ACC-CSH' : 'ACC-BNK';
        if ($locationId) {
            $loc = Location::find($locationId);
            if ($loc) {
                $prefix .= "-{$loc->code}";
            }
        }

        $latest = FinancialAccount::where('account_code', 'like', "{$prefix}-%")
            ->lockForUpdate()
            ->latest('id')
            ->value('account_code');

        $nextSeq = 1;
        if ($latest && preg_match('/-(\d{3,4})$/', $latest, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }

        return sprintf('%s-%03d', $prefix, $nextSeq);
    }

    /**
     * Generate sequential unique transaction number.
     * Format: TXN-{YYYYMMDD}-{XXXX}
     */
    protected function generateTransactionNumber(): string
    {
        $dateStr = now()->format('Ymd');
        $prefix = "TXN-{$dateStr}";

        $latest = AccountTransaction::where('transaction_number', 'like', "{$prefix}-%")
            ->lockForUpdate()
            ->latest('id')
            ->value('transaction_number');

        $nextSeq = 1;
        if ($latest && preg_match('/-(\d{4})$/', $latest, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }

        return sprintf('%s-%04d', $prefix, $nextSeq);
    }

    /**
     * Generate sequential unique transfer number.
     * Format: TRF-{YYYYMMDD}-{XXXX}
     */
    protected function generateTransferNumber(): string
    {
        $dateStr = now()->format('Ymd');
        $prefix = "TRF-{$dateStr}";

        $latest = AccountTransfer::where('transfer_number', 'like', "{$prefix}-%")
            ->lockForUpdate()
            ->latest('id')
            ->value('transfer_number');

        $nextSeq = 1;
        if ($latest && preg_match('/-(\d{4})$/', $latest, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }

        return sprintf('%s-%04d', $prefix, $nextSeq);
    }

    /**
     * Check idempotency constraint collisions.
     */
    protected function isIdempotencyCollision(QueryException $e, string $table): bool
    {
        $code = (int) $e->getCode();
        $sqlState = (string) ($e->errorInfo[0] ?? $code);
        $driverCode = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : null;
        $msg = $e->getMessage();

        $isUnique = ($code === 23000 || $sqlState === '23000' || $driverCode === 1062 || $driverCode === 19);

        return $isUnique && (str_contains($msg, "{$table}_idempotency_unique") || str_contains($msg, 'idempotency_key'));
    }
}
