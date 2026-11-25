@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.purchases.edit_order') }}</h1>
                <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-200">
                    {{ $purchaseOrder->purchase_number }} (Draft)
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">Modify draft purchase order items, pricing, or delivery parameters.</p>
        </div>
        <a href="{{ route('purchases.show', $purchaseOrder) }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
            <i data-lucide="arrow-left" class="w-4 h-4 rtl:rotate-180"></i>
            <span>Back to PO</span>
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

    <form method="POST" action="{{ route('purchases.update', $purchaseOrder) }}" id="poForm" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- Top Details: Supplier, Location, Dates -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                    Supplier / Vendor <span class="text-rose-500">*</span>
                </label>
                <select name="supplier_id" required class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    @foreach ($suppliers as $s)
                        <option value="{{ $s->id }}" {{ old('supplier_id', $purchaseOrder->supplier_id) == $s->id ? 'selected' : '' }}>
                            {{ $s->business_name }} ({{ $s->supplier_code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                    Receiving Location <span class="text-rose-500">*</span>
                </label>
                <select name="location_id" required class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    @foreach ($authorizedLocations as $loc)
                        <option value="{{ $loc->id }}" {{ old('location_id', $purchaseOrder->location_id) == $loc->id ? 'selected' : '' }}>
                            {{ $loc->type === 'factory' ? '🏭' : '🏪' }} {{ $loc->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                    {{ __('erp.purchases.order_date') }} <span class="text-rose-500">*</span>
                </label>
                <input type="date" name="order_date" value="{{ old('order_date', $purchaseOrder->order_date->toDateString()) }}" required
                       class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                    {{ __('erp.purchases.expected_delivery') }}
                </label>
                <input type="date" name="expected_delivery_date" value="{{ old('expected_delivery_date', $purchaseOrder->expected_delivery_date?->toDateString()) }}"
                       class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
        </div>

        <!-- Line Items Card -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="boxes" class="w-5 h-5 text-indigo-500"></i>
                    <span>Ordered Footwear Articles</span>
                </h2>
                <button type="button" id="addRowBtn" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-indigo-50 text-indigo-600 hover:bg-indigo-100 rounded-lg transition-colors">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                    <span>Add Item</span>
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-start text-sm text-slate-600" id="itemsTable">
                    <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-bold tracking-wider border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 text-start min-w-[240px]">Article / Variant</th>
                            <th class="px-3 py-3 text-start w-32">Packing Unit</th>
                            <th class="px-3 py-3 text-end w-28">Quantity</th>
                            <th class="px-3 py-3 text-end w-32">Unit Cost (PKR)</th>
                            <th class="px-3 py-3 text-end w-28">Discount (PKR)</th>
                            <th class="px-4 py-3 text-end w-36">Line Total (PKR)</th>
                            <th class="px-3 py-3 text-center w-12"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100" id="itemsBody">
                        @foreach($purchaseOrder->items as $idx => $item)
                            <tr class="item-row" data-index="{{ $idx }}">
                                <td class="p-3">
                                    <select name="items[{{ $idx }}][product_variant_id]" required class="variant-select w-full px-3 py-2 text-xs rounded-lg border border-slate-300 bg-white text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        @foreach ($products as $p)
                                            <optgroup label="{{ $p->name }} ({{ $p->article_code }}) - {{ $p->carton_pack_qty }} prs/ctn">
                                                @foreach ($p->variants as $v)
                                                    <option value="{{ $v->id }}" data-pack="{{ $p->carton_pack_qty }}" data-cost="{{ $v->purchase_cost ?? 0 }}" {{ $item->product_variant_id == $v->id ? 'selected' : '' }}>
                                                        {{ $v->sku }} | Size: {{ $v->size }} | Color: {{ $v->color }}
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="p-3">
                                    <select name="items[{{ $idx }}][unit_type]" class="unit-select w-full px-2.5 py-2 text-xs rounded-lg border border-slate-300 bg-white text-slate-900">
                                        <option value="carton" {{ $item->unit_type === 'carton' ? 'selected' : '' }}>Carton</option>
                                        <option value="pair" {{ $item->unit_type === 'pair' ? 'selected' : '' }}>Pair</option>
                                    </select>
                                </td>
                                <td class="p-3">
                                    <input type="number" step="0.01" min="0.01" name="items[{{ $idx }}][quantity]" value="{{ $item->unit_type === 'carton' ? (float)$item->quantity_cartons : (float)$item->ordered_pairs }}" required
                                           class="qty-input w-full px-2.5 py-2 text-end text-xs rounded-lg border border-slate-300 bg-white text-slate-900 font-mono">
                                </td>
                                <td class="p-3">
                                    <input type="number" step="0.01" min="0" name="items[{{ $idx }}][unit_cost]" value="{{ (float)$item->unit_cost }}" required
                                           class="cost-input w-full px-2.5 py-2 text-end text-xs rounded-lg border border-slate-300 bg-white text-slate-900 font-mono">
                                </td>
                                <td class="p-3">
                                    <input type="number" step="0.01" min="0" name="items[{{ $idx }}][line_discount]" value="{{ (float)$item->line_discount }}"
                                           class="discount-input w-full px-2.5 py-2 text-end text-xs rounded-lg border border-slate-300 bg-white text-slate-900 font-mono">
                                </td>
                                <td class="p-3 text-end font-mono font-bold text-slate-900 line-total-display">
                                    {{ number_format($item->line_total, 2) }}
                                </td>
                                <td class="p-3 text-center">
                                    <button type="button" class="remove-row-btn text-slate-400 hover:text-rose-500 transition-colors">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Financial Totals Summary Bar -->
            <div class="p-6 bg-slate-50 border-t border-slate-200 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div class="space-y-2 w-full md:w-1/2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
                        Remarks / Delivery Instructions
                    </label>
                    <textarea name="notes" rows="2"
                              class="w-full px-3.5 py-2 text-xs rounded-xl border border-slate-300 bg-white text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">{{ old('notes', $purchaseOrder->notes) }}</textarea>
                </div>

                <div class="w-full md:w-72 space-y-2 text-sm">
                    <div class="flex justify-between text-slate-600">
                        <span>Items Subtotal:</span>
                        <span class="font-mono font-bold text-slate-900" id="subtotalDisplay">0.00 PKR</span>
                    </div>
                    <div class="flex justify-between items-center text-slate-600">
                        <span>Overall Discount:</span>
                        <input type="number" step="0.01" min="0" name="discount_amount" id="overallDiscountInput" value="{{ old('discount_amount', $purchaseOrder->discount_amount) }}"
                               class="w-24 px-2 py-1 text-end text-xs rounded-lg border border-slate-300 bg-white font-mono">
                    </div>
                    <div class="flex justify-between items-center text-slate-600">
                        <span>Additional Freight/Charges:</span>
                        <input type="number" step="0.01" min="0" name="additional_charges" id="additionalChargesInput" value="{{ old('additional_charges', $purchaseOrder->additional_charges) }}"
                               class="w-24 px-2 py-1 text-end text-xs rounded-lg border border-slate-300 bg-white font-mono">
                    </div>
                    <div class="border-t border-slate-200 pt-2 flex justify-between text-base font-bold text-slate-900">
                        <span>Grand Total:</span>
                        <span class="text-indigo-600 font-mono" id="grandTotalDisplay">0.00 PKR</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('purchases.show', $purchaseOrder) }}" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 text-sm font-semibold hover:bg-slate-50 transition">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition shadow-sm flex items-center gap-2">
                <i data-lucide="check" class="w-4 h-4"></i>
                <span>Update Purchase Order</span>
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let rowIndex = {{ count($purchaseOrder->items) }};
    const itemsBody = document.getElementById('itemsBody');
    const addRowBtn = document.getElementById('addRowBtn');
    const overallDiscountInput = document.getElementById('overallDiscountInput');
    const additionalChargesInput = document.getElementById('additionalChargesInput');
    const subtotalDisplay = document.getElementById('subtotalDisplay');
    const grandTotalDisplay = document.getElementById('grandTotalDisplay');

    function calculateTotals() {
        let subtotal = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('.qty-input')?.value || 0);
            const cost = parseFloat(row.querySelector('.cost-input')?.value || 0);
            const disc = parseFloat(row.querySelector('.discount-input')?.value || 0);
            const lineTotal = Math.max(0, (qty * cost) - disc);
            row.querySelector('.line-total-display').textContent = lineTotal.toFixed(2);
            subtotal += lineTotal;
        });

        const discount = parseFloat(overallDiscountInput?.value || 0);
        const freight = parseFloat(additionalChargesInput?.value || 0);
        const grandTotal = Math.max(0, subtotal - discount + freight);

        if (subtotalDisplay) subtotalDisplay.textContent = subtotal.toFixed(2) + ' PKR';
        if (grandTotalDisplay) grandTotalDisplay.textContent = grandTotal.toFixed(2) + ' PKR';
    }

    itemsBody.addEventListener('input', calculateTotals);
    if (overallDiscountInput) overallDiscountInput.addEventListener('input', calculateTotals);
    if (additionalChargesInput) additionalChargesInput.addEventListener('input', calculateTotals);

    // Remove row
    itemsBody.addEventListener('click', function (e) {
        if (e.target.closest('.remove-row-btn')) {
            const rows = document.querySelectorAll('.item-row');
            if (rows.length > 1) {
                e.target.closest('.item-row').remove();
                calculateTotals();
            } else {
                alert('At least one line item is required.');
            }
        }
    });

    // Add row
    if (addRowBtn) {
        addRowBtn.addEventListener('click', function () {
            const firstRow = document.querySelector('.item-row');
            const newRow = firstRow.cloneNode(true);
            newRow.dataset.index = rowIndex;

            newRow.querySelectorAll('input, select').forEach(input => {
                const name = input.name;
                if (name) {
                    input.name = name.replace(/\[\d+\]/, '[' + rowIndex + ']');
                }
                if (input.classList.contains('qty-input')) input.value = '1';
                else if (input.classList.contains('cost-input')) input.value = '0';
                else if (input.classList.contains('discount-input')) input.value = '0';
            });

            newRow.querySelector('.line-total-display').textContent = '0.00';
            itemsBody.appendChild(newRow);
            if (window.lucide) window.lucide.createIcons();
            rowIndex++;
            calculateTotals();
        });
    }

    calculateTotals();
});
</script>
@endsection
