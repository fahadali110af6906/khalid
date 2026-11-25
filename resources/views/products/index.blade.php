@extends('layouts.app')

@section('content')
<div class="space-y-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.nav.articles') }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                    Product Master Catalog
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">Manage footwear models, carton packaging rules, and size/color variant definitions.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('products.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition shadow-xs">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>Add Footwear Article</span>
            </a>
        </div>
    </div>

    <!-- Search & Filters -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col md:flex-row gap-4 justify-between items-center">
        <form method="GET" action="{{ route('products.index') }}" class="flex flex-1 gap-3 w-full md:w-auto">
            <div class="relative flex-1">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-3 rtl:right-3 rtl:left-auto"></i>
                <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('erp.search') }}" class="w-full text-xs font-semibold rounded-lg border-slate-300 bg-slate-50 pl-9 rtl:pr-9 rtl:pl-3 p-2.5">
            </div>

            <select name="category_id" class="text-xs font-semibold rounded-lg border-slate-300 bg-slate-50 p-2.5">
                <option value="">All Categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" {{ $categoryId == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                @endforeach
            </select>

            <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold rounded-lg transition">
                {{ __('erp.filter') }}
            </button>
        </form>
    </div>

    <!-- Products Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left rtl:text-right text-sm">
                <thead class="bg-slate-50/80 text-[11px] uppercase tracking-wider text-slate-500 font-bold border-b border-slate-100">
                    <tr>
                        <th class="py-3 px-6">Article Code</th>
                        <th class="py-3 px-6">Footwear Name</th>
                        <th class="py-3 px-6">Category</th>
                        <th class="py-3 px-6 text-center">Carton Pack Ratio</th>
                        <th class="py-3 px-6 text-center">Variants</th>
                        <th class="py-3 px-6 text-right rtl:text-left">Wholesale Rate</th>
                        <th class="py-3 px-6 text-center">Status</th>
                        <th class="py-3 px-6 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                    @forelse($products as $product)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="py-4 px-6 font-mono font-bold text-indigo-600">
                                <a href="{{ route('products.show', $product) }}" class="hover:underline">
                                    {{ $product->article_code }}
                                </a>
                            </td>
                            <td class="py-4 px-6 font-bold text-slate-900">
                                {{ $product->name }}
                            </td>
                            <td class="py-4 px-6 text-xs text-slate-600">
                                {{ $product->category->name ?? '—' }}
                            </td>
                            <td class="py-4 px-6 text-center font-mono text-xs">
                                <span class="px-2.5 py-1 rounded bg-slate-100 text-slate-800 font-bold">
                                    1 CTN = {{ $product->carton_pack_qty }} Pairs
                                </span>
                            </td>
                            <td class="py-4 px-6 text-center font-mono font-bold text-xs">
                                {{ $product->variants->count() }} Variants
                            </td>
                            <td class="py-4 px-6 text-right rtl:text-left font-mono font-bold text-slate-900">
                                ₨ {{ number_format($product->default_wholesale_rate, 2) }}
                            </td>
                            <td class="py-4 px-6 text-center">
                                @if($product->is_active)
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Active</span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">Inactive</span>
                                @endif
                            </td>
                            <td class="py-4 px-6 text-center">
                                <a href="{{ route('products.show', $product) }}" class="inline-flex items-center gap-1 text-xs font-bold text-indigo-600 hover:text-indigo-800">
                                    <span>Details</span>
                                    <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-slate-400">
                                <i data-lucide="package" class="w-10 h-10 mx-auto text-slate-300 mb-2"></i>
                                <p class="text-sm font-semibold">No footwear articles found.</p>
                                <p class="text-xs text-slate-400 mt-1">Add your first footwear model using the button above.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-slate-100">
            {{ $products->links() }}
        </div>
    </div>
</div>
@endsection
