@extends('layouts.app')

@section('title', __('erp.stock_transfers.title'))

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-3">
                <i data-lucide="arrow-left-right" class="w-7 h-7 text-indigo-600 dark:text-indigo-400"></i>
                <span>{{ __('erp.stock_transfers.title') }}</span>
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('erp.stock_transfers.subtitle') }}
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('inventory.index') }}" class="px-4 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 transition">
                Stock Balances
            </a>
            <a href="{{ route('inventory.transfers.create') }}" class="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-md transition">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>{{ __('erp.stock_transfers.new_transfer') }}</span>
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 shadow-sm">
        <form method="GET" action="{{ route('inventory.transfers.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Transfer #</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search ST-..."
                       class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-2 px-3">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Status</label>
                <select name="status" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-2 px-3">
                    <option value="">All Statuses</option>
                    <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="in_transit" {{ request('status') === 'in_transit' ? 'selected' : '' }}>In Transit</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed / Received</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Source Location</label>
                <select name="source_location_id" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-2 px-3">
                    <option value="">All Sources</option>
                    @foreach($authorizedLocations as $loc)
                        <option value="{{ $loc->id }}" {{ request('source_location_id') == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Destination Location</label>
                <select name="destination_location_id" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-2 px-3">
                    <option value="">All Destinations</option>
                    @foreach($authorizedLocations as $loc)
                        <option value="{{ $loc->id }}" {{ request('destination_location_id') == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg py-2 px-3 text-xs font-semibold flex items-center justify-center gap-1.5 transition">
                    <i data-lucide="filter" class="w-3.5 h-3.5"></i>
                    <span>Filter</span>
                </button>
                <a href="{{ route('inventory.transfers.index') }}" class="bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 rounded-lg py-2 px-3 text-xs font-semibold flex items-center justify-center transition">
                    <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider">
                        <th class="py-3 px-4">Transfer #</th>
                        <th class="py-3 px-4">Date</th>
                        <th class="py-3 px-4">Source Location</th>
                        <th class="py-3 px-4">Destination Location</th>
                        <th class="py-3 px-4 text-center">Cartons</th>
                        <th class="py-3 px-4 text-center">Pairs</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 font-medium">
                    @forelse($transfers as $t)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition">
                            <td class="py-3 px-4 font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                <a href="{{ route('inventory.transfers.show', $t) }}" class="hover:underline">
                                    {{ $t->transfer_number }}
                                </a>
                            </td>
                            <td class="py-3 px-4 font-mono text-slate-600 dark:text-slate-300">
                                {{ $t->transfer_date->format('d M Y') }}
                            </td>
                            <td class="py-3 px-4 font-bold text-slate-800 dark:text-slate-200">
                                {{ $t->sourceLocation->name }}
                            </td>
                            <td class="py-3 px-4 font-bold text-slate-800 dark:text-slate-200">
                                {{ $t->destinationLocation->name }}
                            </td>
                            <td class="py-3 px-4 text-center font-mono text-slate-700 dark:text-slate-300">
                                {{ $t->total_cartons }}
                            </td>
                            <td class="py-3 px-4 text-center font-mono font-bold text-slate-900 dark:text-white">
                                {{ number_format($t->total_pairs, 0) }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold
                                    @if($t->status === 'completed') bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300
                                    @elseif($t->status === 'in_transit') bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 animate-pulse
                                    @elseif($t->status === 'draft') bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300
                                    @else bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300 @endif">
                                    {{ ucfirst(str_replace('_', ' ', $t->status)) }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <a href="{{ route('inventory.transfers.show', $t) }}" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 font-semibold text-xs">
                                    <span>View Challan</span>
                                    <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-slate-400 text-xs">
                                {{ __('erp.stock_transfers.no_transfers') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($transfers->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                {{ $transfers->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
