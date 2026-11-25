@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.supplier_payments.title') }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                    Accounts Payable
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">{{ __('erp.supplier_payments.subtitle') }}</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('supplier-payments.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition shadow-sm">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>{{ __('erp.supplier_payments.record_payment') }}</span>
            </a>
        </div>
    </div>

    <!-- Alert Success -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium flex items-center gap-2">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Filter Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
        <form method="GET" action="{{ route('supplier-payments.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div>
                <label for="search" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.search') }}</label>
                <div class="relative">
                    <input type="text" id="search" name="search" value="{{ $search }}"
                           placeholder="Voucher #, Ref #, Supplier..."
                           class="w-full pl-9 pr-3 py-2 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"></i>
                </div>
            </div>

            <div>
                <label for="supplier_id" class="block text-xs font-bold text-slate-600 uppercase mb-1">Supplier</label>
                <select id="supplier_id" name="supplier_id" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <option value="">All Suppliers</option>
                    @foreach($suppliers as $s)
                        <option value="{{ $s->id }}" {{ $supplierId == $s->id ? 'selected' : '' }}>
                            {{ $s->business_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="location_id" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.location') }}</label>
                <select id="location_id" name="location_id" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    @if(auth()->user()->isOwner())
                        <option value="all" {{ $locationId === 'all' || !$locationId ? 'selected' : '' }}>🏢 {{ __('erp.all_locations') }}</option>
                    @endif
                    @foreach($authorizedLocations as $loc)
                        <option value="{{ $loc->id }}" {{ $locationId == $loc->id ? 'selected' : '' }}>
                            {{ $loc->type === 'factory' ? '🏭' : '🏪' }} {{ $loc->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="payment_method" class="block text-xs font-bold text-slate-600 uppercase mb-1">Payment Method</label>
                <select id="payment_method" name="payment_method" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <option value="all" {{ $paymentMethod === 'all' ? 'selected' : '' }}>All Methods</option>
                    <option value="cash" {{ $paymentMethod === 'cash' ? 'selected' : '' }}>Cash</option>
                    <option value="bank_transfer" {{ $paymentMethod === 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                    <option value="cheque" {{ $paymentMethod === 'cheque' ? 'selected' : '' }}>Cheque</option>
                    <option value="online" {{ $paymentMethod === 'online' ? 'selected' : '' }}>Online</option>
                    <option value="other" {{ $paymentMethod === 'other' ? 'selected' : '' }}>Other</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2 px-4 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-bold transition flex items-center justify-center gap-1.5">
                    <i data-lucide="filter" class="w-4 h-4"></i>
                    <span>{{ __('erp.filter') }}</span>
                </button>
                <a href="{{ route('supplier-payments.index') }}" class="py-2 px-3 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold transition" title="Reset filter">
                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Payments Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left rtl:text-right text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500 font-bold border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-5">Voucher #</th>
                        <th class="py-3 px-5">Supplier</th>
                        <th class="py-3 px-5">Payment Date</th>
                        <th class="py-3 px-5">Branch / Location</th>
                        <th class="py-3 px-5">Method & Reference</th>
                        <th class="py-3 px-5 text-end">Amount Paid (PKR)</th>
                        <th class="py-3 px-5 text-end">{{ __('erp.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($payments as $payment)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3 px-5 font-mono text-xs text-indigo-700 font-bold">
                                <a href="{{ route('supplier-payments.show', $payment) }}" class="hover:underline">
                                    {{ $payment->payment_number }}
                                </a>
                            </td>
                            <td class="py-3 px-5 font-semibold text-slate-900">
                                <a href="{{ route('suppliers.show', $payment->supplier) }}" class="hover:underline hover:text-indigo-600">
                                    {{ $payment->supplier->business_name }}
                                </a>
                            </td>
                            <td class="py-3 px-5 text-slate-600 text-xs font-mono">
                                {{ $payment->payment_date->format('d M Y') }}
                            </td>
                            <td class="py-3 px-5">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold bg-slate-100 text-slate-700">
                                    {{ $payment->location->name }}
                                </span>
                            </td>
                            <td class="py-3 px-5 text-xs text-slate-700">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-100 text-slate-700">
                                    {{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}
                                </span>
                                @if($payment->reference_number)
                                    <span class="block text-[11px] text-slate-500 font-mono mt-0.5">Ref: {{ $payment->reference_number }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-5 text-end font-mono font-bold text-emerald-600 text-sm">
                                PKR {{ number_format($payment->amount, 2) }}
                            </td>
                            <td class="py-3 px-5 text-end">
                                <a href="{{ route('supplier-payments.show', $payment) }}"
                                   class="px-2.5 py-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold transition inline-flex items-center gap-1">
                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                    <span>Voucher</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400">
                                <i data-lucide="credit-card" class="w-10 h-10 mx-auto mb-2 text-slate-300"></i>
                                <p class="font-medium">{{ __('erp.supplier_payments.no_payments') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($payments->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $payments->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
