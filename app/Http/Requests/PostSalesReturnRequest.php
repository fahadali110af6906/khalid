<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostSalesReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $return = $this->route('sales_return') ?? $this->route('salesReturn') ?? $this->route('return');

        if (!$return) {
            return true;
        }

        return $user->hasLocationAccess($return->location_id, true);
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ];
    }
}
