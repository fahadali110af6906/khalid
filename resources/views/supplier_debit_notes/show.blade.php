@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between print:hidden">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('supplier-debit-notes.index') }}" class="text-xs font-semibold text-slate-500 hover:text-indigo-600 transition">
                    {{ __('erp.supplier_debit_notes.title') }}
                </a>
                <span class="text-slate-400 text-xs">/</span>
                <span class="text-xs font-bold text-slate-800">{{ $supplierDebitNote->debit_note_number }}</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 mt-1 flex items-center gap-3">
                <span>Supplier Debit Note</span>
                @if($supplierDebitNote->debit_note_type === 'purchase_return')
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200">
                        Stock Return Debit Note
                    </span>
                @else
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-100 text-purple-800 border border-purple-200">
                        Financial Adjustment Note
                    </span>
                @endif
            </h1>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="window.print()" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition flex items-center gap-1.5 shadow-xs">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span>Print Debit Note</span>
            </button>
            <a href="{{ route('supplier-debit-notes.index') }}" class="px-3.5 py-2 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold transition">
                Back to Debit Notes
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

    <!-- Official Debit Note Voucher Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 md:p-8 space-y-6 print:border-none print:shadow-none print:p-0">
        <!-- Top Voucher Header -->
        <div class="flex justify-between items-start border-b border-slate-200 pb-6">
            <div>
                <h2 class="text-xl font-black text-slate-900 uppercase tracking-wider">Khalid Traders</h2>
                <p class="text-xs text-slate-500">Footwear Wholesale & Distribution ERP</p>
                <div class="mt-3 text-xs space-y-0.5">
                    <p><strong class="text-slate-700">Issued Location:</strong> {{ $supplierDebitNote->location->name }}</p>
                    <p><strong class="text-slate-700">Created By:</strong> {{ $supplierDebitNote->creator->name }}</p>
                    @if($supplierDebitNote->postedBy)
                        <p><strong class="text-slate-700">Authorized By:</strong> {{ $supplierDebitNote->postedBy->name }} ({{ $supplierDebitNote->posted_at ? $supplierDebitNote->posted_at->format('d M Y, h:i A') : '' }})</p>
                    @endif
                </div>
            </div>
            <div class="text-end">
                <div class="inline-block px-3.5 py-1.5 bg-rose-50 border border-rose-200 text-rose-800 font-mono text-sm font-bold rounded-lg mb-2">
                    {{ $supplierDebitNote->debit_note_number }}
                </div>
                <div class="text-xs space-y-0.5 text-slate-600">
                    <p><strong class="text-slate-700">Voucher Date:</strong> {{ $supplierDebitNote->note_date ? $supplierDebitNote->note_date->format('d M Y') : '-' }}</p>
                    @if($supplierDebitNote->reference_number)
                        <p><strong class="text-slate-700">Reference #:</strong> <span class="font-mono">{{ $supplierDebitNote->reference_number }}</span></p>
                    @endif
                    @if($supplierDebitNote->purchaseReturn)
                        <p><strong class="text-slate-700">Purchase Return:</strong>
                            <a href="{{ route('purchases.returns.show', $supplierDebitNote->purchaseReturn) }}" class="text-indigo-600 font-mono underline hover:text-indigo-800 print:no-underline print:text-slate-800">
                                {{ $supplierDebitNote->purchaseReturn->return_number }}
                            </a>
                        </p>
                    @endif
                    @if($supplierDebitNote->purchaseOrder)
                        <p><strong class="text-slate-700">Purchase Order:</strong>
                            <a href="{{ route('purchases.show', $supplierDebitNote->purchaseOrder) }}" class="text-indigo-600 font-mono underline hover:text-indigo-800 print:no-underline print:text-slate-800">
                                {{ $supplierDebitNote->purchaseOrder->purchase_number }}
                            </a>
                        </p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Supplier Details -->
        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 text-xs flex justify-between items-center">
            <div>
                <span class="text-slate-400 uppercase font-bold text-[10px] block">Debit Charged To Supplier</span>
                <span class="text-sm font-bold text-slate-900">{{ $supplierDebitNote->supplier->business_name }}</span>
                <span class="text-slate-500 font-mono ms-2">({{ $supplierDebitNote->supplier->supplier_code }})</span>
            </div>
            <div class="text-end text-slate-600">
                <p>{{ $supplierDebitNote->supplier->contact_person }} • {{ $supplierDebitNote->supplier->phone }}</p>
                <p>{{ $supplierDebitNote->supplier->city ?? '' }}</p>
            </div>
        </div>

        <!-- Voucher Details & Financial Breakdown -->
        <div class="border border-slate-200 rounded-2xl p-6 bg-slate-50/50 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-4">
                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Debit Note Classification</span>
                    <span class="text-sm font-bold text-slate-800 capitalize">
                        @if($supplierDebitNote->debit_note_type === 'purchase_return')
                            Physical Footwear Stock Return (Goods Dispatched Back)
                        @else
                            {{ str_replace('_', ' ', $supplierDebitNote->reason_type ?? 'Financial Adjustment') }}
                        @endif
                    </span>
                </div>
                <div class="text-start sm:text-end">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Debited Amount</span>
                    <span class="text-2xl font-black text-rose-700 font-mono">
                        PKR {{ number_format($supplierDebitNote->amount, 2) }}
                    </span>
                </div>
            </div>

            <div>
                <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider mb-1">Reason / Statement</span>
                <p class="text-sm text-slate-800 font-medium bg-white p-3.5 rounded-xl border border-slate-200">
                    {{ $supplierDebitNote->reason }}
                </p>
            </div>

            @if($supplierDebitNote->notes)
                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider mb-1">Remarks & Approvals</span>
                    <p class="text-xs text-slate-600 bg-white p-3 rounded-xl border border-slate-200 font-sans">
                        {{ $supplierDebitNote->notes }}
                    </p>
                </div>
            @endif

            @if($supplierDebitNote->purchaseReturn)
                <div class="p-3.5 bg-amber-50 rounded-xl border border-amber-200 text-xs text-amber-900 flex items-center justify-between">
                    <span class="flex items-center gap-2">
                        <i data-lucide="package-check" class="w-4 h-4 text-amber-700"></i>
                        <span>This debit note was generated from physical goods return <strong>{{ $supplierDebitNote->purchaseReturn->return_number }}</strong> ({{ number_format($supplierDebitNote->purchaseReturn->total_return_pairs, 0) }} pairs).</span>
                    </span>
                    <a href="{{ route('purchases.returns.show', $supplierDebitNote->purchaseReturn) }}" class="font-bold underline text-amber-900 print:hidden">
                        View Return
                    </a>
                </div>
            @endif
        </div>

        <!-- Khata Notification -->
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center justify-between">
            <span class="flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                <span>Supplier Khata has been debited with <strong>PKR {{ number_format($supplierDebitNote->amount, 2) }}</strong>.</span>
            </span>
            <a href="{{ route('suppliers.ledger', $supplierDebitNote->supplier) }}" class="font-bold underline text-emerald-900 print:hidden">
                View Supplier Khata
            </a>
        </div>

        <!-- Signature Section -->
        <div class="pt-8 grid grid-cols-3 gap-6 text-center text-xs text-slate-500">
            <div>
                <div class="border-b border-slate-300 pb-12"></div>
                <p class="mt-2 font-semibold">Prepared By</p>
            </div>
            <div>
                <div class="border-b border-slate-300 pb-12"></div>
                <p class="mt-2 font-semibold">Authorized Signatory / Owner</p>
            </div>
            <div>
                <div class="border-b border-slate-300 pb-12"></div>
                <p class="mt-2 font-semibold">Supplier Acceptance / Khata Munshi</p>
            </div>
        </div>
    </div>
</div>
@endsection
