<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $receipt = $this->route('goods_receipt') ?? $this->route('goodsReceipt');

        if (!$receipt) {
            return true;
        }

        return $user->hasLocationAccess($receipt->location_id, true);
    }

    public function rules(): array
    {
        return [
            'return_date' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.goods_receipt_item_id' => ['required', 'exists:goods_receipt_items,id'],
            'items.*.returned_pairs' => ['nullable', 'numeric', 'min:0'],
            'items.*.returned_cartons' => ['nullable', 'numeric', 'min:0'],
            'items.*.return_pairs' => ['nullable', 'numeric', 'min:0'],
            'items.*.return_cartons' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_type' => ['nullable', 'in:carton,pair'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
