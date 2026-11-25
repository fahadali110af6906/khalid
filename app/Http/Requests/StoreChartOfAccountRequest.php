<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChartOfAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:chart_of_accounts,code'],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:asset,liability,equity,revenue,expense'],
            'parent_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'is_group' => ['nullable', 'boolean'],
            'normal_balance' => ['required', 'in:debit,credit'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
