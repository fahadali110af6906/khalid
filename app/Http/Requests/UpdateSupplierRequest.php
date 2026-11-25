<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $supplier = $this->route('supplier');
        $locationId = $this->input('primary_location_id', $supplier?->primary_location_id);

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
        $this->merge([
            'supplier_code' => strtoupper(trim((string) $this->input('supplier_code'))),
            'business_name' => trim((string) $this->input('business_name')),
            'contact_person' => trim((string) $this->input('contact_person')),
            'phone' => preg_replace('/[^\d+]/', '', (string) $this->input('phone')),
            'alternate_phone' => !empty($this->input('alternate_phone'))
                ? preg_replace('/[^\d+]/', '', (string) $this->input('alternate_phone'))
                : null,
            'credit_limit' => $this->input('credit_limit') !== null && $this->input('credit_limit') !== ''
                ? (float) $this->input('credit_limit')
                : 0.00,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $supplierId = $this->route('supplier')?->id ?? $this->route('supplier');

        return [
            'supplier_code' => ['required', 'string', 'max:30', 'unique:suppliers,supplier_code,' . $supplierId],
            'business_name' => ['required', 'string', 'max:150'],
            'contact_person' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'alternate_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'primary_location_id' => ['required', 'exists:locations,id'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
