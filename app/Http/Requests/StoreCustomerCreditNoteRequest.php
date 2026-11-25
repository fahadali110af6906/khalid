<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $locationId = $this->input('location_id');

        if (!$locationId) {
            return true;
        }

        return $user->hasLocationAccess((int) $locationId, true);
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'customer_id' => ['required', 'exists:customers,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'sales_invoice_id' => ['nullable', 'exists:sales_invoices,id'],
            'note_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason_type' => ['required', 'in:price_difference,volume_rebate,customer_allowance,invoice_correction,other'],
            'reason' => ['required', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
