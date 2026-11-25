@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.chart_of_accounts.title') }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                    General Ledger Hierarchy
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">{{ __('erp.chart_of_accounts.subtitle') }}</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('accounting.chart-of-accounts.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition shadow-sm">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>{{ __('erp.chart_of_accounts.new_account') }}</span>
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
    @if($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">
            <ul class="list-disc list-inside space-y-0.5 text-xs font-semibold">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Filter Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
        <form method="GET" action="{{ route('accounting.chart-of-accounts.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="lg:col-span-2">
                <label for="search" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.search') }}</label>
                <div class="relative">
                    <input type="text" id="search" name="search" value="{{ request('search') }}"
                           placeholder="Code, Account Name, Description..."
                           class="w-full pl-9 pr-3 py-2 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"></i>
                </div>
            </div>

            <div>
                <label for="type" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.chart_of_accounts.account_type') }}</label>
                <select id="type" name="type" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <option value="all">All Types</option>
                    <option value="asset" {{ request('type') === 'asset' ? 'selected' : '' }}>Assets (1000)</option>
                    <option value="liability" {{ request('type') === 'liability' ? 'selected' : '' }}>Liabilities (2000)</option>
                    <option value="equity" {{ request('type') === 'equity' ? 'selected' : '' }}>Equity (3000)</option>
                    <option value="revenue" {{ request('type') === 'revenue' ? 'selected' : '' }}>Revenue (4000)</option>
                    <option value="expense" {{ request('type') === 'expense' ? 'selected' : '' }}>Expenses & COGS (5000-6000)</option>
                </select>
            </div>

            <div>
                <label for="status" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.status') }}</label>
                <select id="status" name="status" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <option value="all">All</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active Only</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive Only</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="w-full py-2 bg-slate-900 text-white rounded-lg text-sm font-semibold hover:bg-slate-800 transition flex items-center justify-center gap-1.5">
                    <i data-lucide="filter" class="w-4 h-4"></i>
                    <span>{{ __('erp.filter') }}</span>
                </button>
                <a href="{{ route('accounting.chart-of-accounts.index') }}" class="px-3 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm font-semibold hover:bg-slate-200 transition">
                    {{ __('erp.reset') }}
                </a>
            </div>
        </form>
    </div>

    <!-- Accounts Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50/75 border-b border-slate-200/80 text-xs uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3.5 font-bold" style="width: 15%;">{{ __('erp.chart_of_accounts.account_code') }}</th>
                        <th class="px-4 py-3.5 font-bold" style="width: 35%;">{{ __('erp.chart_of_accounts.account_name') }}</th>
                        <th class="px-4 py-3.5 font-bold" style="width: 15%;">{{ __('erp.chart_of_accounts.account_type') }}</th>
                        <th class="px-4 py-3.5 font-bold text-center" style="width: 10%;">Balance Side</th>
                        <th class="px-4 py-3.5 font-bold text-center" style="width: 10%;">Posting Status</th>
                        <th class="px-4 py-3.5 font-bold text-right" style="width: 15%;">{{ __('erp.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($accounts as $acc)
                        @php
                            $typeBadge = match($acc->type) {
                                'asset' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                'liability' => 'bg-rose-50 text-rose-800 border-rose-200',
                                'equity' => 'bg-purple-50 text-purple-800 border-purple-200',
                                'revenue' => 'bg-blue-50 text-blue-800 border-blue-200',
                                'expense' => 'bg-amber-50 text-amber-800 border-amber-200',
                                default => 'bg-slate-100 text-slate-700'
                            };
                        @endphp
                        <tr class="hover:bg-slate-50/60 transition {{ $acc->is_group ? 'bg-slate-50/40 font-semibold text-slate-900' : '' }}">
                            <td class="px-4 py-3 font-mono font-bold text-indigo-700">
                                {{ $acc->code }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    @if($acc->is_group)
                                        <i data-lucide="folder" class="w-4 h-4 text-amber-500"></i>
                                    @else
                                        <span class="w-4 inline-block text-slate-300">↳</span>
                                    @endif
                                    <span class="{{ $acc->is_group ? 'font-bold text-slate-900' : 'text-slate-800' }}">
                                        {{ $acc->name }}
                                    </span>
                                    @if($acc->is_system)
                                        <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200" title="System Integration Account">
                                            SYS
                                        </span>
                                    @endif
                                </div>
                                @if($acc->description)
                                    <div class="text-[11px] text-slate-400 mt-0.5 ps-6">{{ $acc->description }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $typeBadge }}">
                                    {{ ucfirst($acc->type) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-xs uppercase font-mono font-bold {{ $acc->normal_balance === 'debit' ? 'text-blue-700' : 'text-indigo-700' }}">
                                    {{ $acc->normal_balance }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($acc->is_group)
                                    <span class="px-2 py-0.5 rounded-md text-[11px] font-semibold bg-slate-200 text-slate-700">
                                        Group / Header
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-md text-[11px] font-semibold bg-emerald-100 text-emerald-800">
                                        Postable Leaf
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    @if(!$acc->is_group)
                                        <a href="{{ route('accounting.ledger', ['account_id' => $acc->id]) }}" class="p-1 text-slate-500 hover:text-indigo-600 rounded transition" title="View General Ledger">
                                            <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
                                        </a>
                                    @endif
                                    <a href="{{ route('accounting.chart-of-accounts.edit', $acc) }}" class="p-1 text-slate-500 hover:text-blue-600 rounded transition" title="Edit Account">
                                        <i data-lucide="edit" class="w-4 h-4"></i>
                                    </a>
                                    @if(!$acc->is_system && !$acc->is_group)
                                        <form method="POST" action="{{ route('accounting.chart-of-accounts.destroy', $acc) }}" onsubmit="return confirm('Are you sure you want to delete this account?');" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1 text-slate-400 hover:text-rose-600 rounded transition" title="Delete Account">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-slate-400">
                                <i data-lucide="network" class="w-10 h-10 mx-auto mb-2 text-slate-300"></i>
                                <p class="text-sm font-semibold">{{ __('erp.chart_of_accounts.no_accounts') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($accounts->hasPages())
            <div class="px-4 py-3 border-t border-slate-200/80 bg-slate-50/50">
                {{ $accounts->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
