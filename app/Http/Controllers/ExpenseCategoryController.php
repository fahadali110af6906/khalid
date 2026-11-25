<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseCategoryController extends Controller
{
    /**
     * Display listing of expense categories.
     */
    public function index(Request $request): View
    {
        $categories = ExpenseCategory::withCount('expenses')
            ->orderBy('name')
            ->paginate(30);

        return view('expense_categories.index', compact('categories'));
    }

    /**
     * Store a new expense category.
     */
    public function store(StoreExpenseCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        $category = ExpenseCategory::create($data);

        return redirect()
            ->route('expense-categories.index')
            ->with('success', "Expense category '{$category->name}' created successfully.");
    }

    /**
     * Update an existing expense category.
     */
    public function update(StoreExpenseCategoryRequest $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        $expenseCategory->update($data);

        return redirect()
            ->route('expense-categories.index')
            ->with('success', "Expense category '{$expenseCategory->name}' updated successfully.");
    }

    /**
     * Toggle active status.
     */
    public function toggleStatus(ExpenseCategory $expenseCategory): RedirectResponse
    {
        $expenseCategory->is_active = !$expenseCategory->is_active;
        $expenseCategory->save();

        $statusStr = $expenseCategory->is_active ? 'activated' : 'deactivated';

        return redirect()
            ->route('expense-categories.index')
            ->with('success', "Expense category '{$expenseCategory->name}' {$statusStr}.");
    }
}
