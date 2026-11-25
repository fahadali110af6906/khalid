<?php

namespace App\Services\Accounting;

use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CustomerKhataService
{
    /**
     * Calculate comprehensive Khata balance metrics for a customer using exact BCMath arithmetic.
     *
     * Sign convention:
     * - Debit increases receivable (Party owes Khalid Traders / ادھار مال).
     * - Credit decreases receivable (Party paid / وصولی یا واپسی).
     * - Closing Balance > 0: Receivable (Customer owes Khalid Traders / واجب الوصول).
     * - Closing Balance < 0: Advance (Khalid Traders owes customer / پیشگی وصولی).
     * - Closing Balance = 0: Settled (بے باق).
     */
    public function calculateBalance(
        Customer $customer,
        ?int $locationId = null,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        // If opening balance was already posted as an audited ledger entry, avoid double counting
        $hasOpeningEntry = CustomerLedgerEntry::where('customer_id', $customer->id)
            ->where('entry_type', 'opening_balance')
            ->exists();

        $baseOpening = $hasOpeningEntry ? '0.00' : (string) ($customer->opening_balance ?? '0.00');

        // If fromDate is specified, calculate prior opening balance
        $priorDebit = '0.00';
        $priorCredit = '0.00';

        if ($fromDate) {
            $priorEntries = CustomerLedgerEntry::where('customer_id', $customer->id)
                ->where('entry_date', '<', $fromDate)
                ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
                ->get();

            foreach ($priorEntries as $entry) {
                $priorDebit = bcadd($priorDebit, (string) $entry->debit_amount, 2);
                $priorCredit = bcadd($priorCredit, (string) $entry->credit_amount, 2);
            }
        }

        // Period starting opening balance
        $periodOpening = bcsub(bcadd($baseOpening, $priorDebit, 2), $priorCredit, 2);

        // Period query
        $periodQuery = CustomerLedgerEntry::where('customer_id', $customer->id)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->when($fromDate, fn ($q) => $q->where('entry_date', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->where('entry_date', '<=', $toDate));

        $periodEntries = $periodQuery->get();

        $periodDebit = '0.00';
        $periodCredit = '0.00';

        foreach ($periodEntries as $entry) {
            $periodDebit = bcadd($periodDebit, (string) $entry->debit_amount, 2);
            $periodCredit = bcadd($periodCredit, (string) $entry->credit_amount, 2);
        }

        // Closing Balance = Opening + Debits - Credits
        $closingBalance = bcsub(bcadd($periodOpening, $periodDebit, 2), $periodCredit, 2);

        $cmp = bccomp($closingBalance, '0.00', 2);
        $status = match ($cmp) {
            1 => 'receivable', // Customer owes business
            -1 => 'payable',   // Customer has advance credit balance
            default => 'settled',
        };

        return [
            'base_opening_balance' => $baseOpening,
            'period_opening_balance' => $periodOpening,
            'total_debit' => $periodDebit,
            'total_credit' => $periodCredit,
            'closing_balance' => $closingBalance,
            'balance_status' => $status,
            'entries_count' => $periodEntries->count(),
        ];
    }

    /**
     * Post a new ledger entry with transaction safety and duplicate prevention.
     */
    public function recordEntry(
        Customer $customer,
        int $locationId,
        string $entryType,
        float|string $debitAmount,
        float|string $creditAmount,
        string $entryDate,
        User $user,
        ?string $description = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $referenceNumber = null
    ): CustomerLedgerEntry {
        $debitStr = number_format((float) $debitAmount, 2, '.', '');
        $creditStr = number_format((float) $creditAmount, 2, '.', '');

        if (bccomp($debitStr, '0.00', 2) < 0 || bccomp($creditStr, '0.00', 2) < 0) {
            throw new InvalidArgumentException("Debit and Credit amounts must be non-negative.");
        }

        if (bccomp($debitStr, '0.00', 2) === 0 && bccomp($creditStr, '0.00', 2) === 0) {
            throw new InvalidArgumentException("A ledger entry must have a non-zero debit or credit amount.");
        }

        if (bccomp($debitStr, '0.00', 2) > 0 && bccomp($creditStr, '0.00', 2) > 0) {
            throw new InvalidArgumentException("A ledger entry cannot have both positive debit and positive credit amounts simultaneously. Exactly one side must be positive.");
        }

        return DB::transaction(function () use (
            $customer,
            $locationId,
            $entryType,
            $debitStr,
            $creditStr,
            $entryDate,
            $user,
            $description,
            $referenceType,
            $referenceId,
            $referenceNumber
        ) {
            // Prevent duplicate posting of same commercial document
            if ($referenceType && $referenceId) {
                $alreadyExists = CustomerLedgerEntry::where('customer_id', $customer->id)
                    ->where('reference_type', $referenceType)
                    ->where('reference_id', $referenceId)
                    ->exists();

                if ($alreadyExists) {
                    throw new RuntimeException("Duplicate ledger entry: reference {$referenceType} #{$referenceId} has already been posted to Khata for customer #{$customer->id}.");
                }
            }

            try {
                return CustomerLedgerEntry::create([
                    'customer_id' => $customer->id,
                    'location_id' => $locationId,
                    'entry_type' => $entryType,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'reference_number' => $referenceNumber,
                    'entry_date' => $entryDate,
                    'debit_amount' => $debitStr,
                    'credit_amount' => $creditStr,
                    'description' => $description,
                    'created_by' => $user->id,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                if ($e->getCode() == 23000 || str_contains($e->getMessage(), '1062 Duplicate entry') || str_contains($e->getMessage(), 'cust_ledger_cust_ref_unique') || str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
                    throw new RuntimeException("Concurrent duplicate rejected: reference {$referenceType} #{$referenceId} has already been posted to Khata for customer #{$customer->id}.", 0, $e);
                }
                throw $e;
            }
        });
    }
}
