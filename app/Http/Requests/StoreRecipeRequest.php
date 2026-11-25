<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'product_id' => ['required', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'exists:product_variants,id'],
            'version' => ['nullable', 'string', 'max:20'],
            'yield_pairs' => ['nullable', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.raw_material_variant_id' => ['required', 'exists:product_variants,id'],
            'items.*.quantity_per_pair' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_of_measure_id' => ['nullable', 'exists:units_of_measure,id'],
            'items.*.wastage_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
