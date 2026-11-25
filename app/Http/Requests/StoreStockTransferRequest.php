<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sourceLocationId = (int) $this->input('source_location_id');

        return $this->user()?->hasLocationAccess($sourceLocationId, true) ?? false;
    }

    public function rules(): array
    {
        return [
            'source_location_id' => ['required', 'exists:locations,id'],
            'destination_location_id' => ['required', 'exists:locations,id', 'different:source_location_id'],
            'transfer_date' => ['required', 'date'],
            'dispatch_notes' => ['nullable', 'string', 'max:500'],
            'action' => ['required', 'string', 'in:save_draft,dispatch_immediately'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'exists:product_variants,id'],
            'items.*.quantity_cartons' => ['nullable', 'integer', 'min:0'],
            'items.*.quantity_pairs' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
