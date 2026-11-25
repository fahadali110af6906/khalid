<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChartOfAccountRequest;
use App\Http\Requests\UpdateChartOfAccountRequest;
use App\Models\ChartOfAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChartOfAccountController extends Controller
{
    /**
     * Display a listing of chart of accounts.
     */
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $type = $request->query('type');
        $status = $request->query('status');

        $query = ChartOfAccount::with(['parent', 'children'])
            ->when($search, function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            })
            ->when($type && $type !== 'all', fn ($q) => $q->where('type', $type))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('code');

        $accounts = $query->paginate(50)->withQueryString();

        return view('accounting.chart_of_accounts.index', compact('accounts', 'search', 'type', 'status'));
    }

    /**
     * Show form to create a new account.
     */
    public function create(): View
    {
        $groupAccounts = ChartOfAccount::where('is_group', true)->where('is_active', true)->orderBy('code')->get();

        return view('accounting.chart_of_accounts.create', compact('groupAccounts'));
    }

    /**
     * Store a new account in Chart of Accounts.
     */
    public function store(StoreChartOfAccountRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_group'] = $request->boolean('is_group');
        $data['is_active'] = true;
        $data['is_system'] = false; // User-created accounts are not system protected

        $account = ChartOfAccount::create($data);

        return redirect()->route('accounting.chart-of-accounts.index')
            ->with('success', "Account {$account->code} — {$account->name} created successfully.");
    }

    /**
     * Show the form to edit an existing account.
     */
    public function edit(ChartOfAccount $chartOfAccount): View
    {
        $groupAccounts = ChartOfAccount::where('is_group', true)
            ->where('id', '!=', $chartOfAccount->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('accounting.chart_of_accounts.edit', [
            'account' => $chartOfAccount,
            'groupAccounts' => $groupAccounts,
        ]);
    }

    /**
     * Update account details with safeguards.
     */
    public function update(UpdateChartOfAccountRequest $request, ChartOfAccount $chartOfAccount): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        // Safeguard: Cannot deactivate a system account
        if ($chartOfAccount->is_system && !$data['is_active']) {
            return back()->withInput()->withErrors(['is_active' => 'System accounts are essential for ERP integration and cannot be deactivated.']);
        }

        $chartOfAccount->update($data);

        return redirect()->route('accounting.chart-of-accounts.index')
            ->with('success', "Account {$chartOfAccount->code} updated successfully.");
    }

    /**
     * Delete an account with strict safeguards.
     */
    public function destroy(ChartOfAccount $chartOfAccount): RedirectResponse
    {
        // Safeguard 1: Protected system accounts
        if ($chartOfAccount->is_system) {
            return back()->withErrors(['error' => "Account {$chartOfAccount->code} is a protected system account and cannot be deleted."]);
        }

        // Safeguard 2: Posted journal entries exist
        if ($chartOfAccount->journalLines()->exists()) {
            return back()->withErrors(['error' => "Cannot delete account {$chartOfAccount->code} because posted journal entries reference it. Deactivate the account instead."]);
        }

        // Safeguard 3: Child accounts exist
        if ($chartOfAccount->children()->exists()) {
            return back()->withErrors(['error' => "Cannot delete account {$chartOfAccount->code} because it contains child sub-accounts. Remove or reassign child accounts first."]);
        }

        $chartOfAccount->delete();

        return redirect()->route('accounting.chart-of-accounts.index')
            ->with('success', "Account {$chartOfAccount->code} deleted successfully.");
    }
}
