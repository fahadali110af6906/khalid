<?php

namespace App\Services\Accounting;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialAccount;
use App\Models\Location;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ExpenseService
{
    public function __construct(
        protected CashAndBankService $cashAndBankService
    ) {}

    /**
     * Create a new draft expense without deducting cash or bank account.
     */
    public function createDraft(array $data, User $user): Expense
    {
        $locationId = (int) $data['location_id'];
        $accountId = (int) $data['financial_account_id'];
        $amount = (float) $data['amount'];

        if (!$user->hasLocationAccess($locationId, true)) {
            throw new AuthorizationException("User #{$user->id} does not have authorization for location #{$locationId}.");
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException("Expense amount must be greater than zero. Received: {$amount}");
        }

        $location = Location::findOrFail($locationId);
        $account = FinancialAccount::findOrFail($accountId);
        $category = ExpenseCategory::findOrFail((int) $data['expense_category_id']);

        if (!$category->is_active) {
            throw new InvalidArgumentException("Expense category '{$category->name}' is inactive.");
        }

        if (!$account->is_active) {
            throw new InvalidArgumentException("Payment account '{$account->name}' is inactive.");
        }

        return DB::transaction(function () use ($data, $user, $location, $account, $category, $amount) {
            $expenseNumber = $this->generateExpenseNumber($location);
            $expenseDate = $data['expense_date'] ?? now()->toDateString();
            $amountStr = number_format($amount, 2, '.', '');

            return Expense::create([
                'expense_number' => $expenseNumber,
                'location_id' => $location->id,
                'expense_category_id' => $category->id,
                'financial_account_id' => $account->id,
                'expense_date' => $expenseDate,
                'amount' => $amountStr,
                'payee_name' => isset($data['payee_name']) ? trim((string) $data['payee_name']) : null,
                'payment_method' => $data['payment_method'] ?? ($account->isCash() ? 'cash' : 'bank_transfer'),
                'description' => !empty($data['description']) ? trim((string) $data['description']) : ($category->name . ' expense'),
                'reference_number' => isset($data['reference_number']) ? trim((string) $data['reference_number']) : null,
                'notes' => isset($data['notes']) ? trim((string) $data['notes']) : null,
                'status' => 'draft',
                'created_by' => $user->id,
            ]);
        });
    }

    /**
     * Atomically post an expense:
     * 1. Deducts payment account balance via CashAndBankService.
     * 2. Sets expense status to 'posted'.
     * 3. Enforces idempotency to prevent duplicate deductions.
     */
    public function postExpense(
        Expense $expense,
        User|array $userOrData,
        User|int|null $userOrAccountId = null,
        ?string $idempotencyKey = null,
        array $data = []
    ): Expense {
        if ($userOrData instanceof User) {
            $user = $userOrData;
            $financialAccountId = is_int($userOrAccountId) ? $userOrAccountId : null;
        } elseif ($userOrAccountId instanceof User) {
            $user = $userOrAccountId;
            $data = is_array($userOrData) ? $userOrData : [];
            $financialAccountId = $data['financial_account_id'] ?? null;
            $idempotencyKey = $data['idempotency_key'] ?? $idempotencyKey;
        } else {
            $user = auth()->user();
            $financialAccountId = null;
        }

        if (!$user->hasLocationAccess($expense->location_id, true)) {
            throw new AuthorizationException("User #{$user->id} does not have authorization for location #{$expense->location_id}.");
        }

        if ($financialAccountId) {
            $expense->financial_account_id = $financialAccountId;
            $expense->save();
        }

        $idempotencyKey = !empty($idempotencyKey) ? trim((string) $idempotencyKey) : (!empty($data['idempotency_key']) ? trim((string) $data['idempotency_key']) : null);
        $payloadHash = $this->computeExpensePayloadHash($expense);

        if ($idempotencyKey) {
            $existing = Expense::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->request_hash && !hash_equals($existing->request_hash, $payloadHash)) {
                    throw new InvalidArgumentException("Idempotency key '{$idempotencyKey}' has already been used with a different request payload.");
                }
                return $existing->load(['location', 'category', 'account', 'transaction']);
            }
        }

        if ($expense->status !== 'draft') {
            throw new RuntimeException("Only draft expenses can be posted. Current status: {$expense->status}");
        }

        try {
            return DB::transaction(function () use ($expense, $user, $idempotencyKey, $payloadHash) {
                $lockedExpense = Expense::where('id', $expense->id)->lockForUpdate()->firstOrFail();

                if ($lockedExpense->status !== 'draft') {
                    throw new RuntimeException("Expense #{$lockedExpense->expense_number} is already posted.");
                }

                $account = FinancialAccount::where('id', $lockedExpense->financial_account_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Post deduction transaction to cash/bank asset ledger
                $txn = $this->cashAndBankService->recordTransaction(
                    account: $account,
                    transactionType: 'expense',
                    debitAmount: 0.00,
                    creditAmount: (float) $lockedExpense->amount,
                    transactionDate: $lockedExpense->expense_date->toDateString(),
                    user: $user,
                    description: "Expense #{$lockedExpense->expense_number} (" . ($lockedExpense->category ? $lockedExpense->category->name : 'General') . "): {$lockedExpense->description}",
                    referenceType: 'Expense',
                    referenceId: $lockedExpense->id,
                    referenceNumber: $lockedExpense->expense_number
                );

                $lockedExpense->status = 'posted';
                $lockedExpense->posted_at = now();
                $lockedExpense->posted_by = $user->id;
                $lockedExpense->idempotency_key = $idempotencyKey;
                $lockedExpense->request_hash = $payloadHash;
                $lockedExpense->save();

                return $lockedExpense->fresh(['location', 'category', 'account', 'transaction', 'accountTransaction']);
            });
        } catch (QueryException $e) {
            if ($idempotencyKey && $this->isIdempotencyCollision($e, 'expenses')) {
                $existing = Expense::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    if ($existing->request_hash && !hash_equals($existing->request_hash, $payloadHash)) {
                        throw new InvalidArgumentException("Idempotency key '{$idempotencyKey}' has already been used with a different request payload.");
                    }
                    return $existing->load(['location', 'category', 'account', 'transaction', 'accountTransaction']);
                }
            }
            throw $e;
        }
    }

    /**
     * Reverse a posted expense, restoring the cash/bank balance.
     */
    public function reverseExpense(
        Expense $expense,
        User|string $userOrReason,
        User|string|null $reasonOrUser = null,
        ?string $reason = null,
        ?User $user = null
    ): Expense {
        if ($userOrReason instanceof User) {
            $user = $userOrReason;
            $reason = (string) $reasonOrUser;
        } elseif ($reasonOrUser instanceof User) {
            $user = $reasonOrUser;
            $reason = (string) $userOrReason;
        } else {
            $reason = (string) ($reason ?? $userOrReason);
            $user = $user ?? auth()->user();
        }

        if (!$user->hasLocationAccess($expense->location_id, true)) {
            throw new AuthorizationException("User #{$user->id} does not have authorization for location #{$expense->location_id}.");
        }

        if ($expense->status !== 'posted') {
            throw new RuntimeException("Only posted expenses can be reversed. Current status: {$expense->status}");
        }

        return DB::transaction(function () use ($expense, $reason, $user) {
            $lockedExpense = Expense::where('id', $expense->id)->lockForUpdate()->firstOrFail();

            if ($lockedExpense->status !== 'posted') {
                throw new RuntimeException("Expense #{$lockedExpense->expense_number} is already reversed or draft.");
            }

            // Find linked account transaction
            $txn = $lockedExpense->accountTransaction ?? $lockedExpense->transaction;
            if ($txn) {
                $this->cashAndBankService->reverseTransaction(
                    transaction: $txn,
                    reason: "Expense reversal #{$lockedExpense->expense_number}: {$reason}",
                    user: $user
                );
            }

            $lockedExpense->status = 'reversed';
            $lockedExpense->reversal_reason = $reason;
            $lockedExpense->reversed_at = now();
            $lockedExpense->reversed_by = $user->id;
            $lockedExpense->save();

            return $lockedExpense->fresh(['location', 'category', 'account', 'transaction', 'accountTransaction']);
        });
    }

    /**
     * Compute comprehensive expense summaries.
     */
    public function getExpenseSummary(
        ?int $locationId = null,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        $baseQuery = Expense::where('status', 'posted')
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->when($fromDate, fn ($q) => $q->where('expense_date', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->where('expense_date', '<=', $toDate));

        $totalAmount = (clone $baseQuery)->sum('amount');
        $totalCount = (clone $baseQuery)->count();

        // Category-wise totals
        $byCategory = (clone $baseQuery)
            ->with('category')
            ->select('expense_category_id', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as count'))
            ->groupBy('expense_category_id')
            ->get();

        // Payment account-wise totals
        $byAccount = (clone $baseQuery)
            ->with('account')
            ->select('financial_account_id', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as count'))
            ->groupBy('financial_account_id')
            ->get();

        return [
            'total_amount' => number_format((float) $totalAmount, 2, '.', ''),
            'total_count' => $totalCount,
            'by_category' => $byCategory,
            'by_account' => $byAccount,
        ];
    }

    /**
     * Compute a deterministic canonical hash of an expense post request.
     */
    public function computeExpensePayloadHash(Expense $expense): string
    {
        $canonical = [
            'expense_id' => $expense->id,
            'location_id' => $expense->location_id,
            'account_id' => $expense->financial_account_id,
            'category_id' => $expense->expense_category_id,
            'amount' => number_format((float) $expense->amount, 2, '.', ''),
            'date' => $expense->expense_date->toDateString(),
            'description' => trim((string) $expense->description),
        ];

        return hash('sha256', json_encode($canonical));
    }

    /**
     * Generate sequential, location-coded unique expense voucher number.
     * Format: EXP-{LOC_CODE}-{YYYYMMDD}-{XXXX}
     */
    protected function generateExpenseNumber(Location $location): string
    {
        $dateStr = now()->format('Ymd');
        $prefix = "EXP-{$location->code}-{$dateStr}";

        $latest = Expense::where('expense_number', 'like', "{$prefix}-%")
            ->lockForUpdate()
            ->latest('id')
            ->value('expense_number');

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
