@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between print:hidden">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('customer_credit_notes.index') }}" class="text-xs font-semibold text-slate-500 hover:text-indigo-600 transition">
                    {{ __('erp.customer_credit_notes.title') }}
                </a>
                <span class="text-slate-400 text-xs">/</span>
                <span class="text-xs font-bold text-slate-800">{{ $customerCreditNote->credit_note_number }}</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 mt-1 flex items-center gap-3">
                <span>Customer Credit Note (SCN)</span>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                    Posted & Credited
                </span>
            </h1>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="window.print()" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition flex items-center gap-1.5 shadow-xs">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span>Print Credit Note</span>
            </button>
            <a href="{{ route('customer_credit_notes.index') }}" class="px-3.5 py-2 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold transition">
                Back to List
            </a>
        </div>
    </div>

    <!-- Alert Success -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium flex items-center gap-2 print:hidden">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Official Voucher Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 md:p-8 space-y-6 print:border-none print:shadow-none print:p-0">
        <!-- Top Voucher Header -->
        <div class="flex justify-between items-start border-b border-slate-200 pb-6">
            <div>
                <h2 class="text-xl font-black text-slate-900 uppercase tracking-wider">Khalid Traders</h2>
                <p class="text-xs text-slate-500">Footwear Wholesale & Distribution ERP</p>
                <div class="mt-3 text-xs space-y-0.5">
                    <p><strong class="text-slate-700">Issued From Location:</strong> {{ $customerCreditNote->location->name }}</p>
                    <p><strong class="text-slate-700">Created By:</strong> {{ $customerCreditNote->creator->name }}</p>
                    @if($customerCreditNote->postedBy)
                        <p><strong class="text-slate-700">Authorized By:</strong> {{ $customerCreditNote->postedBy->name }} ({{ $customerCreditNote->posted_at ? $customerCreditNote->posted_at->format('d M Y, h:i A') : '' }})</p>
                    @endif
                </div>
            </div>
            <div class="text-end">
                <div class="inline-block px-3 py-1 bg-emerald-50 border-emerald-200 text-emerald-800 border font-mono text-sm font-bold rounded-lg mb-2">
                    {{ $customerCreditNote->credit_note_number }}
                </div>
                <div class="text-xs space-y-0.5 text-slate-600">
                    <p><strong class="text-slate-700">Note Date:</strong> {{ $customerCreditNote->note_date->format('d M Y') }}</p>
                    <p><strong class="text-slate-700">Classification:</strong>
                        <span class="font-semibold text-slate-800">
                            {{ $customerCreditNote->credit_note_type === 'sales_return' ? 'Sales Return Voucher' : 'Financial Adjustment' }}
                        </span>
                    </p>
                    @if($customerCreditNote->salesReturn)
                        <p><strong class="text-slate-700">Linked Return:</strong>
                            <a href="{{ route('sales.returns.show', $customerCreditNote->salesReturn) }}" class="text-indigo-600 font-mono font-bold hover:underline">
                                {{ $customerCreditNote->salesReturn->return_number }}
                            </a>
                        </p>
                    @endif
                    @if($customerCreditNote->reference_number)
                        <p><strong class="text-slate-700">Ref #:</strong> {{ $customerCreditNote->reference_number }}</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Customer & Reason Details -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl text-xs">
            <div>
                <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Credited Customer (Khata)</p>
                <p class="font-bold text-sm text-slate-900">{{ $customerCreditNote->customer->business_name }}</p>
                <p class="text-slate-600">{{ $customerCreditNote->customer->contact_person }} • {{ $customerCreditNote->customer->phone }}</p>
                <p class="text-slate-500">{{ $customerCreditNote->customer->city }}</p>
                <a href="{{ route('customers.ledger', $customerCreditNote->customer) }}" class="inline-flex items-center gap-1 text-indigo-600 hover:underline font-semibold mt-1">
                    <i data-lucide="book-open" class="w-3.5 h-3.5"></i>
                    <span>View Customer Khata Ledger</span>
                </a>
            </div>
            <div>
                <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Adjustment Reason & Type</p>
                <p class="font-bold text-slate-800">{{ ucfirst(str_replace('_', ' ', $customerCreditNote->reason_type)) }}</p>
                <p class="font-semibold text-slate-900 text-sm mt-0.5">{{ $customerCreditNote->reason }}</p>
                @if($customerCreditNote->notes)
                    <p class="text-slate-600 mt-1 italic">{{ $customerCreditNote->notes }}</p>
                @endif
            </div>
        </div>

        <!-- Credit Amount Box -->
        <div class="p-6 rounded-2xl bg-gradient-to-br from-emerald-50 to-teal-50 border border-emerald-200/80 text-center space-y-1">
            <p class="text-xs font-bold uppercase tracking-wider text-emerald-800">Total Amount Credited to Customer</p>
            <p class="text-3xl font-black text-emerald-700 font-mono">
                ₨ {{ number_format($customerCreditNote->amount, 2) }}
            </p>
            <p class="text-xs text-emerald-600">
                This amount has been credited to Customer Khata, reducing customer receivable balance.
            </p>
        </div>

        <!-- Signatures for Printable Voucher -->
        <div class="pt-12 grid grid-cols-2 gap-6 text-center text-xs text-slate-500">
            <div class="border-t border-slate-300 pt-2">
                <p class="font-bold text-slate-800">Prepared By</p>
                <p class="text-slate-400 text-[10px]">Accountant / Sales Officer</p>
            </div>
            <div class="border-t border-slate-300 pt-2">
                <p class="font-bold text-slate-800">Approved By</p>
                <p class="text-slate-400 text-[10px]">Owner / Finance Manager</p>
            </div>
        </div>
    </div>
</div>
@endsection
