<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $locationId = (int) $this->input('factory_location_id');

        if (!$locationId) {
            return true;
        }

        return $user->hasLocationAccess($locationId, true);
    }

    public function rules(): array
    {
        return [
            'factory_location_id' => ['required', 'exists:locations,id'],
            'product_id' => ['required', 'exists:products,id'],
            'product_variant_id' => ['required', 'exists:product_variants,id'],
            'recipe_id' => ['required', 'exists:recipes,id'],
            'planned_quantity_pairs' => ['required', 'numeric', 'gt:0'],
            'start_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'in:draft,planned'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
