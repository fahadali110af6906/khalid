<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteProductionOrderRequest extends FormRequest
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
            'good_pairs' => ['required', 'numeric', 'min:0'],
            'wastage_pairs' => ['nullable', 'numeric', 'min:0'],
            'completion_date' => ['nullable', 'date'],
            'labor_cost' => ['nullable', 'numeric', 'min:0'],
            'other_costs' => ['nullable', 'numeric', 'min:0'],
            'allocated_material_cost' => ['nullable', 'numeric', 'min:0'],
            'mark_completed' => ['nullable', 'boolean'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
