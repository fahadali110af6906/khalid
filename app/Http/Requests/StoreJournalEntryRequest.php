<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        $locationId = (int) $this->input('location_id');
        if ($locationId) {
            return $user->hasLocationAccess($locationId, true);
        }

        return $user->isOwner(); // Global entries require owner privileges
    }

    public function rules(): array
    {
        return [
            'entry_date' => ['required', 'date'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.chart_of_account_id' => ['required', 'exists:chart_of_accounts,id'],
            'lines.*.debit' => ['required', 'numeric', 'min:0'],
            'lines.*.credit' => ['required', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.location_id' => ['nullable', 'exists:locations,id'],
        ];
    }
}
