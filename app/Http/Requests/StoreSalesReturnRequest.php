<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $invoice = $this->route('sales_invoice') ?? $this->route('salesInvoice') ?? $this->route('invoice');

        if (!$invoice) {
            return true;
        }

        return $user->hasLocationAccess($invoice->location_id, true);
    }

    public function rules(): array
    {
        return [
            'return_date' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sales_invoice_item_id' => ['required', 'exists:sales_invoice_items,id'],
            'items.*.returned_pairs' => ['nullable', 'numeric', 'min:0'],
            'items.*.returned_cartons' => ['nullable', 'numeric', 'min:0'],
            'items.*.return_pairs' => ['nullable', 'numeric', 'min:0'],
            'items.*.return_cartons' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_type' => ['nullable', 'in:carton,pair'],
            'items.*.is_restocked' => ['nullable', 'boolean'],
            'items.*.condition' => ['nullable', 'in:resalable,defective,damaged'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
