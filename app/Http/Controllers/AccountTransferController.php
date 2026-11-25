<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountTransferRequest;
use App\Models\AccountTransfer;
use App\Models\FinancialAccount;
use App\Services\Accounting\CashAndBankService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class AccountTransferController extends Controller
{
    public function __construct(
        protected CashAndBankService $cashAndBankService
    ) {}

    /**
     * Display listing of account transfers.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $accountId = $request->query('account_id');
        $search = $request->query('search');

        $query = AccountTransfer::with(['fromAccount', 'toAccount', 'creator'])
            ->where(function ($q) use ($user) {
                // Must have access to either source or destination account
                $q->whereHas('fromAccount', fn ($aq) => $aq->authorizedFor($user))
                  ->orWhereHas('toAccount', fn ($aq) => $aq->authorizedFor($user));
            })
            ->when($accountId, function ($q) use ($accountId) {
                $q->where(fn ($sub) => $sub->where('from_financial_account_id', $accountId)->orWhere('to_financial_account_id', $accountId));
            })
            ->when($search, function ($q) use ($search) {
                $q->where('transfer_number', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            })
            ->latest('transfer_date')
            ->latest('id');

        $transfers = $query->paginate(20)->withQueryString();

        $accounts = FinancialAccount::authorizedFor($user)->where('is_active', true)->orderBy('name')->get();

        return view('account_transfers.index', compact('transfers', 'accounts'));
    }

    /**
     * Show form to create an inter-account transfer.
     */
    public function create(Request $request): View
    {
        $user = $request->user();
        $accounts = FinancialAccount::authorizedFor($user)->where('is_active', true)->orderBy('type')->orderBy('name')->get();

        $defaultFromId = $request->query('from_account_id');

        return view('account_transfers.create', compact('accounts', 'defaultFromId'));
    }

    /**
     * Store and execute an atomic account transfer.
     */
    public function store(StoreAccountTransferRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $fromAccount = FinancialAccount::findOrFail($data['from_financial_account_id']);
        $toAccount = FinancialAccount::findOrFail($data['to_financial_account_id']);

        if ($fromAccount->location_id && !$user->hasLocationAccess($fromAccount->location_id)) {
            abort(403, 'Forbidden: You do not have authorization to transfer from this account.');
        }

        if ($toAccount->location_id && !$user->hasLocationAccess($toAccount->location_id)) {
            abort(403, 'Forbidden: You do not have authorization to transfer to this account.');
        }

        try {
            $transfer = $this->cashAndBankService->recordTransfer(
                fromAccount: $fromAccount,
                toAccount: $toAccount,
                amount: (float) $data['amount'],
                transferDate: $data['transfer_date'],
                user: $user,
                notes: $data['notes'] ?? null,
                referenceNumber: $data['reference_number'] ?? null,
                idempotencyKey: !empty($data['idempotency_key']) ? trim((string) $data['idempotency_key']) : null
            );

            return redirect()
                ->route('account-transfers.show', $transfer)
                ->with('success', "Transfer {$transfer->transfer_number} of PKR " . number_format($transfer->amount, 2) . " completed successfully.");
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Display details of a specific transfer.
     */
    public function show(AccountTransfer $accountTransfer, Request $request): View
    {
        $user = $request->user();

        $fromAccess = !$accountTransfer->fromAccount->location_id || $user->hasLocationAccess($accountTransfer->fromAccount->location_id);
        $toAccess = !$accountTransfer->toAccount->location_id || $user->hasLocationAccess($accountTransfer->toAccount->location_id);

        if (!$fromAccess && !$toAccess) {
            abort(403, 'Forbidden: You do not have authorization to view this transfer.');
        }

        $accountTransfer->load(['fromAccount', 'toAccount', 'transactions', 'creator']);

        return view('account_transfers.show', compact('accountTransfer'));
    }
}
