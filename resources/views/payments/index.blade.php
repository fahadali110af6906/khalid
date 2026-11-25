@extends('layouts.app')

@section('title', __('erp.payments.title') . ' — Khalid Traders ERP')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800">
        <div>
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 rounded-xl">
                    <i data-lucide="banknote" class="w-6 h-6"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('erp.payments.title') }}</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('erp.payments.subtitle') }}</p>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-3 w-full sm:w-auto">
            <button type="button" onclick="document.getElementById('directPaymentModal').classList.remove('hidden')" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-semibold shadow-sm shadow-emerald-600/30 transition-colors">
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>{{ __('erp.payments.record_advance') }}</span>
            </button>
        </div>
    </div>

    @if (session('success'))
        <div class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-xl text-emerald-800 dark:text-emerald-300 text-sm flex items-center gap-3">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0"></i>
            <div>{{ session('success') }}</div>
        </div>
    @endif

    <!-- Filters -->
    <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <form method="GET" action="{{ route('payments.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">{{ __('erp.search') }}</label>
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 absolute start-3 top-3 text-slate-400"></i>
                    <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('erp.payments.payment_number') }} / {{ __('erp.customers.business_name') }}"
                           class="w-full ps-9 pe-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">{{ __('erp.location') }}</label>
                <select name="location_id" class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
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
                <select name="customer_id" class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <option value="">{{ __('erp.customers.all_status') }}</option>
                    @foreach ($customers as $cust)
                        <option value="{{ $cust->id }}" {{ (string)$customerId === (string)$cust->id ? 'selected' : '' }}>
                            {{ $cust->business_name }} ({{ $cust->customer_code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">{{ __('erp.payments.payment_method') }}</label>
                <select name="payment_method" class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <option value="all" {{ $paymentMethod === 'all' ? 'selected' : '' }}>{{ __('erp.customers.all_status') }}</option>
                    <option value="cash" {{ $paymentMethod === 'cash' ? 'selected' : '' }}>{{ __('erp.cash') }}</option>
                    <option value="bank_transfer" {{ $paymentMethod === 'bank_transfer' ? 'selected' : '' }}>{{ __('erp.bank') }}</option>
                    <option value="cheque" {{ $paymentMethod === 'cheque' ? 'selected' : '' }}>{{ __('erp.cheque') }}</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2 px-3 bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-sm font-semibold rounded-xl transition-colors">
                    {{ __('erp.filter') }}
                </button>
                <a href="{{ route('payments.index') }}" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 text-sm font-semibold rounded-xl transition-colors">
                    {{ __('erp.customers.reset_filter') }}
                </a>
            </div>
        </form>
    </div>

    <!-- Payments Table -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-start text-sm text-slate-600 dark:text-slate-300">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 text-xs uppercase font-bold tracking-wider border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="px-5 py-3.5 text-start">{{ __('erp.payments.payment_number') }}</th>
                        <th class="px-4 py-3.5 text-start">{{ __('erp.date') }}</th>
                        <th class="px-4 py-3.5 text-start">{{ __('erp.invoices.customer') }}</th>
                        <th class="px-4 py-3.5 text-start">{{ __('erp.location') }}</th>
                        <th class="px-4 py-3.5 text-start">{{ __('erp.invoices.invoice_number') }}</th>
                        <th class="px-4 py-3.5 text-start">{{ __('erp.payments.payment_method') }}</th>
                        <th class="px-4 py-3.5 text-start">{{ __('erp.payments.reference_number') }}</th>
                        <th class="px-4 py-3.5 text-end">{{ __('erp.payments.amount') }}</th>
                        <th class="px-5 py-3.5 text-end">{{ __('erp.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                    @forelse ($payments as $pay)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="px-5 py-4 font-mono font-bold text-slate-900 dark:text-white">
                                <a href="{{ route('payments.show', $pay) }}" class="text-emerald-600 dark:text-emerald-400 hover:underline">
                                    {{ $pay->payment_number }}
                                </a>
                            </td>
                            <td class="px-4 py-4 text-xs">
                                {{ $pay->payment_date->format('Y-m-d') }}
                            </td>
                            <td class="px-4 py-4">
                                <div class="font-semibold text-slate-900 dark:text-white">{{ $pay->customer->business_name }}</div>
                                <div class="text-xs text-slate-400 font-mono">{{ $pay->customer->customer_code }}</div>
                            </td>
                            <td class="px-4 py-4 text-xs font-mono">
                                <span class="px-2 py-1 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-md">
                                    {{ $pay->location->code }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-xs font-mono">
                                @if ($pay->invoice)
                                    <a href="{{ route('sales.invoices.show', $pay->invoice) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline font-semibold">
                                        {{ $pay->invoice->invoice_number }}
                                    </a>
                                @else
                                    <span class="text-slate-400 italic">On-Account Advance</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-xs">
                                <span class="px-2.5 py-1 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 rounded-md font-semibold capitalize">
                                    {{ str_replace('_', ' ', $pay->payment_method) }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-xs text-slate-400 font-mono">
                                {{ $pay->reference_number ?? '—' }}
                            </td>
                            <td class="px-4 py-4 text-end font-mono font-bold text-emerald-600 dark:text-emerald-400 text-base">
                                {{ number_format($pay->amount, 2) }}
                            </td>
                            <td class="px-5 py-4 text-end">
                                <a href="{{ route('payments.show', $pay) }}" class="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-lg transition-colors inline-block" title="View Voucher">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-slate-400">
                                <i data-lucide="receipt" class="w-10 h-10 mx-auto text-slate-300 dark:text-slate-600 mb-3"></i>
                                <p class="font-semibold">{{ __('erp.payments.no_payments') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($payments->hasPages())
            <div class="p-4 border-t border-slate-100 dark:border-slate-800">
                {{ $payments->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Modal for Direct On-Account Advance Payment -->
<div id="directPaymentModal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 dark:border-slate-800 space-y-4">
        <div class="flex justify-between items-center pb-3 border-b border-slate-100 dark:border-slate-800">
            <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ __('erp.payments.record_advance') }}</h3>
            <button type="button" onclick="document.getElementById('directPaymentModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 dark:hover:text-white">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('payments.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">
                    {{ __('erp.invoices.customer') }} <span class="text-rose-500">*</span>
                </label>
                <select name="customer_id" required class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white">
                    <option value="">-- Select Customer --</option>
                    @foreach ($customers as $c)
                        <option value="{{ $c->id }}">{{ $c->business_name }} ({{ $c->customer_code }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">
                    {{ __('erp.location') }} <span class="text-rose-500">*</span>
                </label>
                <select name="location_id" required class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white">
                    @foreach ($authorizedLocations as $loc)
                        <option value="{{ $loc->id }}">{{ $loc->name }} ({{ $loc->code }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">
                    {{ __('erp.payments.amount') }} <span class="text-rose-500">*</span>
                </label>
                <input type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00"
                       class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white font-mono font-bold">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">
                        {{ __('erp.date') }} <span class="text-rose-500">*</span>
                    </label>
                    <input type="date" name="payment_date" value="{{ now()->toDateString() }}" required
                           class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">
                        {{ __('erp.payments.payment_method') }} <span class="text-rose-500">*</span>
                    </label>
                    <select name="payment_method" required class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white">
                        <option value="cash">{{ __('erp.cash') }}</option>
                        <option value="bank_transfer">{{ __('erp.bank') }}</option>
                        <option value="cheque">{{ __('erp.cheque') }}</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">
                    {{ __('erp.payments.reference_number') }}
                </label>
                <input type="text" name="reference_number" placeholder="Optional Cheque/Slip #"
                       class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white">
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('directPaymentModal').classList.add('hidden')" class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl">
                    {{ __('erp.cancel') }}
                </button>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-xl shadow-sm">
                    {{ __('erp.payments.record_payment') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
