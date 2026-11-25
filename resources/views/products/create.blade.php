@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Add New Footwear Article</h1>
            <p class="text-sm text-slate-500 mt-1">Define article model, packaging conversion ratios, and size/color variant combinations.</p>
        </div>
        <a href="{{ route('products.index') }}" class="px-3.5 py-2 rounded-lg bg-white border border-slate-300 text-slate-700 text-xs font-bold hover:bg-slate-50 transition">
            Back to Articles
        </a>
    </div>

    @if($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-medium space-y-1">
            @foreach($errors->all() as $error)
                <p>• {{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('products.store') }}" class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 lg:p-8 space-y-8">
        @csrf

        <!-- General Product Details -->
        <div>
            <h2 class="text-base font-bold text-slate-900 mb-4 pb-2 border-b border-slate-100">1. Article Specification</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Article / Style Code *</label>
                    <input type="text" name="article_code" value="{{ old('article_code') }}" required placeholder="e.g. ART-9020" class="w-full text-sm font-semibold rounded-lg border-slate-300 bg-slate-50 p-2.5 focus:bg-white focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Footwear Name / Description *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. Executive Formal Leather Oxford" class="w-full text-sm font-semibold rounded-lg border-slate-300 bg-slate-50 p-2.5 focus:bg-white focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Category *</label>
                    <select name="category_id" required class="w-full text-sm font-semibold rounded-lg border-slate-300 bg-slate-50 p-2.5">
                        <option value="">Select Category</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Brand (Optional)</label>
                    <select name="brand_id" class="w-full text-sm font-semibold rounded-lg border-slate-300 bg-slate-50 p-2.5">
                        <option value="">No Brand</option>
                        @foreach($brands as $brand)
                            <option value="{{ $brand->id }}" {{ old('brand_id') == $brand->id ? 'selected' : '' }}>{{ $brand->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Base Unit of Stock *</label>
                    <select name="base_unit_id" required class="w-full text-sm font-semibold rounded-lg border-slate-300 bg-slate-50 p-2.5">
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}" {{ old('base_unit_id', $unit->code === 'PR' ? $unit->id : '') == $unit->id ? 'selected' : '' }}>
                                {{ $unit->name }} ({{ $unit->code }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- Packaging & Commercial Rates -->
        <div>
            <h2 class="text-base font-bold text-slate-900 mb-4 pb-2 border-b border-slate-100">2. Packaging Conversion & Pricing</h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Carton Ratio (Pairs / CTN) *</label>
                    <input type="number" name="carton_pack_qty" value="{{ old('carton_pack_qty', 12) }}" min="1" max="100" required class="w-full text-sm font-semibold rounded-lg border-slate-300 bg-slate-50 p-2.5 focus:bg-white focus:ring-2 focus:ring-indigo-500 font-mono">
                    <p class="text-[10px] text-slate-400 mt-1">e.g. 12 or 24 pairs in 1 master carton</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Default Wholesale Rate / PR *</label>
                    <input type="number" step="0.01" name="default_wholesale_rate" value="{{ old('default_wholesale_rate', '2200.00') }}" required class="w-full text-sm font-semibold rounded-lg border-slate-300 bg-slate-50 p-2.5 focus:bg-white focus:ring-2 focus:ring-indigo-500 font-mono">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Default Retail Rate / PR</label>
                    <input type="number" step="0.01" name="default_retail_rate" value="{{ old('default_retail_rate', '2800.00') }}" class="w-full text-sm font-semibold rounded-lg border-slate-300 bg-slate-50 p-2.5 focus:bg-white focus:ring-2 focus:ring-indigo-500 font-mono">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Reorder Threshold (CTN)</label>
                    <input type="number" name="reorder_threshold_cartons" value="{{ old('reorder_threshold_cartons', 5) }}" min="0" required class="w-full text-sm font-semibold rounded-lg border-slate-300 bg-slate-50 p-2.5 focus:bg-white focus:ring-2 focus:ring-indigo-500 font-mono">
                </div>
            </div>
        </div>

        <!-- Variant Matrix (Sizes & Colors) -->
        <div>
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-slate-100">
                <h2 class="text-base font-bold text-slate-900">3. Variant Matrix (Sizes, Colors & Barcodes)</h2>
                <button type="button" onclick="addVariantRow()" class="inline-flex items-center gap-1.5 text-xs font-bold text-indigo-600 hover:text-indigo-800">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Add Variant Row</span>
                </button>
            </div>

            <div class="border border-slate-200 rounded-xl overflow-hidden">
                <table class="w-full text-left rtl:text-right text-xs" id="variants-table">
                    <thead class="bg-slate-100 text-slate-600 font-bold uppercase tracking-wider">
                        <tr>
                            <th class="p-3">Shoe Size</th>
                            <th class="p-3">Color</th>
                            <th class="p-3">Barcode (Optional)</th>
                            <th class="p-3">Est. Purchase Cost / PR (PKR)</th>
                            <th class="p-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 font-medium" id="variants-body">
                        <tr>
                            <td class="p-2.5"><input type="text" name="variants[0][size]" value="40" required class="w-full text-xs font-semibold rounded border-slate-300 p-2 bg-slate-50"></td>
                            <td class="p-2.5"><input type="text" name="variants[0][color]" value="Black" required class="w-full text-xs font-semibold rounded border-slate-300 p-2 bg-slate-50"></td>
                            <td class="p-2.5"><input type="text" name="variants[0][barcode]" placeholder="Optional" class="w-full text-xs font-semibold rounded border-slate-300 p-2 bg-slate-50 font-mono"></td>
                            <td class="p-2.5"><input type="number" step="0.01" name="variants[0][purchase_cost]" value="1450.00" class="w-full text-xs font-semibold rounded border-slate-300 p-2 bg-slate-50 font-mono"></td>
                            <td class="p-2.5 text-center"><button type="button" onclick="this.closest('tr').remove()" class="text-rose-500 hover:text-rose-700"><i data-lucide="trash-2" class="w-4 h-4"></i></button></td>
                        </tr>
                        <tr>
                            <td class="p-2.5"><input type="text" name="variants[1][size]" value="41" required class="w-full text-xs font-semibold rounded border-slate-300 p-2 bg-slate-50"></td>
                            <td class="p-2.5"><input type="text" name="variants[1][color]" value="Black" required class="w-full text-xs font-semibold rounded border-slate-300 p-2 bg-slate-50"></td>
                            <td class="p-2.5"><input type="text" name="variants[1][barcode]" placeholder="Optional" class="w-full text-xs font-semibold rounded border-slate-300 p-2 bg-slate-50 font-mono"></td>
                            <td class="p-2.5"><input type="number" step="0.01" name="variants[1][purchase_cost]" value="1450.00" class="w-full text-xs font-semibold rounded border-slate-300 p-2 bg-slate-50 font-mono"></td>
                            <td class="p-2.5 text-center"><button type="button" onclick="this.closest('tr').remove()" class="text-rose-500 hover:text-rose-700"><i data-lucide="trash-2" class="w-4 h-4"></i></button></td>
                        </tr>
                        <tr>
                            <td class="p-2.5"><input type="text" name="variants[2][size]" value="42" required class="w-full text-xs font-semibold rounded border-slate-300 p-2 bg-slate-50"></td>
                            <td class="p-2.5"><input type="text" name="variants[2][color]" value="Black" required class="w-full text-xs font-semibold rounded border-slate-300 p-2 bg-slate-50"></td>
                            <td class="p-2.5"><input type="text" name="variants[2][barcode]" placeholder="Optional" class="w-full text-xs font-semibold rounded border-slate-300 p-2 bg-slate-50 font-mono"></td>
                            <td class="p-2.5"><input type="number" step="0.01" name="variants[2][purchase_cost]" value="1450.00" class="w-full text-xs font-semibold rounded border-slate-300 p-2 bg-slate-50 font-mono"></td>
                            <td class="p-2.5 text-center"><button type="button" onclick="this.closest('tr').remove()" class="text-rose-500 hover:text-rose-700"><i data-lucide="trash-2" class="w-4 h-4"></i></button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
            <a href="{{ route('products.index') }}" class="px-5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-md shadow-indigo-600/30 transition">
                Save Product & Generate SKUs
            </button>
        </div>
    </form>
</div>

<script>
    let variantIndex = 3;
    function addVariantRow() {
        const tbody = document.getElementById('variants-body');
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="p-2.5"><input type="text" name="variants[${variantIndex}][size]" placeholder="Size" required class="w-full text-xs font-semibold rounded border-slate-300 p-2 bg-slate-50"></td>
            <td class="p-2.5"><input type="text" name="variants[${variantIndex}][color]" placeholder="Color" required class="w-full text-xs font-semibold rounded border-slate-300 p-2 bg-slate-50"></td>
            <td class="p-2.5"><input type="text" name="variants[${variantIndex}][barcode]" placeholder="Optional" class="w-full text-xs font-semibold rounded border-slate-300 p-2 bg-slate-50 font-mono"></td>
            <td class="p-2.5"><input type="number" step="0.01" name="variants[${variantIndex}][purchase_cost]" value="1450.00" class="w-full text-xs font-semibold rounded border-slate-300 p-2 bg-slate-50 font-mono"></td>
            <td class="p-2.5 text-center"><button type="button" onclick="this.closest('tr').remove()" class="text-rose-500 hover:text-rose-700"><i data-lucide="trash-2" class="w-4 h-4"></i></button></td>
        `;
        tbody.appendChild(tr);
        variantIndex++;
        lucide.createIcons();
    }
</script>
@endsection
