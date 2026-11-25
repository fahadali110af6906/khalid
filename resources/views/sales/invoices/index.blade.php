@extends('layouts.app')

@section('title', __('erp.invoices.title') . ' — Khalid Traders ERP')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800">
        <div>
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 rounded-xl">
                    <i data-lucide="receipt" class="w-6 h-6"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('erp.invoices.title') }}</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('erp.invoices.subtitle') }}</p>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-3 w-full sm:w-auto">
            <a href="{{ route('sales.invoices.create') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-semibold shadow-sm shadow-indigo-600/30 transition-colors">
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>{{ __('erp.invoices.create_invoice') }}</span>
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-xl text-emerald-800 dark:text-emerald-300 text-sm flex items-center gap-3">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0"></i>
            <div>{{ session('success') }}</div>
        </div>
    @endif

    <!-- Filters & Search -->
    <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <form method="GET" action="{{ route('sales.invoices.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">{{ __('erp.search') }}</label>
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 absolute start-3 top-3 text-slate-400"></i>
                    <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('erp.invoices.invoice_number') }} / {{ __('erp.customers.business_name') }}"
                           class="w-full ps-9 pe-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">{{ __('erp.location') }}</label>
                <select name="location_id" class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="all">{{ __('erp.all_locations') }}</option>
                    @foreach ($authorizedLocations as $loc)
                        <option value="{{ $loc->id }}" {{ (string)$locationId === (string)$loc->id ? 'selected' : '' }}>
                            {{ $loc->name }} ({{ $loc->code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">{{ __('erp.invoices.customer') }}</label>
                <select name="customer_id" class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">{{ __('erp.customers.all_status') }}</option>
                    @foreach ($customers as $cust)
                        <option value="{{ $cust->id }}" {{ (string)$customerId === (string)$cust->id ? 'selected' : '' }}>
                            {{ $cust->business_name }} ({{ $cust->customer_code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">{{ __('erp.invoices.status') }}</label>
                <select name="status" class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="all" {{ $status === 'all' ? 'selected' : '' }}>{{ __('erp.customers.all_status') }}</option>
                    <option value="draft" {{ $status === 'draft' ? 'selected' : '' }}>{{ __('erp.invoices.draft') }}</option>
                    <option value="posted" {{ $status === 'posted' ? 'selected' : '' }}>{{ __('erp.invoices.posted') }}</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2 px-3 bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-sm font-semibold rounded-xl transition-colors">
                    {{ __('erp.filter') }}
                </button>
                <a href="{{ route('sales.invoices.index') }}" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 text-sm font-semibold rounded-xl transition-colors">
                    {{ __('erp.customers.reset_filter') }}
                </a>
            </div>
        </form>
    </div>

    <!-- Invoices Table -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-start text-sm text-slate-600 dark:text-slate-300">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 text-xs uppercase font-bold tracking-wider border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="px-5 py-3.5 text-start">{{ __('erp.invoices.invoice_number') }}</th>
                        <th class="px-4 py-3.5 text-start">{{ __('erp.date') }}</th>
                        <th class="px-4 py-3.5 text-start">{{ __('erp.invoices.customer') }}</th>
                        <th class="px-4 py-3.5 text-start">{{ __('erp.location') }}</th>
                        <th class="px-4 py-3.5 text-end">{{ __('erp.invoices.quantity') }}</th>
                        <th class="px-4 py-3.5 text-end">{{ __('erp.total') }} (PKR)</th>
                        <th class="px-4 py-3.5 text-end">{{ __('erp.invoices.paid_amount') }}</th>
                        <th class="px-4 py-3.5 text-end">{{ __('erp.invoices.balance_amount') }}</th>
                        <th class="px-4 py-3.5 text-center">{{ __('erp.status') }}</th>
                        <th class="px-5 py-3.5 text-end">{{ __('erp.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                    @forelse ($invoices as $inv)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="px-5 py-4 font-mono font-bold text-slate-900 dark:text-white">
                                <a href="{{ route('sales.invoices.show', $inv) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                    {{ $inv->invoice_number }}
                                </a>
                            </td>
                            <td class="px-4 py-4 text-xs">
                                {{ $inv->invoice_date->format('Y-m-d') }}
                            </td>
                            <td class="px-4 py-4">
                                <div class="font-semibold text-slate-900 dark:text-white">{{ $inv->customer->business_name }}</div>
                                <div class="text-xs text-slate-400 font-mono">{{ $inv->customer->customer_code }}</div>
                            </td>
                            <td class="px-4 py-4 text-xs">
                                <span class="px-2 py-1 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-md font-mono">
                                    {{ $inv->location->code }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-end text-xs font-mono">
                                <span class="font-bold text-slate-800 dark:text-slate-200">{{ $inv->total_cartons }} ctn</span>
                                <span class="text-slate-400 block">({{ number_format($inv->total_pairs, 0) }} prs)</span>
                            </td>
                            <td class="px-4 py-4 text-end font-mono font-bold text-slate-900 dark:text-white">
                                {{ number_format($inv->total_amount, 2) }}
                            </td>
                            <td class="px-4 py-4 text-end font-mono text-emerald-600 dark:text-emerald-400 font-semibold">
                                {{ number_format($inv->paid_amount, 2) }}
                            </td>
                            <td class="px-4 py-4 text-end font-mono {{ (float)$inv->balance_amount > 0 ? 'text-amber-600 dark:text-amber-400 font-bold' : 'text-slate-400' }}">
                                {{ number_format($inv->balance_amount, 2) }}
                            </td>
                            <td class="px-4 py-4 text-center">
                                @if ($inv->isPosted())
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">
                                        <i data-lucide="check" class="w-3 h-3"></i>
                                        {{ __('erp.invoices.posted') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400 border border-amber-200/60 dark:border-amber-800/60">
                                        <i data-lucide="clock" class="w-3 h-3"></i>
                                        {{ __('erp.invoices.draft') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-end">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('sales.invoices.show', $inv) }}" class="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-lg transition-colors" title="{{ __('erp.customers.view_khata') }}">
                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                    </a>
                                    <a href="{{ route('sales.invoices.print', $inv) }}" target="_blank" class="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-lg transition-colors" title="{{ __('erp.print') }}">
                                        <i data-lucide="printer" class="w-4 h-4"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-6 py-12 text-center text-slate-400">
                                <div class="max-w-xs mx-auto">
                                    <i data-lucide="inbox" class="w-10 h-10 mx-auto text-slate-300 dark:text-slate-600 mb-3"></i>
                                    <p class="font-semibold">{{ __('erp.invoices.no_invoices') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($invoices->hasPages())
            <div class="p-4 border-t border-slate-100 dark:border-slate-800">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
