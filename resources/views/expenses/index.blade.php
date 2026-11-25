@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.expenses.title') }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800">
                    Operating & Factory Expenses
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">{{ __('erp.expenses.subtitle') }}</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('expenses.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition shadow-sm">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>{{ __('erp.expenses.new_expense') }}</span>
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
        <form method="GET" action="{{ route('expenses.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
            <div>
                <label for="search" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.search') }}</label>
                <div class="relative">
                    <input type="text" id="search" name="search" value="{{ request('search') }}"
                           placeholder="Voucher #, Payee, Desc..."
                           class="w-full pl-9 pr-3 py-2 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"></i>
                </div>
            </div>

            <div>
                <label for="status" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.status') }}</label>
                <select id="status" name="status" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <option value="all" {{ request('status') === 'all' || !request('status') ? 'selected' : '' }}>All Statuses</option>
                    <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft (Unposted)</option>
                    <option value="posted" {{ request('status') === 'posted' ? 'selected' : '' }}>Posted (Deducted)</option>
                    <option value="reversed" {{ request('status') === 'reversed' ? 'selected' : '' }}>Reversed</option>
                </select>
            </div>

            <div>
                <label for="category_id" class="block text-xs font-bold text-slate-600 uppercase mb-1">Category</label>
                <select id="category_id" name="category_id" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="location_id" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.location') }}</label>
                <select id="location_id" name="location_id" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    @if(auth()->user()->isOwner())
                        <option value="all" {{ request('location_id') === 'all' || !request('location_id') ? 'selected' : '' }}>🏢 {{ __('erp.all_locations') }}</option>
                    @endif
                    @foreach($authorizedLocations as $loc)
                        <option value="{{ $loc->id }}" {{ request('location_id') == $loc->id ? 'selected' : '' }}>
                            {{ $loc->type === 'factory' ? '🏭' : '🏪' }} {{ $loc->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="financial_account_id" class="block text-xs font-bold text-slate-600 uppercase mb-1">Paid From</label>
                <select id="financial_account_id" name="financial_account_id" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <option value="">All Accounts</option>
                    @foreach($accounts as $acc)
                        <option value="{{ $acc->id }}" {{ request('financial_account_id') == $acc->id ? 'selected' : '' }}>
                            {{ $acc->type === 'cash' ? '💵' : '🏦' }} {{ $acc->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2 px-4 bg-slate-800 hover:bg-slate-900 text-white rounded-lg text-sm font-semibold transition">
                    {{ __('erp.filter') }}
                </button>
                <a href="{{ route('expenses.index') }}" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg text-sm font-semibold transition" title="Reset">
                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Expenses Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 border-b border-slate-200 text-xs font-bold uppercase text-slate-500 tracking-wider">
                    <tr>
                        <th class="px-5 py-3.5">{{ __('erp.expenses.expense_number') }}</th>
                        <th class="px-5 py-3.5">{{ __('erp.expenses.expense_date') }}</th>
                        <th class="px-5 py-3.5">{{ __('erp.expenses.category') }}</th>
                        <th class="px-5 py-3.5">{{ __('erp.expenses.payee') }}</th>
                        <th class="px-5 py-3.5">{{ __('erp.expenses.paid_from') }}</th>
                        <th class="px-5 py-3.5 text-right">{{ __('erp.total') }}</th>
                        <th class="px-5 py-3.5 text-center">{{ __('erp.status') }}</th>
                        <th class="px-5 py-3.5 text-right">{{ __('erp.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($expenses as $exp)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-5 py-4 font-mono font-bold text-slate-900 whitespace-nowrap">
                                <a href="{{ route('expenses.show', $exp) }}" class="text-indigo-600 hover:underline">
                                    {{ $exp->expense_number }}
                                </a>
                            </td>
                            <td class="px-5 py-4 text-slate-700 whitespace-nowrap">
                                {{ $exp->expense_date->format('d M Y') }}
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap font-medium text-slate-800">
                                {{ $exp->category ? $exp->category->name : 'General' }}
                            </td>
                            <td class="px-5 py-4">
                                <span class="font-semibold text-slate-800">{{ $exp->payee_name ?: '—' }}</span>
                                @if($exp->description)
                                    <div class="text-xs text-slate-400 truncate max-w-xs">{{ $exp->description }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-xs font-mono text-slate-600 whitespace-nowrap">
                                @if($exp->financialAccount)
                                    {{ $exp->financialAccount->name }}
                                @else
                                    <span class="text-slate-400 italic">None (Unassigned)</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right font-mono font-bold text-slate-900 whitespace-nowrap">
                                PKR {{ number_format((float) $exp->amount, 2) }}
                            </td>
                            <td class="px-5 py-4 text-center whitespace-nowrap">
                                @if($exp->status === 'posted')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800">
                                        Posted
                                    </span>
                                @elseif($exp->status === 'draft')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800">
                                        Draft
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-semibold bg-rose-100 text-rose-800">
                                        Reversed
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                <a href="{{ route('expenses.show', $exp) }}" class="p-1.5 rounded-lg text-slate-500 hover:text-indigo-600 hover:bg-slate-100 transition inline-block" title="View Voucher">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-8 text-center text-slate-400">
                                <div class="flex flex-col items-center justify-center">
                                    <i data-lucide="file-text" class="w-10 h-10 text-slate-300 mb-2"></i>
                                    <span>{{ __('erp.expenses.no_expenses') }}</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($expenses->hasPages())
            <div class="p-4 border-t border-slate-100 bg-slate-50">
                {{ $expenses->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
