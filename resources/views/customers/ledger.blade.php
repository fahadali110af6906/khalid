@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header (Hidden on Print) -->
    <div class="print:hidden flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <a href="{{ route('customers.show', $customer) }}" class="p-1 rounded-lg hover:bg-slate-200 text-slate-500 transition">
                    <i data-lucide="arrow-left" class="w-5 h-5 rtl:rotate-180"></i>
                </a>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.customers.khata_statement') }}</h1>
                <span class="font-mono text-xs font-bold px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-200">
                    {{ $customer->customer_code }}
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">
                {{ $customer->business_name }} • {{ $customer->contact_person }} ({{ $customer->phone }})
            </p>
        </div>

        <div class="flex items-center gap-3">
            <button onclick="window.print()" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-black text-white text-sm font-bold transition shadow-xs">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span>{{ __('erp.print') }} {{ __('erp.customers.khata_statement') }}</span>
            </button>
        </div>
    </div>

    <!-- Print-Only Header -->
    <div class="hidden print:block border-b-2 border-slate-900 pb-4 mb-4">
        <div class="flex justify-between items-start">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900">{{ config('app.name', 'Khalid Traders') }}</h1>
                <p class="text-xs text-slate-600">Wholesale Footwear Manufacturers & Distributors</p>
                <p class="text-xs text-slate-500">Shop #1 Anarkali Bazaar / Shah Alam Market, Lahore</p>
            </div>
            <div class="text-right">
                <h2 class="text-lg font-bold text-slate-900">CUSTOMER LEDGER STATEMENT (کھاتہ)</h2>
                <p class="text-xs font-mono text-slate-600">Printed: {{ now()->format('d M Y, h:i A') }}</p>
            </div>
        </div>
        <div class="mt-4 p-3 bg-slate-100 rounded-lg flex justify-between text-xs">
            <div>
                <span class="font-bold text-slate-700">Party:</span> {{ $customer->business_name }} ({{ $customer->contact_person }})<br>
                <span class="font-bold text-slate-700">Phone:</span> {{ $customer->phone }} | <span class="font-bold text-slate-700">City:</span> {{ $customer->city ?? 'N/A' }}
            </div>
            <div class="text-right">
                <span class="font-bold text-slate-700">Code:</span> {{ $customer->customer_code }}<br>
                <span class="font-bold text-slate-700">Statement Period:</span> {{ $fromDate ?? 'Inception' }} to {{ $toDate ?? 'Present' }}
            </div>
        </div>
    </div>

    <!-- Date Range & Location Filter (Hidden on Print) -->
    <div class="print:hidden bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
        <form method="GET" action="{{ route('customers.ledger', $customer) }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label for="from_date" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.customers.from_date') }}</label>
                <input type="date" id="from_date" name="from_date" value="{{ $fromDate }}"
                       class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
            </div>

            <div>
                <label for="to_date" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.customers.to_date') }}</label>
                <input type="date" id="to_date" name="to_date" value="{{ $toDate }}"
                       class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
            </div>

            <div>
                <label for="location_id" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.location') }}</label>
                <select id="location_id" name="location_id" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    @if(auth()->user()->isOwner())
                        <option value="all" {{ !$locationId ? 'selected' : '' }}>🏢 {{ __('erp.all_locations') }}</option>
                    @endif
                    @foreach($authorizedLocations as $loc)
                        <option value="{{ $loc->id }}" {{ $locationId == $loc->id ? 'selected' : '' }}>
                            {{ $loc->type === 'factory' ? '🏭' : '🏪' }} {{ $loc->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2 px-4 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition flex items-center justify-center gap-1.5 shadow-xs">
                    <i data-lucide="filter" class="w-4 h-4"></i>
                    <span>{{ __('erp.customers.filter_khata') }}</span>
                </button>
                <a href="{{ route('customers.ledger', $customer) }}" class="py-2 px-3 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold transition" title="{{ __('erp.customers.reset_filter') }}">
                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Khata Balance Cards -->
    @php
        $opening = (float) $khataSummary['period_opening_balance'];
        $debit = (float) $khataSummary['total_debit'];
        $credit = (float) $khataSummary['total_credit'];
        $closing = (float) $khataSummary['closing_balance'];
        $status = $khataSummary['balance_status'];
    @endphp
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">{{ __('erp.customers.opening_balance') }}</span>
            <div class="text-lg font-bold font-mono mt-1 text-slate-800">
                ₨ {{ number_format($opening, 2) }}
            </div>
            <span class="text-[10px] text-slate-500 block">At start of period</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">{{ __('erp.customers.total_debit') }}</span>
            <div class="text-lg font-bold font-mono mt-1 text-slate-900">
                ₨ {{ number_format($debit, 2) }}
            </div>
            <span class="text-[10px] text-slate-500 block">Goods sold / Charges</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">{{ __('erp.customers.total_credit') }}</span>
            <div class="text-lg font-bold font-mono mt-1 text-emerald-600">
                ₨ {{ number_format($credit, 2) }}
            </div>
            <span class="text-[10px] text-slate-500 block">Cash, Bank, Returns</span>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">{{ __('erp.customers.closing_balance') }}</span>
            <div class="text-lg font-extrabold font-mono mt-1 {{ $status === 'receivable' ? 'text-amber-600' : ($status === 'payable' ? 'text-emerald-600' : 'text-slate-800') }}">
                ₨ {{ number_format(abs($closing), 2) }}
            </div>
            <span class="text-[10px] font-bold block {{ $status === 'receivable' ? 'text-amber-700' : ($status === 'payable' ? 'text-emerald-700' : 'text-slate-400') }}">
                {{ $status === 'receivable' ? 'Dr (واجب الوصول)' : ($status === 'payable' ? 'Cr (پیشگی وصولی)' : 'Settled (بے باق)') }}
            </span>
        </div>
    </div>

    <!-- Statement Ledger Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left rtl:text-right text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500 font-bold border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">{{ __('erp.date') }}</th>
                        <th class="py-3 px-4">{{ __('erp.customers.voucher_ref') }}</th>
                        <th class="py-3 px-4">{{ __('erp.customers.description') }}</th>
                        <th class="py-3 px-4">{{ __('erp.location') }}</th>
                        <th class="py-3 px-4 text-right rtl:text-left">{{ __('erp.customers.debit') }} (بنام)</th>
                        <th class="py-3 px-4 text-right rtl:text-left">{{ __('erp.customers.credit') }} (جمع)</th>
                        <th class="py-3 px-4 text-right rtl:text-left">{{ __('erp.customers.running_balance') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                    <!-- Starting Balance Row -->
                    <tr class="bg-slate-50/50 italic text-slate-600">
                        <td class="py-3 px-4 font-mono text-xs">{{ $fromDate ?? '—' }}</td>
                        <td class="py-3 px-4 font-mono text-xs">—</td>
                        <td class="py-3 px-4 font-semibold text-slate-800">
                            {{ __('erp.customers.opening_balance') }} (حساب کا ابتدائی بقایا)
                        </td>
                        <td class="py-3 px-4 text-xs">—</td>
                        <td class="py-3 px-4 text-right rtl:text-left font-mono">
                            {{ $opening > 0 ? number_format($opening, 2) : '—' }}
                        </td>
                        <td class="py-3 px-4 text-right rtl:text-left font-mono">
                            {{ $opening < 0 ? number_format(abs($opening), 2) : '—' }}
                        </td>
                        <td class="py-3 px-4 text-right rtl:text-left font-mono font-bold text-slate-900">
                            ₨ {{ number_format(abs($opening), 2) }}
                            <span class="text-[10px] font-sans font-bold text-slate-500">{{ $opening >= 0 ? 'Dr' : 'Cr' }}</span>
                        </td>
                    </tr>

                    @forelse($calculatedEntries as $calc)
                        @php
                            $entry = $calc['entry'];
                            $running = (float) $calc['running_balance'];
                        @endphp
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="py-3.5 px-4 font-mono text-xs text-slate-600">
                                {{ $entry->entry_date->format('Y-m-d') }}
                            </td>
                            <td class="py-3.5 px-4 font-mono font-bold text-indigo-700 text-xs">
                                {{ $entry->reference_number ?? ('REF-' . $entry->id) }}
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="font-medium text-slate-900 block">{{ $entry->description ?? ucfirst(str_replace('_', ' ', $entry->entry_type)) }}</span>
                                <span class="text-[10px] text-slate-400 capitalize">Type: {{ str_replace('_', ' ', $entry->entry_type) }}</span>
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-600">
                                {{ $entry->location?->name ?? '—' }}
                            </td>
                            <td class="py-3.5 px-4 text-right rtl:text-left font-mono font-bold text-slate-900">
                                {{ (float) $entry->debit_amount > 0 ? '₨ ' . number_format((float) $entry->debit_amount, 2) : '—' }}
                            </td>
                            <td class="py-3.5 px-4 text-right rtl:text-left font-mono font-bold text-emerald-600">
                                {{ (float) $entry->credit_amount > 0 ? '₨ ' . number_format((float) $entry->credit_amount, 2) : '—' }}
                            </td>
                            <td class="py-3.5 px-4 text-right rtl:text-left font-mono font-bold">
                                ₨ {{ number_format(abs($running), 2) }}
                                <span class="text-[10px] font-sans font-bold {{ $running >= 0 ? 'text-amber-700' : 'text-emerald-700' }}">
                                    {{ $running >= 0 ? 'Dr' : 'Cr' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        @if(empty($calculatedEntries) && $opening == 0)
                            <tr>
                                <td colspan="7" class="py-10 text-center text-slate-400">
                                    {{ __('erp.customers.no_entries') }}
                                </td>
                            </tr>
                        @endif
                    @endforelse
                </tbody>
                <!-- Statement Footer Totals -->
                <tfoot class="bg-slate-50 font-bold border-t-2 border-slate-300 text-slate-900">
                    <tr>
                        <td colspan="4" class="py-3 px-4 uppercase text-xs tracking-wider">
                            Statement Totals (کل میزان)
                        </td>
                        <td class="py-3 px-4 text-right rtl:text-left font-mono">
                            ₨ {{ number_format($debit, 2) }}
                        </td>
                        <td class="py-3 px-4 text-right rtl:text-left font-mono text-emerald-600">
                            ₨ {{ number_format($credit, 2) }}
                        </td>
                        <td class="py-3 px-4 text-right rtl:text-left font-mono font-extrabold text-indigo-700">
                            ₨ {{ number_format(abs($closing), 2) }}
                            <span class="text-[10px] font-sans font-bold">{{ $closing >= 0 ? 'Dr' : 'Cr' }}</span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
