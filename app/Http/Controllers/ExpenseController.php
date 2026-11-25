<?php

namespace App\Http\Controllers;

use App\Http\Requests\PostExpenseRequest;
use App\Http\Requests\StoreExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialAccount;
use App\Models\Location;
use App\Services\Accounting\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class ExpenseController extends Controller
{
    public function __construct(
        protected ExpenseService $expenseService
    ) {}

    /**
     * Display listing of expenses.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $status = $request->query('status', 'all');
        $categoryId = $request->query('category_id');
        $locationId = $request->query('location_id');
        $accountId = $request->query('financial_account_id');
        $search = $request->query('search');
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        if ($locationId && $locationId !== 'all') {
            if (!$user->hasLocationAccess($locationId)) {
                abort(403, 'Forbidden: You do not have authorization to view expenses for this location.');
            }
        } elseif ($locationId === 'all' && !$user->isOwner()) {
            abort(403, 'Forbidden: Only the owner can view cross-location expenses.');
        }

        $query = Expense::authorizedFor($user)
            ->with(['category', 'financialAccount', 'location', 'creator'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($categoryId, fn ($q) => $q->where('expense_category_id', $categoryId))
            ->when($locationId && $locationId !== 'all', fn ($q) => $q->where('location_id', $locationId))
            ->when($accountId, fn ($q) => $q->where('financial_account_id', $accountId))
            ->when($fromDate, fn ($q) => $q->whereDate('expense_date', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->whereDate('expense_date', '<=', $toDate))
            ->when($search, function ($q) use ($search) {
                $q->where('expense_number', 'like', "%{$search}%")
                  ->orWhere('payee_name', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            })
            ->latest('expense_date')
            ->latest('id');

        $expenses = $query->paginate(20)->withQueryString();

        $categories = ExpenseCategory::where('is_active', true)->orderBy('name')->get();
        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->get()
            : $user->locations()->wherePivot('can_view', true)->get();
        $accounts = FinancialAccount::authorizedFor($user)->where('is_active', true)->orderBy('name')->get();

        return view('expenses.index', compact('expenses', 'categories', 'authorizedLocations', 'accounts'));
    }

    /**
     * Show form to create a new expense voucher.
     */
    public function create(Request $request): View
    {
        $user = $request->user();

        $categories = ExpenseCategory::where('is_active', true)->orderBy('name')->get();
        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->get()
            : $user->locations()->wherePivot('can_manage_inventory', true)->get();
        $accounts = FinancialAccount::authorizedFor($user)->where('is_active', true)->orderBy('type')->orderBy('name')->get();

        return view('expenses.create', compact('categories', 'authorizedLocations', 'accounts'));
    }

    /**
     * Store a new expense (and optionally post immediately).
     */
    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if (!$user->hasLocationAccess($data['location_id'])) {
            abort(403, 'Forbidden: You do not have authorization for this location.');
        }

        try {
            $expense = $this->expenseService->createDraft($data, $user);

            if ($request->boolean('post_immediately')) {
                $idempotencyKey = !empty($data['idempotency_key']) ? trim((string) $data['idempotency_key']) : null;
                $expense = $this->expenseService->postExpense(
                    expense: $expense,
                    user: $user,
                    financialAccountId: $data['financial_account_id'] ?? null,
                    idempotencyKey: $idempotencyKey
                );

                return redirect()
                    ->route('expenses.show', $expense)
                    ->with('success', "Expense voucher {$expense->expense_number} posted successfully.");
            }

            return redirect()
                ->route('expenses.show', $expense)
                ->with('success', "Expense voucher {$expense->expense_number} created as draft.");
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Display a specific expense voucher.
     */
    public function show(Expense $expense, Request $request): View
    {
        $user = $request->user();

        if (!$user->hasLocationAccess($expense->location_id)) {
            abort(403, 'Forbidden: You do not have authorization to view this expense.');
        }

        $expense->load(['category', 'financialAccount', 'location', 'creator', 'poster', 'reverser', 'accountTransaction']);

        $accounts = FinancialAccount::authorizedFor($user)->where('is_active', true)->orderBy('name')->get();

        return view('expenses.show', compact('expense', 'accounts'));
    }

    /**
     * Post a draft expense voucher.
     */
    public function post(Expense $expense, PostExpenseRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (!$user->hasLocationAccess($expense->location_id)) {
            abort(403, 'Forbidden: You do not have authorization to post this expense.');
        }

        try {
            $data = $request->validated();
            $this->expenseService->postExpense(
                expense: $expense,
                user: $user,
                financialAccountId: $data['financial_account_id'] ?? null,
                idempotencyKey: !empty($data['idempotency_key']) ? trim((string) $data['idempotency_key']) : null
            );

            return redirect()
                ->route('expenses.show', $expense)
                ->with('success', "Expense voucher {$expense->expense_number} posted successfully.");
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Reverse a posted expense voucher.
     */
    public function reverse(Expense $expense, Request $request): RedirectResponse
    {
        $user = $request->user();

        if (!$user->hasLocationAccess($expense->location_id)) {
            abort(403, 'Forbidden: You do not have authorization to reverse this expense.');
        }

        $request->validate([
            'reversal_reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->expenseService->reverseExpense(
                expense: $expense,
                user: $user,
                reason: $request->input('reversal_reason')
            );

            return redirect()
                ->route('expenses.show', $expense)
                ->with('success', "Expense voucher {$expense->expense_number} reversed successfully.");
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
