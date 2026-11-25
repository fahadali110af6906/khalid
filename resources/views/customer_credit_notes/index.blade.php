@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.customer_credit_notes.title') }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                    Receivables Reductions & Allowances
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">{{ __('erp.customer_credit_notes.subtitle') }}</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('customer_credit_notes.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition shadow-sm">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>{{ __('erp.customer_credit_notes.create_credit_note') }}</span>
            </a>
            <a href="{{ route('sales.returns.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-bold transition">
                <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                <span>{{ __('erp.sales_returns.title') }}</span>
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
            <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Credit Notes</p>
                <p class="text-2xl font-black text-slate-900 mt-1 font-mono">{{ $creditNotes->total() }}</p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600">
                <i data-lucide="file-plus" class="w-6 h-6"></i>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Stock Returns</p>
                <p class="text-2xl font-black text-amber-600 mt-1 font-mono">
                    {{ $creditNotes->where('credit_note_type', 'sales_return')->count() }}
                </p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-600">
                <i data-lucide="rotate-ccw" class="w-6 h-6"></i>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Financial Only</p>
                <p class="text-2xl font-black text-purple-600 mt-1 font-mono">
                    {{ $creditNotes->where('credit_note_type', 'financial_adjustment')->count() }}
                </p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-purple-50 border border-purple-100 flex items-center justify-center text-purple-600">
                <i data-lucide="badge-percent" class="w-6 h-6"></i>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Page Total Credited</p>
                <p class="text-xl font-black text-slate-900 mt-1 font-mono">
                    ₨ {{ number_format($creditNotes->sum('amount'), 2) }}
                </p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600">
                <i data-lucide="calculator" class="w-6 h-6"></i>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
        <form method="GET" action="{{ route('customer_credit_notes.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div>
                <label for="search" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.search') }}</label>
                <div class="relative">
                    <input type="text" id="search" name="search" value="{{ $search }}"
                           placeholder="CN #, reference, reason..."
                           class="w-full pl-9 pr-3 py-2 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"></i>
                </div>
            </div>

            <div>
                <label for="type" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.customer_credit_notes.credit_note_type') }}</label>
                <select id="type" name="type" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <option value="all" {{ $type === 'all' ? 'selected' : '' }}>All Types</option>
                    <option value="sales_return" {{ $type === 'sales_return' ? 'selected' : '' }}>Sales Return</option>
                    <option value="financial_adjustment" {{ $type === 'financial_adjustment' ? 'selected' : '' }}>Financial Adjustment</option>
                </select>
            </div>

            <div>
                <label for="location_id" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.location') }}</label>
                <select id="location_id" name="location_id" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    @if(auth()->user()->isOwner())
                        <option value="all" {{ $locationId === 'all' ? 'selected' : '' }}>All Locations</option>
                    @endif
                    @foreach($authorizedLocations as $loc)
                        <option value="{{ $loc->id }}" {{ (string)$locationId === (string)$loc->id ? 'selected' : '' }}>
                            {{ $loc->name }} ({{ $loc->code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="customer_id" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.customers.title') }}</label>
                <select id="customer_id" name="customer_id" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <option value="">All Customers</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}" {{ (string)$customerId === (string)$c->id ? 'selected' : '' }}>
                            {{ $c->business_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2 px-4 bg-slate-800 hover:bg-slate-900 text-white rounded-lg text-sm font-semibold transition flex items-center justify-center gap-1.5 shadow-sm">
                    <i data-lucide="filter" class="w-4 h-4"></i>
                    <span>Filter</span>
                </button>
                <a href="{{ route('customer_credit_notes.index') }}" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-sm font-medium transition" title="Clear Filters">
                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Credit Notes Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-500 font-bold text-xs uppercase tracking-wider">
                        <th class="py-3 px-4">{{ __('erp.customer_credit_notes.credit_note_number') }}</th>
                        <th class="py-3 px-4">{{ __('erp.customers.title') }}</th>
                        <th class="py-3 px-4">{{ __('erp.location') }}</th>
                        <th class="py-3 px-4">{{ __('erp.customer_credit_notes.note_date') }}</th>
                        <th class="py-3 px-4">{{ __('erp.customer_credit_notes.credit_note_type') }}</th>
                        <th class="py-3 px-4">Reason / Notes</th>
                        <th class="py-3 px-4 text-right">Amount Credited</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($creditNotes as $cn)
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="py-3 px-4 font-mono font-bold text-indigo-600">
                                <a href="{{ route('customer_credit_notes.show', $cn) }}" class="hover:underline">
                                    {{ $cn->credit_note_number }}
                                </a>
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-bold text-slate-800">{{ $cn->customer->business_name }}</span>
                                <span class="block text-xs text-slate-400">{{ $cn->customer->city }}</span>
                            </td>
                            <td class="py-3 px-4 text-slate-600">
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-semibold bg-slate-100 text-slate-700">
                                    {{ $cn->location->code }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-slate-600 font-mono text-xs">
                                {{ $cn->note_date->format('Y-m-d') }}
                            </td>
                            <td class="py-3 px-4">
                                @if($cn->credit_note_type === 'sales_return')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                                        <i data-lucide="rotate-ccw" class="w-3 h-3"></i>
                                        <span>Sales Return</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-100 text-purple-800">
                                        <i data-lucide="badge-percent" class="w-3 h-3"></i>
                                        <span>Financial</span>
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-slate-600 max-w-xs truncate">
                                <span class="font-semibold text-slate-800">{{ $cn->reason }}</span>
                                @if($cn->reference_number)
                                    <span class="block text-xs text-slate-400 font-mono">Ref: {{ $cn->reference_number }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-black text-emerald-700">
                                ₨ {{ number_format($cn->amount, 2) }}
                            </td>
                            <td class="py-3 px-4 text-right">
                                <a href="{{ route('customer_credit_notes.show', $cn) }}" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                    <span>View</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-slate-400">
                                <div class="flex flex-col items-center justify-center">
                                    <i data-lucide="file-plus" class="w-12 h-12 text-slate-300 stroke-1 mb-2"></i>
                                    <p class="text-base font-semibold text-slate-600">{{ __('erp.customer_credit_notes.no_credit_notes') }}</p>
                                    <p class="text-xs text-slate-400 mt-1">Create a new customer credit note to record special discounts, rebates, or return adjustments.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($creditNotes->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $creditNotes->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
