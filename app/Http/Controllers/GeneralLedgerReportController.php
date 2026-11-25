<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOpeningBalanceRequest;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Location;
use App\Services\Accounting\GeneralLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GeneralLedgerReportController extends Controller
{
    public function __construct(
        protected GeneralLedgerService $glService
    ) {}

    /**
     * General Ledger: Account-wise ledger running balance statement.
     */
    public function ledger(Request $request): View
    {
        $user = $request->user();
        $accountId = $request->query('account_id');
        $fromDate = $request->query('from_date', now()->startOfMonth()->toDateString());
        $toDate = $request->query('to_date', now()->toDateString());
        $locationId = $request->query('location_id') ? (int) $request->query('location_id') : null;

        if ($locationId && !$user->hasLocationAccess($locationId)) {
            abort(403, 'Forbidden: Location not authorized.');
        }

        $postableAccounts = ChartOfAccount::postable()->orderBy('code')->get();
        $selectedAccount = $accountId ? ChartOfAccount::find($accountId) : $postableAccounts->first();

        $ledgerData = null;
        if ($selectedAccount) {
            $ledgerData = $this->glService->getGeneralLedger($selectedAccount, $fromDate, $toDate, $locationId, $user);
        }

        $locations = $user->isOwner()
            ? Location::where('is_active', true)->orderBy('name')->get()
            : $user->locations()->wherePivot('can_view', true)->orderBy('name')->get();

        return view('accounting.reports.ledger', compact('postableAccounts', 'selectedAccount', 'ledgerData', 'locations', 'fromDate', 'toDate', 'locationId'));
    }

    /**
     * Trial Balance statement.
     */
    public function trialBalance(Request $request): View
    {
        $user = $request->user();
        $fromDate = $request->query('from_date', now()->startOfYear()->toDateString());
        $toDate = $request->query('to_date', now()->toDateString());
        $locationId = $request->query('location_id') ? (int) $request->query('location_id') : null;

        if ($locationId && !$user->hasLocationAccess($locationId)) {
            abort(403, 'Forbidden: Location not authorized.');
        }

        $report = $this->glService->getTrialBalance($fromDate, $toDate, $locationId, $user);

        $locations = $user->isOwner()
            ? Location::where('is_active', true)->orderBy('name')->get()
            : $user->locations()->wherePivot('can_view', true)->orderBy('name')->get();

        return view('accounting.reports.trial_balance', compact('report', 'locations', 'fromDate', 'toDate', 'locationId'));
    }

    /**
     * Export Trial Balance as CSV.
     */
    public function exportTrialBalanceCsv(Request $request): StreamedResponse
    {
        $user = $request->user();
        $fromDate = $request->query('from_date', now()->startOfYear()->toDateString());
        $toDate = $request->query('to_date', now()->toDateString());
        $locationId = $request->query('location_id') ? (int) $request->query('location_id') : null;

        $report = $this->glService->getTrialBalance($fromDate, $toDate, $locationId, $user);

        return $this->glService->exportTrialBalanceCsv($report);
    }

    /**
     * Formal Double-Entry Profit & Loss Statement.
     */
    public function profitLoss(Request $request): View
    {
        $user = $request->user();
        $fromDate = $request->query('from_date', now()->startOfMonth()->toDateString());
        $toDate = $request->query('to_date', now()->toDateString());
        $locationId = $request->query('location_id') ? (int) $request->query('location_id') : null;

        if ($locationId && !$user->hasLocationAccess($locationId)) {
            abort(403, 'Forbidden: Location not authorized.');
        }

        $report = $this->glService->getProfitAndLoss($fromDate, $toDate, $locationId, $user);

        $locations = $user->isOwner()
            ? Location::where('is_active', true)->orderBy('name')->get()
            : $user->locations()->wherePivot('can_view', true)->orderBy('name')->get();

        return view('accounting.reports.profit_loss', compact('report', 'locations', 'fromDate', 'toDate', 'locationId'));
    }

    /**
     * Formal Double-Entry Balance Sheet.
     */
    public function balanceSheet(Request $request): View
    {
        $user = $request->user();
        $asOfDate = $request->query('as_of_date', now()->toDateString());
        $locationId = $request->query('location_id') ? (int) $request->query('location_id') : null;

        if ($locationId && !$user->hasLocationAccess($locationId)) {
            abort(403, 'Forbidden: Location not authorized.');
        }

        $report = $this->glService->getBalanceSheet($asOfDate, $locationId, $user);

        $locations = $user->isOwner()
            ? Location::where('is_active', true)->orderBy('name')->get()
            : $user->locations()->wherePivot('can_view', true)->orderBy('name')->get();

        return view('accounting.reports.balance_sheet', compact('report', 'locations', 'asOfDate', 'locationId'));
    }

    /**
     * Sub-Ledger vs General Ledger Reconciliation Dashboard.
     */
    public function reconciliation(Request $request): View
    {
        $user = $request->user();
        $locationId = $request->query('location_id') ? (int) $request->query('location_id') : null;

        if ($locationId && !$user->hasLocationAccess($locationId)) {
            abort(403, 'Forbidden: Location not authorized.');
        }

        $reconData = $this->glService->getReconciliationReport($user, $locationId);

        $locations = $user->isOwner()
            ? Location::where('is_active', true)->orderBy('name')->get()
            : $user->locations()->wherePivot('can_view', true)->orderBy('name')->get();

        return view('accounting.reports.reconciliation', compact('reconData', 'locations', 'locationId'));
    }

    /**
     * Opening Balances Initial Setup view & post handler.
     */
    public function openingBalances(Request $request): View
    {
        $user = $request->user();
        if (!$user->isOwner()) {
            abort(403, 'Forbidden: Only the business owner can configure opening accounting balances.');
        }

        $existing = JournalEntry::where('source_type', 'opening_balance')->with('lines.account')->first();
        $postableAccounts = ChartOfAccount::postable()->orderBy('code')->get();
        $locations = Location::where('is_active', true)->orderBy('name')->get();

        return view('accounting.opening_balances.index', compact('existing', 'postableAccounts', 'locations'));
    }

    /**
     * Store initial opening balances.
     */
    public function storeOpeningBalances(StoreOpeningBalanceRequest $request): RedirectResponse
    {
        $user = $request->user();

        try {
            $entry = $this->glService->recordOpeningBalances(
                $request->validated('lines'),
                $request->validated('opening_date'),
                $user,
                $request->validated('notes')
            );

            return redirect()->route('accounting.opening-balances.index')
                ->with('success', "Opening Balances successfully recorded under Journal Voucher #{$entry->entry_number}.");
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
