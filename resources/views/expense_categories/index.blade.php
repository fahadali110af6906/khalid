@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.expense_categories.title') }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                    Heads of Account
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">{{ __('erp.expense_categories.subtitle') }}</p>
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Add Category Form -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 h-fit space-y-4">
            <h2 class="text-base font-bold text-slate-900">{{ __('erp.expense_categories.new_category') }}</h2>
            <form action="{{ route('expense-categories.store') }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label for="name" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.expense_categories.category_name') }} *</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required placeholder="e.g. Shop Rent, Electricity, Salaries"
                           class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label for="code" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.expense_categories.category_code') }} *</label>
                    <input type="text" id="code" name="code" value="{{ old('code') }}" required placeholder="e.g. EXP-RENT, EXP-UTIL"
                           class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none font-mono uppercase">
                </div>

                <div>
                    <label for="description" class="block text-xs font-bold text-slate-700 uppercase mb-1">Description</label>
                    <textarea id="description" name="description" rows="2" placeholder="Optional category description..."
                              class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">{{ old('description') }}</textarea>
                </div>

                <div class="flex items-center">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" checked class="w-4 h-4 rounded text-indigo-600 border-slate-300 focus:ring-indigo-500">
                        <span class="text-xs font-semibold text-slate-700">Category is Active</span>
                    </label>
                </div>

                <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition shadow-sm">
                    {{ __('erp.save') }} Category
                </button>
            </form>
        </div>

        <!-- Categories Table -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 border-b border-slate-200 text-xs font-bold uppercase text-slate-500 tracking-wider">
                        <tr>
                            <th class="px-5 py-3.5">{{ __('erp.expense_categories.category_code') }}</th>
                            <th class="px-5 py-3.5">{{ __('erp.expense_categories.category_name') }}</th>
                            <th class="px-5 py-3.5">Description</th>
                            <th class="px-5 py-3.5 text-center">Expenses Count</th>
                            <th class="px-5 py-3.5 text-center">{{ __('erp.status') }}</th>
                            <th class="px-5 py-3.5 text-right">{{ __('erp.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($categories as $cat)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-5 py-4 font-mono font-bold text-slate-900 whitespace-nowrap">
                                    {{ $cat->code }}
                                </td>
                                <td class="px-5 py-4 font-bold text-slate-800 whitespace-nowrap">
                                    {{ $cat->name }}
                                </td>
                                <td class="px-5 py-4 text-xs text-slate-500 max-w-xs truncate">
                                    {{ $cat->description ?: '—' }}
                                </td>
                                <td class="px-5 py-4 text-center font-mono font-bold text-slate-700">
                                    {{ $cat->expenses_count }}
                                </td>
                                <td class="px-5 py-4 text-center">
                                    @if($cat->is_active)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800">
                                            Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-slate-100 text-slate-600">
                                            Inactive
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <form action="{{ route('expense-categories.toggle-status', $cat) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold {{ $cat->is_active ? 'text-amber-600 hover:text-amber-700' : 'text-emerald-600 hover:text-emerald-700' }}">
                                            {{ $cat->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-8 text-center text-slate-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <i data-lucide="tag" class="w-10 h-10 text-slate-300 mb-2"></i>
                                        <span>{{ __('erp.expense_categories.no_categories') }}</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($categories->hasPages())
                <div class="p-4 border-t border-slate-100 bg-slate-50">
                    {{ $categories->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
