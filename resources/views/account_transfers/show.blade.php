@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <!-- Breadcrumb / Back -->
    <div class="flex items-center justify-between">
        <a href="{{ route('account-transfers.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-900 transition">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to Transfers</span>
        </a>
    </div>

    <!-- Transfer Voucher Card -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-xl font-bold text-slate-900 font-mono">{{ $accountTransfer->transfer_number }}</h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                        Executed & Atomic
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-1">
                    Date: <span class="font-semibold text-slate-700">{{ $accountTransfer->transfer_date->format('d M Y') }}</span>
                    @if($accountTransfer->creator)
                        • Created by: <span class="font-semibold text-slate-700">{{ $accountTransfer->creator->name }}</span>
                    @endif
                </p>
            </div>

            <div class="text-right">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block">Transfer Amount</span>
                <span class="text-2xl font-extrabold text-slate-900 font-mono">
                    PKR {{ number_format((float) $accountTransfer->amount, 2) }}
                </span>
            </div>
        </div>

        <div class="p-6 space-y-6">
            <!-- Transfer Flow Visual -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center bg-slate-50 p-5 rounded-xl border border-slate-200/80">
                <!-- Source -->
                <div class="space-y-1 text-center md:text-left">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-rose-600 block">Outflow Source (Credit)</span>
                    <a href="{{ route('financial-accounts.show', $accountTransfer->fromAccount) }}" class="font-bold text-slate-900 hover:text-indigo-600 block">
                        {{ $accountTransfer->fromAccount->name }}
                    </a>
                    <div class="text-xs text-slate-500 font-mono">
                        {{ $accountTransfer->fromAccount->account_code }} ({{ ucfirst($accountTransfer->fromAccount->type) }})
                    </div>
                </div>

                <!-- Arrow -->
                <div class="flex flex-col items-center justify-center text-slate-400">
                    <div class="w-10 h-10 rounded-full bg-white border border-slate-200 shadow-xs flex items-center justify-center">
                        <i data-lucide="arrow-right" class="w-5 h-5 text-indigo-600"></i>
                    </div>
                    <span class="text-[11px] font-mono text-slate-500 mt-1">Zero Net Impact</span>
                </div>

                <!-- Destination -->
                <div class="space-y-1 text-center md:text-right">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-600 block">Inflow Destination (Debit)</span>
                    <a href="{{ route('financial-accounts.show', $accountTransfer->toAccount) }}" class="font-bold text-slate-900 hover:text-indigo-600 block">
                        {{ $accountTransfer->toAccount->name }}
                    </a>
                    <div class="text-xs text-slate-500 font-mono">
                        {{ $accountTransfer->toAccount->account_code }} ({{ ucfirst($accountTransfer->toAccount->type) }})
                    </div>
                </div>
            </div>

            <!-- Notes & Reference -->
            @if($accountTransfer->reference_number || $accountTransfer->notes)
                <div class="space-y-2">
                    @if($accountTransfer->reference_number)
                        <div>
                            <span class="text-xs font-bold text-slate-500 uppercase">Reference / Instrument #:</span>
                            <span class="text-sm font-mono font-bold text-slate-800 ml-2">{{ $accountTransfer->reference_number }}</span>
                        </div>
                    @endif
                    @if($accountTransfer->notes)
                        <div>
                            <span class="text-xs font-bold text-slate-500 uppercase block mb-1">Notes:</span>
                            <p class="text-sm text-slate-700 bg-slate-50 p-3 rounded-lg border border-slate-200/80">{{ $accountTransfer->notes }}</p>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Ledger Transactions Generated -->
            <div class="space-y-3 pt-3 border-t border-slate-100">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Atomic Ledger Leg Transactions</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs font-mono">
                        <thead class="bg-slate-50 text-slate-500 uppercase">
                            <tr>
                                <th class="px-3 py-2">Txn #</th>
                                <th class="px-3 py-2">Account</th>
                                <th class="px-3 py-2">Type</th>
                                <th class="px-3 py-2 text-right">Debit</th>
                                <th class="px-3 py-2 text-right">Credit</th>
                                <th class="px-3 py-2 text-right">Balance After</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($accountTransfer->transactions as $txn)
                                <tr>
                                    <td class="px-3 py-2.5 font-bold text-slate-900">{{ $txn->transaction_number }}</td>
                                    <td class="px-3 py-2.5">{{ $txn->account->name }} ({{ $txn->account->account_code }})</td>
                                    <td class="px-3 py-2.5">{{ $txn->transaction_type }}</td>
                                    <td class="px-3 py-2.5 text-right font-bold text-emerald-600">
                                        {{ bccomp((string)$txn->debit_amount, '0.00', 2) > 0 ? 'PKR ' . number_format((float)$txn->debit_amount, 2) : '—' }}
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-bold text-rose-600">
                                        {{ bccomp((string)$txn->credit_amount, '0.00', 2) > 0 ? 'PKR ' . number_format((float)$txn->credit_amount, 2) : '—' }}
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-bold text-slate-900">
                                        PKR {{ number_format((float)$txn->balance_after, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
