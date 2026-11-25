<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $return = $this->route('sales_return') ?? $this->route('salesReturn') ?? $this->route('return');

        if (!$return) {
            return true;
        }

        return $user->hasLocationAccess($return->location_id, true);
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'refund_date' => ['required', 'date'],
            'payment_method' => ['required', 'in:cash,bank_transfer,cheque,online,other'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'financial_account_id' => ['nullable', 'exists:financial_accounts,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
