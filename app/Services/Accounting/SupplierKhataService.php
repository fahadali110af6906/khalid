<?php

namespace App\Services\Accounting;

use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class SupplierKhataService
{
    /**
     * Calculate comprehensive Supplier Khata balance metrics using exact BCMath arithmetic.
     *
     * Sign convention (Payables):
     * - Credit increases payable (Goods received from supplier / مال خریدا - واجب الادا رقم میں اضافہ).
     * - Debit decreases payable (Payment made to supplier / ادائیگی کی - واجب الادا رقم میں کمی).
     * - Closing Balance > 0: Payable (Khalid Traders owes supplier / واجب الادا رقم).
     * - Closing Balance < 0: Advance (Khalid Traders paid advance to supplier / پیشگی رقم).
     * - Closing Balance = 0: Settled (بے باق / حساب برابر).
     */
    public function calculateBalance(
        Supplier $supplier,
        ?int $locationId = null,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        // If opening balance was already posted as an audited ledger entry, avoid double-counting
        $hasOpeningEntry = SupplierLedgerEntry::where('supplier_id', $supplier->id)
            ->where('entry_type', 'opening_balance')
            ->exists();

        $baseOpening = $hasOpeningEntry ? '0.00' : (string) ($supplier->opening_balance ?? '0.00');

        // If fromDate is specified, calculate prior opening balance
        $priorCredit = '0.00';
        $priorDebit = '0.00';

        if ($fromDate) {
            $priorEntries = SupplierLedgerEntry::where('supplier_id', $supplier->id)
                ->where('entry_date', '<', $fromDate)
                ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
                ->get();

            foreach ($priorEntries as $entry) {
                $priorCredit = bcadd($priorCredit, (string) $entry->credit_amount, 2);
                $priorDebit = bcadd($priorDebit, (string) $entry->debit_amount, 2);
            }
        }

        // Period starting opening payable: base + priorCredits - priorDebits
        $periodOpening = bcsub(bcadd($baseOpening, $priorCredit, 2), $priorDebit, 2);

        // Period query
        $periodQuery = SupplierLedgerEntry::where('supplier_id', $supplier->id)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->when($fromDate, fn ($q) => $q->where('entry_date', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->where('entry_date', '<=', $toDate));

        $periodEntries = $periodQuery->get();

        $periodCredit = '0.00';
        $periodDebit = '0.00';

        foreach ($periodEntries as $entry) {
            $periodCredit = bcadd($periodCredit, (string) $entry->credit_amount, 2);
            $periodDebit = bcadd($periodDebit, (string) $entry->debit_amount, 2);
        }

        // Closing Payable = Opening + Credits (purchases) - Debits (payments)
        $closingBalance = bcsub(bcadd($periodOpening, $periodCredit, 2), $periodDebit, 2);

        $cmp = bccomp($closingBalance, '0.00', 2);
        $status = match ($cmp) {
            1 => 'payable',   // Khalid Traders owes supplier
            -1 => 'advance',  // Advance paid to supplier
            default => 'settled',
        };

        return [
            'base_opening_balance' => $baseOpening,
            'period_opening_balance' => $periodOpening,
            'total_credit' => $periodCredit,
            'total_debit' => $periodDebit,
            'closing_balance' => $closingBalance,
            'balance_status' => $status,
            'entries_count' => $periodEntries->count(),
        ];
    }

    /**
     * Post a new supplier ledger entry with transaction safety and duplicate reference protection.
     */
    public function recordEntry(
        Supplier $supplier,
        int $locationId,
        string $entryType,
        float|string $debitAmount = 0.00,
        float|string $creditAmount = 0.00,
        ?string $entryDate = null,
        ?User $user = null,
        ?string $description = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $referenceNumber = null
    ): SupplierLedgerEntry {
        $entryDate = $entryDate ?: now()->toDateString();
        $userId = $user?->id ?? auth()->id();
        if (!$userId) {
            throw new InvalidArgumentException("User must be authenticated or provided to record ledger entry.");
        }
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
            $supplier,
            $locationId,
            $entryType,
            $debitStr,
            $creditStr,
            $entryDate,
            $userId,
            $description,
            $referenceType,
            $referenceId,
            $referenceNumber
        ) {
            // Prevent duplicate posting of same commercial document
            if ($referenceType && $referenceId) {
                $alreadyExists = SupplierLedgerEntry::where('supplier_id', $supplier->id)
                    ->where('reference_type', $referenceType)
                    ->where('reference_id', $referenceId)
                    ->exists();

                if ($alreadyExists) {
                    throw new RuntimeException("Duplicate supplier ledger entry: reference {$referenceType} #{$referenceId} has already been posted to Khata for supplier #{$supplier->id}.");
                }
            }

            try {
                return SupplierLedgerEntry::create([
                    'supplier_id' => $supplier->id,
                    'location_id' => $locationId,
                    'entry_type' => $entryType,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'reference_number' => $referenceNumber,
                    'entry_date' => $entryDate,
                    'debit_amount' => $debitStr,
                    'credit_amount' => $creditStr,
                    'description' => $description,
                    'created_by' => $userId,
                ]);
            } catch (QueryException $e) {
                if ($e->getCode() == 23000 || str_contains($e->getMessage(), '1062 Duplicate entry') || str_contains($e->getMessage(), 'supp_ledger_supp_ref_unique') || str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
                    throw new RuntimeException("Concurrent duplicate rejected: reference {$referenceType} #{$referenceId} has already been posted to Khata for supplier #{$supplier->id}.", 0, $e);
                }
                throw $e;
            }
        });
    }
}
