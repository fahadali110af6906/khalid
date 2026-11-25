@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('purchases.returns.index') }}" class="text-xs font-semibold text-slate-500 hover:text-indigo-600 transition">
                    {{ __('erp.purchase_returns.title') }}
                </a>
                <span class="text-slate-400 text-xs">/</span>
                <a href="{{ route('purchases.show', $goodsReceipt->purchaseOrder) }}" class="text-xs font-semibold text-slate-500 hover:text-indigo-600 transition">
                    {{ $goodsReceipt->purchaseOrder->purchase_number }}
                </a>
                <span class="text-slate-400 text-xs">/</span>
                <span class="text-xs font-bold text-slate-800">Return Items from {{ $goodsReceipt->receipt_number }}</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 mt-1">
                {{ __('erp.purchase_returns.new_return') }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Create a draft purchase return against Goods Receipt <strong class="text-slate-700 font-mono">{{ $goodsReceipt->receipt_number }}</strong>.
                Drafts do not alter stock or khata until posted.
            </p>
        </div>
        <a href="{{ route('purchases.returns.index') }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
            <i data-lucide="arrow-left" class="w-4 h-4 rtl:rotate-180"></i>
            <span>Cancel</span>
        </a>
    </div>

    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">
            <p class="font-bold flex items-center gap-1.5 mb-2">
                <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-600"></i>
                <span>Please correct the errors below:</span>
            </p>
            <ul class="list-disc list-inside space-y-0.5 text-xs text-rose-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('purchases.receipts.returns.store', $goodsReceipt) }}" class="space-y-6" id="returnForm" onsubmit="var btn=document.getElementById('saveDraftBtn'); if(btn){ btn.disabled=true; btn.classList.add('opacity-50','cursor-not-allowed'); }">
        @csrf

        <!-- GRN & Return Metadata Card -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                    {{ __('erp.purchase_returns.return_date') }} <span class="text-rose-500">*</span>
                </label>
                <input type="date" name="return_date" value="{{ old('return_date', now()->toDateString()) }}" required
                       class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                    Goods Receipt Note (GRN)
                </label>
                <div class="px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 bg-slate-100 text-slate-800 font-mono font-semibold">
                    {{ $goodsReceipt->receipt_number }} (PO: {{ $goodsReceipt->purchaseOrder->purchase_number }})
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                    Supplier & Location
                </label>
                <div class="px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 bg-slate-100 text-slate-800 font-semibold truncate">
                    {{ $goodsReceipt->supplier->business_name }} • {{ $goodsReceipt->location->name }}
                </div>
            </div>

            <div class="sm:col-span-3">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                    {{ __('erp.purchase_returns.reason') }} <span class="text-rose-500">*</span>
                </label>
                <input type="text" name="reason" value="{{ old('reason') }}" required
                       placeholder="e.g. Defective sole bonding, wrong color batch received, leather finish rejected..."
                       class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
        </div>

        <!-- Items Return Table -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
            <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-slate-900 text-sm">Select Quantities to Return</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Specify pairs to return. Cannot exceed remaining returnable quantity from this receipt.</p>
                </div>
                <button type="button" id="returnAllBtn" class="text-xs font-bold text-amber-700 hover:text-amber-900 bg-amber-50 hover:bg-amber-100 px-3 py-1.5 rounded-lg transition border border-amber-200">
                    Return All Available
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left rtl:text-right text-xs">
                    <thead class="bg-slate-50 text-slate-600 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-3">#</th>
                            <th class="py-3 px-3">Article / Variant</th>
                            <th class="py-3 px-3 text-end">GRN Received</th>
                            <th class="py-3 px-3 text-end">Prev. Returned</th>
                            <th class="py-3 px-3 text-end">Returnable</th>
                            <th class="py-3 px-3 text-end w-28">Return Cartons</th>
                            <th class="py-3 px-3 text-end w-28">Return Pairs</th>
                            <th class="py-3 px-3 text-end">Unit Cost (PKR)</th>
                            <th class="py-3 px-3 text-end">Return Value (PKR)</th>
                            <th class="py-3 px-3 w-40">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        @foreach($goodsReceipt->items as $idx => $item)
                            @php
                                $variant = $item->productVariant;
                                $packRatio = $item->orderItem && $item->orderItem->pack_ratio > 0 ? (float) $item->orderItem->pack_ratio : 12;
                                $maxReturnable = (float) $item->remaining_returnable_pairs;
                                $unitCost = (float) $item->unit_cost_per_pair;
                            @endphp
                            <tr class="hover:bg-slate-50/60 transition {{ $maxReturnable <= 0 ? 'opacity-50 bg-slate-50/40' : '' }}" data-row="{{ $idx }}">
                                <td class="py-3 px-3 text-slate-400 font-mono">{{ $idx + 1 }}</td>
                                <td class="py-3 px-3">
                                    <input type="hidden" name="items[{{ $idx }}][goods_receipt_item_id]" value="{{ $item->id }}">
                                    <span class="font-bold text-slate-900 block">{{ $variant->product->name }}</span>
                                    <span class="text-[11px] font-mono text-slate-500">
                                        {{ $variant->sku }} • Size: {{ $variant->size }} • Color: {{ $variant->color }}
                                    </span>
                                    <span class="text-[10px] text-slate-400 font-mono block">Ratio: {{ $packRatio }} prs/ctn</span>
                                </td>
                                <td class="py-3 px-3 text-end font-mono text-slate-600">
                                    {{ number_format($item->received_pairs, 0) }}
                                </td>
                                <td class="py-3 px-3 text-end font-mono text-amber-700">
                                    {{ number_format($item->previously_returned_pairs, 0) }}
                                </td>
                                <td class="py-3 px-3 text-end font-mono font-bold {{ $maxReturnable > 0 ? 'text-emerald-700' : 'text-slate-400' }}">
                                    {{ number_format($maxReturnable, 0) }}
                                </td>
                                <td class="py-3 px-3 text-end">
                                    <input type="number" step="any" min="0"
                                           name="items[{{ $idx }}][return_cartons]"
                                           id="cartons_{{ $idx }}"
                                           data-idx="{{ $idx }}"
                                           data-ratio="{{ $packRatio }}"
                                           data-max="{{ $maxReturnable }}"
                                           data-cost="{{ $unitCost }}"
                                           value="{{ old("items.{$idx}.return_cartons") }}"
                                           {{ $maxReturnable <= 0 ? 'disabled' : '' }}
                                           placeholder="0"
                                           class="carton-input w-24 px-2 py-1.5 text-xs text-end rounded-lg border border-slate-300 font-mono focus:ring-1 focus:ring-indigo-500">
                                </td>
                                <td class="py-3 px-3 text-end">
                                    <input type="number" step="1" min="0" max="{{ $maxReturnable }}"
                                           name="items[{{ $idx }}][return_pairs]"
                                           id="pairs_{{ $idx }}"
                                           data-idx="{{ $idx }}"
                                           data-ratio="{{ $packRatio }}"
                                           data-max="{{ $maxReturnable }}"
                                           data-cost="{{ $unitCost }}"
                                           value="{{ old("items.{$idx}.return_pairs", 0) }}"
                                           {{ $maxReturnable <= 0 ? 'disabled' : '' }}
                                           class="pair-input w-24 px-2 py-1.5 text-xs text-end rounded-lg border border-slate-300 font-mono font-bold text-amber-900 focus:ring-1 focus:ring-indigo-500">
                                </td>
                                <td class="py-3 px-3 text-end font-mono text-slate-600">
                                    {{ number_format($unitCost, 2) }}
                                </td>
                                <td class="py-3 px-3 text-end font-mono font-bold text-slate-900" id="line_total_{{ $idx }}">
                                    0.00
                                </td>
                                <td class="py-3 px-3">
                                    <input type="text"
                                           name="items[{{ $idx }}][notes]"
                                           placeholder="Item defect note..."
                                           value="{{ old("items.{$idx}.notes") }}"
                                           class="w-full px-2 py-1.5 text-xs rounded-lg border border-slate-300">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-slate-300 font-bold font-mono">
                        <tr class="bg-slate-50">
                            <td colspan="6" class="py-3 px-3 text-slate-800 font-sans text-xs">
                                Total Return Summary (Draft)
                            </td>
                            <td class="py-3 px-3 text-end text-amber-700 text-sm" id="grandPairs">
                                0 prs
                            </td>
                            <td class="py-3 px-3 text-end font-sans text-slate-500 text-xs">
                                Total Value:
                            </td>
                            <td class="py-3 px-3 text-end text-slate-900 text-sm" id="grandTotal">
                                PKR 0.00
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('purchases.returns.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 text-sm font-semibold hover:bg-slate-50 transition">
                Discard
            </a>
            <button type="submit" id="saveDraftBtn" class="px-6 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold shadow-sm transition flex items-center gap-2">
                <i data-lucide="save" class="w-4 h-4"></i>
                <span>Save Draft Return</span>
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const pairInputs = document.querySelectorAll('.pair-input');
    const cartonInputs = document.querySelectorAll('.carton-input');
    const returnAllBtn = document.getElementById('returnAllBtn');

    function updateRow(idx) {
        const pairInput = document.getElementById('pairs_' + idx);
        const cartonInput = document.getElementById('cartons_' + idx);
        const lineTotalEl = document.getElementById('line_total_' + idx);
        if (!pairInput || !lineTotalEl) return;

        const max = parseFloat(pairInput.dataset.max) || 0;
        let pairs = parseFloat(pairInput.value) || 0;
        if (pairs < 0) pairs = 0;
        if (pairs > max) pairs = max;

        const cost = parseFloat(pairInput.dataset.cost) || 0;
        const total = pairs * cost;
        lineTotalEl.textContent = total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        updateGrandTotals();
    }

    function updateGrandTotals() {
        let totalPairs = 0;
        let grandCost = 0;

        pairInputs.forEach(input => {
            if (input.disabled) return;
            const pairs = parseFloat(input.value) || 0;
            const cost = parseFloat(input.dataset.cost) || 0;
            totalPairs += pairs;
            grandCost += (pairs * cost);
        });

        document.getElementById('grandPairs').textContent = totalPairs.toLocaleString('en-US') + ' prs';
        document.getElementById('grandTotal').textContent = 'PKR ' + grandCost.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    pairInputs.forEach(input => {
        input.addEventListener('input', function () {
            const idx = this.dataset.idx;
            const ratio = parseFloat(this.dataset.ratio) || 12;
            const cartonInput = document.getElementById('cartons_' + idx);
            const pairs = parseFloat(this.value) || 0;
            if (cartonInput && ratio > 0) {
                cartonInput.value = (pairs / ratio).toFixed(2);
            }
            updateRow(idx);
        });
    });

    cartonInputs.forEach(input => {
        input.addEventListener('input', function () {
            const idx = this.dataset.idx;
            const ratio = parseFloat(this.dataset.ratio) || 12;
            const pairInput = document.getElementById('pairs_' + idx);
            const cartons = parseFloat(this.value) || 0;
            const max = parseFloat(this.dataset.max) || 0;
            if (pairInput) {
                let calcPairs = Math.round(cartons * ratio);
                if (calcPairs > max) calcPairs = max;
                pairInput.value = calcPairs;
            }
            updateRow(idx);
        });
    });

    if (returnAllBtn) {
        returnAllBtn.addEventListener('click', function () {
            pairInputs.forEach(input => {
                if (input.disabled) return;
                const max = parseFloat(input.dataset.max) || 0;
                input.value = max;
                const idx = input.dataset.idx;
                const ratio = parseFloat(input.dataset.ratio) || 12;
                const cartonInput = document.getElementById('cartons_' + idx);
                if (cartonInput && ratio > 0) {
                    cartonInput.value = (max / ratio).toFixed(2);
                }
                updateRow(idx);
            });
        });
    }

    // Initialize initial calculations
    pairInputs.forEach(input => {
        updateRow(input.dataset.idx);
    });
});
</script>
@endsection
