@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.production.orders') }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                    Factory Floor Operations
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">{{ __('erp.production.subtitle') }}</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('production.orders.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition shadow-sm">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>{{ __('erp.production.new_order') }}</span>
            </a>
        </div>
    </div>

    <!-- Alert Success / Error -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium flex items-center gap-2">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-medium flex items-center gap-2">
            <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-600"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Filter Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
        <form method="GET" action="{{ route('production.orders.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div>
                <label for="search" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.search') }}</label>
                <div class="relative">
                    <input type="text" id="search" name="search" value="{{ request('search') }}"
                           placeholder="Order #, Article, SKU..."
                           class="w-full pl-9 pr-3 py-2 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"></i>
                </div>
            </div>

            <div>
                <label for="status" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.status') }}</label>
                <select id="status" name="status" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <option value="all" {{ request('status') === 'all' || !request('status') ? 'selected' : '' }}>All Statuses</option>
                    <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="planned" {{ request('status') === 'planned' ? 'selected' : '' }}>Planned</option>
                    <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>

            <div>
                <label for="location_id" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.production.factory_location') }}</label>
                <select id="location_id" name="location_id" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <option value="all">All Authorized Factories</option>
                    @foreach($authorizedLocations as $loc)
                        <option value="{{ $loc->id }}" {{ request('location_id') == $loc->id ? 'selected' : '' }}>
                            {{ $loc->name }} ({{ $loc->code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end gap-2 lg:col-span-2">
                <button type="submit" class="px-4 py-2 bg-slate-900 text-white rounded-lg text-sm font-semibold hover:bg-slate-800 transition flex items-center gap-1.5">
                    <i data-lucide="filter" class="w-4 h-4"></i>
                    <span>{{ __('erp.filter') }}</span>
                </button>
                <a href="{{ route('production.orders.index') }}" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm font-semibold hover:bg-slate-200 transition">
                    {{ __('erp.reset') }}
                </a>
            </div>
        </form>
    </div>

    <!-- Orders Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50/75 border-b border-slate-200/80 text-xs uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3.5 font-bold">{{ __('erp.production.order_number') }}</th>
                        <th class="px-4 py-3.5 font-bold">{{ __('erp.production.factory_location') }}</th>
                        <th class="px-4 py-3.5 font-bold">{{ __('erp.production.target_product') }}</th>
                        <th class="px-4 py-3.5 font-bold text-center">{{ __('erp.production.status') }}</th>
                        <th class="px-4 py-3.5 font-bold text-right">{{ __('erp.production.planned_quantity') }}</th>
                        <th class="px-4 py-3.5 font-bold text-right">{{ __('erp.production.completed_good_pairs') }}</th>
                        <th class="px-4 py-3.5 font-bold text-right">{{ __('erp.production.unit_cost_per_pair') }}</th>
                        <th class="px-4 py-3.5 font-bold text-right">{{ __('erp.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($orders as $order)
                        @php
                            $statusBadge = match($order->status) {
                                'draft' => 'bg-slate-100 text-slate-700 border-slate-200',
                                'planned' => 'bg-blue-50 text-blue-700 border-blue-200',
                                'in_progress' => 'bg-amber-50 text-amber-800 border-amber-200 animate-pulse',
                                'completed' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                'cancelled' => 'bg-rose-50 text-rose-700 border-rose-200',
                                default => 'bg-slate-100 text-slate-700'
                            };
                        @endphp
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-4 py-3 font-mono font-bold text-indigo-700">
                                <a href="{{ route('production.orders.show', $order) }}" class="hover:underline">
                                    {{ $order->order_number }}
                                </a>
                                <div class="text-[11px] text-slate-400 font-sans font-normal">{{ $order->start_date->format('M d, Y') }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 text-xs font-semibold">
                                    {{ $order->factoryLocation->code }}
                                </span>
                                <div class="text-xs text-slate-500">{{ $order->factoryLocation->name }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-bold text-slate-900">{{ $order->product->article_code }} — {{ $order->product->name }}</div>
                                <div class="text-xs text-slate-500 font-mono">{{ $order->productVariant->sku }} (Size {{ $order->productVariant->size }}/{{ $order->productVariant->color }})</div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $statusBadge }}">
                                    {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">
                                {{ number_format($order->planned_quantity_pairs, 0) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold {{ (float)$order->completed_good_pairs > 0 ? 'text-emerald-700' : 'text-slate-400' }}">
                                {{ number_format($order->completed_good_pairs, 0) }}
                                @if((float)$order->wastage_pairs > 0)
                                    <span class="block text-[11px] text-rose-500 font-normal">({{ number_format($order->wastage_pairs, 0) }} scrap)</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">
                                @if((float)$order->unit_cost_per_pair > 0)
                                    Rs. {{ number_format($order->unit_cost_per_pair, 2) }}
                                @else
                                    <span class="text-slate-300 text-xs">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('production.orders.show', $order) }}" class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-bold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition">
                                    <span>Manage</span>
                                    <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-slate-400">
                                <i data-lucide="cog" class="w-10 h-10 mx-auto mb-2 text-slate-300"></i>
                                <p class="text-sm font-semibold">{{ __('erp.production.no_orders') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($orders->hasPages())
            <div class="px-4 py-3 border-t border-slate-200/80 bg-slate-50/50">
                {{ $orders->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
