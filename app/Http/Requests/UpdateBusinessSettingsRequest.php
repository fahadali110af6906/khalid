<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() ?? false;
    }

    public function rules(): array
    {
        return [
            // General Details
            'business_name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'business_phone' => ['required', 'string', 'max:50'],
            'business_email' => ['nullable', 'email', 'max:100'],
            'business_address' => ['required', 'string', 'max:500'],
            'tax_number' => ['nullable', 'string', 'max:50'],

            // Branding & Invoices
            'invoice_header_text' => ['nullable', 'string', 'max:255'],
            'invoice_footer_terms' => ['nullable', 'string', 'max:1000'],
            'logo_url' => ['nullable', 'string', 'max:255'],

            // Localization
            'default_locale' => ['required', 'in:en,ur'],
            'timezone' => ['required', 'string', 'timezone'],
            'date_format' => ['required', 'string', 'max:20'],
            'time_format' => ['required', 'string', 'max:20'],
            'currency_code' => ['required', 'string', 'max:10'],
            'currency_symbol' => ['required', 'string', 'max:10'],
            'currency_position' => ['required', 'in:before,after'],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:4'],

            // Numbering Prefixes
            'sales_invoice_prefix' => ['required', 'string', 'max:15'],
            'purchase_order_prefix' => ['required', 'string', 'max:15'],
            'goods_receipt_prefix' => ['required', 'string', 'max:15'],
            'sales_return_prefix' => ['required', 'string', 'max:15'],
            'purchase_return_prefix' => ['required', 'string', 'max:15'],
            'payment_voucher_prefix' => ['required', 'string', 'max:15'],
            'journal_entry_prefix' => ['required', 'string', 'max:15'],
            'production_order_prefix' => ['required', 'string', 'max:15'],

            // Operational & Backup Defaults
            'default_report_range' => ['required', 'string', 'in:today,this_week,this_month,last_month,this_year'],
            'backup_retention_count' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
