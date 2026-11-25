@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.financial_accounts.title') }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                    Liquidity & Treasury
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">{{ __('erp.financial_accounts.subtitle') }}</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('account-transfers.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-bold transition shadow-xs">
                <i data-lucide="arrow-left-right" class="w-4 h-4 text-indigo-600"></i>
                <span>{{ __('erp.account_transfers.new_transfer') }}</span>
            </a>
            <a href="{{ route('financial-accounts.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition shadow-sm">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>{{ __('erp.financial_accounts.create_account') }}</span>
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

    <!-- Summary Liquidity KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- Total Combined Liquidity -->
        <div class="bg-gradient-to-br from-indigo-900 to-slate-900 rounded-2xl p-5 text-white shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-indigo-200">{{ __('erp.financial_accounts.total_liquidity') }}</span>
                <div class="w-8 h-8 rounded-lg bg-indigo-500/20 border border-indigo-400/30 flex items-center justify-center">
                    <i data-lucide="wallet" class="w-4 h-4 text-indigo-300"></i>
                </div>
            </div>
            <div class="mt-4">
                <span class="text-2xl font-extrabold tracking-tight">PKR {{ number_format((float) $totalLiquidity, 2) }}</span>
                <p class="text-xs text-indigo-200/80 mt-1">Authorized cash drawers + bank accounts</p>
            </div>
        </div>

        <!-- Total Cash in Hand -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ __('erp.financial_accounts.total_cash') }}</span>
                <div class="w-8 h-8 rounded-lg bg-amber-50 border border-amber-200 flex items-center justify-center">
                    <i data-lucide="coins" class="w-4 h-4 text-amber-600"></i>
                </div>
            </div>
            <div class="mt-4">
                <span class="text-2xl font-extrabold tracking-tight text-slate-900">PKR {{ number_format((float) $totalCash, 2) }}</span>
                <p class="text-xs text-slate-500 mt-1">Shop cash drawers & factory vaults</p>
            </div>
        </div>

        <!-- Total Bank Balances -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ __('erp.financial_accounts.total_bank') }}</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-50 border border-emerald-200 flex items-center justify-center">
                    <i data-lucide="landmark" class="w-4 h-4 text-emerald-600"></i>
                </div>
            </div>
            <div class="mt-4">
                <span class="text-2xl font-extrabold tracking-tight text-slate-900">PKR {{ number_format((float) $totalBank, 2) }}</span>
                <p class="text-xs text-slate-500 mt-1">Commercial & current bank accounts</p>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
        <form method="GET" action="{{ route('financial-accounts.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label for="search" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.search') }}</label>
                <div class="relative">
                    <input type="text" id="search" name="search" value="{{ request('search') }}"
                           placeholder="Account name, code, bank..."
                           class="w-full pl-9 pr-3 py-2 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"></i>
                </div>
            </div>

            <div>
                <label for="type" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.financial_accounts.type') }}</label>
                <select id="type" name="type" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <option value="all" {{ request('type') === 'all' || !request('type') ? 'selected' : '' }}>All Types</option>
                    <option value="cash" {{ request('type') === 'cash' ? 'selected' : '' }}>💵 Cash Drawers</option>
                    <option value="bank" {{ request('type') === 'bank' ? 'selected' : '' }}>🏦 Bank Accounts</option>
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

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2 px-4 bg-slate-800 hover:bg-slate-900 text-white rounded-lg text-sm font-semibold transition">
                    {{ __('erp.filter') }}
                </button>
                <a href="{{ route('financial-accounts.index') }}" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg text-sm font-semibold transition" title="Reset">
                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Accounts Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 border-b border-slate-200 text-xs font-bold uppercase text-slate-500 tracking-wider">
                    <tr>
                        <th class="px-5 py-3.5">{{ __('erp.financial_accounts.account_code') }}</th>
                        <th class="px-5 py-3.5">{{ __('erp.financial_accounts.account_name') }}</th>
                        <th class="px-5 py-3.5">{{ __('erp.financial_accounts.type') }}</th>
                        <th class="px-5 py-3.5">{{ __('erp.financial_accounts.location') }}</th>
                        <th class="px-5 py-3.5 text-right">{{ __('erp.financial_accounts.opening_balance') }}</th>
                        <th class="px-5 py-3.5 text-right">{{ __('erp.financial_accounts.current_balance') }}</th>
                        <th class="px-5 py-3.5 text-center">{{ __('erp.status') }}</th>
                        <th class="px-5 py-3.5 text-right">{{ __('erp.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($accounts as $acc)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-5 py-4 font-mono font-bold text-slate-900">
                                <a href="{{ route('financial-accounts.show', $acc) }}" class="text-indigo-600 hover:underline">
                                    {{ $acc->account_code }}
                                </a>
                            </td>
                            <td class="px-5 py-4">
                                <span class="font-bold text-slate-800">{{ $acc->name }}</span>
                                @if($acc->type === 'bank' && $acc->bank_name)
                                    <div class="text-xs text-slate-400 mt-0.5">
                                        {{ $acc->bank_name }} @if($acc->account_number) • {{ $acc->account_number }} @endif
                                    </div>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                @if($acc->type === 'cash')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                        <i data-lucide="coins" class="w-3 h-3"></i> Cash Drawer
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <i data-lucide="landmark" class="w-3 h-3"></i> Bank
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-xs font-medium text-slate-600">
                                @if($acc->location)
                                    {{ $acc->location->name }}
                                @else
                                    <span class="text-indigo-600 font-semibold">Central / All Locations</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right font-mono text-xs text-slate-500">
                                PKR {{ number_format((float) $acc->opening_balance, 2) }}
                            </td>
                            <td class="px-5 py-4 text-right font-mono font-bold text-slate-900">
                                <span class="{{ bccomp((string) $acc->current_balance, '0.00', 2) < 0 ? 'text-rose-600' : 'text-slate-900' }}">
                                    PKR {{ number_format((float) $acc->current_balance, 2) }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-center">
                                @if($acc->is_active)
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
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('account-transfers.create', ['from_account_id' => $acc->id]) }}"
                                       class="p-1.5 rounded-lg text-slate-500 hover:text-indigo-600 hover:bg-slate-100 transition"
                                       title="Transfer funds from this account">
                                        <i data-lucide="arrow-left-right" class="w-4 h-4"></i>
                                    </a>
                                    <a href="{{ route('financial-accounts.show', $acc) }}"
                                       class="p-1.5 rounded-lg text-slate-500 hover:text-indigo-600 hover:bg-slate-100 transition"
                                       title="View Statement">
                                        <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
                                    </a>
                                    <a href="{{ route('financial-accounts.edit', $acc) }}"
                                       class="p-1.5 rounded-lg text-slate-500 hover:text-indigo-600 hover:bg-slate-100 transition"
                                       title="Edit">
                                        <i data-lucide="pencil" class="w-4 h-4"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-8 text-center text-slate-400">
                                <div class="flex flex-col items-center justify-center">
                                    <i data-lucide="wallet" class="w-10 h-10 text-slate-300 mb-2"></i>
                                    <span>{{ __('erp.financial_accounts.no_accounts') }}</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($accounts->hasPages())
            <div class="p-4 border-t border-slate-100 bg-slate-50">
                {{ $accounts->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
