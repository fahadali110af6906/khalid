@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.general_ledger.journals') }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                    Double-Entry Vouchers
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">Audit trail of financial transactions, automated posting entries, and manual journal adjustments.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('accounting.journals.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition shadow-sm">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>{{ __('erp.general_ledger.new_journal') }}</span>
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
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-medium flex items-center gap-2">
            <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-600"></i>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <!-- Filter Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
        <form method="GET" action="{{ route('accounting.journals.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
            <div class="lg:col-span-2">
                <label for="search" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.search') }}</label>
                <div class="relative">
                    <input type="text" id="search" name="search" value="{{ request('search') }}"
                           placeholder="Voucher #, Ref #, Description..."
                           class="w-full pl-9 pr-3 py-2 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"></i>
                </div>
            </div>

            <div>
                <label for="status" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.status') }}</label>
                <select id="status" name="status" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <option value="all">All Statuses</option>
                    <option value="posted" {{ request('status') === 'posted' ? 'selected' : '' }}>Posted</option>
                    <option value="reversed" {{ request('status') === 'reversed' ? 'selected' : '' }}>Reversed</option>
                </select>
            </div>

            <div>
                <label for="location_id" class="block text-xs font-bold text-slate-600 uppercase mb-1">Branch / Location</label>
                <select id="location_id" name="location_id" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <option value="all">All Authorized Branches</option>
                    @foreach($authorizedLocations as $loc)
                        <option value="{{ $loc->id }}" {{ request('location_id') == $loc->id ? 'selected' : '' }}>
                            {{ $loc->name }} ({{ $loc->code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="from_date" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.reports.from_date') }}</label>
                <input type="date" id="from_date" name="from_date" value="{{ request('from_date') }}"
                       class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 font-mono">
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="w-full py-2 bg-slate-900 text-white rounded-lg text-sm font-semibold hover:bg-slate-800 transition flex items-center justify-center gap-1.5">
                    <i data-lucide="filter" class="w-4 h-4"></i>
                    <span>{{ __('erp.filter') }}</span>
                </button>
                <a href="{{ route('accounting.journals.index') }}" class="px-3 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm font-semibold hover:bg-slate-200 transition">
                    {{ __('erp.reset') }}
                </a>
            </div>
        </form>
    </div>

    <!-- Journals Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50/75 border-b border-slate-200/80 text-xs uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3.5 font-bold">{{ __('erp.general_ledger.entry_number') }}</th>
                        <th class="px-4 py-3.5 font-bold">{{ __('erp.general_ledger.entry_date') }}</th>
                        <th class="px-4 py-3.5 font-bold">Branch / Scope</th>
                        <th class="px-4 py-3.5 font-bold">Source Module</th>
                        <th class="px-4 py-3.5 font-bold">Narration / Description</th>
                        <th class="px-4 py-3.5 font-bold text-right">{{ __('erp.general_ledger.total_debit') }}</th>
                        <th class="px-4 py-3.5 font-bold text-center">{{ __('erp.status') }}</th>
                        <th class="px-4 py-3.5 font-bold text-right">{{ __('erp.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($entries as $entry)
                        @php
                            $statusBadge = match($entry->status) {
                                'posted' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                'reversed' => 'bg-rose-50 text-rose-800 border-rose-200 line-through',
                                default => 'bg-slate-100 text-slate-700'
                            };
                        @endphp
                        <tr class="hover:bg-slate-50/60 transition {{ $entry->status === 'reversed' ? 'opacity-60' : '' }}">
                            <td class="px-4 py-3 font-mono font-bold text-indigo-700">
                                <a href="{{ route('accounting.journals.show', $entry) }}" class="hover:underline">
                                    {{ $entry->entry_number }}
                                </a>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-800">
                                {{ $entry->entry_date->format('M d, Y') }}
                            </td>
                            <td class="px-4 py-3 text-xs">
                                @if($entry->location)
                                    <span class="font-semibold text-slate-800">{{ $entry->location->code }}</span>
                                    <div class="text-slate-400">{{ $entry->location->name }}</div>
                                @else
                                    <span class="text-slate-400 font-mono">Global / KT HQ</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <span class="px-2 py-0.5 rounded bg-slate-100 border border-slate-200 text-slate-700 font-mono capitalize">
                                    {{ str_replace('_', ' ', $entry->source_type ?? 'manual') }}
                                </span>
                                @if($entry->reference_number)
                                    <div class="text-[11px] text-slate-400 font-mono mt-0.5">Ref: {{ $entry->reference_number }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 max-w-xs truncate text-slate-700" title="{{ $entry->description }}">
                                {{ $entry->description }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">
                                Rs. {{ number_format($entry->total_debit, 2) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border {{ $statusBadge }}">
                                    {{ ucfirst($entry->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('accounting.journals.show', $entry) }}" class="p-1.5 text-slate-500 hover:text-indigo-600 hover:bg-slate-100 rounded-lg transition inline-flex items-center gap-1 text-xs font-semibold">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                    <span>View</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-slate-400">
                                <i data-lucide="book-open" class="w-10 h-10 mx-auto mb-2 text-slate-300"></i>
                                <p class="text-sm font-semibold">{{ __('erp.general_ledger.no_entries') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($entries->hasPages())
            <div class="px-4 py-3 border-t border-slate-200/80 bg-slate-50/50">
                {{ $entries->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
