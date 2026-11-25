@extends('layouts.app')

@section('title', __('erp.reports.sales_report') . ' — ' . __('erp.brand_name'))

@section('content')
<div class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="bg-white border border-slate-200/80 rounded-xl p-6 shadow-xs">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold uppercase text-indigo-600 tracking-wider mb-1">
                    <a href="{{ route('reports.index') }}" class="hover:underline">Reports</a>
                    <span>/</span>
                    <span>Commercial</span>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ __('erp.reports.sales_report') }}</h1>
                <p class="text-xs text-slate-500 mt-1">Confirmed wholesale invoices excluding drafts and voided sales.</p>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('reports.sales.export', request()->query()) }}" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs px-4 py-2.5 rounded-lg shadow-xs transition">
                    <i data-lucide="download" class="w-4 h-4"></i>
                    <span>{{ __('erp.reports.export_csv') }}</span>
                </a>
            </div>
        </div>

        <!-- Filter Form -->
        <form method="GET" action="{{ route('reports.sales') }}" class="mt-6 pt-5 border-t border-slate-100 flex flex-wrap items-center gap-3">
            @if(auth()->user()->isOwner())
            <div>
                <select name="location_id" class="text-xs bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 font-medium text-slate-700">
                    <option value="">🏢 {{ __('erp.reports.select_location') }}</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" {{ ($filters['location_id'] ?? '') == $loc->id ? 'selected' : '' }}>
                            {{ $loc->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif

            <div>
                <select name="customer_id" class="text-xs bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 font-medium text-slate-700">
                    <option value="">👥 {{ __('erp.reports.all_customers') }}</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}" {{ ($filters['customer_id'] ?? '') == $c->id ? 'selected' : '' }}>
                            {{ $c->business_name }} ({{ $c->customer_code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2">
                <input type="date" name="from_date" value="{{ $filters['from_date'] }}" class="text-xs bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-2 font-medium text-slate-700">
                <span class="text-xs text-slate-400">to</span>
                <input type="date" name="to_date" value="{{ $filters['to_date'] }}" class="text-xs bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-2 font-medium text-slate-700">
            </div>

            <button type="submit" class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2 rounded-lg transition shadow-xs">
                {{ __('erp.reports.filter_apply') }}
            </button>
            <a href="{{ route('reports.sales') }}" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold px-3 py-2 rounded-lg transition">
                {{ __('erp.reports.filter_reset') }}
            </a>
        </form>
    </div>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white border border-slate-200/80 rounded-xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase text-slate-400">Invoices Count</span>
            <div class="text-xl font-bold text-slate-900 mt-1">{{ $summary['invoices_count'] }}</div>
            <div class="text-[11px] text-slate-500 mt-1">{{ number_format($summary['total_cartons']) }} Cartons</div>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase text-slate-400">Total Pairs Sold</span>
            <div class="text-xl font-bold text-slate-900 mt-1">{{ number_format((float) $summary['total_pairs'], 0) }}</div>
            <div class="text-[11px] text-slate-500 mt-1">Wholesale volume</div>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase text-slate-400">Gross Sales</span>
            <div class="text-xl font-bold text-slate-900 mt-1">PKR {{ number_format((float) $summary['total_amount'], 2) }}</div>
            <div class="text-[11px] text-slate-500 mt-1">Disc: PKR {{ number_format((float) $summary['discount_amount'], 2) }}</div>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase text-slate-400">Sales Returns</span>
            <div class="text-xl font-bold text-rose-600 mt-1">PKR {{ number_format((float) $summary['returns_amount'], 2) }}</div>
            <div class="text-[11px] text-rose-500 mt-1">{{ $summary['returns_count'] }} Returns Posted</div>
        </div>

        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase text-emerald-700">Net Sales Revenue</span>
            <div class="text-xl font-black text-emerald-900 mt-1">PKR {{ number_format((float) $summary['net_sales_amount'], 2) }}</div>
            <div class="text-[11px] text-emerald-600 mt-1">Net of Returns</div>
        </div>

        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase text-amber-700">Balance Outstanding</span>
            <div class="text-xl font-black text-amber-900 mt-1">PKR {{ number_format((float) $summary['balance_amount'], 2) }}</div>
            <div class="text-[11px] text-amber-700 mt-1">Paid: PKR {{ number_format((float) $summary['paid_amount'], 2) }}</div>
        </div>
    </div>

    <!-- Customer Breakdown Table -->
    @if(count($customer_breakdown) > 0)
    <div class="bg-white border border-slate-200/80 rounded-xl shadow-xs overflow-hidden">
        <div class="px-6 py-4 bg-slate-50/70 border-b border-slate-200/80 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Customer Performance Breakdown</h2>
            <span class="text-xs text-slate-500">{{ count($customer_breakdown) }} Active Customers</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-start">
                <thead class="bg-slate-100 text-slate-600 font-bold uppercase border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 text-start">Customer</th>
                        <th class="px-4 py-3 text-end">Invoices</th>
                        <th class="px-4 py-3 text-end">Pairs Sold</th>
                        <th class="px-4 py-3 text-end">Gross Sales (PKR)</th>
                        <th class="px-4 py-3 text-end">Paid (PKR)</th>
                        <th class="px-4 py-3 text-end">Outstanding Balance (PKR)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                    @foreach($customer_breakdown as $cb)
                    <tr class="hover:bg-slate-50/70 transition">
                        <td class="px-4 py-3 font-semibold text-slate-900">
                            <div>{{ $cb['customer_name'] }}</div>
                            <div class="text-[10px] text-slate-400 font-mono">{{ $cb['customer_code'] }}</div>
                        </td>
                        <td class="px-4 py-3 text-end">{{ $cb['invoices_count'] }}</td>
                        <td class="px-4 py-3 text-end">{{ number_format((float) $cb['total_pairs'], 0) }}</td>
                        <td class="px-4 py-3 text-end font-bold text-slate-900">PKR {{ number_format((float) $cb['total_amount'], 2) }}</td>
                        <td class="px-4 py-3 text-end text-emerald-600 font-semibold">PKR {{ number_format((float) $cb['paid_amount'], 2) }}</td>
                        <td class="px-4 py-3 text-end font-bold {{ bccomp((string)$cb['balance_amount'], '0.00', 2) > 0 ? 'text-amber-600' : 'text-slate-500' }}">
                            PKR {{ number_format((float) $cb['balance_amount'], 2) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- Invoices List Table -->
    <div class="bg-white border border-slate-200/80 rounded-xl shadow-xs overflow-hidden">
        <div class="px-6 py-4 bg-slate-50/70 border-b border-slate-200/80 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Confirmed Invoices Log ({{ $invoices->count() }})</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-start">
                <thead class="bg-slate-100 text-slate-600 font-bold uppercase border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 text-start">Invoice #</th>
                        <th class="px-4 py-3 text-start">Date</th>
                        <th class="px-4 py-3 text-start">Customer</th>
                        <th class="px-4 py-3 text-start">Location</th>
                        <th class="px-4 py-3 text-end">Cartons</th>
                        <th class="px-4 py-3 text-end">Pairs</th>
                        <th class="px-4 py-3 text-end">Total Amount (PKR)</th>
                        <th class="px-4 py-3 text-end">Paid (PKR)</th>
                        <th class="px-4 py-3 text-end">Balance (PKR)</th>
                        <th class="px-4 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                    @forelse($invoices as $inv)
                    <tr class="hover:bg-slate-50/70 transition">
                        <td class="px-4 py-3 font-mono font-bold text-indigo-600">
                            <a href="{{ route('sales.invoices.show', $inv->id) }}" class="hover:underline">
                                {{ $inv->invoice_number }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $inv->invoice_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 font-semibold text-slate-900">{{ $inv->customer ? $inv->customer->business_name : 'N/A' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $inv->location ? $inv->location->name : 'N/A' }}</td>
                        <td class="px-4 py-3 text-end">{{ $inv->total_cartons }}</td>
                        <td class="px-4 py-3 text-end">{{ number_format((float) $inv->total_pairs, 0) }}</td>
                        <td class="px-4 py-3 text-end font-bold text-slate-900">PKR {{ number_format((float) $inv->total_amount, 2) }}</td>
                        <td class="px-4 py-3 text-end text-emerald-600">PKR {{ number_format((float) $inv->paid_amount, 2) }}</td>
                        <td class="px-4 py-3 text-end font-bold text-amber-600">PKR {{ number_format((float) $inv->balance_amount, 2) }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold uppercase {{ $inv->payment_status === 'paid' ? 'bg-emerald-100 text-emerald-800' : ($inv->payment_status === 'partial' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800') }}">
                                {{ $inv->payment_status }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="px-6 py-12 text-center text-slate-400">
                            {{ __('erp.reports.no_data') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
