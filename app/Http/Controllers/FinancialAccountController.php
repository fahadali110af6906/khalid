<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFinancialAccountRequest;
use App\Http\Requests\UpdateFinancialAccountRequest;
use App\Models\FinancialAccount;
use App\Models\Location;
use App\Services\Accounting\CashAndBankService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class FinancialAccountController extends Controller
{
    public function __construct(
        protected CashAndBankService $cashAndBankService
    ) {}

    /**
     * Display listing of financial accounts.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $type = $request->query('type', 'all');
        $locationId = $request->query('location_id');
        $search = $request->query('search');

        if ($locationId && $locationId !== 'all') {
            if (!$user->hasLocationAccess($locationId)) {
                abort(403, 'Forbidden: You do not have authorization to view accounts for this location.');
            }
        } elseif ($locationId === 'all' && !$user->isOwner()) {
            abort(403, 'Forbidden: Only the owner can view cross-location accounts.');
        }

        $query = FinancialAccount::authorizedFor($user)
            ->with(['location', 'creator'])
            ->when($type !== 'all', fn ($q) => $q->where('type', $type))
            ->when($locationId && $locationId !== 'all', fn ($q) => $q->where('location_id', $locationId))
            ->when($search, function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('account_code', 'like', "%{$search}%")
                  ->orWhere('bank_name', 'like', "%{$search}%")
                  ->orWhere('account_number', 'like', "%{$search}%");
            })
            ->orderBy('type')
            ->orderBy('name');

        $accounts = $query->paginate(25)->withQueryString();

        // Calculate summary cards across user's authorized accounts
        $allAccounts = FinancialAccount::authorizedFor($user)->where('is_active', true)->get();
        $totalCash = '0.00';
        $totalBank = '0.00';

        foreach ($allAccounts as $acc) {
            if ($acc->type === 'cash') {
                $totalCash = bcadd($totalCash, (string) $acc->current_balance, 2);
            } else {
                $totalBank = bcadd($totalBank, (string) $acc->current_balance, 2);
            }
        }
        $totalLiquidity = bcadd($totalCash, $totalBank, 2);

        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->get()
            : $user->locations()->wherePivot('can_view', true)->get();

        return view('financial_accounts.index', compact(
            'accounts',
            'authorizedLocations',
            'totalCash',
            'totalBank',
            'totalLiquidity'
        ));
    }

    /**
     * Show form to create a new financial account.
     */
    public function create(Request $request): View
    {
        $user = $request->user();
        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->get()
            : $user->locations()->wherePivot('can_manage_inventory', true)->get();

        return view('financial_accounts.create', compact('authorizedLocations'));
    }

    /**
     * Store a new financial account.
     */
    public function store(StoreFinancialAccountRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if (!empty($data['location_id']) && !$user->hasLocationAccess($data['location_id'])) {
            abort(403, 'Forbidden: You do not have access to create an account for this location.');
        }

        try {
            $account = $this->cashAndBankService->createAccount($data, $user);

            if (!empty($data['opening_balance']) && (float) $data['opening_balance'] > 0) {
                $this->cashAndBankService->recordOpeningBalance(
                    account: $account,
                    amount: (float) $data['opening_balance'],
                    date: $data['opening_balance_date'] ?? now()->toDateString(),
                    user: $user,
                    notes: "Initial opening balance on account setup"
                );
            }

            return redirect()
                ->route('financial-accounts.show', $account)
                ->with('success', "Account '{$account->name}' ({$account->account_code}) created successfully.");
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Display account details, statement, and transaction history.
     */
    public function show(FinancialAccount $financialAccount, Request $request): View
    {
        $user = $request->user();

        if ($financialAccount->location_id && !$user->hasLocationAccess($financialAccount->location_id)) {
            abort(403, 'Forbidden: You do not have authorization to view this account.');
        }

        $fromDate = $request->query('from_date', now()->startOfMonth()->toDateString());
        $toDate = $request->query('to_date', now()->toDateString());
        $txnType = $request->query('transaction_type', 'all');

        $statement = $this->cashAndBankService->getAccountStatement(
            account: $financialAccount,
            fromDate: $fromDate,
            toDate: $toDate
        );

        $query = $financialAccount->transactions()
            ->with(['creator', 'transfer'])
            ->whereDate('transaction_date', '>=', $fromDate)
            ->whereDate('transaction_date', '<=', $toDate)
            ->when($txnType !== 'all', fn ($q) => $q->where('transaction_type', $txnType))
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc');

        $transactions = $query->paginate(30)->withQueryString();

        $reconciliation = $this->cashAndBankService->reconcileBalance($financialAccount);

        return view('financial_accounts.show', compact(
            'financialAccount',
            'transactions',
            'statement',
            'reconciliation',
            'fromDate',
            'toDate',
            'txnType'
        ));
    }

    /**
     * Show form to edit an existing account.
     */
    public function edit(FinancialAccount $financialAccount, Request $request): View
    {
        $user = $request->user();

        if ($financialAccount->location_id && !$user->hasLocationAccess($financialAccount->location_id)) {
            abort(403, 'Forbidden: You do not have authorization to edit this account.');
        }

        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->get()
            : $user->locations()->wherePivot('can_manage_inventory', true)->get();

        return view('financial_accounts.edit', compact('financialAccount', 'authorizedLocations'));
    }

    /**
     * Update an existing financial account.
     */
    public function update(UpdateFinancialAccountRequest $request, FinancialAccount $financialAccount): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if ($financialAccount->location_id && !$user->hasLocationAccess($financialAccount->location_id)) {
            abort(403, 'Forbidden: You do not have authorization to edit this account.');
        }

        if (!empty($data['location_id']) && !$user->hasLocationAccess($data['location_id'])) {
            abort(403, 'Forbidden: You do not have access to associate with this location.');
        }

        try {
            $this->cashAndBankService->updateAccount($financialAccount, $data, $user);

            return redirect()
                ->route('financial-accounts.show', $financialAccount)
                ->with('success', "Account '{$financialAccount->name}' updated successfully.");
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}
