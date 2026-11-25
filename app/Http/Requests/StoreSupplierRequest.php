<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreSupplierRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $locationId = $this->input('primary_location_id');

        if (!$locationId) {
            return true;
        }

        return $user->hasLocationAccess($locationId, true);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $code = $this->input('supplier_code');
        if (!empty($code)) {
            $code = strtoupper(trim($code));
        } else {
            $code = 'SUPP-' . date('Ym') . '-' . strtoupper(Str::random(4));
        }

        $this->merge([
            'supplier_code' => $code,
            'business_name' => trim((string) $this->input('business_name')),
            'contact_person' => trim((string) $this->input('contact_person')),
            'phone' => preg_replace('/[^\d+]/', '', (string) $this->input('phone')),
            'alternate_phone' => !empty($this->input('alternate_phone'))
                ? preg_replace('/[^\d+]/', '', (string) $this->input('alternate_phone'))
                : null,
            'credit_limit' => $this->input('credit_limit') !== null && $this->input('credit_limit') !== ''
                ? (float) $this->input('credit_limit')
                : 0.00,
            'opening_balance' => $this->input('opening_balance') !== null && $this->input('opening_balance') !== ''
                ? (float) $this->input('opening_balance')
                : 0.00,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'supplier_code' => ['required', 'string', 'max:30', 'unique:suppliers,supplier_code'],
            'business_name' => ['required', 'string', 'max:150'],
            'contact_person' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'alternate_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'opening_balance' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'primary_location_id' => ['required', 'exists:locations,id'],
        ];
    }
}
