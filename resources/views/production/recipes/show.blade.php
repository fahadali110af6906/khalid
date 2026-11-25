@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('production.recipes.index') }}" class="p-2 rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-slate-800 transition">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <div class="flex items-center gap-2.5">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $recipe->name }}</h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $recipe->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">
                        {{ $recipe->is_active ? 'Active' : 'Inactive' }}
                    </span>
                    <span class="font-mono text-xs px-2 py-0.5 rounded bg-slate-100 border border-slate-200 text-slate-700">
                        v{{ $recipe->version }}
                    </span>
                </div>
                <p class="text-sm text-slate-500 mt-0.5 font-mono">{{ $recipe->recipe_number }}</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('production.orders.create', ['recipe_id' => $recipe->id]) }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition shadow-sm">
                <i data-lucide="play" class="w-4 h-4"></i>
                <span>Create Production Order</span>
            </a>
        </div>
    </div>

    <!-- Alert Success -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium flex items-center gap-2">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Recipe Overview Grid -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Target Footwear Article</div>
            <div class="mt-2 text-base font-bold text-slate-900">{{ $recipe->product->article_code }}</div>
            <div class="text-xs text-slate-500">{{ $recipe->product->name }}</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Variant Scope</div>
            <div class="mt-2 text-base font-bold text-slate-900">
                @if($recipe->productVariant)
                    {{ $recipe->productVariant->sku }}
                @else
                    All Sizes / Colors
                @endif
            </div>
            <div class="text-xs text-slate-500">
                {{ $recipe->productVariant ? "Size {$recipe->productVariant->size} / {$recipe->productVariant->color}" : 'Universal Master BOM' }}
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Standard Batch Yield</div>
            <div class="mt-2 text-2xl font-bold font-mono text-slate-900">{{ number_format($recipe->yield_pairs, 0) }}</div>
            <div class="text-xs text-slate-500">Pairs per batch</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Created By / Date</div>
            <div class="mt-2 text-sm font-bold text-slate-900">{{ $recipe->creator->name ?? 'System' }}</div>
            <div class="text-xs text-slate-500">{{ $recipe->created_at->format('M d, Y h:i A') }}</div>
        </div>
    </div>

    <!-- Components Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="p-4 border-b border-slate-200/80 flex items-center justify-between">
            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                <i data-lucide="package" class="w-4 h-4 text-indigo-600"></i>
                Raw Material Consumption Formula (Per Single Pair)
            </h2>
            <span class="text-xs text-slate-500 font-mono">{{ $recipe->items->count() }} components</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50/75 border-b border-slate-200/80 text-xs uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-bold">#</th>
                        <th class="px-4 py-3 font-bold">Raw Material Component</th>
                        <th class="px-4 py-3 font-bold text-right">Net Qty / Pair</th>
                        <th class="px-4 py-3 font-bold text-right">Wastage %</th>
                        <th class="px-4 py-3 font-bold text-right">Gross Qty / Pair</th>
                        <th class="px-4 py-3 font-bold text-right">Std Purchase Cost</th>
                        <th class="px-4 py-3 font-bold text-right">Est. Cost / Pair</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @php $totalEstCost = '0.00'; @endphp
                    @foreach($recipe->items as $idx => $item)
                        @php
                            $mat = $item->rawMaterialVariant;
                            $grossQty = $item->gross_quantity_per_pair;
                            $purchaseCost = (float) $mat->purchase_cost;
                            $lineCost = bcmul((string) $grossQty, (string) $purchaseCost, 4);
                            $totalEstCost = bcadd($totalEstCost, $lineCost, 4);
                        @endphp
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-4 py-3 text-xs text-slate-400">{{ $idx + 1 }}</td>
                            <td class="px-4 py-3">
                                <div class="font-bold text-slate-900">{{ $mat->product->name }}</div>
                                <div class="text-xs text-slate-500 font-mono">SKU: {{ $mat->sku }} ({{ $mat->product->baseUnit->code ?? 'unit' }})</div>
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-medium text-slate-800">
                                {{ number_format($item->quantity_per_pair, 4) }} {{ $mat->product->baseUnit->code ?? '' }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-xs">
                                <span class="px-2 py-0.5 rounded {{ (float)$item->wastage_percentage > 0 ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-slate-100 text-slate-600' }}">
                                    +{{ number_format($item->wastage_percentage, 2) }}%
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-indigo-700">
                                {{ number_format($grossQty, 4) }} {{ $mat->product->baseUnit->code ?? '' }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-slate-600">
                                Rs. {{ number_format($purchaseCost, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">
                                Rs. {{ number_format((float) $lineCost, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-slate-50/75 border-t border-slate-200/80 font-bold">
                    <tr>
                        <td colspan="6" class="px-4 py-3.5 text-right text-xs uppercase text-slate-700">
                            Total Estimated Material Cost Per Pair:
                        </td>
                        <td class="px-4 py-3.5 text-right font-mono text-base text-indigo-700">
                            Rs. {{ number_format((float) $totalEstCost, 2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Notes Card -->
    @if($recipe->notes)
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Formula Notes & Specifications</div>
            <p class="text-sm text-slate-700 whitespace-pre-line">{{ $recipe->notes }}</p>
        </div>
    @endif
</div>
@endsection
