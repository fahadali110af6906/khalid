<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $locationId = $this->input('location_id');

        return $user && $user->hasLocationAccess((int) $locationId);
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'action' => ['required', 'in:save_draft,post_immediately'],

            // Items
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'exists:product_variants,id'],
            'items.*.unit_type' => ['required', 'in:carton,pair'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0.01'],
            'items.*.line_discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],

            // Optional Initial Payment
            'initial_payment' => ['nullable', 'array'],
            'initial_payment.amount' => ['nullable', 'numeric', 'min:0'],
            'initial_payment.payment_method' => ['nullable', 'in:cash,bank_transfer,cheque,online,other'],
            'initial_payment.reference_number' => ['nullable', 'string', 'max:100'],
            'initial_payment.notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
