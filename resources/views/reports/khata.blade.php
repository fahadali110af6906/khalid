@extends('layouts.app')

@section('title', __('erp.reports.khata_report') . ' — ' . __('erp.brand_name'))

@section('content')
<div class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="bg-white border border-slate-200/80 rounded-xl p-6 shadow-xs">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold uppercase text-emerald-600 tracking-wider mb-1">
                    <a href="{{ route('reports.index') }}" class="hover:underline">Reports</a>
                    <span>/</span>
                    <span>Ledgers & Khata</span>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ __('erp.reports.khata_report') }}</h1>
                <p class="text-xs text-slate-500 mt-1">Reconciled customer and supplier running balances with 0-30, 31-60, 61-90, and 90+ days aging.</p>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('reports.khata.customer.export', request()->query()) }}" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs px-3.5 py-2 rounded-lg shadow-xs transition">
                    <i data-lucide="download" class="w-3.5 h-3.5"></i>
                    <span>Export Customer Aging</span>
                </a>
                <a href="{{ route('reports.khata.supplier.export', request()->query()) }}" class="inline-flex items-center gap-2 bg-amber-600 hover:bg-amber-700 text-white font-semibold text-xs px-3.5 py-2 rounded-lg shadow-xs transition">
                    <i data-lucide="download" class="w-3.5 h-3.5"></i>
                    <span>Export Supplier Aging</span>
                </a>
            </div>
        </div>

        <!-- Filter Form -->
        <form method="GET" action="{{ route('reports.khata') }}" class="mt-6 pt-5 border-t border-slate-100 flex flex-wrap items-center gap-3">
            @if(auth()->user()->isOwner())
            <div>
                <select name="location_id" class="text-xs bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 font-medium text-slate-700">
                    <option value="">🏢 {{ __('erp.reports.select_location') }}</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" {{ $currentLocationId == $loc->id ? 'selected' : '' }}>
                            {{ $loc->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif

            <div class="flex items-center gap-2">
                <input type="date" name="from_date" value="{{ $fromDate }}" class="text-xs bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-2 font-medium text-slate-700">
                <span class="text-xs text-slate-400">to</span>
                <input type="date" name="to_date" value="{{ $toDate }}" class="text-xs bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-2 font-medium text-slate-700">
            </div>

            <button type="submit" class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2 rounded-lg transition shadow-xs">
                {{ __('erp.reports.filter_apply') }}
            </button>
            <a href="{{ route('reports.khata') }}" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold px-3 py-2 rounded-lg transition">
                {{ __('erp.reports.filter_reset') }}
            </a>
        </form>
    </div>

    <!-- Section 1: Customer Khata & Receivables Aging -->
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-bold text-slate-900 tracking-tight flex items-center gap-2">
                <i data-lucide="users" class="w-4 h-4 text-indigo-600"></i>
                <span>Customer Khata & Receivables Aging</span>
            </h2>
            <div class="text-xs text-slate-500">
                Total Receivables: <strong class="text-indigo-600 font-bold">PKR {{ number_format((float) $customerData['summary']['total_closing'], 2) }}</strong>
            </div>
        </div>

        <!-- Aging KPI Summary Cards (Customers) -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="bg-white border border-slate-200/80 rounded-xl p-3 shadow-xs">
                <span class="text-[10px] font-bold uppercase text-slate-400">Current (0-30 Days)</span>
                <div class="text-base font-bold text-slate-900 mt-1">PKR {{ number_format((float) $customerData['summary']['aging_0_30'], 2) }}</div>
            </div>
            <div class="bg-white border border-slate-200/80 rounded-xl p-3 shadow-xs">
                <span class="text-[10px] font-bold uppercase text-slate-400">31 - 60 Days</span>
                <div class="text-base font-bold text-amber-600 mt-1">PKR {{ number_format((float) $customerData['summary']['aging_31_60'], 2) }}</div>
            </div>
            <div class="bg-white border border-slate-200/80 rounded-xl p-3 shadow-xs">
                <span class="text-[10px] font-bold uppercase text-slate-400">61 - 90 Days</span>
                <div class="text-base font-bold text-orange-600 mt-1">PKR {{ number_format((float) $customerData['summary']['aging_61_90'], 2) }}</div>
            </div>
            <div class="bg-white border border-slate-200/80 rounded-xl p-3 shadow-xs">
                <span class="text-[10px] font-bold uppercase text-slate-400">Over 90 Days (Overdue)</span>
                <div class="text-base font-bold text-rose-600 mt-1">PKR {{ number_format((float) $customerData['summary']['aging_over_90'], 2) }}</div>
            </div>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-xl shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-start">
                    <thead class="bg-slate-100 text-slate-600 font-bold uppercase border-b border-slate-200">
                        <tr>
                            <th class="px-3 py-2.5 text-start">Customer</th>
                            <th class="px-3 py-2.5 text-start">Branch</th>
                            <th class="px-3 py-2.5 text-end">Opening (PKR)</th>
                            <th class="px-3 py-2.5 text-end">Debits (PKR)</th>
                            <th class="px-3 py-2.5 text-end">Credits (PKR)</th>
                            <th class="px-3 py-2.5 text-end">Closing Due (PKR)</th>
                            <th class="px-3 py-2.5 text-end">0-30 d</th>
                            <th class="px-3 py-2.5 text-end">31-60 d</th>
                            <th class="px-3 py-2.5 text-end">61-90 d</th>
                            <th class="px-3 py-2.5 text-end">90+ d</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                        @forelse($customerData['customers'] as $c)
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="px-3 py-2.5">
                                <a href="{{ route('customers.ledger', $c['customer_id']) }}" class="font-bold text-indigo-600 hover:underline">
                                    {{ $c['business_name'] }}
                                </a>
                                <div class="text-[10px] text-slate-400 font-mono">{{ $c['customer_code'] }}</div>
                            </td>
                            <td class="px-3 py-2.5 text-slate-600">{{ $c['location_name'] }}</td>
                            <td class="px-3 py-2.5 text-end font-mono">PKR {{ number_format((float) $c['period_opening_balance'], 2) }}</td>
                            <td class="px-3 py-2.5 text-end text-slate-900 font-mono">PKR {{ number_format((float) $c['total_debit'], 2) }}</td>
                            <td class="px-3 py-2.5 text-end text-emerald-600 font-mono">PKR {{ number_format((float) $c['total_credit'], 2) }}</td>
                            <td class="px-3 py-2.5 text-end font-bold text-indigo-700 font-mono">PKR {{ number_format((float) $c['closing_balance'], 2) }}</td>
                            <td class="px-3 py-2.5 text-end font-mono">{{ number_format((float) $c['aging_0_30'], 2) }}</td>
                            <td class="px-3 py-2.5 text-end font-mono text-amber-600">{{ number_format((float) $c['aging_31_60'], 2) }}</td>
                            <td class="px-3 py-2.5 text-end font-mono text-orange-600">{{ number_format((float) $c['aging_61_90'], 2) }}</td>
                            <td class="px-3 py-2.5 text-end font-mono text-rose-600 font-bold">{{ number_format((float) $c['aging_over_90'], 2) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="px-6 py-8 text-center text-slate-400">{{ __('erp.reports.no_data') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Section 2: Supplier Khata & Payables Aging -->
    <div class="space-y-4 pt-4 border-t border-slate-200">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-bold text-slate-900 tracking-tight flex items-center gap-2">
                <i data-lucide="truck" class="w-4 h-4 text-amber-600"></i>
                <span>Supplier Khata & Payables Aging</span>
            </h2>
            <div class="text-xs text-slate-500">
                Total Payables: <strong class="text-amber-600 font-bold">PKR {{ number_format((float) $supplierData['summary']['total_closing'], 2) }}</strong>
            </div>
        </div>

        <!-- Aging KPI Summary Cards (Suppliers) -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="bg-white border border-slate-200/80 rounded-xl p-3 shadow-xs">
                <span class="text-[10px] font-bold uppercase text-slate-400">Current (0-30 Days)</span>
                <div class="text-base font-bold text-slate-900 mt-1">PKR {{ number_format((float) $supplierData['summary']['aging_0_30'], 2) }}</div>
            </div>
            <div class="bg-white border border-slate-200/80 rounded-xl p-3 shadow-xs">
                <span class="text-[10px] font-bold uppercase text-slate-400">31 - 60 Days</span>
                <div class="text-base font-bold text-amber-600 mt-1">PKR {{ number_format((float) $supplierData['summary']['aging_31_60'], 2) }}</div>
            </div>
            <div class="bg-white border border-slate-200/80 rounded-xl p-3 shadow-xs">
                <span class="text-[10px] font-bold uppercase text-slate-400">61 - 90 Days</span>
                <div class="text-base font-bold text-orange-600 mt-1">PKR {{ number_format((float) $supplierData['summary']['aging_61_90'], 2) }}</div>
            </div>
            <div class="bg-white border border-slate-200/80 rounded-xl p-3 shadow-xs">
                <span class="text-[10px] font-bold uppercase text-slate-400">Over 90 Days (Overdue)</span>
                <div class="text-base font-bold text-rose-600 mt-1">PKR {{ number_format((float) $supplierData['summary']['aging_over_90'], 2) }}</div>
            </div>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-xl shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-start">
                    <thead class="bg-slate-100 text-slate-600 font-bold uppercase border-b border-slate-200">
                        <tr>
                            <th class="px-3 py-2.5 text-start">Supplier</th>
                            <th class="px-3 py-2.5 text-start">Contact</th>
                            <th class="px-3 py-2.5 text-end">Opening (PKR)</th>
                            <th class="px-3 py-2.5 text-end">Credits (PKR)</th>
                            <th class="px-3 py-2.5 text-end">Debits (PKR)</th>
                            <th class="px-3 py-2.5 text-end">Closing Payable (PKR)</th>
                            <th class="px-3 py-2.5 text-end">0-30 d</th>
                            <th class="px-3 py-2.5 text-end">31-60 d</th>
                            <th class="px-3 py-2.5 text-end">61-90 d</th>
                            <th class="px-3 py-2.5 text-end">90+ d</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                        @forelse($supplierData['suppliers'] as $s)
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="px-3 py-2.5">
                                <a href="{{ route('suppliers.ledger', $s['supplier_id']) }}" class="font-bold text-amber-600 hover:underline">
                                    {{ $s['business_name'] }}
                                </a>
                                <div class="text-[10px] text-slate-400 font-mono">{{ $s['supplier_code'] }}</div>
                            </td>
                            <td class="px-3 py-2.5 text-slate-600">{{ $s['contact_person'] ?: '-' }}</td>
                            <td class="px-3 py-2.5 text-end font-mono">PKR {{ number_format((float) $s['period_opening_balance'], 2) }}</td>
                            <td class="px-3 py-2.5 text-end text-slate-900 font-mono">PKR {{ number_format((float) $s['total_credit'], 2) }}</td>
                            <td class="px-3 py-2.5 text-end text-emerald-600 font-mono">PKR {{ number_format((float) $s['total_debit'], 2) }}</td>
                            <td class="px-3 py-2.5 text-end font-bold text-amber-700 font-mono">PKR {{ number_format((float) $s['closing_balance'], 2) }}</td>
                            <td class="px-3 py-2.5 text-end font-mono">{{ number_format((float) $s['aging_0_30'], 2) }}</td>
                            <td class="px-3 py-2.5 text-end font-mono text-amber-600">{{ number_format((float) $s['aging_31_60'], 2) }}</td>
                            <td class="px-3 py-2.5 text-end font-mono text-orange-600">{{ number_format((float) $s['aging_61_90'], 2) }}</td>
                            <td class="px-3 py-2.5 text-end font-mono text-rose-600 font-bold">{{ number_format((float) $s['aging_over_90'], 2) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="px-6 py-8 text-center text-slate-400">{{ __('erp.reports.no_data') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
