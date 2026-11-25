@extends('layouts.app')

@section('title', "Payment Receipt #{$payment->payment_number} — Khalid Traders ERP")

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <a href="{{ route('payments.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-800 dark:hover:text-white">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to Receipts</span>
        </a>
        <button onclick="window.print()" class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-sm">
            <i data-lucide="printer" class="w-4 h-4"></i>
            <span>Print Receipt Voucher</span>
        </button>
    </div>

    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm p-8 space-y-6">
        <!-- Header -->
        <div class="flex justify-between items-start border-b border-slate-200 dark:border-slate-800 pb-6">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">Khalid Traders</h1>
                <p class="text-xs text-slate-500">Official Payment Receipt Voucher</p>
                <p class="text-xs text-slate-400 font-mono mt-0.5">Location: {{ $payment->location->name }} ({{ $payment->location->code }})</p>
            </div>
            <div class="text-end">
                <span class="text-sm font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ $payment->payment_number }}</span>
                <p class="text-xs text-slate-400 mt-0.5">Date: {{ $payment->payment_date->format('Y-m-d') }}</p>
            </div>
        </div>

        <!-- Amount Box -->
        <div class="bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/80 rounded-xl p-5 text-center">
            <div class="text-xs font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-300">Amount Received</div>
            <div class="text-3xl font-extrabold font-mono text-emerald-700 dark:text-emerald-400 mt-1">
                {{ number_format($payment->amount, 2) }} PKR
            </div>
            <div class="text-xs text-emerald-600 dark:text-emerald-500 mt-1 capitalize font-medium">
                Via {{ str_replace('_', ' ', $payment->payment_method) }}
                @if ($payment->reference_number)
                    (Ref: {{ $payment->reference_number }})
                @endif
            </div>
        </div>

        <!-- Party & Reference Details -->
        <div class="space-y-3 text-xs text-slate-600 dark:text-slate-300">
            <div class="flex justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                <span class="text-slate-400 font-medium">Customer / Party:</span>
                <span class="font-bold text-slate-900 dark:text-white">{{ $payment->customer->business_name }} ({{ $payment->customer->customer_code }})</span>
            </div>
            <div class="flex justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                <span class="text-slate-400 font-medium">Linked Sales Invoice:</span>
                <span>
                    @if ($payment->invoice)
                        <a href="{{ route('sales.invoices.show', $payment->invoice) }}" class="font-mono font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                            {{ $payment->invoice->invoice_number }}
                        </a>
                    @else
                        <span class="italic text-slate-400">On-Account / Advance Payment</span>
                    @endif
                </span>
            </div>
            <div class="flex justify-between py-2 border-b border-slate-100 dark:border-slate-800">
                <span class="text-slate-400 font-medium">Recorded By:</span>
                <span class="font-medium text-slate-800 dark:text-slate-200">{{ $payment->creator->name }} on {{ $payment->created_at->format('Y-m-d H:i') }}</span>
            </div>
            @if ($payment->notes)
                <div class="pt-2">
                    <span class="text-slate-400 font-medium block mb-1">Remarks / Particulars:</span>
                    <p class="italic text-slate-700 dark:text-slate-300 bg-slate-50 dark:bg-slate-800/40 p-2.5 rounded-lg">{{ $payment->notes }}</p>
                </div>
            @endif
        </div>

        <!-- Signatures -->
        <div class="flex justify-between pt-10 text-center text-xs text-slate-400">
            <div class="border-t border-dashed border-slate-300 dark:border-slate-700 w-36 pt-1">
                Authorized Cashier
            </div>
            <div class="border-t border-dashed border-slate-300 dark:border-slate-700 w-36 pt-1">
                Customer Signature
            </div>
        </div>
    </div>
</div>
@endsection
