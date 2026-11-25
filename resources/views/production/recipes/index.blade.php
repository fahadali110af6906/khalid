@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.recipes.title') }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                    BOM / Recipes
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">{{ __('erp.recipes.subtitle') }}</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('production.recipes.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition shadow-sm">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>{{ __('erp.recipes.new_recipe') }}</span>
            </a>
        </div>
    </div>

    <!-- Alert Success / Error -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium flex items-center gap-2">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-medium flex items-center gap-2">
            <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-600"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Filter Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
        <form method="GET" action="{{ route('production.recipes.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label for="search" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.search') }}</label>
                <div class="relative">
                    <input type="text" id="search" name="search" value="{{ request('search') }}"
                           placeholder="BOM #, Name, Article..."
                           class="w-full pl-9 pr-3 py-2 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"></i>
                </div>
            </div>

            <div>
                <label for="product_id" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.production.target_product') }}</label>
                <select id="product_id" name="product_id" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <option value="">All Products</option>
                    @foreach($products as $prod)
                        <option value="{{ $prod->id }}" {{ request('product_id') == $prod->id ? 'selected' : '' }}>
                            {{ $prod->article_code }} — {{ $prod->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end gap-2 lg:col-span-2">
                <button type="submit" class="px-4 py-2 bg-slate-900 text-white rounded-lg text-sm font-semibold hover:bg-slate-800 transition flex items-center gap-1.5">
                    <i data-lucide="filter" class="w-4 h-4"></i>
                    <span>{{ __('erp.filter') }}</span>
                </button>
                <a href="{{ route('production.recipes.index') }}" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm font-semibold hover:bg-slate-200 transition">
                    {{ __('erp.reset') }}
                </a>
            </div>
        </form>
    </div>

    <!-- Recipes Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50/75 border-b border-slate-200/80 text-xs uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3.5 font-bold">{{ __('erp.recipes.recipe_number') }}</th>
                        <th class="px-4 py-3.5 font-bold">{{ __('erp.recipes.recipe_name') }}</th>
                        <th class="px-4 py-3.5 font-bold">{{ __('erp.production.target_product') }}</th>
                        <th class="px-4 py-3.5 font-bold">{{ __('erp.recipes.version') }}</th>
                        <th class="px-4 py-3.5 font-bold text-center">{{ __('erp.recipes.components') }}</th>
                        <th class="px-4 py-3.5 font-bold text-center">{{ __('erp.recipes.is_active') }}</th>
                        <th class="px-4 py-3.5 font-bold text-right">{{ __('erp.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($recipes as $recipe)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-4 py-3 font-mono font-bold text-indigo-700">
                                <a href="{{ route('production.recipes.show', $recipe) }}" class="hover:underline">
                                    {{ $recipe->recipe_number }}
                                </a>
                            </td>
                            <td class="px-4 py-3 font-semibold text-slate-900">
                                {{ $recipe->name }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $recipe->product->article_code }} — {{ $recipe->product->name }}</div>
                                @if($recipe->productVariant)
                                    <div class="text-xs text-slate-500 font-mono">Variant: {{ $recipe->productVariant->sku }} ({{ $recipe->productVariant->size }}/{{ $recipe->productVariant->color }})</div>
                                @else
                                    <div class="text-xs text-emerald-600">All Sizes & Colors</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">
                                <span class="px-2 py-0.5 rounded bg-slate-100 border border-slate-200 text-slate-700 font-semibold">
                                    v{{ $recipe->version }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-800">
                                    {{ $recipe->items->count() }} items
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($recipe->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-200 text-slate-600">
                                        Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('production.recipes.show', $recipe) }}" class="p-1.5 text-slate-500 hover:text-indigo-600 hover:bg-slate-100 rounded-lg transition" title="View BOM">
                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                    </a>
                                    <a href="{{ route('production.orders.create', ['recipe_id' => $recipe->id]) }}" class="px-2.5 py-1 text-xs font-bold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition" title="Create Order">
                                        Create Order
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-slate-400">
                                <i data-lucide="layers" class="w-10 h-10 mx-auto mb-2 text-slate-300"></i>
                                <p class="text-sm font-semibold">{{ __('erp.recipes.no_recipes') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($recipes->hasPages())
            <div class="px-4 py-3 border-t border-slate-200/80 bg-slate-50/50">
                {{ $recipes->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
