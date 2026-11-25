<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFinancialAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'account_code' => ['required', 'string', 'max:50', 'unique:financial_accounts,account_code'],
            'type' => ['required', 'string', 'in:cash,bank'],
            'bank_name' => ['nullable', 'string', 'max:100', 'required_if:type,bank'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'branch_code' => ['nullable', 'string', 'max:50'],
            'currency' => ['nullable', 'string', 'max:10'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'opening_balance_date' => ['nullable', 'date', 'required_with:opening_balance'],
        ];
    }
}
