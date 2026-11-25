@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.sales_returns.title') }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                    Restock & Khata Credits
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">{{ __('erp.sales_returns.subtitle') }}</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('sales.invoices.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition shadow-sm">
                <i data-lucide="file-search" class="w-4 h-4"></i>
                <span>Find Invoice to Return</span>
            </a>
            <a href="{{ route('customer_credit_notes.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-bold transition">
                <i data-lucide="file-plus" class="w-4 h-4"></i>
                <span>{{ __('erp.customer_credit_notes.title') }}</span>
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
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Returns</p>
                <p class="text-2xl font-black text-slate-900 mt-1 font-mono">{{ $returns->total() }}</p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600">
                <i data-lucide="rotate-ccw" class="w-6 h-6"></i>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Draft Returns</p>
                <p class="text-2xl font-black text-amber-600 mt-1 font-mono">
                    {{ $returns->where('status', 'draft')->count() }}
                </p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-600">
                <i data-lucide="clock" class="w-6 h-6"></i>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Posted Returns</p>
                <p class="text-2xl font-black text-emerald-600 mt-1 font-mono">
                    {{ $returns->where('status', 'posted')->count() }}
                </p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600">
                <i data-lucide="check-circle" class="w-6 h-6"></i>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Page Value (PKR)</p>
                <p class="text-xl font-black text-slate-900 mt-1 font-mono">
                    ₨ {{ number_format($returns->sum('total_return_amount'), 2) }}
                </p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-purple-50 border border-purple-100 flex items-center justify-center text-purple-600">
                <i data-lucide="banknote" class="w-6 h-6"></i>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
        <form method="GET" action="{{ route('sales.returns.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div>
                <label for="search" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.search') }}</label>
                <div class="relative">
                    <input type="text" id="search" name="search" value="{{ $search }}"
                           placeholder="Return #, Invoice #, reason..."
                           class="w-full pl-9 pr-3 py-2 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"></i>
                </div>
            </div>

            <div>
                <label for="status" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.status') }}</label>
                <select id="status" name="status" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All Statuses</option>
                    <option value="draft" {{ $status === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="posted" {{ $status === 'posted' ? 'selected' : '' }}>Posted</option>
                    <option value="reversed" {{ $status === 'reversed' ? 'selected' : '' }}>Reversed</option>
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
                <a href="{{ route('sales.returns.index') }}" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-sm font-medium transition" title="Clear Filters">
                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Returns Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-500 font-bold text-xs uppercase tracking-wider">
                        <th class="py-3 px-4">{{ __('erp.sales_returns.return_number') }}</th>
                        <th class="py-3 px-4">{{ __('erp.sales_returns.invoice_number') }}</th>
                        <th class="py-3 px-4">{{ __('erp.customers.title') }}</th>
                        <th class="py-3 px-4">{{ __('erp.location') }}</th>
                        <th class="py-3 px-4">{{ __('erp.sales_returns.return_date') }}</th>
                        <th class="py-3 px-4 text-right">{{ __('erp.sales_returns.returned_cartons') }}</th>
                        <th class="py-3 px-4 text-right">{{ __('erp.sales_returns.returned_pairs') }}</th>
                        <th class="py-3 px-4 text-right">{{ __('erp.sales_returns.total_amount') }}</th>
                        <th class="py-3 px-4 text-center">{{ __('erp.status') }}</th>
                        <th class="py-3 px-4 text-center">{{ __('erp.sales_returns.refund_status') }}</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($returns as $ret)
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="py-3 px-4 font-mono font-bold text-indigo-600">
                                <a href="{{ route('sales.returns.show', $ret) }}" class="hover:underline">
                                    {{ $ret->return_number }}
                                </a>
                            </td>
                            <td class="py-3 px-4 font-mono text-slate-700">
                                <a href="{{ route('sales.invoices.show', $ret->salesInvoice) }}" class="hover:underline text-indigo-600">
                                    {{ $ret->salesInvoice->invoice_number }}
                                </a>
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-bold text-slate-800">{{ $ret->customer->business_name }}</span>
                                <span class="block text-xs text-slate-400">{{ $ret->customer->city }}</span>
                            </td>
                            <td class="py-3 px-4 text-slate-600">
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-semibold bg-slate-100 text-slate-700">
                                    {{ $ret->location->code }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-slate-600 font-mono text-xs">
                                {{ $ret->return_date->format('Y-m-d') }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-slate-700">
                                {{ number_format($ret->total_returned_cartons, 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-black text-slate-900">
                                {{ number_format($ret->total_returned_pairs, 0) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-black text-indigo-700">
                                ₨ {{ number_format($ret->total_return_amount, 2) }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if($ret->status === 'posted')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                        Posted
                                    </span>
                                @elseif($ret->status === 'draft')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                                        Draft
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700">
                                        {{ ucfirst($ret->status) }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if($ret->refund_status === 'refunded')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-purple-100 text-purple-800">
                                        Refunded
                                    </span>
                                @elseif($ret->refund_status === 'partially_refunded')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-800">
                                        Partial Refund
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-slate-100 text-slate-600">
                                        Khata Credit
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right">
                                <a href="{{ route('sales.returns.show', $ret) }}" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                    <span>View</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="py-12 text-center text-slate-400">
                                <div class="flex flex-col items-center justify-center">
                                    <i data-lucide="rotate-ccw" class="w-12 h-12 text-slate-300 stroke-1 mb-2"></i>
                                    <p class="text-base font-semibold text-slate-600">{{ __('erp.sales_returns.no_returns') }}</p>
                                    <p class="text-xs text-slate-400 mt-1">Open a posted sales invoice to create a return voucher.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($returns->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $returns->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
