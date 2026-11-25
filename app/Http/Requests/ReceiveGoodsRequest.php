<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveGoodsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $order = $this->route('purchase_order') ?? $this->route('purchaseOrder');

        if (!$order) {
            return true;
        }

        return $user->hasLocationAccess($order->location_id, true);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'receipt_date' => ['nullable', 'date'],
            'challan_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'exists:purchase_order_items,id'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.received_pairs' => ['nullable', 'numeric', 'min:0'],
            'items.*.received_cartons' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_type' => ['nullable', 'in:carton,pair'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
