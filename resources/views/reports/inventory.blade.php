@extends('layouts.app')

@section('title', __('erp.reports.inventory_report') . ' — ' . __('erp.brand_name'))

@section('content')
<div class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="bg-white border border-slate-200/80 rounded-xl p-6 shadow-xs">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold uppercase text-cyan-600 tracking-wider mb-1">
                    <a href="{{ route('reports.index') }}" class="hover:underline">Reports</a>
                    <span>/</span>
                    <span>Inventory & Valuation</span>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ __('erp.reports.inventory_report') }}</h1>
                <p class="text-xs text-slate-500 mt-1">Stock on hand by location and variant, reorder threshold alerts, and valuation at Weighted Average Cost (WAC).</p>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('reports.inventory.export', request()->query()) }}" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs px-4 py-2.5 rounded-lg shadow-xs transition">
                    <i data-lucide="download" class="w-4 h-4"></i>
                    <span>{{ __('erp.reports.export_csv') }}</span>
                </a>
            </div>
        </div>

        <!-- Filter Form -->
        <form method="GET" action="{{ route('reports.inventory') }}" class="mt-6 pt-5 border-t border-slate-100 flex flex-wrap items-center gap-3">
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

            <div>
                <select name="category_id" class="text-xs bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 font-medium text-slate-700">
                    <option value="">📦 All Product Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ $currentCategoryId == $cat->id ? 'selected' : '' }}>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <label class="flex items-center gap-2 text-xs font-semibold text-slate-700 bg-slate-50 border border-slate-300 px-3 py-2 rounded-lg cursor-pointer">
                <input type="checkbox" name="low_stock" value="1" {{ $onlyLowStock ? 'checked' : '' }} class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                <span>⚠️ Low Stock Only</span>
            </label>

            <button type="submit" class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2 rounded-lg transition shadow-xs">
                {{ __('erp.reports.filter_apply') }}
            </button>
            <a href="{{ route('reports.inventory') }}" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold px-3 py-2 rounded-lg transition">
                {{ __('erp.reports.filter_reset') }}
            </a>
        </form>
    </div>

    <!-- Inventory Valuation KPI Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white border border-slate-200/80 rounded-xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase text-slate-400">Total Variants / SKUs</span>
            <div class="text-xl font-bold text-slate-900 mt-1">{{ number_format($summary['variants_count']) }}</div>
            <div class="text-[11px] text-slate-500 mt-1">Unique item lines</div>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase text-slate-400">Total Cartons in Stock</span>
            <div class="text-xl font-bold text-slate-900 mt-1">{{ number_format($summary['total_cartons']) }}</div>
            <div class="text-[11px] text-slate-500 mt-1">Wholesale master packs</div>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase text-slate-400">Total Pairs in Stock</span>
            <div class="text-xl font-bold text-slate-900 mt-1">{{ number_format((float) $summary['total_pairs'], 0) }}</div>
            <div class="text-[11px] text-slate-500 mt-1">Physical pair units</div>
        </div>

        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase text-amber-800">Low Stock SKUs</span>
            <div class="text-xl font-black text-amber-900 mt-1">{{ $summary['low_stock_count'] }}</div>
            <div class="text-[11px] text-amber-700 mt-1">Below reorder threshold</div>
        </div>

        <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase text-indigo-800">Valuation at WAC</span>
            <div class="text-xl font-black text-indigo-900 mt-1">PKR {{ number_format((float) $summary['total_valuation_wac'], 2) }}</div>
            <div class="text-[11px] text-indigo-600 mt-1">Weighted average cost</div>
        </div>

        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase text-emerald-800">Valuation at Wholesale</span>
            <div class="text-xl font-black text-emerald-900 mt-1">PKR {{ number_format((float) $summary['total_valuation_wholesale'], 2) }}</div>
            <div class="text-[11px] text-emerald-600 mt-1">Commercial sales value</div>
        </div>
    </div>

    <!-- Inventory Valuation Table -->
    <div class="bg-white border border-slate-200/80 rounded-xl shadow-xs overflow-hidden">
        <div class="px-6 py-4 bg-slate-50/70 border-b border-slate-200/80 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Stock on Hand & Valuation Details ({{ count($items) }})</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-start">
                <thead class="bg-slate-100 text-slate-600 font-bold uppercase border-b border-slate-200">
                    <tr>
                        <th class="px-3 py-3 text-start">Branch</th>
                        <th class="px-3 py-3 text-start">Article</th>
                        <th class="px-3 py-3 text-start">SKU / Variant</th>
                        <th class="px-3 py-3 text-center">Pack</th>
                        <th class="px-3 py-3 text-end">Cartons</th>
                        <th class="px-3 py-3 text-end">Pairs Qty</th>
                        <th class="px-3 py-3 text-end">Unit WAC (PKR)</th>
                        <th class="px-3 py-3 text-end">Valuation at WAC (PKR)</th>
                        <th class="px-3 py-3 text-end">Wholesale Val. (PKR)</th>
                        <th class="px-3 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                    @forelse($items as $row)
                    <tr class="hover:bg-slate-50/70 transition {{ $row['is_low_stock'] ? 'bg-amber-50/30' : '' }}">
                        <td class="px-3 py-3 text-slate-600">{{ $row['location_name'] }}</td>
                        <td class="px-3 py-3 font-semibold text-slate-900">
                            <div>{{ $row['product_name'] }}</div>
                            <div class="text-[10px] text-slate-400 font-mono">{{ $row['article_code'] }} ({{ $row['category_name'] }})</div>
                        </td>
                        <td class="px-3 py-3 font-mono">
                            <span class="font-bold text-slate-800">{{ $row['variant_sku'] }}</span>
                            <div class="text-[10px] text-slate-500">Size: {{ $row['size'] ?: '-' }} | Color: {{ $row['color'] ?: '-' }}</div>
                        </td>
                        <td class="px-3 py-3 text-center font-mono">{{ $row['pack_ratio'] }} prs</td>
                        <td class="px-3 py-3 text-end font-semibold text-slate-900">{{ number_format($row['cartons_quantity'], 1) }}</td>
                        <td class="px-3 py-3 text-end font-bold text-slate-900">{{ number_format((float) $row['pairs_quantity'], 0) }}</td>
                        <td class="px-3 py-3 text-end font-mono text-slate-700">PKR {{ number_format((float) $row['avg_unit_cost'], 2) }}</td>
                        <td class="px-3 py-3 text-end font-bold text-indigo-700 font-mono">PKR {{ number_format((float) $row['valuation_wac'], 2) }}</td>
                        <td class="px-3 py-3 text-end font-semibold text-emerald-700 font-mono">PKR {{ number_format((float) $row['valuation_wholesale'], 2) }}</td>
                        <td class="px-3 py-3 text-center">
                            @if($row['is_low_stock'])
                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-100 text-amber-800">
                                    Low Stock
                                </span>
                            @else
                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800">
                                    Normal
                                </span>
                            @endif
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
