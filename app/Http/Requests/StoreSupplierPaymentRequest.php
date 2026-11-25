<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $locationId = $this->input('location_id');

        if (!$locationId) {
            return true;
        }

        return $user->hasLocationAccess($locationId, true);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', 'in:cash,bank_transfer,cheque,online,other'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'financial_account_id' => ['nullable', 'exists:financial_accounts,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
