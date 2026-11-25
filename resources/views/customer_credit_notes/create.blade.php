@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('customer_credit_notes.index') }}" class="text-xs font-semibold text-slate-500 hover:text-indigo-600 transition">
                    {{ __('erp.customer_credit_notes.title') }}
                </a>
                <span class="text-slate-400 text-xs">/</span>
                <span class="text-xs font-bold text-slate-800">New Financial Credit Note</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 mt-1">
                {{ __('erp.customer_credit_notes.create_credit_note') }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Post an approved commercial adjustment, price difference, or volume rebate directly to Customer Khata.
            </p>
        </div>
        <a href="{{ route('customer_credit_notes.index') }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
            <i data-lucide="arrow-left" class="w-4 h-4 rtl:rotate-180"></i>
            <span>Cancel</span>
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

    <form method="POST" action="{{ route('customer_credit_notes.store') }}" class="space-y-6" id="creditNoteForm" onsubmit="var btn=document.getElementById('submitBtn'); if(btn){ btn.disabled=true; btn.classList.add('opacity-50','cursor-not-allowed'); }">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                        {{ __('erp.customers.title') }} <span class="text-rose-500">*</span>
                    </label>
                    <select name="customer_id" id="customer_id" required
                            class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- Select Customer --</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" {{ (old('customer_id', $selectedCustomer?->id) == $c->id) ? 'selected' : '' }}>
                                {{ $c->business_name }} ({{ $c->city }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                        {{ __('erp.location') }} <span class="text-rose-500">*</span>
                    </label>
                    <select name="location_id" required
                            class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @foreach($authorizedLocations as $loc)
                            <option value="{{ $loc->id }}" {{ old('location_id', $selectedCustomer?->primary_location_id) == $loc->id ? 'selected' : '' }}>
                                {{ $loc->name }} ({{ $loc->code }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                        {{ __('erp.customer_credit_notes.note_date') }} <span class="text-rose-500">*</span>
                    </label>
                    <input type="date" name="note_date" value="{{ old('note_date', now()->toDateString()) }}" required
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                        Amount to Credit (PKR) <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-2.5 text-slate-400 font-bold text-sm">₨</span>
                        <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required placeholder="0.00"
                               class="w-full pl-9 pr-3.5 py-2.5 text-sm font-mono font-bold rounded-xl border border-slate-300 bg-slate-50 text-emerald-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                        {{ __('erp.customer_credit_notes.reason_type') }} <span class="text-rose-500">*</span>
                    </label>
                    <select name="reason_type" required
                            class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="price_difference" {{ old('reason_type') === 'price_difference' ? 'selected' : '' }}>Price Difference / Discount</option>
                        <option value="volume_rebate" {{ old('reason_type') === 'volume_rebate' ? 'selected' : '' }}>Volume / Target Rebate</option>
                        <option value="customer_allowance" {{ old('reason_type') === 'customer_allowance' ? 'selected' : '' }}>Customer Allowance</option>
                        <option value="invoice_correction" {{ old('reason_type') === 'invoice_correction' ? 'selected' : '' }}>Invoice Correction</option>
                        <option value="other" {{ old('reason_type') === 'other' ? 'selected' : '' }}>Other Authorized Adjustment</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                        Claim / Agreement Reference #
                    </label>
                    <input type="text" name="reference_number" value="{{ old('reference_number') }}"
                           placeholder="e.g. CLAIM-884, Season Target Agreement..."
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                    Reason & Description <span class="text-rose-500">*</span>
                </label>
                <input type="text" name="reason" value="{{ old('reason') }}" required
                       placeholder="e.g. Approved 5% Eid sales target volume rebate on Summer Collection..."
                       class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                    Internal Authorization Notes
                </label>
                <textarea name="notes" rows="2"
                          placeholder="Manager approval details, calculation breakdown, or customer discussion notes..."
                          class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">{{ old('notes') }}</textarea>
            </div>
        </div>

        <!-- Submit & Actions -->
        <div class="flex items-center justify-between p-4 bg-white rounded-2xl border border-slate-200/80 shadow-xs">
            <span class="text-xs text-slate-500">
                Submitting will immediately post a <strong>Credit entry</strong> to the customer's khata ledger.
            </span>
            <div class="flex items-center gap-3">
                <a href="{{ route('customer_credit_notes.index') }}" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-bold transition">
                    Cancel
                </a>
                <button type="submit" id="submitBtn" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition shadow-sm flex items-center gap-2">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    <span>Post Credit Note</span>
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
