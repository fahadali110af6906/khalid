@extends('layouts.app')

@section('title', __('erp.invoices.create_invoice') . ' — Khalid Traders ERP')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800">
        <div class="flex items-center gap-3">
            <div class="p-2.5 bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 rounded-xl">
                <i data-lucide="receipt" class="w-6 h-6"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('erp.invoices.create_invoice') }}</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('erp.invoices.subtitle') }}</p>
            </div>
        </div>
        <a href="{{ route('sales.invoices.index') }}" class="px-4 py-2 text-sm font-semibold text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 rounded-xl transition-colors">
            {{ __('erp.cancel') }}
        </a>
    </div>

    @if ($errors->any())
        <div class="p-4 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded-xl text-rose-800 dark:text-rose-300 text-sm space-y-1">
            <div class="font-bold flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600 dark:text-rose-400"></i>
                <span>Please correct the errors below:</span>
            </div>
            <ul class="list-disc list-inside ps-4 text-xs space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('sales.invoices.store') }}" id="invoiceForm" class="space-y-6">
        @csrf

        <!-- Top Details: Customer, Location, Dates -->
        <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">
                    {{ __('erp.invoices.customer') }} <span class="text-rose-500">*</span>
                </label>
                <select name="customer_id" required class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">-- Select Customer --</option>
                    @foreach ($customers as $c)
                        <option value="{{ $c->id }}" {{ old('customer_id') == $c->id ? 'selected' : '' }}>
                            {{ $c->business_name }} ({{ $c->customer_code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">
                    {{ __('erp.invoices.location') }} <span class="text-rose-500">*</span>
                </label>
                <select name="location_id" required class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    @foreach ($authorizedLocations as $loc)
                        <option value="{{ $loc->id }}" {{ old('location_id') == $loc->id ? 'selected' : '' }}>
                            {{ $loc->name }} ({{ $loc->code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">
                    {{ __('erp.invoices.invoice_date') }} <span class="text-rose-500">*</span>
                </label>
                <input type="date" name="invoice_date" value="{{ old('invoice_date', now()->toDateString()) }}" required
                       class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">
                    {{ __('erp.invoices.due_date') }}
                </label>
                <input type="date" name="due_date" value="{{ old('due_date') }}"
                       class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
        </div>

        <!-- Line Items Card -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <i data-lucide="boxes" class="w-5 h-5 text-indigo-500"></i>
                    <span>{{ __('erp.invoices.items') }}</span>
                </h2>
                <button type="button" id="addRowBtn" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 rounded-lg transition-colors">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                    <span>Add Item</span>
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-start text-sm text-slate-600 dark:text-slate-300" id="itemsTable">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 text-xs uppercase font-bold tracking-wider border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-4 py-3 text-start min-w-[240px]">{{ __('erp.invoices.product') }} / {{ __('erp.invoices.variant') }}</th>
                            <th class="px-3 py-3 text-start w-32">{{ __('erp.invoices.unit_type') }}</th>
                            <th class="px-3 py-3 text-end w-28">{{ __('erp.invoices.quantity') }}</th>
                            <th class="px-3 py-3 text-end w-32">{{ __('erp.invoices.unit_price') }} (PKR)</th>
                            <th class="px-3 py-3 text-end w-28">{{ __('erp.invoices.discount') }}</th>
                            <th class="px-4 py-3 text-end w-36">{{ __('erp.invoices.line_total') }} (PKR)</th>
                            <th class="px-3 py-3 text-center w-12"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80" id="itemsBody">
                        <tr class="item-row" data-index="0">
                            <td class="p-3">
                                <select name="items[0][product_variant_id]" required class="variant-select w-full px-3 py-2 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="">-- Choose Article / Variant --</option>
                                    @foreach ($products as $p)
                                        <optgroup label="{{ $p->name }} ({{ $p->article_code }}) - {{ $p->carton_pack_qty }} prs/ctn">
                                            @foreach ($p->variants as $v)
                                                <option value="{{ $v->id }}" data-pack="{{ $p->carton_pack_qty }}" data-price="{{ $v->effective_wholesale_rate }}">
                                                    {{ $v->sku }} | Size: {{ $v->size }} | Color: {{ $v->color }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                            </td>
                            <td class="p-3">
                                <select name="items[0][unit_type]" class="unit-select w-full px-2.5 py-2 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white">
                                    <option value="carton">{{ __('erp.invoices.carton') }}</option>
                                    <option value="pair">{{ __('erp.invoices.pair') }}</option>
                                </select>
                            </td>
                            <td class="p-3">
                                <input type="number" step="0.01" min="0.01" name="items[0][quantity]" value="1" required
                                       class="qty-input w-full px-2.5 py-2 text-end text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white font-mono">
                            </td>
                            <td class="p-3">
                                <input type="number" step="0.01" min="0.01" name="items[0][unit_price]" value="0" required
                                       class="price-input w-full px-2.5 py-2 text-end text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white font-mono">
                            </td>
                            <td class="p-3">
                                <input type="number" step="0.01" min="0" name="items[0][line_discount]" value="0"
                                       class="discount-input w-full px-2.5 py-2 text-end text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white font-mono">
                            </td>
                            <td class="p-3 text-end font-mono font-bold text-slate-900 dark:text-white line-total-display">
                                0.00
                            </td>
                            <td class="p-3 text-center">
                                <button type="button" class="remove-row-btn text-slate-400 hover:text-rose-500 transition-colors">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Financial Totals Summary Bar -->
            <div class="p-6 bg-slate-50/70 dark:bg-slate-800/40 border-t border-slate-200 dark:border-slate-800 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div class="space-y-2 w-full md:w-1/2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        Notes / Delivery Instructions
                    </label>
                    <textarea name="notes" rows="2" placeholder="e.g. Bilty number, goods transport name, or delivery remarks..."
                              class="w-full px-3.5 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>

                <div class="w-full md:w-72 space-y-2 text-sm">
                    <div class="flex justify-between text-slate-600 dark:text-slate-400">
                        <span>{{ __('erp.invoices.subtotal') }}:</span>
                        <span class="font-mono font-bold text-slate-900 dark:text-white" id="subtotalDisplay">0.00 PKR</span>
                    </div>
                    <div class="flex justify-between items-center text-slate-600 dark:text-slate-400">
                        <span>{{ __('erp.invoices.discount') }}:</span>
                        <input type="number" step="0.01" min="0" name="discount_amount" id="overallDiscountInput" value="0.00"
                               class="w-24 px-2 py-1 text-end text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 font-mono">
                    </div>
                    <div class="flex justify-between items-center text-slate-600 dark:text-slate-400">
                        <span>{{ __('erp.invoices.tax') }}:</span>
                        <input type="number" step="0.01" min="0" name="tax_amount" id="taxInput" value="0.00"
                               class="w-24 px-2 py-1 text-end text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 font-mono">
                    </div>
                    <div class="border-t border-slate-200 dark:border-slate-700 pt-2 flex justify-between text-base font-bold text-slate-900 dark:text-white">
                        <span>{{ __('erp.invoices.total') }}:</span>
                        <span class="text-indigo-600 dark:text-indigo-400 font-mono" id="grandTotalDisplay">0.00 PKR</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Optional Immediate Payment Section -->
        <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="banknote" class="w-4 h-4 text-emerald-500"></i>
                <span>{{ __('erp.invoices.initial_payment') }} (Optional for Immediate Post)</span>
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">
                        Amount Paid (PKR)
                    </label>
                    <input type="number" step="0.01" min="0" name="initial_payment[amount]" id="initialPaymentAmount" placeholder="0.00"
                           class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white font-mono">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">
                        Payment Method
                    </label>
                    <select name="initial_payment[payment_method]" class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white">
                        <option value="cash">{{ __('erp.cash') }}</option>
                        <option value="bank_transfer">{{ __('erp.bank') }}</option>
                        <option value="cheque">{{ __('erp.cheque') }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">
                        Reference # (e.g. Cheque/Slip #)
                    </label>
                    <input type="text" name="initial_payment[reference_number]" placeholder="Optional ref #"
                           class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white">
                </div>
            </div>
        </div>

        <!-- Submission Buttons -->
        <div class="flex flex-col sm:flex-row items-center justify-end gap-3.5">
            <button type="submit" name="action" value="save_draft" class="w-full sm:w-auto px-5 py-2.5 bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white font-semibold text-sm rounded-xl shadow-sm transition-colors flex items-center justify-center gap-2">
                <i data-lucide="file-text" class="w-4 h-4"></i>
                <span>{{ __('erp.invoices.save_draft') }}</span>
            </button>
            <button type="submit" name="action" value="post_immediately" onclick="return confirm('{{ __('erp.invoices.post_confirmation') }}')" class="w-full sm:w-auto px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm rounded-xl shadow-sm shadow-emerald-600/30 transition-colors flex items-center justify-center gap-2">
                <i data-lucide="check-check" class="w-4 h-4"></i>
                <span>{{ __('erp.invoices.post_now') }}</span>
            </button>
        </div>
    </form>
</div>

<script>
document-addEventListener('DOMContentLoaded', function () {
    let rowIndex = 1;
    const tableBody = document.getElementById('itemsBody');
    const addRowBtn = document.getElementById('addRowBtn');

    function calculateTotals() {
        let subtotal = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
            const price = parseFloat(row.querySelector('.price-input').value) || 0;
            const discount = parseFloat(row.querySelector('.discount-input').value) || 0;
            const lineTotal = Math.max(0, (qty * price) - discount);
            row.querySelector('.line-total-display').textContent = lineTotal.toFixed(2);
            subtotal += lineTotal;
        });

        const overallDiscount = parseFloat(document.getElementById('overallDiscountInput').value) || 0;
        const tax = parseFloat(document.getElementById('taxInput').value) || 0;
        const grandTotal = Math.max(0, subtotal - overallDiscount + tax);

        document.getElementById('subtotalDisplay').textContent = subtotal.toFixed(2) + ' PKR';
        document.getElementById('grandTotalDisplay').textContent = grandTotal.toFixed(2) + ' PKR';
    }

    function attachRowListeners(row) {
        row.querySelectorAll('input, select').forEach(el => {
            el.addEventListener('input', calculateTotals);
            el.addEventListener('change', calculateTotals);
        });

        const variantSelect = row.querySelector('.variant-select');
        variantSelect.addEventListener('change', function () {
            const selected = this.options[this.selectedIndex];
            if (selected && selected.dataset.price) {
                row.querySelector('.price-input').value = selected.dataset.price;
                calculateTotals();
            }
        });

        const removeBtn = row.querySelector('.remove-row-btn');
        removeBtn.addEventListener('click', function () {
            if (document.querySelectorAll('.item-row').length > 1) {
                row.remove();
                calculateTotals();
            } else {
                alert('An invoice must contain at least one line item.');
            }
        });
    }

    document.querySelectorAll('.item-row').forEach(attachRowListeners);

    document.getElementById('overallDiscountInput').addEventListener('input', calculateTotals);
    document.getElementById('taxInput').addEventListener('input', calculateTotals);

    addRowBtn.addEventListener('click', function () {
        const firstRow = document.querySelector('.item-row');
        const newRow = firstRow.cloneNode(true);
        newRow.dataset.index = rowIndex;

        newRow.querySelectorAll('input, select').forEach(input => {
            const name = input.getAttribute('name');
            if (name) {
                input.setAttribute('name', name.replace(/\[\d+\]/, '[' + rowIndex + ']'));
            }
            if (input.classList.contains('qty-input')) input.value = '1';
            else if (input.classList.contains('price-input')) input.value = '0';
            else if (input.classList.contains('discount-input')) input.value = '0';
            else if (input.tagName === 'SELECT' && input.classList.contains('variant-select')) input.selectedIndex = 0;
        });

        tableBody.appendChild(newRow);
        attachRowListeners(newRow);
        if (window.lucide) window.lucide.createIcons();
        rowIndex++;
        calculateTotals();
    });

    calculateTotals();
});
</script>
@endsection
