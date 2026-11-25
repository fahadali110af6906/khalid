@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('purchases.index') }}" class="text-xs font-semibold text-slate-500 hover:text-indigo-600 transition">
                    Purchases
                </a>
                <span class="text-slate-400 text-xs">/</span>
                <a href="{{ route('purchases.show', $purchaseOrder) }}" class="text-xs font-semibold text-slate-500 hover:text-indigo-600 transition">
                    {{ $purchaseOrder->purchase_number }}
                </a>
                <span class="text-slate-400 text-xs">/</span>
                <span class="text-xs font-bold text-slate-800">Receive Goods (GRN)</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 mt-1">
                {{ __('erp.purchases.receive_goods') }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Receive incoming footwear shipment, add stock to <strong class="text-slate-700">{{ $purchaseOrder->location->name }}</strong>, and credit Supplier Khata.
            </p>
        </div>
        <a href="{{ route('purchases.show', $purchaseOrder) }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
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

    <form method="POST" action="{{ route('purchases.receive.store', $purchaseOrder) }}" class="space-y-6" id="receiveGoodsForm" onsubmit="var btn=document.getElementById('receiveSubmitBtn'); if(btn){ btn.disabled=true; btn.classList.add('opacity-50','cursor-not-allowed'); }">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">

        <!-- Shipment Meta Card -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                    {{ __('erp.purchases.receipt_date') }} <span class="text-rose-500">*</span>
                </label>
                <input type="date" name="receipt_date" value="{{ old('receipt_date', now()->toDateString()) }}" required
                       class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                    {{ __('erp.purchases.challan_number') }}
                </label>
                <input type="text" name="challan_number" value="{{ old('challan_number') }}"
                       placeholder="e.g. Bilty # 8742 / Challan 104"
                       class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                    Supplier / Vendor
                </label>
                <div class="px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 bg-slate-100 text-slate-800 font-semibold truncate">
                    {{ $purchaseOrder->supplier->business_name }}
                </div>
            </div>

            <div class="sm:col-span-3">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                    Receiving Inspection / Remarks
                </label>
                <input type="text" name="notes" value="{{ old('notes') }}"
                       placeholder="Inspection notes, carton condition, driver contact..."
                       class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
        </div>

        <!-- Receiving Items Table -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
            <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-slate-900 text-sm">Quantities Received in This Delivery</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Enter number of pairs received for each line item.</p>
                </div>
                <button type="button" id="fillRemainingBtn" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-lg transition">
                    Fill All Remaining
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left rtl:text-right text-xs">
                    <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500 font-bold border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-4">Article / Variant</th>
                            <th class="py-3 px-4 text-end">Ordered (Pairs)</th>
                            <th class="py-3 px-4 text-end text-emerald-700">Previously Recv</th>
                            <th class="py-3 px-4 text-end text-amber-700">Remaining (Pairs)</th>
                            <th class="py-3 px-4 text-end w-44">Receiving Now (Pairs)</th>
                            <th class="py-3 px-4 text-end">Rate / Pair</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium font-mono">
                        @foreach($purchaseOrder->items as $idx => $item)
                            @php
                                $variant = $item->productVariant;
                                $remPairs = (float) max(0, $item->ordered_pairs - $item->received_pairs);
                            @endphp
                            <tr class="hover:bg-slate-50 transition-colors {{ $remPairs <= 0 ? 'opacity-50 bg-slate-50/50' : '' }}">
                                <td class="py-3 px-4 font-sans">
                                    <div class="font-bold text-slate-900">{{ $variant->product->name }}</div>
                                    <div class="text-[11px] text-slate-500 font-mono">
                                        {{ $variant->sku }} • Size: {{ $variant->size }} • Color: {{ $variant->color }}
                                    </div>
                                    <input type="hidden" name="items[{{ $idx }}][purchase_order_item_id]" value="{{ $item->id }}">
                                </td>
                                <td class="py-3 px-4 text-end text-slate-700">
                                    {{ number_format($item->ordered_pairs, 0) }}
                                </td>
                                <td class="py-3 px-4 text-end text-emerald-600 font-bold">
                                    {{ number_format($item->received_pairs, 0) }}
                                </td>
                                <td class="py-3 px-4 text-end text-amber-600 font-bold remaining-pairs-val" data-rem="{{ $remPairs }}">
                                    {{ number_format($remPairs, 0) }}
                                </td>
                                <td class="py-3 px-4 text-end">
                                    <input type="number" step="0.0001" min="0" max="{{ $remPairs }}"
                                           name="items[{{ $idx }}][received_pairs]"
                                           value="{{ $remPairs > 0 ? $remPairs : 0 }}"
                                           {{ $remPairs <= 0 ? 'readonly' : '' }}
                                           class="receive-qty-input w-36 px-3 py-1.5 text-end text-xs font-bold rounded-lg border border-slate-300 bg-white text-slate-900 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                                </td>
                                <td class="py-3 px-4 text-end text-slate-600 font-sans">
                                    PKR {{ number_format($item->unit_cost_per_pair, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('purchases.show', $purchaseOrder) }}" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 text-sm font-semibold hover:bg-slate-50 transition">
                Cancel
            </a>
            <button type="submit" id="receiveSubmitBtn" class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold transition shadow-sm flex items-center gap-2">
                <i data-lucide="check" class="w-4 h-4"></i>
                <span>Post Goods Receipt (GRN)</span>
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const fillBtn = document.getElementById('fillRemainingBtn');
    if (fillBtn) {
        fillBtn.addEventListener('click', function () {
            document.querySelectorAll('tr').forEach(row => {
                const remEl = row.querySelector('.remaining-pairs-val');
                const input = row.querySelector('.receive-qty-input');
                if (remEl && input && !input.readOnly) {
                    const rem = parseFloat(remEl.dataset.rem || 0);
                    input.value = rem;
                }
            });
        });
    }
});
</script>
@endsection
