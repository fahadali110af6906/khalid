<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IssueProductionMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $order = $this->route('production_order') ?? $this->route('order') ?? $this->route('productionOrder');

        if (!$order) {
            return true;
        }

        return $user->hasLocationAccess($order->factory_location_id, true);
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.raw_material_variant_id' => ['required', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
