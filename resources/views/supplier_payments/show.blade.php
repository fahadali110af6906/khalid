@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <!-- Action Header -->
    <div class="flex items-center justify-between print:hidden">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('supplier-payments.index') }}" class="text-xs font-semibold text-slate-500 hover:text-indigo-600 transition">
                    Supplier Payments
                </a>
                <span class="text-slate-400 text-xs">/</span>
                <span class="text-xs font-bold text-slate-800">{{ $supplierPayment->payment_number }}</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 mt-1">
                {{ __('erp.supplier_payments.payment_voucher') }}
            </h1>
        </div>

        <div class="flex items-center gap-2">
            <button onclick="window.print()" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition flex items-center gap-1.5 shadow-xs">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span>Print Voucher</span>
            </button>
            <a href="{{ route('suppliers.ledger', $supplierPayment->supplier) }}" class="px-3.5 py-2 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold transition">
                View Supplier Khata
            </a>
        </div>
    </div>

    <!-- Printable Payment Voucher Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 md:p-8 space-y-6 print:border-none print:shadow-none print:p-0">
        <!-- Top Voucher Header -->
        <div class="flex justify-between items-start border-b border-slate-200 pb-6">
            <div>
                <h2 class="text-xl font-black text-slate-900 uppercase tracking-wider">Khalid Traders</h2>
                <p class="text-xs text-slate-500">Official Supplier Payment & Debit Voucher</p>
                <div class="mt-2 text-xs text-slate-600">
                    <p><strong>Disbursed From:</strong> {{ $supplierPayment->location->name }}</p>
                    <p><strong>Issued By:</strong> {{ $supplierPayment->creator->name }}</p>
                </div>
            </div>
            <div class="text-end">
                <div class="inline-block px-3 py-1 bg-emerald-50 border border-emerald-200 text-emerald-800 font-mono text-sm font-bold rounded-lg mb-2">
                    {{ $supplierPayment->payment_number }}
                </div>
                <div class="text-xs text-slate-600">
                    <p><strong>Date:</strong> {{ $supplierPayment->payment_date->format('d M Y') }}</p>
                    @if($supplierPayment->purchaseOrder)
                        <p><strong>PO Reference:</strong> {{ $supplierPayment->purchaseOrder->purchase_number }}</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Supplier Details -->
        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 text-xs flex justify-between items-center">
            <div>
                <span class="text-slate-400 uppercase font-bold text-[10px] block">Paid To (Vendor)</span>
                <span class="text-sm font-bold text-slate-900">{{ $supplierPayment->supplier->business_name }}</span>
                <span class="text-slate-500 font-mono ms-2">({{ $supplierPayment->supplier->supplier_code }})</span>
            </div>
            <div class="text-end text-slate-600">
                <p>{{ $supplierPayment->supplier->contact_person }}</p>
                <p class="font-mono">{{ $supplierPayment->supplier->phone }}</p>
            </div>
        </div>

        <!-- Payment Amount & Details -->
        <div class="p-6 rounded-2xl bg-emerald-50/70 border border-emerald-200 text-center space-y-2">
            <span class="text-xs font-bold uppercase tracking-wider text-emerald-700">Amount Paid</span>
            <div class="text-3xl font-black font-mono text-emerald-800">
                PKR {{ number_format($supplierPayment->amount, 2) }}
            </div>
            <p class="text-xs text-emerald-700 font-medium">
                Payment Method: <strong class="uppercase">{{ str_replace('_', ' ', $supplierPayment->payment_method) }}</strong>
                @if($supplierPayment->reference_number)
                    • Ref / Cheque #: <strong class="font-mono">{{ $supplierPayment->reference_number }}</strong>
                @endif
            </p>
        </div>

        @if($supplierPayment->notes)
            <div class="text-xs text-slate-600 bg-slate-50 p-3 rounded-lg border border-slate-200">
                <strong>Notes / Purpose:</strong> {{ $supplierPayment->notes }}
            </div>
        @endif

        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-600 text-xs">
            <i data-lucide="info" class="w-4 h-4 inline-block text-slate-500 me-1"></i>
            This payment voucher immediately debits the supplier's Khata, reducing Khalid Traders' payable liability.
        </div>

        <!-- Signatures -->
        <div class="pt-12 grid grid-cols-2 gap-8 text-center text-xs text-slate-500">
            <div>
                <div class="border-b border-slate-300 pb-12"></div>
                <p class="mt-2 font-semibold">Vendor / Recipient Signature</p>
            </div>
            <div>
                <div class="border-b border-slate-300 pb-12"></div>
                <p class="mt-2 font-semibold">Authorized Cashier / Accountant</p>
            </div>
        </div>
    </div>
</div>
@endsection
