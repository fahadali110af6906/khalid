@extends('layouts.app')

@section('title', __('erp.settings.title'))

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-3">
                <i data-lucide="settings" class="w-7 h-7 text-indigo-600 dark:text-indigo-400"></i>
                <span>{{ __('erp.settings.title') }}</span>
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('erp.settings.subtitle') }}
            </p>
        </div>

        <!-- Location Override Selector -->
        <div class="flex items-center gap-3 bg-white dark:bg-slate-800 p-2 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
            <span class="text-xs font-semibold text-slate-600 dark:text-slate-300 pl-2">
                {{ __('erp.settings.location_override') }}
            </span>
            <form method="GET" action="{{ route('admin.settings.index') }}" class="m-0">
                <select name="location_id" onchange="this.form.submit()" class="text-sm rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-1.5 px-3 focus:ring-indigo-500">
                    <option value="">{{ __('erp.settings.all_locations') }}</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" {{ $locationId == $loc->id ? 'selected' : '' }}>
                            {{ $loc->name }} ({{ ucfirst($loc->type) }})
                        </option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>

    <!-- Settings Form -->
    <form action="{{ route('admin.settings.update') }}" method="POST" class="space-y-6">
        @csrf
        @if($locationId)
            <input type="hidden" name="location_id" value="{{ $locationId }}">
        @endif

        <!-- Card: Organization Profile & General -->
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex items-center gap-2">
                <i data-lucide="building-2" class="w-5 h-5 text-indigo-600 dark:text-indigo-400"></i>
                <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ __('erp.settings.tabs.general') }}</h2>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.settings.fields.business_name') }} <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="business_name" value="{{ old('business_name', $settingsGrouped['general']['business_name'] ?? 'Khalid Traders') }}" required
                           class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white focus:ring-indigo-500 text-sm py-2 px-3">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.settings.fields.legal_name') }}
                    </label>
                    <input type="text" name="legal_name" value="{{ old('legal_name', $settingsGrouped['general']['legal_name'] ?? 'Khalid Traders Private Limited') }}"
                           class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white focus:ring-indigo-500 text-sm py-2 px-3">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.settings.fields.business_phone') }}
                    </label>
                    <input type="text" name="business_phone" value="{{ old('business_phone', $settingsGrouped['general']['business_phone'] ?? '+92 300 1234567') }}"
                           class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white focus:ring-indigo-500 text-sm py-2 px-3">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.settings.fields.business_email') }}
                    </label>
                    <input type="email" name="business_email" value="{{ old('business_email', $settingsGrouped['general']['business_email'] ?? 'info@khalidtraders.com') }}"
                           class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white focus:ring-indigo-500 text-sm py-2 px-3">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.settings.fields.business_address') }}
                    </label>
                    <input type="text" name="business_address" value="{{ old('business_address', $settingsGrouped['general']['business_address'] ?? 'Wholesale Market, Lahore, Pakistan') }}"
                           class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white focus:ring-indigo-500 text-sm py-2 px-3">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.settings.fields.tax_number') }}
                    </label>
                    <input type="text" name="tax_number" value="{{ old('tax_number', $settingsGrouped['general']['tax_number'] ?? 'NTN-1234567-8') }}"
                           class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white focus:ring-indigo-500 text-sm py-2 px-3">
                </div>
            </div>
        </div>

        <!-- Card: Invoice & Print Branding -->
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex items-center gap-2">
                <i data-lucide="printer" class="w-5 h-5 text-indigo-600 dark:text-indigo-400"></i>
                <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ __('erp.settings.tabs.branding') }}</h2>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.settings.fields.invoice_header_text') }}
                    </label>
                    <input type="text" name="invoice_header_text" value="{{ old('invoice_header_text', $settingsGrouped['branding']['invoice_header_text'] ?? 'Wholesale Footwear & Factory Direct Supplies') }}"
                           class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white focus:ring-indigo-500 text-sm py-2 px-3">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.settings.fields.logo_url') }}
                    </label>
                    <input type="text" name="logo_url" value="{{ old('logo_url', $settingsGrouped['branding']['logo_url'] ?? '') }}" placeholder="/images/logo.png"
                           class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white focus:ring-indigo-500 text-sm py-2 px-3">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.settings.fields.invoice_footer_terms') }}
                    </label>
                    <textarea name="invoice_footer_terms" rows="2"
                              class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white focus:ring-indigo-500 text-sm py-2 px-3">{{ old('invoice_footer_terms', $settingsGrouped['branding']['invoice_footer_terms'] ?? 'Goods sold are subject to standard return policy. Invoices must be cleared within agreed terms.') }}</textarea>
                </div>
            </div>
        </div>

        <!-- Card: Localization & Currency -->
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex items-center gap-2">
                <i data-lucide="globe" class="w-5 h-5 text-indigo-600 dark:text-indigo-400"></i>
                <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ __('erp.settings.tabs.localization') }}</h2>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-4 gap-6">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.settings.fields.default_locale') }}
                    </label>
                    <select name="default_locale" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white text-sm py-2 px-3">
                        <option value="en" {{ old('default_locale', $settingsGrouped['localization']['default_locale'] ?? 'en') == 'en' ? 'selected' : '' }}>English</option>
                        <option value="ur" {{ old('default_locale', $settingsGrouped['localization']['default_locale'] ?? 'en') == 'ur' ? 'selected' : '' }}>اردو (Urdu)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.settings.fields.timezone') }}
                    </label>
                    <input type="text" name="timezone" value="{{ old('timezone', $settingsGrouped['localization']['timezone'] ?? 'Asia/Karachi') }}"
                           class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white text-sm py-2 px-3">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.settings.fields.date_format') }}
                    </label>
                    <input type="text" name="date_format" value="{{ old('date_format', $settingsGrouped['localization']['date_format'] ?? 'Y-m-d') }}"
                           class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white text-sm py-2 px-3">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.settings.fields.time_format') }}
                    </label>
                    <input type="text" name="time_format" value="{{ old('time_format', $settingsGrouped['localization']['time_format'] ?? 'H:i:s') }}"
                           class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white text-sm py-2 px-3">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.settings.fields.currency_code') }}
                    </label>
                    <input type="text" name="currency_code" value="{{ old('currency_code', $settingsGrouped['localization']['currency_code'] ?? 'PKR') }}"
                           class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white text-sm py-2 px-3">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.settings.fields.currency_symbol') }}
                    </label>
                    <input type="text" name="currency_symbol" value="{{ old('currency_symbol', $settingsGrouped['localization']['currency_symbol'] ?? 'Rs.') }}"
                           class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white text-sm py-2 px-3">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.settings.fields.currency_position') }}
                    </label>
                    <select name="currency_position" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white text-sm py-2 px-3">
                        <option value="before" {{ old('currency_position', $settingsGrouped['localization']['currency_position'] ?? 'before') == 'before' ? 'selected' : '' }}>Before (Rs. 1,000)</option>
                        <option value="after" {{ old('currency_position', $settingsGrouped['localization']['currency_position'] ?? 'before') == 'after' ? 'selected' : '' }}>After (1,000 Rs.)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.settings.fields.decimal_places') }}
                    </label>
                    <input type="number" min="0" max="4" name="decimal_places" value="{{ old('decimal_places', $settingsGrouped['localization']['decimal_places'] ?? 2) }}"
                           class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white text-sm py-2 px-3">
                </div>
            </div>
        </div>

        <!-- Card: Document Numbering Prefixes -->
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex items-center gap-2">
                <i data-lucide="hash" class="w-5 h-5 text-indigo-600 dark:text-indigo-400"></i>
                <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ __('erp.settings.tabs.numbering') }}</h2>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.settings.fields.sales_invoice_prefix') }}
                    </label>
                    <input type="text" name="sales_invoice_prefix" value="{{ old('sales_invoice_prefix', $settingsGrouped['numbering']['sales_invoice_prefix'] ?? 'INV-') }}"
                           class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white text-sm py-2 px-3 font-mono">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.settings.fields.purchase_order_prefix') }}
                    </label>
                    <input type="text" name="purchase_order_prefix" value="{{ old('purchase_order_prefix', $settingsGrouped['numbering']['purchase_order_prefix'] ?? 'PO-') }}"
                           class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white text-sm py-2 px-3 font-mono">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.settings.fields.goods_receipt_prefix') }}
                    </label>
                    <input type="text" name="goods_receipt_prefix" value="{{ old('goods_receipt_prefix', $settingsGrouped['numbering']['goods_receipt_prefix'] ?? 'GRN-') }}"
                           class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white text-sm py-2 px-3 font-mono">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.settings.fields.sales_return_prefix') }}
                    </label>
                    <input type="text" name="sales_return_prefix" value="{{ old('sales_return_prefix', $settingsGrouped['numbering']['sales_return_prefix'] ?? 'SR-') }}"
                           class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white text-sm py-2 px-3 font-mono">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.settings.fields.purchase_return_prefix') }}
                    </label>
                    <input type="text" name="purchase_return_prefix" value="{{ old('purchase_return_prefix', $settingsGrouped['numbering']['purchase_return_prefix'] ?? 'PR-') }}"
                           class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white text-sm py-2 px-3 font-mono">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.settings.fields.payment_voucher_prefix') }}
                    </label>
                    <input type="text" name="payment_voucher_prefix" value="{{ old('payment_voucher_prefix', $settingsGrouped['numbering']['payment_voucher_prefix'] ?? 'PMT-') }}"
                           class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white text-sm py-2 px-3 font-mono">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.settings.fields.journal_entry_prefix') }}
                    </label>
                    <input type="text" name="journal_entry_prefix" value="{{ old('journal_entry_prefix', $settingsGrouped['numbering']['journal_entry_prefix'] ?? 'JV-') }}"
                           class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white text-sm py-2 px-3 font-mono">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.settings.fields.production_order_prefix') }}
                    </label>
                    <input type="text" name="production_order_prefix" value="{{ old('production_order_prefix', $settingsGrouped['numbering']['production_order_prefix'] ?? 'MFG-') }}"
                           class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white text-sm py-2 px-3 font-mono">
                </div>
            </div>
        </div>

        <!-- Card: Operational & Backups -->
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex items-center gap-2">
                <i data-lucide="shield" class="w-5 h-5 text-indigo-600 dark:text-indigo-400"></i>
                <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ __('erp.settings.tabs.operational') }}</h2>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.settings.fields.default_report_range') }}
                    </label>
                    <select name="default_report_range" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white text-sm py-2 px-3">
                        <option value="this_month" {{ old('default_report_range', $settingsGrouped['operational']['default_report_range'] ?? 'this_month') == 'this_month' ? 'selected' : '' }}>This Month</option>
                        <option value="today" {{ old('default_report_range', $settingsGrouped['operational']['default_report_range'] ?? 'this_month') == 'today' ? 'selected' : '' }}>Today</option>
                        <option value="this_quarter" {{ old('default_report_range', $settingsGrouped['operational']['default_report_range'] ?? 'this_month') == 'this_quarter' ? 'selected' : '' }}>This Quarter</option>
                        <option value="this_year" {{ old('default_report_range', $settingsGrouped['operational']['default_report_range'] ?? 'this_month') == 'this_year' ? 'selected' : '' }}>This Financial Year</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('erp.settings.fields.backup_retention_count') }}
                    </label>
                    <input type="number" min="1" max="100" name="backup_retention_count" value="{{ old('backup_retention_count', $settingsGrouped['operational']['backup_retention_count'] ?? 10) }}"
                           class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-white text-sm py-2 px-3">
                    <p class="text-xs text-slate-500 mt-1">Older completed backups will be pruned automatically when exceeding this threshold.</p>
                </div>
            </div>
        </div>

        <!-- Submit Button Bar -->
        <div class="flex items-center justify-end gap-4">
            <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold shadow-md transition">
                <i data-lucide="save" class="w-4 h-4"></i>
                <span>{{ __('erp.settings.save_settings') }}</span>
            </button>
        </div>
    </form>
</div>
@endsection
