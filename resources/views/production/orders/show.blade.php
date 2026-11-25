@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Top Bar Navigation & Actions -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('production.orders.index') }}" class="p-2 rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-slate-800 transition">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <div class="flex items-center gap-2.5">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 font-mono">{{ $productionOrder->order_number }}</h1>
                    @php
                        $statusBadge = match($productionOrder->status) {
                            'draft' => 'bg-slate-100 text-slate-700 border-slate-200',
                            'planned' => 'bg-blue-50 text-blue-700 border-blue-200',
                            'in_progress' => 'bg-amber-50 text-amber-800 border-amber-200',
                            'completed' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                            'cancelled' => 'bg-rose-50 text-rose-700 border-rose-200',
                            default => 'bg-slate-100 text-slate-700'
                        };
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $statusBadge }}">
                        {{ ucfirst(str_replace('_', ' ', $productionOrder->status)) }}
                    </span>
                    <span class="text-xs px-2 py-0.5 rounded bg-slate-100 border border-slate-200 text-slate-700 font-mono">
                        {{ $productionOrder->factoryLocation->code }} — {{ $productionOrder->factoryLocation->name }}
                    </span>
                </div>
                <p class="text-sm text-slate-500 mt-0.5">
                    {{ $productionOrder->product->article_code }} — {{ $productionOrder->product->name }} | SKU: {{ $productionOrder->productVariant->sku }} (Size {{ $productionOrder->productVariant->size }}/{{ $productionOrder->productVariant->color }})
                </p>
            </div>
        </div>

        <!-- Workflow Action Buttons -->
        <div class="flex flex-wrap items-center gap-2">
            @if(in_array($productionOrder->status, ['draft', 'planned']))
                <form method="POST" action="{{ route('production.orders.start', $productionOrder) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold shadow-sm transition flex items-center gap-1.5">
                        <i data-lucide="play" class="w-4 h-4"></i>
                        <span>{{ __('erp.production.start_order') }}</span>
                    </button>
                </form>
            @endif

            @if(in_array($productionOrder->status, ['planned', 'in_progress']))
                <button type="button" onclick="openIssueModal()" class="px-3.5 py-2 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-700 text-sm font-bold border border-blue-200 transition flex items-center gap-1.5">
                    <i data-lucide="arrow-down-right" class="w-4 h-4"></i>
                    <span>{{ __('erp.production.issue_materials') }}</span>
                </button>

                @if((float)$productionOrder->actual_material_cost > 0)
                    <button type="button" onclick="openReturnModal()" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-bold border border-slate-300 transition flex items-center gap-1.5">
                        <i data-lucide="arrow-up-left" class="w-4 h-4"></i>
                        <span>{{ __('erp.production.return_materials') }}</span>
                    </button>
                @endif
            @endif

            @if($productionOrder->status === 'in_progress')
                <button type="button" onclick="openCompleteModal()" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold shadow-sm transition flex items-center gap-1.5">
                    <i data-lucide="check-circle" class="w-4 h-4"></i>
                    <span>{{ __('erp.production.complete_order') }}</span>
                </button>
            @endif

            @if(in_array($productionOrder->status, ['draft', 'planned', 'in_progress']))
                <button type="button" onclick="openCancelModal()" class="px-3.5 py-2 rounded-xl bg-white hover:bg-rose-50 text-rose-600 text-sm font-semibold border border-rose-200 transition flex items-center gap-1.5">
                    <i data-lucide="x" class="w-4 h-4"></i>
                    <span>{{ __('erp.production.cancel_order') }}</span>
                </button>
            @endif
        </div>
    </div>

    <!-- Alert Success / Error -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium flex items-center gap-2">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">
            <div class="font-bold mb-1 flex items-center gap-1.5">
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600"></i>
                Action Error:
            </div>
            <ul class="list-disc list-inside space-y-0.5 text-xs">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Key Performance Indicators (KPIs) -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">{{ __('erp.production.planned_quantity') }}</div>
            <div class="mt-1 text-2xl font-bold font-mono text-slate-900">{{ number_format($productionOrder->planned_quantity_pairs, 0) }}</div>
            <div class="text-xs text-slate-500">Pairs</div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">{{ __('erp.production.completed_good_pairs') }}</div>
            <div class="mt-1 text-2xl font-bold font-mono text-emerald-600">{{ number_format($productionOrder->completed_good_pairs, 0) }}</div>
            <div class="text-xs text-slate-500">Good saleable pairs</div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">{{ __('erp.production.wastage_pairs') }}</div>
            <div class="mt-1 text-2xl font-bold font-mono {{ (float)$productionOrder->wastage_pairs > 0 ? 'text-rose-600' : 'text-slate-400' }}">
                {{ number_format($productionOrder->wastage_pairs, 0) }}
            </div>
            <div class="text-xs text-slate-500">Defects / scrap pairs</div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">{{ __('erp.production.actual_material_cost') }}</div>
            <div class="mt-1 text-lg font-bold font-mono text-slate-900">Rs. {{ number_format($productionOrder->actual_material_cost, 2) }}</div>
            <div class="text-xs text-slate-400">Net raw materials</div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">{{ __('erp.production.total_production_cost') }}</div>
            <div class="mt-1 text-lg font-bold font-mono text-indigo-700">Rs. {{ number_format($productionOrder->total_production_cost, 2) }}</div>
            <div class="text-xs text-slate-400">Material + Labor + Overhead</div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">{{ __('erp.production.unit_cost_per_pair') }}</div>
            <div class="mt-1 text-lg font-bold font-mono text-emerald-700">
                @if((float)$productionOrder->unit_cost_per_pair > 0)
                    Rs. {{ number_format($productionOrder->unit_cost_per_pair, 2) }}
                @else
                    —
                @endif
            </div>
            <div class="text-xs text-slate-400">WAC on completion</div>
        </div>
    </div>

    <!-- Live Costing Analysis & Variance Card -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                <i data-lucide="calculator" class="w-4 h-4 text-indigo-600"></i>
                Batch Costing Breakdown & Variance Analysis
            </h2>
            <span class="text-xs font-mono text-slate-500">Formula: Total Cost ÷ Good Pairs</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                <div class="text-xs text-slate-500 font-semibold uppercase">Planned Material Cost</div>
                <div class="text-lg font-bold font-mono text-slate-800 mt-1">Rs. {{ number_format($costing['planned_material_cost'], 2) }}</div>
                <div class="text-xs text-slate-400 mt-1">Based on BOM Recipe standard rates</div>
            </div>

            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                <div class="text-xs text-slate-500 font-semibold uppercase">Material Variance</div>
                @php $matVar = (float)$costing['variance_material_cost']; @endphp
                <div class="text-lg font-bold font-mono mt-1 {{ $matVar > 0 ? 'text-rose-600' : ($matVar < 0 ? 'text-emerald-600' : 'text-slate-700') }}">
                    {{ $matVar > 0 ? '+' : '' }}Rs. {{ number_format($matVar, 2) }}
                </div>
                <div class="text-xs text-slate-400 mt-1">{{ $matVar > 0 ? 'Cost Overrun' : ($matVar < 0 ? 'Favorable Savings' : 'On Target') }}</div>
            </div>

            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                <div class="text-xs text-slate-500 font-semibold uppercase">Scrap / Wastage Rate</div>
                <div class="text-lg font-bold font-mono text-slate-800 mt-1">{{ number_format($costing['scrap_rate_percentage'], 2) }}%</div>
                <div class="text-xs text-slate-400 mt-1">{{ number_format($costing['actual_wastage_pairs'], 0) }} pairs absorbed in batch cost</div>
            </div>

            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                <div class="text-xs text-slate-500 font-semibold uppercase">Estimated Scrap Cost</div>
                <div class="text-lg font-bold font-mono text-rose-600 mt-1">Rs. {{ number_format($costing['scrap_cost'], 2) }}</div>
                <div class="text-xs text-slate-400 mt-1">Defective pairs excluded from stock</div>
            </div>
        </div>
    </div>

    <!-- BOM Recipe Components Comparison -->
    @if($productionOrder->recipe)
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
            <div class="p-4 border-b border-slate-200/80 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <i data-lucide="layers" class="w-4 h-4 text-indigo-600"></i>
                        Bill of Materials Recipe: {{ $productionOrder->recipe->name }} (v{{ $productionOrder->recipe->version }})
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Component requirements calculated for {{ number_format($productionOrder->planned_quantity_pairs, 0) }} planned pairs</p>
                </div>
                <a href="{{ route('production.recipes.show', $productionOrder->recipe) }}" class="text-xs font-bold text-indigo-600 hover:underline">
                    View Recipe Details &rarr;
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50/75 border-b border-slate-200/80 text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-bold">Raw Material Component</th>
                            <th class="px-4 py-3 font-bold text-right">Qty / Pair (Gross)</th>
                            <th class="px-4 py-3 font-bold text-right">Required for Order</th>
                            <th class="px-4 py-3 font-bold text-right">Std Purchase Cost</th>
                            <th class="px-4 py-3 font-bold text-right">Est. Budget (PKR)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($productionOrder->recipe->items as $item)
                            @php
                                $mat = $item->rawMaterialVariant;
                                $grossPerPair = $item->gross_quantity_per_pair;
                                $totalReq = bcmul((string) $grossPerPair, (string) $productionOrder->planned_quantity_pairs, 4);
                                $unitCost = (float) $mat->purchase_cost;
                                $budget = bcmul((string) $totalReq, (string) $unitCost, 2);
                            @endphp
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-900">{{ $mat->product->name }}</div>
                                    <div class="text-xs text-slate-500 font-mono">{{ $mat->sku }} ({{ $mat->product->baseUnit->code ?? 'unit' }})</div>
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-medium text-slate-800">
                                    {{ number_format($grossPerPair, 4) }} {{ $mat->product->baseUnit->code ?? '' }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-indigo-700">
                                    {{ number_format((float) $totalReq, 2) }} {{ $mat->product->baseUnit->code ?? '' }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-slate-600">
                                    Rs. {{ number_format($unitCost, 2) }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">
                                    Rs. {{ number_format((float) $budget, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Material Issues & Returns Log -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="p-4 border-b border-slate-200/80 flex items-center justify-between">
            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                <i data-lucide="package-minus" class="w-4 h-4 text-indigo-600"></i>
                {{ __('erp.production.material_issues') }}
            </h2>
            <span class="text-xs text-slate-500 font-mono">{{ $productionOrder->materialIssues->count() }} vouchers</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50/75 border-b border-slate-200/80 text-xs uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-bold">Voucher #</th>
                        <th class="px-4 py-3 font-bold">Type</th>
                        <th class="px-4 py-3 font-bold">Date & Issuer</th>
                        <th class="px-4 py-3 font-bold">Items Details</th>
                        <th class="px-4 py-3 font-bold text-right">Total Material Cost</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($productionOrder->materialIssues as $issue)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-4 py-3 font-mono font-bold text-slate-900">
                                {{ $issue->issue_number }}
                            </td>
                            <td class="px-4 py-3">
                                @if($issue->type === 'return')
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                        Return to Stock
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                                        Issue to Floor
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <div class="font-bold text-slate-800">{{ $issue->issue_date->format('M d, Y') }}</div>
                                <div class="text-slate-400">By: {{ $issue->creator->name ?? 'System' }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <ul class="space-y-1">
                                    @foreach($issue->items as $itm)
                                        <li class="flex items-center gap-2">
                                            <span class="font-mono font-bold text-slate-800">{{ number_format($itm->quantity, 2) }} {{ $itm->rawMaterialVariant->product->baseUnit->code ?? '' }}</span>
                                            <span class="text-slate-600">{{ $itm->rawMaterialVariant->product->name }}</span>
                                            <span class="text-slate-400">(@ Rs. {{ number_format($itm->unit_cost, 2) }})</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold {{ $issue->type === 'return' ? 'text-emerald-700' : 'text-slate-900' }}">
                                {{ $issue->type === 'return' ? '-' : '+' }}Rs. {{ number_format($issue->total_cost, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-slate-400 text-sm">
                                No raw materials have been issued or returned yet for this order.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Finished Goods Output & Completions Log -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="p-4 border-b border-slate-200/80 flex items-center justify-between">
            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                <i data-lucide="package-check" class="w-4 h-4 text-emerald-600"></i>
                {{ __('erp.production.completion_records') }}
            </h2>
            <span class="text-xs text-slate-500 font-mono">{{ $productionOrder->completions->count() }} batches</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50/75 border-b border-slate-200/80 text-xs uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-bold">Completion #</th>
                        <th class="px-4 py-3 font-bold">Date & Operator</th>
                        <th class="px-4 py-3 font-bold text-right">Good Pairs (To Stock)</th>
                        <th class="px-4 py-3 font-bold text-right">Wastage Pairs</th>
                        <th class="px-4 py-3 font-bold text-right">Labor Cost</th>
                        <th class="px-4 py-3 font-bold text-right">Other Costs</th>
                        <th class="px-4 py-3 font-bold text-right">Batch Total Cost</th>
                        <th class="px-4 py-3 font-bold text-right">Calculated Unit Cost / Pair</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($productionOrder->completions as $cmp)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-4 py-3 font-mono font-bold text-slate-900">
                                {{ $cmp->completion_number }}
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <div class="font-bold text-slate-800">{{ $cmp->completion_date->format('M d, Y') }}</div>
                                <div class="text-slate-400">By: {{ $cmp->creator->name ?? 'System' }}</div>
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-emerald-600">
                                {{ number_format($cmp->good_pairs, 0) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-rose-600 font-bold">
                                {{ number_format($cmp->wastage_pairs, 0) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-slate-700">
                                Rs. {{ number_format($cmp->labor_cost, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-slate-700">
                                Rs. {{ number_format($cmp->other_costs, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-indigo-700">
                                Rs. {{ number_format($cmp->total_batch_cost, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">
                                Rs. {{ number_format($cmp->unit_cost_per_pair, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-slate-400 text-sm">
                                No finished goods output has been completed yet for this order.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal 1: Issue Materials -->
<div id="issueModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                <i data-lucide="arrow-down-right" class="w-5 h-5 text-indigo-600"></i>
                Issue Raw Materials to Factory Floor
            </h3>
            <button type="button" onclick="closeIssueModal()" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('production.orders.issue-materials', $productionOrder) }}" class="space-y-4">
            @csrf
            <p class="text-xs text-slate-500">
                Deducts stock from <strong class="text-slate-800">{{ $productionOrder->factoryLocation->name }}</strong> and increments production order actual material cost.
            </p>

            <div class="space-y-3" id="issueItemsContainer">
                @if($productionOrder->recipe)
                    @foreach($productionOrder->recipe->items as $idx => $rItem)
                        @php
                            $mat = $rItem->rawMaterialVariant;
                            $remPairs = max(0, (float)$productionOrder->planned_quantity_pairs - (float)$productionOrder->completed_good_pairs);
                            $suggested = bcmul((string)$rItem->gross_quantity_per_pair, (string)$remPairs, 2);
                        @endphp
                        <div class="grid grid-cols-12 gap-2 items-center bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                            <div class="col-span-7">
                                <input type="hidden" name="items[{{ $idx }}][raw_material_variant_id]" value="{{ $mat->id }}">
                                <div class="text-sm font-bold text-slate-900">{{ $mat->product->name }}</div>
                                <div class="text-xs text-slate-500 font-mono">{{ $mat->sku }} ({{ $mat->product->baseUnit->code ?? 'unit' }})</div>
                            </div>
                            <div class="col-span-5">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase">Issue Qty ({{ $mat->product->baseUnit->code ?? '' }})</label>
                                <input type="number" step="0.0001" min="0" name="items[{{ $idx }}][quantity]" value="{{ $suggested }}"
                                       class="w-full py-1.5 px-2.5 text-sm bg-white border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-right font-mono font-bold">
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>

            <div>
                <label for="issue_notes" class="block text-xs font-bold text-slate-600 uppercase mb-1">Issue Voucher Notes</label>
                <input type="text" id="issue_notes" name="notes" placeholder="Batch requisition reference..."
                       class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeIssueModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl">
                    {{ __('erp.cancel') }}
                </button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl shadow-sm">
                    Confirm Material Issue
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Return Materials -->
<div id="returnModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                <i data-lucide="arrow-up-left" class="w-5 h-5 text-emerald-600"></i>
                Return Unused Raw Materials to Stock
            </h3>
            <button type="button" onclick="closeReturnModal()" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('production.orders.return-materials', $productionOrder) }}" class="space-y-4">
            @csrf
            <p class="text-xs text-slate-500">
                Restores unused materials into <strong class="text-slate-800">{{ $productionOrder->factoryLocation->name }}</strong> inventory and reduces actual material cost.
            </p>

            <div class="space-y-3" id="returnItemsContainer">
                @if($productionOrder->recipe)
                    @foreach($productionOrder->recipe->items as $idx => $rItem)
                        @php $mat = $rItem->rawMaterialVariant; @endphp
                        <div class="grid grid-cols-12 gap-2 items-center bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                            <div class="col-span-7">
                                <input type="hidden" name="items[{{ $idx }}][raw_material_variant_id]" value="{{ $mat->id }}">
                                <div class="text-sm font-bold text-slate-900">{{ $mat->product->name }}</div>
                                <div class="text-xs text-slate-500 font-mono">{{ $mat->sku }} ({{ $mat->product->baseUnit->code ?? 'unit' }})</div>
                            </div>
                            <div class="col-span-5">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase">Return Qty</label>
                                <input type="number" step="0.0001" min="0" name="items[{{ $idx }}][quantity]" value="0"
                                       class="w-full py-1.5 px-2.5 text-sm bg-white border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-right font-mono font-bold">
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>

            <div>
                <label for="return_notes" class="block text-xs font-bold text-slate-600 uppercase mb-1">Return Notes</label>
                <input type="text" id="return_notes" name="notes" placeholder="Leftover batch materials returned..."
                       class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeReturnModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl">
                    {{ __('erp.cancel') }}
                </button>
                <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl shadow-sm">
                    Confirm Material Return
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 3: Complete Production Order -->
<div id="completeModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                <i data-lucide="check-circle" class="w-5 h-5 text-emerald-600"></i>
                Complete Production Output & Add to Stock
            </h3>
            <button type="button" onclick="closeCompleteModal()" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('production.orders.complete', $productionOrder) }}" class="space-y-4">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

            <div class="bg-emerald-50 p-3 rounded-xl border border-emerald-200 text-xs text-emerald-800">
                Good pairs will be added to finished stock at <strong class="font-bold">{{ $productionOrder->factoryLocation->name }}</strong> with newly calculated unit cost. Wastage pairs are logged for scrap audit and <em>never</em> added to inventory.
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="good_pairs" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.production.completed_good_pairs') }} *</label>
                    <input type="number" step="1" min="0" id="good_pairs" name="good_pairs" required
                           value="{{ max(0, (float)$productionOrder->planned_quantity_pairs - (float)$productionOrder->completed_good_pairs) }}"
                           class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 font-mono font-bold text-right">
                </div>

                <div>
                    <label for="wastage_pairs" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.production.wastage_pairs') }}</label>
                    <input type="number" step="1" min="0" id="wastage_pairs" name="wastage_pairs" value="0"
                           class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-rose-500 font-mono font-bold text-right text-rose-600">
                </div>

                <div>
                    <label for="labor_cost" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.production.labor_cost') }} (PKR)</label>
                    <input type="number" step="0.01" min="0" id="labor_cost" name="labor_cost" value="0"
                           class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 font-mono font-bold text-right">
                </div>

                <div>
                    <label for="other_costs" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.production.other_costs') }} (PKR)</label>
                    <input type="number" step="0.01" min="0" id="other_costs" name="other_costs" value="0"
                           class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 font-mono font-bold text-right">
                </div>

                <div>
                    <label for="completion_date" class="block text-xs font-bold text-slate-700 uppercase mb-1">Completion Date</label>
                    <input type="date" id="completion_date" name="completion_date" value="{{ now()->toDateString() }}"
                           class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 font-mono">
                </div>

                <div class="flex items-center pt-5">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="mark_completed" value="1" checked class="w-4 h-4 text-emerald-600 rounded">
                        <span class="text-xs font-bold text-slate-800">Close Order as Completed</span>
                    </label>
                </div>

                <div class="col-span-2">
                    <label for="comp_notes" class="block text-xs font-bold text-slate-600 uppercase mb-1">Notes / Inspection Stamp</label>
                    <input type="text" id="comp_notes" name="notes" placeholder="QC passed, batch stamped..."
                           class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeCompleteModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl">
                    {{ __('erp.cancel') }}
                </button>
                <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl shadow-sm">
                    Confirm Completion & Add to Stock
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 4: Cancel Order -->
<div id="cancelModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-lg font-bold text-rose-700 flex items-center gap-2">
                <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-600"></i>
                Cancel Production Order
            </h3>
            <button type="button" onclick="closeCancelModal()" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('production.orders.cancel', $productionOrder) }}" class="space-y-4">
            @csrf
            <p class="text-xs text-slate-600">
                Are you sure you want to cancel Order <strong class="font-mono text-slate-900">{{ $productionOrder->order_number }}</strong>? This action cannot be reversed.
            </p>

            <div>
                <label for="cancel_reason" class="block text-xs font-bold text-slate-700 uppercase mb-1">Cancellation Reason *</label>
                <textarea id="cancel_reason" name="reason" required rows="2" placeholder="State reason for cancellation..."
                          class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-rose-500"></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeCancelModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl">
                    Back
                </button>
                <button type="submit" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white text-sm font-bold rounded-xl shadow-sm">
                    Confirm Cancellation
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function openIssueModal() { document.getElementById('issueModal').classList.remove('hidden'); }
    function closeIssueModal() { document.getElementById('issueModal').classList.add('hidden'); }
    function openReturnModal() { document.getElementById('returnModal').classList.remove('hidden'); }
    function closeReturnModal() { document.getElementById('returnModal').classList.add('hidden'); }
    function openCompleteModal() { document.getElementById('completeModal').classList.remove('hidden'); }
    function closeCompleteModal() { document.getElementById('completeModal').classList.add('hidden'); }
    function openCancelModal() { document.getElementById('cancelModal').classList.remove('hidden'); }
    function closeCancelModal() { document.getElementById('cancelModal').classList.add('hidden'); }
</script>
@endpush
@endsection
