<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $id = $this->route('expenseCategory')?->id ?? $this->route('expense_category')?->id;

        return [
            'name' => ['required', 'string', 'max:100', 'unique:expense_categories,name,' . ($id ?? 'NULL') . ',id'],
            'code' => ['required', 'string', 'max:50', 'unique:expense_categories,code,' . ($id ?? 'NULL') . ',id'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
