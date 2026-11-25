@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('sales.returns.index') }}" class="text-xs font-semibold text-slate-500 hover:text-indigo-600 transition">
                    {{ __('erp.sales_returns.title') }}
                </a>
                <span class="text-slate-400 text-xs">/</span>
                <a href="{{ route('sales.invoices.show', $salesInvoice) }}" class="text-xs font-semibold text-slate-500 hover:text-indigo-600 transition">
                    {{ $salesInvoice->invoice_number }}
                </a>
                <span class="text-slate-400 text-xs">/</span>
                <span class="text-xs font-bold text-slate-800">Return Items</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 mt-1">
                {{ __('erp.sales_returns.create_return') }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Create a draft sales return against Invoice <strong class="text-slate-700 font-mono">{{ $salesInvoice->invoice_number }}</strong>.
                Drafts do not restock inventory or credit customer khata until posted.
            </p>
        </div>
        <a href="{{ route('sales.invoices.show', $salesInvoice) }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
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

    <form method="POST" action="{{ route('sales.returns.store', $salesInvoice) }}" class="space-y-6" id="returnForm" onsubmit="var btn=document.getElementById('saveDraftBtn'); if(btn){ btn.disabled=true; btn.classList.add('opacity-50','cursor-not-allowed'); }">
        @csrf

        <!-- Invoice & Return Metadata Card -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                    {{ __('erp.sales_returns.return_date') }} <span class="text-rose-500">*</span>
                </label>
                <input type="date" name="return_date" value="{{ old('return_date', now()->toDateString()) }}" required
                       class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                    Sales Invoice
                </label>
                <div class="px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 bg-slate-100 text-slate-800 font-mono font-semibold">
                    {{ $salesInvoice->invoice_number }} (Date: {{ $salesInvoice->invoice_date->format('Y-m-d') }})
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                    Customer & Location
                </label>
                <div class="px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 bg-slate-100 text-slate-800 font-semibold truncate">
                    {{ $salesInvoice->customer->business_name }} • {{ $salesInvoice->location->name }}
                </div>
            </div>

            <div class="sm:col-span-3">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                    {{ __('erp.sales_returns.reason') }} <span class="text-rose-500">*</span>
                </label>
                <input type="text" name="reason" value="{{ old('reason') }}" required
                       placeholder="e.g. Size mismatch, defective stitching, customer returned unsold stock..."
                       class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="sm:col-span-3">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                    Internal Notes / Comments
                </label>
                <input type="text" name="notes" value="{{ old('notes') }}"
                       placeholder="Additional remarks or warehouse receiver notes..."
                       class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
        </div>

        <!-- Items Return Table -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
            <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-slate-900 text-sm">Select Quantities to Return</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Specify returned pairs/cartons. Quantities cannot exceed remaining returnable balance.</p>
                </div>
                <button type="button" id="returnAllBtn" class="text-xs font-bold text-emerald-700 hover:text-emerald-900 bg-emerald-50 hover:bg-emerald-100 px-3 py-1.5 rounded-lg transition border border-emerald-200">
                    Return All Available
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-500 font-bold text-xs uppercase tracking-wider">
                            <th class="py-3 px-4">Item & Variant</th>
                            <th class="py-3 px-4 text-center">Pack</th>
                            <th class="py-3 px-4 text-right">Sold (Pairs)</th>
                            <th class="py-3 px-4 text-right">Available to Return</th>
                            <th class="py-3 px-4 text-right">Price / Pair</th>
                            <th class="py-3 px-4 text-center w-36">Return Cartons</th>
                            <th class="py-3 px-4 text-center w-36">Return Pairs</th>
                            <th class="py-3 px-4 text-center">Condition</th>
                            <th class="py-3 px-4 text-right">Line Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($salesInvoice->items as $idx => $item)
                            @php
                                $remainingPairs = $item->remaining_returnable_pairs;
                                $packRatio = $item->carton_pack_qty > 0 ? $item->carton_pack_qty : 12;
                                $remainingCartons = round($remainingPairs / $packRatio, 2);
                            @endphp
                            <tr class="hover:bg-slate-50/50 transition item-row {{ $remainingPairs <= 0 ? 'opacity-40 bg-slate-50' : '' }}"
                                data-pack="{{ $packRatio }}"
                                data-max-pairs="{{ $remainingPairs }}"
                                data-price="{{ $item->unit_price_per_pair }}">
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-900">
                                        {{ $item->variant->product->name ?? 'Footwear' }}
                                    </div>
                                    <div class="text-xs text-slate-500 font-mono">
                                        {{ $item->variant->sku }} • Size: {{ $item->variant->size }} • Color: {{ $item->variant->color }}
                                    </div>
                                    <input type="hidden" name="items[{{ $idx }}][sales_invoice_item_id]" value="{{ $item->id }}">
                                </td>
                                <td class="py-3 px-4 text-center font-mono text-xs text-slate-600">
                                    {{ $packRatio }} prs
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-medium text-slate-700">
                                    {{ number_format($item->quantity_pairs, 0) }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-slate-800">
                                    <span class="max-pairs-badge">{{ number_format($remainingPairs, 0) }}</span>
                                    <span class="text-xs text-slate-400 block font-normal">({{ $remainingCartons }} ctn)</span>
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-medium text-slate-700">
                                    ₨ {{ number_format($item->unit_price_per_pair, 2) }}
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <input type="number" step="0.0001" min="0" max="{{ $remainingCartons }}"
                                           name="items[{{ $idx }}][returned_cartons]"
                                           value="{{ old("items.{$idx}.returned_cartons", 0) }}"
                                           class="w-28 px-2.5 py-1.5 text-center text-sm font-mono rounded-lg border border-slate-300 bg-white text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 carton-input"
                                           {{ $remainingPairs <= 0 ? 'disabled' : '' }}>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <input type="number" step="1" min="0" max="{{ $remainingPairs }}"
                                           name="items[{{ $idx }}][returned_pairs]"
                                           value="{{ old("items.{$idx}.returned_pairs", 0) }}"
                                           class="w-28 px-2.5 py-1.5 text-center text-sm font-mono font-bold rounded-lg border border-slate-300 bg-white text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 pair-input"
                                           {{ $remainingPairs <= 0 ? 'disabled' : '' }}>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <select name="items[{{ $idx }}][condition]" class="text-xs py-1.5 px-2 bg-slate-50 border border-slate-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                        <option value="resalable" selected>Resalable</option>
                                        <option value="defective">Defective</option>
                                        <option value="damaged">Damaged</option>
                                    </select>
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-slate-900 line-total">
                                    ₨ 0.00
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50 font-bold border-t border-slate-200">
                        <tr>
                            <td colspan="5" class="py-3 px-4 text-right text-xs uppercase tracking-wider text-slate-600">
                                Total Return Summary:
                            </td>
                            <td class="py-3 px-4 text-center font-mono text-sm" id="grandCartons">0.00</td>
                            <td class="py-3 px-4 text-center font-mono text-sm text-indigo-700" id="grandPairs">0</td>
                            <td></td>
                            <td class="py-3 px-4 text-right font-mono text-base text-indigo-700" id="grandTotal">₨ 0.00</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Submit & Actions -->
        <div class="flex items-center justify-between p-4 bg-white rounded-2xl border border-slate-200/80 shadow-xs">
            <span class="text-xs text-slate-500">
                Returns are created in <strong>Draft</strong> status and must be verified before posting.
            </span>
            <div class="flex items-center gap-3">
                <a href="{{ route('sales.invoices.show', $salesInvoice) }}" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-bold transition">
                    Cancel
                </a>
                <button type="submit" id="saveDraftBtn" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition shadow-sm flex items-center gap-2">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    <span>Create Draft Return</span>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const rows = document.querySelectorAll('.item-row');
    const grandCartonsEl = document.getElementById('grandCartons');
    const grandPairsEl = document.getElementById('grandPairs');
    const grandTotalEl = document.getElementById('grandTotal');
    const returnAllBtn = document.getElementById('returnAllBtn');

    function recalculate() {
        let totalCartons = 0;
        let totalPairs = 0;
        let grandTotal = 0;

        rows.forEach(row => {
            const pack = parseFloat(row.dataset.pack) || 12;
            const price = parseFloat(row.dataset.price) || 0;
            const pairInput = row.querySelector('.pair-input');
            const cartonInput = row.querySelector('.carton-input');
            const lineTotalEl = row.querySelector('.line-total');

            if (!pairInput || pairInput.disabled) return;

            const pairs = parseFloat(pairInput.value) || 0;
            const cartons = parseFloat(cartonInput.value) || 0;

            const lineTotal = pairs * price;
            lineTotalEl.textContent = '₨ ' + lineTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            totalPairs += pairs;
            totalCartons += cartons;
            grandTotal += lineTotal;
        });

        grandCartonsEl.textContent = totalCartons.toFixed(2);
        grandPairsEl.textContent = Math.round(totalPairs).toLocaleString('en-US');
        grandTotalEl.textContent = '₨ ' + grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    rows.forEach(row => {
        const pack = parseFloat(row.dataset.pack) || 12;
        const maxPairs = parseFloat(row.dataset.maxPairs) || 0;
        const pairInput = row.querySelector('.pair-input');
        const cartonInput = row.querySelector('.carton-input');

        if (!pairInput || pairInput.disabled) return;

        cartonInput.addEventListener('input', function () {
            let cartons = parseFloat(this.value) || 0;
            let pairs = Math.round(cartons * pack);
            if (pairs > maxPairs) {
                pairs = maxPairs;
                cartons = pairs / pack;
                this.value = cartons.toFixed(2);
            }
            pairInput.value = pairs;
            recalculate();
        });

        pairInput.addEventListener('input', function () {
            let pairs = parseFloat(this.value) || 0;
            if (pairs > maxPairs) {
                pairs = maxPairs;
                this.value = pairs;
            }
            let cartons = pairs / pack;
            cartonInput.value = (cartons % 1 === 0) ? cartons : cartons.toFixed(2);
            recalculate();
        });
    });

    if (returnAllBtn) {
        returnAllBtn.addEventListener('click', function () {
            rows.forEach(row => {
                const pack = parseFloat(row.dataset.pack) || 12;
                const maxPairs = parseFloat(row.dataset.maxPairs) || 0;
                const pairInput = row.querySelector('.pair-input');
                const cartonInput = row.querySelector('.carton-input');

                if (pairInput && !pairInput.disabled && maxPairs > 0) {
                    pairInput.value = maxPairs;
                    let cartons = maxPairs / pack;
                    cartonInput.value = (cartons % 1 === 0) ? cartons : cartons.toFixed(2);
                }
            });
            recalculate();
        });
    }

    recalculate();
});
</script>
@endsection
