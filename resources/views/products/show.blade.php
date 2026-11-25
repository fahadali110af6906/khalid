@extends('layouts.app')

@section('content')
<div class="space-y-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="font-mono text-xs font-bold bg-indigo-100 text-indigo-800 px-2.5 py-1 rounded-md">
                    {{ $product->article_code }}
                </span>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $product->name }}</h1>
            </div>
            <p class="text-sm text-slate-500 mt-1">
                Category: <strong>{{ $product->category->name }}</strong> 
                @if($product->brand) • Brand: <strong>{{ $product->brand->name }}</strong> @endif
                • Packaging: <strong>1 Carton = {{ $product->carton_pack_qty }} Pairs</strong>
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('products.index') }}" class="px-3.5 py-2 rounded-lg bg-white border border-slate-300 text-slate-700 text-xs font-bold hover:bg-slate-50 transition">
                Back to Articles
            </a>
            <a href="{{ route('products.edit', $product) }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs transition">
                <i data-lucide="edit-3" class="w-4 h-4"></i>
                <span>Edit Article</span>
            </a>
        </div>
    </div>

    <!-- Product Details Grid -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Carton Pack Ratio</span>
            <div class="text-xl font-extrabold text-slate-900 mt-2 font-mono">1 CTN = {{ $product->carton_pack_qty }} Pairs</div>
            <div class="text-xs text-slate-400 mt-1">Configurable Packaging Unit</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Default Wholesale Rate</span>
            <div class="text-xl font-extrabold text-indigo-600 mt-2 font-mono">₨ {{ number_format($product->default_wholesale_rate, 2) }} / PR</div>
            <div class="text-xs text-slate-400 mt-1">Per Master Carton: ₨ {{ number_format($product->default_wholesale_rate * $product->carton_pack_qty, 2) }}</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Reorder Threshold</span>
            <div class="text-xl font-extrabold text-amber-600 mt-2 font-mono">{{ $product->reorder_threshold_cartons }} Cartons</div>
            <div class="text-xs text-slate-400 mt-1">({{ $product->reorder_threshold_cartons * $product->carton_pack_qty }} Pairs minimum)</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Defined Variants</span>
            <div class="text-xl font-extrabold text-slate-900 mt-2 font-mono">{{ $product->variants->count() }} Variants</div>
            <div class="text-xs text-slate-400 mt-1">Unique Size & Color combinations</div>
        </div>
    </div>

    <!-- Defined Variants Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-bold text-slate-900">Footwear Variant Matrix</h2>
            <span class="text-xs text-slate-500 font-mono">Unique SKUs generated automatically</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left rtl:text-right text-xs">
                <thead class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider">
                    <tr>
                        <th class="p-3 px-6">SKU</th>
                        <th class="p-3 px-6">Size</th>
                        <th class="p-3 px-6">Color</th>
                        <th class="p-3 px-6">Barcode</th>
                        <th class="p-3 px-6 text-right rtl:text-left">Wholesale Rate</th>
                        <th class="p-3 px-6 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @foreach($product->variants as $variant)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="p-3 px-6 font-mono font-bold text-indigo-600">{{ $variant->sku }}</td>
                            <td class="p-3 px-6 font-mono font-bold text-slate-900">{{ $variant->size }}</td>
                            <td class="p-3 px-6">
                                <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-800 font-semibold">{{ $variant->color }}</span>
                            </td>
                            <td class="p-3 px-6 font-mono text-slate-500">{{ $variant->barcode ?? '—' }}</td>
                            <td class="p-3 px-6 text-right rtl:text-left font-mono font-bold text-slate-900">
                                ₨ {{ number_format($variant->effective_wholesale_rate, 2) }}
                            </td>
                            <td class="p-3 px-6 text-center">
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700">Active</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Location-Restricted Stock Balances -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-slate-900">Current Stock Balances by Authorized Location</h2>
                <p class="text-xs text-slate-500">Live inventory balances converted into cartons and loose pairs</p>
            </div>
            <span class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700">
                Server-Side Location Scoped
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left rtl:text-right text-xs">
                <thead class="bg-slate-50 text-slate-500 font-bold uppercase tracking-wider">
                    <tr>
                        <th class="p-3 px-6">Location</th>
                        <th class="p-3 px-6">Variant SKU</th>
                        <th class="p-3 px-6">Size / Color</th>
                        <th class="p-3 px-6 text-center">Cartons</th>
                        <th class="p-3 px-6 text-center">Loose Pairs</th>
                        <th class="p-3 px-6 text-center">Total Base Pairs</th>
                        <th class="p-3 px-6 text-right rtl:text-left">Avg Unit Cost</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @php $hasStock = false; @endphp
                    @foreach($product->variants as $variant)
                        @foreach($variant->stockBalances as $balance)
                            @if(auth()->user()->hasLocationAccess($balance->location_id))
                                @php 
                                    $hasStock = true;
                                    $converted = $product->convertPairsToCartons($balance->pairs_quantity);
                                @endphp
                                <tr class="hover:bg-slate-50/60 transition">
                                    <td class="p-3 px-6 font-bold text-slate-900">
                                        {{ $balance->location->name }}
                                    </td>
                                    <td class="p-3 px-6 font-mono font-bold text-indigo-600">{{ $variant->sku }}</td>
                                    <td class="p-3 px-6">{{ $variant->size }} / {{ $variant->color }}</td>
                                    <td class="p-3 px-6 text-center font-mono font-bold text-slate-900">{{ $converted['cartons'] }} CTN</td>
                                    <td class="p-3 px-6 text-center font-mono text-slate-600">{{ $converted['loose_pairs'] }} PR</td>
                                    <td class="p-3 px-6 text-center font-mono font-bold text-indigo-700">{{ $balance->pairs_quantity }} PR</td>
                                    <td class="p-3 px-6 text-right rtl:text-left font-mono">₨ {{ number_format($balance->avg_unit_cost, 2) }}</td>
                                </tr>
                            @endif
                        @endforeach
                    @endforeach

                    @if(!$hasStock)
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400">
                                No stock recorded for this footwear article at your authorized location(s).
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
