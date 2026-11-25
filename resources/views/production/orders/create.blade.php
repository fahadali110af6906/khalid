@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('production.orders.index') }}" class="p-2 rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-slate-800 transition">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.production.new_order') }}</h1>
                <p class="text-sm text-slate-500 mt-0.5">{{ __('erp.production.subtitle') }}</p>
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

    <form method="POST" action="{{ route('production.orders.store') }}" class="space-y-6">
        @csrf

        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-5">
            <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3 flex items-center gap-2">
                <i data-lucide="factory" class="w-4 h-4 text-indigo-600"></i>
                Production Order Specification
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="factory_location_id" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.production.factory_location') }} *</label>
                    <select id="factory_location_id" name="factory_location_id" required
                            class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                        <option value="">Select Factory Location</option>
                        @foreach($authorizedLocations as $loc)
                            <option value="{{ $loc->id }}" {{ old('factory_location_id') == $loc->id ? 'selected' : '' }}>
                                {{ $loc->name }} ({{ $loc->code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="recipe_id" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.production.recipe_bom') }} *</label>
                    <select id="recipe_id" name="recipe_id" required
                            class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                        <option value="">Select BOM Recipe</option>
                        @foreach($recipes as $rec)
                            <option value="{{ $rec->id }}" 
                                    data-product-id="{{ $rec->product_id }}"
                                    data-product-name="{{ $rec->product->article_code }} — {{ $rec->product->name }}"
                                    data-variant-id="{{ $rec->product_variant_id }}"
                                    {{ (old('recipe_id', request('recipe_id')) == $rec->id) ? 'selected' : '' }}>
                                {{ $rec->recipe_number }} — {{ $rec->name }} (v{{ $rec->version }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="product_display" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.production.target_product') }} *</label>
                    <input type="text" id="product_display" readonly
                           placeholder="Automatically populated from Recipe BOM"
                           class="w-full py-2.5 px-3 text-sm bg-slate-100 border border-slate-300 rounded-lg text-slate-700 font-medium focus:outline-none cursor-not-allowed">
                    <input type="hidden" id="product_id" name="product_id" value="{{ old('product_id') }}">
                </div>

                <div>
                    <label for="product_variant_id" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.production.target_variant') }} *</label>
                    <select id="product_variant_id" name="product_variant_id" required
                            class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                        <option value="">Select Target Size & Color Variant</option>
                    </select>
                </div>

                <div>
                    <label for="planned_quantity_pairs" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.production.planned_quantity') }} *</label>
                    <input type="number" step="1" min="1" id="planned_quantity_pairs" name="planned_quantity_pairs" required
                           value="{{ old('planned_quantity_pairs', 100) }}"
                           class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none text-right font-mono font-bold">
                </div>

                <div>
                    <label for="status" class="block text-xs font-bold text-slate-700 uppercase mb-1">Initial Status</label>
                    <select id="status" name="status"
                            class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                        <option value="draft" {{ old('status') === 'draft' ? 'selected' : '' }}>Draft (Working state - zero stock effect)</option>
                        <option value="planned" {{ old('status', 'planned') === 'planned' ? 'selected' : '' }}>Planned (Scheduled for floor)</option>
                    </select>
                </div>

                <div>
                    <label for="start_date" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.production.start_date') }} *</label>
                    <input type="date" id="start_date" name="start_date" required
                           value="{{ old('start_date', now()->toDateString()) }}"
                           class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none font-mono">
                </div>

                <div>
                    <label for="due_date" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.production.target_completion_date') }}</label>
                    <input type="date" id="due_date" name="due_date"
                           value="{{ old('due_date', now()->addDays(7)->toDateString()) }}"
                           class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none font-mono">
                </div>

                <div class="md:col-span-2">
                    <label for="notes" class="block text-xs font-bold text-slate-700 uppercase mb-1">Floor Batch Instructions / Notes</label>
                    <textarea id="notes" name="notes" rows="2" placeholder="Special leather tanning notes, stitcher batch assignments, carton packing codes..."
                              class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('production.orders.index') }}" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl transition">
                {{ __('erp.cancel') }}
            </a>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl shadow-sm transition flex items-center gap-2">
                <i data-lucide="check" class="w-4 h-4"></i>
                Create Production Order
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const recipesData = @json($recipes->keyBy('id'));
        const recipeSelect = document.getElementById('recipe_id');
        const productDisplay = document.getElementById('product_display');
        const productIdInput = document.getElementById('product_id');
        const variantSelect = document.getElementById('product_variant_id');

        function onRecipeChange() {
            const recipeId = recipeSelect.value;
            variantSelect.innerHTML = '<option value="">Select Target Size & Color Variant</option>';
            productDisplay.value = '';
            productIdInput.value = '';

            if (recipeId && recipesData[recipeId]) {
                const rec = recipesData[recipeId];
                productDisplay.value = `${rec.product.article_code} — ${rec.product.name}`;
                productIdInput.value = rec.product_id;

                if (rec.product && rec.product.variants) {
                    rec.product.variants.forEach(function (v) {
                        const opt = document.createElement('option');
                        opt.value = v.id;
                        opt.textContent = `${v.sku} (Size ${v.size || 'Std'} / Color ${v.color || 'Std'})`;
                        if (rec.product_variant_id && rec.product_variant_id == v.id) {
                            opt.selected = true;
                        }
                        variantSelect.appendChild(opt);
                    });
                }
            }
        }

        recipeSelect.addEventListener('change', onRecipeChange);
        if (recipeSelect.value) {
            onRecipeChange();
        }
    });
</script>
@endpush
@endsection
