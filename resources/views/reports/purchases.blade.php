@extends('layouts.app')

@section('title', __('erp.reports.purchases_report') . ' — ' . __('erp.brand_name'))

@section('content')
<div class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="bg-white border border-slate-200/80 rounded-xl p-6 shadow-xs">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold uppercase text-amber-600 tracking-wider mb-1">
                    <a href="{{ route('reports.index') }}" class="hover:underline">Reports</a>
                    <span>/</span>
                    <span>Purchases & Suppliers</span>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ __('erp.reports.purchases_report') }}</h1>
                <p class="text-xs text-slate-500 mt-1">Confirmed goods receipts (GRN), PO status, purchase returns, and supplier payments.</p>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('reports.purchases.export', request()->query()) }}" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs px-4 py-2.5 rounded-lg shadow-xs transition">
                    <i data-lucide="download" class="w-4 h-4"></i>
                    <span>{{ __('erp.reports.export_csv') }}</span>
                </a>
            </div>
        </div>

        <!-- Filter Form -->
        <form method="GET" action="{{ route('reports.purchases') }}" class="mt-6 pt-5 border-t border-slate-100 flex flex-wrap items-center gap-3">
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
                <select name="supplier_id" class="text-xs bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 font-medium text-slate-700">
                    <option value="">🏭 {{ __('erp.reports.all_suppliers') }}</option>
                    @foreach($suppliers as $s)
                        <option value="{{ $s->id }}" {{ ($filters['supplier_id'] ?? '') == $s->id ? 'selected' : '' }}>
                            {{ $s->business_name }} ({{ $s->supplier_code }})
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
            <a href="{{ route('reports.purchases') }}" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold px-3 py-2 rounded-lg transition">
                {{ __('erp.reports.filter_reset') }}
            </a>
        </form>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white border border-slate-200/80 rounded-xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase text-slate-400">Goods Receipts (GRN)</span>
            <div class="text-xl font-bold text-slate-900 mt-1">{{ $summary['receipts_count'] }}</div>
            <div class="text-[11px] text-slate-500 mt-1">{{ number_format((float) $summary['received_pairs'], 0) }} Pairs</div>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase text-slate-400">Total Received Cost</span>
            <div class="text-xl font-bold text-slate-900 mt-1">PKR {{ number_format((float) $summary['received_cost'], 2) }}</div>
            <div class="text-[11px] text-slate-500 mt-1">Physical stock inbound</div>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase text-slate-400">Purchase Orders (PO)</span>
            <div class="text-xl font-bold text-slate-900 mt-1">{{ $summary['po_count'] }}</div>
            <div class="text-[11px] text-slate-500 mt-1">PKR {{ number_format((float) $summary['ordered_amount'], 2) }}</div>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase text-slate-400">Purchase Returns</span>
            <div class="text-xl font-bold text-rose-600 mt-1">PKR {{ number_format((float) $summary['return_amount'], 2) }}</div>
            <div class="text-[11px] text-rose-500 mt-1">{{ $summary['returns_count'] }} Debit Notes</div>
        </div>

        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase text-amber-700">Net Purchases Cost</span>
            <div class="text-xl font-black text-amber-900 mt-1">PKR {{ number_format((float) $summary['net_purchases_cost'], 2) }}</div>
            <div class="text-[11px] text-amber-700 mt-1">Net of Debit Notes</div>
        </div>

        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase text-emerald-700">Supplier Payments</span>
            <div class="text-xl font-black text-emerald-900 mt-1">PKR {{ number_format((float) $summary['supplier_payments_total'], 2) }}</div>
            <div class="text-[11px] text-emerald-600 mt-1">Cash / Bank paid</div>
        </div>
    </div>

    <!-- Supplier Breakdown Table -->
    @if(count($supplier_breakdown) > 0)
    <div class="bg-white border border-slate-200/80 rounded-xl shadow-xs overflow-hidden">
        <div class="px-6 py-4 bg-slate-50/70 border-b border-slate-200/80 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Supplier Receiving & Payment Breakdown</h2>
            <span class="text-xs text-slate-500">{{ count($supplier_breakdown) }} Active Suppliers</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-start">
                <thead class="bg-slate-100 text-slate-600 font-bold uppercase border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 text-start">Supplier</th>
                        <th class="px-4 py-3 text-end">GRN Count</th>
                        <th class="px-4 py-3 text-end">Pairs Received</th>
                        <th class="px-4 py-3 text-end">Received Cost (PKR)</th>
                        <th class="px-4 py-3 text-end">Debit Notes (PKR)</th>
                        <th class="px-4 py-3 text-end">Payments Made (PKR)</th>
                        <th class="px-4 py-3 text-end">Current Khata Balance (PKR)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                    @foreach($supplier_breakdown as $sb)
                    <tr class="hover:bg-slate-50/70 transition">
                        <td class="px-4 py-3 font-semibold text-slate-900">
                            <div>{{ $sb['supplier_name'] }}</div>
                            <div class="text-[10px] text-slate-400 font-mono">{{ $sb['supplier_code'] }}</div>
                        </td>
                        <td class="px-4 py-3 text-end">{{ $sb['receipts_count'] }}</td>
                        <td class="px-4 py-3 text-end">{{ number_format((float) $sb['received_pairs'], 0) }}</td>
                        <td class="px-4 py-3 text-end font-bold text-slate-900">PKR {{ number_format((float) $sb['received_cost'], 2) }}</td>
                        <td class="px-4 py-3 text-end text-rose-600">PKR {{ number_format((float) $sb['returned_cost'], 2) }}</td>
                        <td class="px-4 py-3 text-end text-emerald-600">PKR {{ number_format((float) $sb['paid_amount'], 2) }}</td>
                        <td class="px-4 py-3 text-end font-bold text-amber-600">PKR {{ number_format((float) $sb['current_khata_balance'], 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- Goods Receipts Log Table -->
    <div class="bg-white border border-slate-200/80 rounded-xl shadow-xs overflow-hidden">
        <div class="px-6 py-4 bg-slate-50/70 border-b border-slate-200/80 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Goods Receipts (GRN) Inbound Log ({{ $receipts->count() }})</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-start">
                <thead class="bg-slate-100 text-slate-600 font-bold uppercase border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 text-start">Receipt # (GRN)</th>
                        <th class="px-4 py-3 text-start">Date</th>
                        <th class="px-4 py-3 text-start">Supplier</th>
                        <th class="px-4 py-3 text-start">Location</th>
                        <th class="px-4 py-3 text-start">PO Reference</th>
                        <th class="px-4 py-3 text-start">Challan #</th>
                        <th class="px-4 py-3 text-end">Pairs Received</th>
                        <th class="px-4 py-3 text-end">Total Cost (PKR)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                    @forelse($receipts as $grn)
                    <tr class="hover:bg-slate-50/70 transition">
                        <td class="px-4 py-3 font-mono font-bold text-amber-600">
                            <a href="{{ route('goods-receipts.show', $grn->id) }}" class="hover:underline">
                                {{ $grn->receipt_number }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $grn->receipt_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 font-semibold text-slate-900">{{ $grn->supplier ? $grn->supplier->business_name : 'N/A' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $grn->location ? $grn->location->name : 'N/A' }}</td>
                        <td class="px-4 py-3 font-mono text-slate-700">{{ $grn->purchaseOrder ? $grn->purchaseOrder->po_number : 'Direct Receiving' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $grn->challan_number ?: '-' }}</td>
                        <td class="px-4 py-3 text-end font-semibold">{{ number_format((float) $grn->total_received_pairs, 0) }}</td>
                        <td class="px-4 py-3 text-end font-bold text-slate-900">PKR {{ number_format((float) $grn->total_received_cost, 2) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-slate-400">
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
