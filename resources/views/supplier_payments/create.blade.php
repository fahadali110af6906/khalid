@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.supplier_payments.record_payment') }}</h1>
            <p class="text-sm text-slate-500 mt-1">Disburse cash or bank payment to a supplier, reducing outstanding Khata payable.</p>
        </div>
        <a href="{{ route('supplier-payments.index') }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
            <i data-lucide="arrow-left" class="w-4 h-4 rtl:rotate-180"></i>
            <span>Back to Payments</span>
        </a>
    </div>

    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">
            <p class="font-bold flex items-center gap-1.5 mb-2">
                <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-600"></i>
                <span>Please correct the errors below:</span>
            </p>
            <ul class="list-disc list-inside space-y-0.5 text-xs text-rose-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Form Card -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 md:p-8">
        <form method="POST" action="{{ route('supplier-payments.store') }}" class="space-y-6" id="supplierPaymentForm" onsubmit="var btn=document.getElementById('paymentSubmitBtn'); if(btn){ btn.disabled=true; btn.classList.add('opacity-50','cursor-not-allowed'); }">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">

            <!-- Supplier & Current Balance Alert -->
            <div>
                <label for="supplier_id" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                    Supplier / Vendor <span class="text-rose-500">*</span>
                </label>
                <select id="supplier_id" name="supplier_id" required onchange="window.location.href='{{ route('supplier-payments.create') }}?supplier_id=' + this.value"
                        class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">-- Select Supplier --</option>
                    @foreach ($suppliers as $s)
                        <option value="{{ $s->id }}" {{ (old('supplier_id', $selectedSupplier?->id) == $s->id) ? 'selected' : '' }}>
                            {{ $s->business_name }} ({{ $s->supplier_code }})
                        </option>
                    @endforeach
                </select>
            </div>

            @if($supplierBalance)
                @php
                    $closeBal = (float) $supplierBalance['closing_balance'];
                @endphp
                <div class="p-4 rounded-xl border {{ $closeBal > 0 ? 'bg-amber-50 border-amber-200 text-amber-900' : 'bg-slate-50 border-slate-200 text-slate-800' }} flex items-center justify-between text-xs">
                    <div>
                        <span class="text-slate-500 font-semibold uppercase text-[10px] block">Current Khata Balance</span>
                        <span class="text-base font-bold font-mono">
                            PKR {{ number_format(abs($closeBal), 2) }}
                        </span>
                        <span class="ms-2 font-bold uppercase text-[10px] {{ $closeBal > 0 ? 'text-amber-800' : 'text-slate-600' }}">
                            {{ $closeBal > 0 ? 'Payable (واجب الادا)' : ($closeBal < 0 ? 'Advance (پیشگی)' : 'Settled') }}
                        </span>
                    </div>
                    <a href="{{ route('suppliers.ledger', $selectedSupplier) }}" target="_blank" class="font-bold underline text-indigo-700 text-xs">
                        View Full Khata
                    </a>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="location_id" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                        Disbursing Location / Cash Drawer <span class="text-rose-500">*</span>
                    </label>
                    <select id="location_id" name="location_id" required
                            class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @foreach ($authorizedLocations as $loc)
                            <option value="{{ $loc->id }}" {{ old('location_id', $selectedSupplier?->primary_location_id) == $loc->id ? 'selected' : '' }}>
                                {{ $loc->type === 'factory' ? '🏭' : '🏪' }} {{ $loc->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="payment_date" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                        Payment Date <span class="text-rose-500">*</span>
                    </label>
                    <input type="date" id="payment_date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" required
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label for="amount" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                        Amount to Pay (PKR) <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-2.5 text-xs font-bold text-slate-400 font-mono">PKR</span>
                        <input type="number" step="0.01" min="0.01" id="amount" name="amount" value="{{ old('amount', $selectedOrder?->total_amount) }}" required
                               placeholder="0.00"
                               class="w-full pl-14 pr-3.5 py-2.5 text-sm font-bold font-mono rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div>
                    <label for="payment_method" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                        Payment Mode <span class="text-rose-500">*</span>
                    </label>
                    <select id="payment_method" name="payment_method" required
                            class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="cash" {{ old('payment_method') === 'cash' ? 'selected' : '' }}>Cash</option>
                        <option value="bank_transfer" {{ old('payment_method') === 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                        <option value="cheque" {{ old('payment_method') === 'cheque' ? 'selected' : '' }}>Cheque</option>
                        <option value="online" {{ old('payment_method') === 'online' ? 'selected' : '' }}>Online / Raast / EasyPaisa</option>
                        <option value="other" {{ old('payment_method') === 'other' ? 'selected' : '' }}>Other</option>
                    </select>
                </div>

                @if($selectedOrder)
                    <input type="hidden" name="purchase_order_id" value="{{ $selectedOrder->id }}">
                    <div class="md:col-span-2">
                        <span class="text-xs text-slate-500 block">
                            Paying against Purchase Order: <strong class="font-mono text-slate-800">{{ $selectedOrder->purchase_number }}</strong>
                        </span>
                    </div>
                @endif

                <div class="md:col-span-2">
                    <label for="reference_number" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                        Bank Reference / Cheque # / Transaction ID
                    </label>
                    <input type="text" id="reference_number" name="reference_number" value="{{ old('reference_number') }}"
                           placeholder="e.g. HBL Cheque # 984521 / IBFT Ref 100982"
                           class="w-full px-3.5 py-2.5 text-sm font-mono rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="md:col-span-2">
                    <label for="notes" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                        Payment Remarks
                    </label>
                    <textarea id="notes" name="notes" rows="2" placeholder="e.g. Advance for summer article production..."
                              class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                <a href="{{ route('supplier-payments.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 text-sm font-semibold hover:bg-slate-50 transition">
                    Cancel
                </a>
                <button type="submit" id="paymentSubmitBtn" class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold transition shadow-sm flex items-center gap-2">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    <span>Confirm & Record Payment</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
