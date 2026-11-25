@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.production.reports') }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                    Factory Analytics & Costing
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">Review batch throughput, material consumption, labor allocations, defect rates, and unit production costing.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('production.reports.export', request()->query()) }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold transition shadow-sm">
                <i data-lucide="download" class="w-4 h-4"></i>
                <span>{{ __('erp.reports.export_csv') }}</span>
            </a>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
        <form method="GET" action="{{ route('production.reports.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div>
                <label for="from_date" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.reports.from_date') }}</label>
                <input type="date" id="from_date" name="from_date" value="{{ request('from_date', $filters['from_date'] ?? '') }}"
                       class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 font-mono">
            </div>

            <div>
                <label for="to_date" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.reports.to_date') }}</label>
                <input type="date" id="to_date" name="to_date" value="{{ request('to_date', $filters['to_date'] ?? '') }}"
                       class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 font-mono">
            </div>

            <div>
                <label for="location_id" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.production.factory_location') }}</label>
                <select id="location_id" name="location_id" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                    <option value="">All Authorized Factories</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" {{ (string)request('location_id') === (string)$loc->id ? 'selected' : '' }}>
                            {{ $loc->name }} ({{ $loc->code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="status" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.status') }}</label>
                <select id="status" name="status" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                    <option value="">All Statuses</option>
                    <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="planned" {{ request('status') === 'planned' ? 'selected' : '' }}>Planned</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="w-full py-2 bg-slate-900 text-white rounded-lg text-sm font-semibold hover:bg-slate-800 transition flex items-center justify-center gap-1.5">
                    <i data-lucide="filter" class="w-4 h-4"></i>
                    <span>{{ __('erp.reports.filter_apply') }}</span>
                </button>
                <a href="{{ route('production.reports.index') }}" class="px-3 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm font-semibold hover:bg-slate-200 transition">
                    {{ __('erp.reset') }}
                </a>
            </div>
        </form>
    </div>

    <!-- Summary KPIs Grid -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Production Batches</div>
            <div class="mt-2 text-2xl font-bold font-mono text-slate-900">{{ number_format($summary['orders_count']) }}</div>
            <div class="text-xs text-slate-500">Total orders</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Completed Good Pairs</div>
            <div class="mt-2 text-2xl font-bold font-mono text-emerald-600">{{ number_format($summary['total_good_pairs'], 0) }}</div>
            <div class="text-xs text-slate-500">Planned: {{ number_format($summary['total_planned_pairs'], 0) }}</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Defect / Scrap Pairs</div>
            <div class="mt-2 text-2xl font-bold font-mono text-rose-600">{{ number_format($summary['total_wastage_pairs'], 0) }}</div>
            <div class="text-xs text-slate-500">
                @php
                    $totalFinished = bcadd($summary['total_good_pairs'], $summary['total_wastage_pairs'], 4);
                    $scrapRate = (bccomp($totalFinished, '0', 4) > 0) ? (bcmul(bcdiv($summary['total_wastage_pairs'], $totalFinished, 4), '100', 2)) : '0.00';
                @endphp
                Scrap Rate: {{ $scrapRate }}%
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Production Cost</div>
            <div class="mt-2 text-xl font-bold font-mono text-indigo-700">Rs. {{ number_format($summary['total_cost'], 2) }}</div>
            <div class="text-xs text-slate-400">Material + Labor + Overhead</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Avg Cost / Good Pair</div>
            <div class="mt-2 text-xl font-bold font-mono text-emerald-700">Rs. {{ number_format($summary['overall_cost_per_pair'], 2) }}</div>
            <div class="text-xs text-slate-400">Average WAC impact</div>
        </div>
    </div>

    <!-- Orders Detailed Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="p-4 border-b border-slate-200/80 flex items-center justify-between">
            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                <i data-lucide="table" class="w-4 h-4 text-indigo-600"></i>
                Detailed Orders Log
            </h2>
            <span class="text-xs text-slate-500 font-mono">{{ $orders->count() }} batches displayed</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50/75 border-b border-slate-200/80 text-xs uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-bold">Order #</th>
                        <th class="px-4 py-3 font-bold">Factory</th>
                        <th class="px-4 py-3 font-bold">Article / Variant</th>
                        <th class="px-4 py-3 font-bold">Status</th>
                        <th class="px-4 py-3 font-bold text-right">Planned</th>
                        <th class="px-4 py-3 font-bold text-right">Good Pairs</th>
                        <th class="px-4 py-3 font-bold text-right">Scrap</th>
                        <th class="px-4 py-3 font-bold text-right">Material Cost</th>
                        <th class="px-4 py-3 font-bold text-right">Labor</th>
                        <th class="px-4 py-3 font-bold text-right">Total Cost</th>
                        <th class="px-4 py-3 font-bold text-right">Cost / Pair</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($orders as $ord)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-4 py-3 font-mono font-bold text-indigo-700">
                                <a href="{{ route('production.orders.show', $ord) }}" class="hover:underline">
                                    {{ $ord->order_number }}
                                </a>
                                <div class="text-[11px] text-slate-400 font-normal font-sans">{{ $ord->start_date->format('M d, Y') }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-800">{{ $ord->factoryLocation->code }}</div>
                                <div class="text-xs text-slate-400">{{ $ord->factoryLocation->name }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-bold text-slate-900">{{ $ord->product->article_code }}</div>
                                <div class="text-xs text-slate-500 font-mono">{{ $ord->productVariant->sku }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold capitalize bg-slate-100 text-slate-700">
                                    {{ str_replace('_', ' ', $ord->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-800">
                                {{ number_format($ord->planned_quantity_pairs, 0) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-emerald-600">
                                {{ number_format($ord->completed_good_pairs, 0) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-rose-600 font-bold">
                                {{ number_format($ord->wastage_pairs, 0) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-slate-700">
                                Rs. {{ number_format($ord->actual_material_cost, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-slate-700">
                                Rs. {{ number_format($ord->labor_cost, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-indigo-700">
                                Rs. {{ number_format($ord->total_production_cost, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">
                                @if((float)$ord->unit_cost_per_pair > 0)
                                    Rs. {{ number_format($ord->unit_cost_per_pair, 2) }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-4 py-12 text-center text-slate-400">
                                <i data-lucide="bar-chart-2" class="w-10 h-10 mx-auto mb-2 text-slate-300"></i>
                                <p class="text-sm font-semibold">{{ __('erp.reports.no_data') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
