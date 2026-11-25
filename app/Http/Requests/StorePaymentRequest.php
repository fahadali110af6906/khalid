<?php

namespace App\Http\Requests;

use App\Models\SalesInvoice;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        if ($this->route('invoice')) {
            $invoice = $this->route('invoice');
            return $user->hasLocationAccess($invoice->location_id);
        }

        $locationId = $this->input('location_id');
        return $locationId ? $user->hasLocationAccess((int) $locationId) : false;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', 'in:cash,bank_transfer,cheque,online,other'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'financial_account_id' => ['nullable', 'exists:financial_accounts,id'],
        ];
    }
}
