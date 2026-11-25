@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.account_transfers.title') }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                    Zero Net Impact
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">{{ __('erp.account_transfers.subtitle') }}</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('account-transfers.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition shadow-sm">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>{{ __('erp.account_transfers.new_transfer') }}</span>
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
        <form method="GET" action="{{ route('account-transfers.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <div>
                <label for="search" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.search') }}</label>
                <div class="relative">
                    <input type="text" id="search" name="search" value="{{ request('search') }}"
                           placeholder="Transfer #, Ref #, Notes..."
                           class="w-full pl-9 pr-3 py-2 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"></i>
                </div>
            </div>

            <div>
                <label for="account_id" class="block text-xs font-bold text-slate-600 uppercase mb-1">Financial Account</label>
                <select id="account_id" name="account_id" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <option value="">All Accounts</option>
                    @foreach($accounts as $acc)
                        <option value="{{ $acc->id }}" {{ request('account_id') == $acc->id ? 'selected' : '' }}>
                            {{ $acc->type === 'cash' ? '💵' : '🏦' }} {{ $acc->name }} ({{ $acc->account_code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2 px-4 bg-slate-800 hover:bg-slate-900 text-white rounded-lg text-sm font-semibold transition">
                    {{ __('erp.filter') }}
                </button>
                <a href="{{ route('account-transfers.index') }}" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg text-sm font-semibold transition" title="Reset">
                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Transfers Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 border-b border-slate-200 text-xs font-bold uppercase text-slate-500 tracking-wider">
                    <tr>
                        <th class="px-5 py-3.5">{{ __('erp.account_transfers.transfer_number') }}</th>
                        <th class="px-5 py-3.5">{{ __('erp.account_transfers.transfer_date') }}</th>
                        <th class="px-5 py-3.5">{{ __('erp.account_transfers.from_account') }}</th>
                        <th class="px-5 py-3.5">{{ __('erp.account_transfers.to_account') }}</th>
                        <th class="px-5 py-3.5 text-right">{{ __('erp.account_transfers.amount') }}</th>
                        <th class="px-5 py-3.5">Reference / Notes</th>
                        <th class="px-5 py-3.5 text-center">User</th>
                        <th class="px-5 py-3.5 text-right">{{ __('erp.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($transfers as $tr)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-5 py-4 font-mono font-bold text-slate-900 whitespace-nowrap">
                                <a href="{{ route('account-transfers.show', $tr) }}" class="text-indigo-600 hover:underline">
                                    {{ $tr->transfer_number }}
                                </a>
                            </td>
                            <td class="px-5 py-4 text-slate-700 whitespace-nowrap">
                                {{ $tr->transfer_date->format('d M Y') }}
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap">
                                <span class="font-bold text-rose-700">
                                    {{ $tr->fromAccount->name }}
                                </span>
                                <div class="text-xs text-slate-400 font-mono">{{ $tr->fromAccount->account_code }}</div>
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap">
                                <span class="font-bold text-emerald-700">
                                    {{ $tr->toAccount->name }}
                                </span>
                                <div class="text-xs text-slate-400 font-mono">{{ $tr->toAccount->account_code }}</div>
                            </td>
                            <td class="px-5 py-4 text-right font-mono font-bold text-slate-900 whitespace-nowrap">
                                PKR {{ number_format((float) $tr->amount, 2) }}
                            </td>
                            <td class="px-5 py-4">
                                @if($tr->reference_number)
                                    <div class="text-xs font-mono font-semibold text-slate-700">Ref: {{ $tr->reference_number }}</div>
                                @endif
                                @if($tr->notes)
                                    <div class="text-xs text-slate-500 truncate max-w-xs">{{ $tr->notes }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-center text-xs text-slate-500 whitespace-nowrap">
                                {{ $tr->creator ? $tr->creator->name : 'System' }}
                            </td>
                            <td class="px-5 py-4 text-right">
                                <a href="{{ route('account-transfers.show', $tr) }}" class="p-1.5 rounded-lg text-slate-500 hover:text-indigo-600 hover:bg-slate-100 transition" title="View Details">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-8 text-center text-slate-400">
                                <div class="flex flex-col items-center justify-center">
                                    <i data-lucide="arrow-left-right" class="w-10 h-10 text-slate-300 mb-2"></i>
                                    <span>{{ __('erp.account_transfers.no_transfers') }}</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($transfers->hasPages())
            <div class="p-4 border-t border-slate-100 bg-slate-50">
                {{ $transfers->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
