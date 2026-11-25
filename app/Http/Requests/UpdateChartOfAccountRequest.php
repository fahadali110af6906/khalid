<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChartOfAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $account = $this->route('chart_of_account') ?? $this->route('account');
        $accountId = is_object($account) ? $account->id : $account;

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('chart_of_accounts', 'code')->ignore($accountId)],
            'name' => ['required', 'string', 'max:150'],
            'parent_id' => ['nullable', 'exists:chart_of_accounts,id', Rule::notIn([$accountId])],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
