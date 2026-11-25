<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'financial_account_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ];
    }
}
