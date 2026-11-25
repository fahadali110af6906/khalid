@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('supplier-debit-notes.index') }}" class="text-xs font-semibold text-slate-500 hover:text-indigo-600 transition">
                    {{ __('erp.supplier_debit_notes.title') }}
                </a>
                <span class="text-slate-400 text-xs">/</span>
                <span class="text-xs font-bold text-slate-800">New Financial Debit Note</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 mt-1">
                {{ __('erp.supplier_debit_notes.new_debit_note') }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Record a financial claim, discount, rate difference, or allowance against a supplier.
                This reduces payable balance in Supplier Khata without altering warehouse inventory.
            </p>
        </div>
        <a href="{{ route('supplier-debit-notes.index') }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
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

    <form method="POST" action="{{ route('supplier-debit-notes.store') }}" class="space-y-6" id="debitNoteForm" onsubmit="var btn=document.getElementById('submitBtn'); if(btn){ btn.disabled=true; btn.classList.add('opacity-50','cursor-not-allowed'); }">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">

        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-5">
            <!-- Supplier & Location Selection -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="supplier_id" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                        Supplier / Vendor <span class="text-rose-500">*</span>
                    </label>
                    <select id="supplier_id" name="supplier_id" required
                            class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Select Supplier</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" {{ (old('supplier_id') == $supplier->id || ($selectedSupplier && $selectedSupplier->id == $supplier->id)) ? 'selected' : '' }}>
                                {{ $supplier->business_name }} ({{ $supplier->supplier_code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="location_id" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                        Operating Location <span class="text-rose-500">*</span>
                    </label>
                    <select id="location_id" name="location_id" required
                            class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        @foreach($authorizedLocations as $loc)
                            <option value="{{ $loc->id }}" {{ old('location_id') == $loc->id ? 'selected' : '' }}>
                                {{ $loc->type === 'factory' ? '🏭' : '🏪' }} {{ $loc->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Date, Type & Amount -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="note_date" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                        Debit Note Date <span class="text-rose-500">*</span>
                    </label>
                    <input type="date" id="note_date" name="note_date" value="{{ old('note_date', now()->toDateString()) }}" required
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label for="reason_type" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                        Reason Category <span class="text-rose-500">*</span>
                    </label>
                    <select id="reason_type" name="reason_type" required
                            class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="supplier_rebate" {{ old('reason_type') === 'supplier_rebate' ? 'selected' : '' }}>Supplier Rebate / Discount</option>
                        <option value="damage_compensation" {{ old('reason_type') === 'damage_compensation' ? 'selected' : '' }}>Damage / Defective Compensation</option>
                        <option value="price_difference" {{ old('reason_type') === 'price_difference' ? 'selected' : '' }}>Price / Rate Difference</option>
                        <option value="invoice_correction" {{ old('reason_type') === 'invoice_correction' ? 'selected' : '' }}>Invoice / Overbilling Correction</option>
                        <option value="other" {{ old('reason_type') === 'other' ? 'selected' : '' }}>Other Financial Claim</option>
                    </select>
                </div>

                <div>
                    <label for="amount" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                        Debit Amount (PKR) <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-2.5 font-bold text-slate-400 text-sm">₨</span>
                        <input type="number" step="0.01" min="0.01" id="amount" name="amount" value="{{ old('amount') }}" required
                               placeholder="0.00"
                               class="w-full pl-9 pr-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 font-mono font-bold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
            </div>

            <!-- Reason Description & Reference -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="reason" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                        Reason / Justification <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="reason" name="reason" value="{{ old('reason') }}" required
                           placeholder="e.g. 5% trade discount agreed by supplier representative"
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label for="reference_number" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                        Supplier Ref / Agreement #
                    </label>
                    <input type="text" id="reference_number" name="reference_number" value="{{ old('reference_number') }}"
                           placeholder="e.g. Letter ref # 482 / Credit Memo # 99"
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono">
                </div>
            </div>

            <!-- Internal Notes -->
            <div>
                <label for="notes" class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                    Internal Remarks & Audit Trail
                </label>
                <textarea id="notes" name="notes" rows="2"
                          placeholder="Optional notes regarding approvals or settlement terms..."
                          class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">{{ old('notes') }}</textarea>
            </div>
        </div>

        <!-- Khata Effect Notice -->
        <div class="p-4 rounded-2xl bg-indigo-50 border border-indigo-200 text-indigo-900 text-xs flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <i data-lucide="info" class="w-5 h-5 text-indigo-600 flex-shrink-0"></i>
                <div>
                    <strong class="font-bold block">Financial Khata Impact</strong>
                    <span>Submitting this form posts an immediate debit entry (`adjustment_debit`) to the Supplier Khata, lowering the outstanding payable. Physical inventory will remain unchanged.</span>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('supplier-debit-notes.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 text-sm font-semibold hover:bg-slate-50 transition">
                Discard
            </a>
            <button type="submit" id="submitBtn" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold shadow-sm transition flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4"></i>
                <span>Post Debit Note</span>
            </button>
        </div>
    </form>
</div>
@endsection
