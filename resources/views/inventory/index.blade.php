@extends('layouts.app')

@section('content')
<div class="space-y-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.nav.inventory') }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                    Stock Ledger & Balances
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">Real-time inventory balances strictly scoped to your authorized business locations.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('inventory.adjustments') }}" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg bg-white border border-slate-300 text-slate-700 text-xs font-bold hover:bg-slate-50 transition shadow-xs">
                <i data-lucide="scale" class="w-4 h-4"></i>
                <span>Stock Adjustments</span>
            </a>
            <a href="{{ route('products.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition shadow-xs">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>Add Footwear Article</span>
            </a>
        </div>
    </div>

    <!-- Filters & Location Switcher -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col md:flex-row gap-4 justify-between items-center">
        <form method="GET" action="{{ route('inventory.index') }}" class="flex flex-1 gap-3 w-full md:w-auto">
            <div class="relative flex-1">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-3 rtl:right-3 rtl:left-auto"></i>
                <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('erp.search') }}" class="w-full text-xs font-semibold rounded-lg border-slate-300 bg-slate-50 pl-9 rtl:pr-9 rtl:pl-3 p-2.5">
            </div>

            <select name="location_id" class="text-xs font-semibold rounded-lg border-slate-300 bg-slate-50 p-2.5">
                @if(auth()->user()->isOwner())
                    <option value="all" {{ $locationId === 'all' || !$locationId ? 'selected' : '' }}>All Authorized Locations (Consolidated)</option>
                @endif
                @foreach($authorizedLocations as $loc)
                    <option value="{{ $loc->id }}" {{ $locationId == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                @endforeach
            </select>

            <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold rounded-lg transition">
                {{ __('erp.filter') }}
            </button>
        </form>
    </div>

    <!-- Live Stock Ledger Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left rtl:text-right text-sm">
                <thead class="bg-slate-50/80 text-[11px] uppercase tracking-wider text-slate-500 font-bold border-b border-slate-100">
                    <tr>
                        <th class="py-3 px-6">Location</th>
                        <th class="py-3 px-6">Article & Description</th>
                        <th class="py-3 px-6">Variant SKU</th>
                        <th class="py-3 px-6">Size / Color</th>
                        <th class="py-3 px-6 text-center">Cartons</th>
                        <th class="py-3 px-6 text-center">Loose Pairs</th>
                        <th class="py-3 px-6 text-center">Total Pairs</th>
                        <th class="py-3 px-6 text-right rtl:text-left">Avg Cost (PKR)</th>
                        <th class="py-3 px-6 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                    @forelse($balances as $balance)
                        @php
                            $product = $balance->productVariant->product;
                            $conversion = $product->convertPairsToCartons($balance->pairs_quantity);
                            $isLowStock = $conversion['cartons'] < $product->reorder_threshold_cartons;
                        @endphp
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="py-4 px-6 font-bold text-slate-900 text-xs">
                                {{ $balance->location->name }}
                            </td>
                            <td class="py-4 px-6">
                                <a href="{{ route('products.show', $product) }}" class="font-bold text-slate-900 hover:text-indigo-600 block">
                                    {{ $product->article_code }}
                                </a>
                                <span class="text-xs text-slate-500">{{ $product->name }}</span>
                            </td>
                            <td class="py-4 px-6 font-mono text-xs font-bold text-indigo-600">
                                {{ $balance->productVariant->sku }}
                            </td>
                            <td class="py-4 px-6 text-xs">
                                <span class="font-bold text-slate-800">{{ $balance->productVariant->size }}</span> / {{ $balance->productVariant->color }}
                            </td>
                            <td class="py-4 px-6 text-center font-mono font-bold text-slate-900">
                                {{ $conversion['cartons'] }} CTN
                            </td>
                            <td class="py-4 px-6 text-center font-mono text-xs text-slate-500">
                                {{ $conversion['loose_pairs'] }} PR
                            </td>
                            <td class="py-4 px-6 text-center font-mono font-bold text-indigo-700">
                                {{ number_format($balance->pairs_quantity, 0) }} PR
                            </td>
                            <td class="py-4 px-6 text-right rtl:text-left font-mono text-xs">
                                ₨ {{ number_format($balance->avg_unit_cost, 2) }}
                            </td>
                            <td class="py-4 px-6 text-center">
                                @if($balance->pairs_quantity <= 0)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">Out of Stock</span>
                                @elseif($isLowStock)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Low Stock</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Adequate</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center text-slate-400">
                                <i data-lucide="boxes" class="w-10 h-10 mx-auto text-slate-300 mb-2"></i>
                                <p class="text-sm font-semibold">No stock records found for the selected location.</p>
                                <p class="text-xs text-slate-400 mt-1">Approved purchases, production output, or adjustments will update stock balances automatically.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-slate-100">
            {{ $balances->links() }}
        </div>
    </div>
</div>
@endsection
