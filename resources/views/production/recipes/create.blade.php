@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('production.recipes.index') }}" class="p-2 rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-slate-800 transition">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.recipes.new_recipe') }}</h1>
                <p class="text-sm text-slate-500 mt-0.5">{{ __('erp.recipes.subtitle') }}</p>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">
            <div class="font-bold mb-1 flex items-center gap-1.5">
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600"></i>
                Please correct the following errors:
            </div>
            <ul class="list-disc list-inside space-y-0.5 text-xs">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('production.recipes.store') }}" id="recipeForm" class="space-y-6">
        @csrf

        <!-- Recipe Details Card -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-5">
            <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3 flex items-center gap-2">
                <i data-lucide="layers" class="w-4 h-4 text-indigo-600"></i>
                Recipe Header Information
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label for="name" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.recipes.recipe_name') }} *</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required
                           placeholder="e.g., Peshawari Chappal Classic Leather BOM"
                           class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label for="version" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.recipes.version') }}</label>
                    <input type="text" id="version" name="version" value="{{ old('version', '1.0') }}"
                           placeholder="1.0"
                           class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label for="product_id" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.production.target_product') }} *</label>
                    <select id="product_id" name="product_id" required
                            class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                        <option value="">Select Finished Footwear</option>
                        @foreach($finishedProducts as $prod)
                            <option value="{{ $prod->id }}" {{ old('product_id') == $prod->id ? 'selected' : '' }}>
                                {{ $prod->article_code }} — {{ $prod->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="product_variant_id" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.production.target_variant') }} (Optional)</label>
                    <select id="product_variant_id" name="product_variant_id"
                            class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                        <option value="">All Variants (Master Product Recipe)</option>
                    </select>
                </div>

                <div>
                    <label for="yield_pairs" class="block text-xs font-bold text-slate-700 uppercase mb-1">Standard Batch Yield (Pairs)</label>
                    <input type="number" step="1" min="1" id="yield_pairs" name="yield_pairs" value="{{ old('yield_pairs', 1) }}"
                           class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                </div>

                <div class="md:col-span-3">
                    <label for="notes" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.recipes.notes') }}</label>
                    <textarea id="notes" name="notes" rows="2" placeholder="Standard specifications, supplier references, stitching guides..."
                              class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>

        <!-- Recipe Items (BOM Components) Card -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="package" class="w-4 h-4 text-indigo-600"></i>
                    {{ __('erp.recipes.components') }} (Per Single Finished Pair)
                </h2>
                <button type="button" id="addRowBtn" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold transition">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                    Add Raw Material
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm" id="itemsTable">
                    <thead class="bg-slate-50/75 border-b border-slate-200/80 text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-3 py-2.5 font-bold" style="width: 45%;">Raw Material / Component *</th>
                            <th class="px-3 py-2.5 font-bold" style="width: 20%;">Qty / Pair *</th>
                            <th class="px-3 py-2.5 font-bold" style="width: 15%;">Wastage %</th>
                            <th class="px-3 py-2.5 font-bold" style="width: 15%;">Est. Cost (PKR)</th>
                            <th class="px-3 py-2.5 text-center" style="width: 5%;"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100" id="itemsTbody">
                        <!-- Initial Row -->
                        <tr class="item-row">
                            <td class="px-3 py-2">
                                <select name="items[0][raw_material_variant_id]" required
                                        class="item-select w-full py-2 px-2.5 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                                    <option value="">Select Raw Material</option>
                                    @foreach($rawMaterials as $mat)
                                        <option value="{{ $mat->id }}" data-cost="{{ $mat->purchase_cost }}" data-unit="{{ $mat->product->baseUnit->code ?? '' }}">
                                            {{ $mat->product->name }} — {{ $mat->sku }} ({{ $mat->product->baseUnit->code ?? 'unit' }}) - Cost: Rs. {{ number_format($mat->purchase_cost, 2) }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-1">
                                    <input type="number" step="0.0001" min="0.0001" name="items[0][quantity_per_pair]" required value="1"
                                           class="qty-input w-full py-2 px-2.5 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none text-right">
                                    <span class="unit-label text-xs text-slate-400 font-mono w-10"></span>
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" step="0.1" min="0" max="100" name="items[0][wastage_percentage]" value="2.5"
                                       class="wastage-input w-full py-2 px-2.5 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none text-right">
                            </td>
                            <td class="px-3 py-2 text-right font-mono font-semibold text-slate-700 line-cost">
                                Rs. 0.00
                            </td>
                            <td class="px-3 py-2 text-center">
                                <button type="button" class="remove-row-btn p-1 text-slate-400 hover:text-rose-600 rounded transition">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-slate-50/50 border-t border-slate-200/80">
                        <tr>
                            <td colspan="3" class="px-3 py-3 text-right font-bold text-slate-700 text-xs uppercase">
                                Estimated Total Material Cost Per Pair:
                            </td>
                            <td class="px-3 py-3 text-right font-mono font-bold text-indigo-700 text-base" id="totalEstimatedCost">
                                Rs. 0.00
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4">
            <a href="{{ route('production.recipes.index') }}" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl transition">
                {{ __('erp.cancel') }}
            </a>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl shadow-sm transition flex items-center gap-2">
                <i data-lucide="check" class="w-4 h-4"></i>
                Save Recipe BOM
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        let rowIdx = 1;
        const tbody = document.getElementById('itemsTbody');
        const addBtn = document.getElementById('addRowBtn');

        // Dynamic Product Variant Loader
        const productSelect = document.getElementById('product_id');
        const variantSelect = document.getElementById('product_variant_id');
        const productsData = @json($finishedProducts->keyBy('id'));

        productSelect.addEventListener('change', function () {
            const prodId = this.value;
            variantSelect.innerHTML = '<option value="">All Variants (Master Product Recipe)</option>';
            if (prodId && productsData[prodId] && productsData[prodId].variants) {
                productsData[prodId].variants.forEach(function (v) {
                    const opt = document.createElement('option');
                    opt.value = v.id;
                    opt.textContent = `${v.sku} (${v.size || ''} / ${v.color || ''})`;
                    variantSelect.appendChild(opt);
                });
            }
        });

        function calculateTotals() {
            let total = 0;
            document.querySelectorAll('.item-row').forEach(function (row) {
                const select = row.querySelector('.item-select');
                const qtyInput = row.querySelector('.qty-input');
                const wastageInput = row.querySelector('.wastage-input');
                const lineCostEl = row.querySelector('.line-cost');
                const unitLabel = row.querySelector('.unit-label');

                const selectedOpt = select.options[select.selectedIndex];
                const cost = selectedOpt ? parseFloat(selectedOpt.dataset.cost || 0) : 0;
                const unit = selectedOpt ? (selectedOpt.dataset.unit || '') : '';
                if (unitLabel) unitLabel.textContent = unit;

                const qty = parseFloat(qtyInput.value || 0);
                const wastage = parseFloat(wastageInput.value || 0);
                const grossQty = qty * (1 + (wastage / 100));
                const line = grossQty * cost;

                lineCostEl.textContent = 'Rs. ' + line.toFixed(2);
                total += line;
            });

            document.getElementById('totalEstimatedCost').textContent = 'Rs. ' + total.toFixed(2);
        }

        tbody.addEventListener('input', calculateTotals);
        tbody.addEventListener('change', calculateTotals);

        addBtn.addEventListener('click', function () {
            const firstRow = document.querySelector('.item-row');
            const clone = firstRow.cloneNode(true);

            clone.querySelectorAll('input, select').forEach(function (input) {
                const name = input.getAttribute('name');
                if (name) {
                    input.setAttribute('name', name.replace(/\[\d+\]/, '[' + rowIdx + ']'));
                }
                if (input.tagName === 'INPUT') {
                    if (input.classList.contains('qty-input')) input.value = '1';
                    else if (input.classList.contains('wastage-input')) input.value = '0';
                    else input.value = '';
                } else if (input.tagName === 'SELECT') {
                    input.selectedIndex = 0;
                }
            });

            clone.querySelector('.line-cost').textContent = 'Rs. 0.00';
            clone.querySelector('.unit-label').textContent = '';
            tbody.appendChild(clone);
            rowIdx++;
            lucide.createIcons();
            calculateTotals();
        });

        tbody.addEventListener('click', function (e) {
            const removeBtn = e.target.closest('.remove-row-btn');
            if (removeBtn) {
                const rows = document.querySelectorAll('.item-row');
                if (rows.length > 1) {
                    removeBtn.closest('.item-row').remove();
                    calculateTotals();
                } else {
                    alert('Recipe must contain at least one raw material component.');
                }
            }
        });

        calculateTotals();
    });
</script>
@endpush
@endsection
