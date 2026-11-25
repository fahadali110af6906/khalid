<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'financial_account_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'expense_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payee_name' => ['nullable', 'string', 'max:150'],
            'payment_method' => ['required', 'string', 'in:cash,bank,cheque,online_transfer'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'post_immediately' => ['nullable', 'boolean'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ];
    }
}
