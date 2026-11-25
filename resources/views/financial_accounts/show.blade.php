@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('financial-accounts.index') }}" class="text-slate-400 hover:text-slate-600 transition">
                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                </a>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $financialAccount->name }}</h1>
                @if($financialAccount->type === 'cash')
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                        <i data-lucide="coins" class="w-3 h-3"></i> Cash Drawer
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                        <i data-lucide="landmark" class="w-3 h-3"></i> Bank Account
                    </span>
                @endif
                @if(!$financialAccount->is_active)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600">
                        Inactive
                    </span>
                @endif
            </div>
            <p class="text-sm text-slate-500 mt-1">
                Account Code: <span class="font-mono font-bold text-slate-700">{{ $financialAccount->account_code }}</span>
                @if($financialAccount->type === 'bank' && $financialAccount->bank_name)
                    • {{ $financialAccount->bank_name }}
                    @if($financialAccount->account_number) ({{ $financialAccount->account_number }}) @endif
                @endif
                • Location: <span class="font-semibold">{{ $financialAccount->location ? $financialAccount->location->name : 'Central (All Locations)' }}</span>
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('account-transfers.create', ['from_account_id' => $financialAccount->id]) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-bold transition shadow-xs">
                <i data-lucide="arrow-left-right" class="w-4 h-4 text-indigo-600"></i>
                <span>Transfer Funds</span>
            </a>
            <a href="{{ route('financial-accounts.edit', $financialAccount) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-sm font-bold transition shadow-xs">
                <i data-lucide="pencil" class="w-4 h-4"></i>
                <span>Edit Account</span>
            </a>
        </div>
    </div>

    <!-- Alert Success / Error -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium flex items-center gap-2">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-medium flex items-center gap-2">
            <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-600"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Balance & Statement KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Current Balance -->
        <div class="bg-gradient-to-br from-indigo-900 to-slate-900 rounded-2xl p-5 text-white shadow-sm flex flex-col justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-indigo-200">Current Balance</span>
            <div class="mt-4">
                <span class="text-2xl font-extrabold tracking-tight font-mono">
                    PKR {{ number_format((float) $financialAccount->current_balance, 2) }}
                </span>
                <p class="text-xs text-indigo-200/80 mt-1">Live authorized balance</p>
            </div>
        </div>

        <!-- Period Inflows (Debits) -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Period Inflows (Debits)</span>
                <div class="w-7 h-7 rounded-lg bg-emerald-50 border border-emerald-200 flex items-center justify-center">
                    <i data-lucide="arrow-down-left" class="w-4 h-4 text-emerald-600"></i>
                </div>
            </div>
            <div class="mt-4">
                <span class="text-2xl font-extrabold tracking-tight text-emerald-600 font-mono">
                    + PKR {{ number_format((float) $statement['period_debit'], 2) }}
                </span>
                <p class="text-xs text-slate-500 mt-1">Receipts & incoming transfers</p>
            </div>
        </div>

        <!-- Period Outflows (Credits) -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Period Outflows (Credits)</span>
                <div class="w-7 h-7 rounded-lg bg-rose-50 border border-rose-200 flex items-center justify-center">
                    <i data-lucide="arrow-up-right" class="w-4 h-4 text-rose-600"></i>
                </div>
            </div>
            <div class="mt-4">
                <span class="text-2xl font-extrabold tracking-tight text-rose-600 font-mono">
                    - PKR {{ number_format((float) $statement['period_credit'], 2) }}
                </span>
                <p class="text-xs text-slate-500 mt-1">Expenses, payments & transfers out</p>
            </div>
        </div>

        <!-- Reconciliation Status -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-xs flex flex-col justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Integrity & Reconciliation</span>
            <div class="mt-4">
                @if($reconciliation['reconciled'])
                    <div class="flex items-center gap-2 text-emerald-600 font-bold text-base">
                        <i data-lucide="check-check" class="w-5 h-5"></i>
                        <span>100% Reconciled</span>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">Ledger sum matches stored balance</p>
                @else
                    <div class="flex items-center gap-2 text-rose-600 font-bold text-base">
                        <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                        <span>Variance Detected</span>
                    </div>
                    <p class="text-xs text-rose-500 mt-1">Diff: PKR {{ number_format((float) $reconciliation['difference'], 2) }}</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
        <form method="GET" action="{{ route('financial-accounts.show', $financialAccount) }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label for="from_date" class="block text-xs font-bold text-slate-600 uppercase mb-1">From Date</label>
                <input type="date" id="from_date" name="from_date" value="{{ $fromDate }}"
                       class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
            </div>

            <div>
                <label for="to_date" class="block text-xs font-bold text-slate-600 uppercase mb-1">To Date</label>
                <input type="date" id="to_date" name="to_date" value="{{ $toDate }}"
                       class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
            </div>

            <div>
                <label for="transaction_type" class="block text-xs font-bold text-slate-600 uppercase mb-1">Transaction Type</label>
                <select id="transaction_type" name="transaction_type" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <option value="all" {{ $txnType === 'all' ? 'selected' : '' }}>All Transactions</option>
                    <option value="opening_balance" {{ $txnType === 'opening_balance' ? 'selected' : '' }}>Opening Balance</option>
                    <option value="customer_receipt" {{ $txnType === 'customer_receipt' ? 'selected' : '' }}>Customer Receipt</option>
                    <option value="supplier_payment" {{ $txnType === 'supplier_payment' ? 'selected' : '' }}>Supplier Payment</option>
                    <option value="customer_refund" {{ $txnType === 'customer_refund' ? 'selected' : '' }}>Customer Refund</option>
                    <option value="expense" {{ $txnType === 'expense' ? 'selected' : '' }}>Expense</option>
                    <option value="transfer_in" {{ $txnType === 'transfer_in' ? 'selected' : '' }}>Transfer In</option>
                    <option value="transfer_out" {{ $txnType === 'transfer_out' ? 'selected' : '' }}>Transfer Out</option>
                    <option value="reversal" {{ $txnType === 'reversal' ? 'selected' : '' }}>Reversal</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2 px-4 bg-slate-800 hover:bg-slate-900 text-white rounded-lg text-sm font-semibold transition">
                    {{ __('erp.filter') }}
                </button>
                <a href="{{ route('financial-accounts.show', $financialAccount) }}" class="py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg text-sm font-semibold transition" title="Reset">
                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Statement Transactions Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-bold text-slate-900">Account Statement & General Ledger</h2>
            <div class="text-xs text-slate-500 font-mono">
                Opening: <span class="font-bold text-slate-700">PKR {{ number_format((float) $statement['opening_balance'], 2) }}</span>
                • Closing: <span class="font-bold text-slate-900">PKR {{ number_format((float) $statement['closing_balance'], 2) }}</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 border-b border-slate-200 text-xs font-bold uppercase text-slate-500 tracking-wider">
                    <tr>
                        <th class="px-5 py-3.5">{{ __('erp.date') }}</th>
                        <th class="px-5 py-3.5">Txn #</th>
                        <th class="px-5 py-3.5">Type</th>
                        <th class="px-5 py-3.5">Description / Reference</th>
                        <th class="px-5 py-3.5 text-right text-emerald-700">Inflow (Debit)</th>
                        <th class="px-5 py-3.5 text-right text-rose-700">Outflow (Credit)</th>
                        <th class="px-5 py-3.5 text-right">Balance</th>
                        <th class="px-5 py-3.5 text-center">User</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($transactions as $txn)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-5 py-3.5 font-medium text-slate-700 whitespace-nowrap">
                                {{ $txn->transaction_date->format('d M Y') }}
                            </td>
                            <td class="px-5 py-3.5 font-mono text-xs font-bold text-slate-900 whitespace-nowrap">
                                {{ $txn->transaction_number }}
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                @php
                                    $badgeColors = [
                                        'opening_balance' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                        'customer_receipt' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                        'transfer_in' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                        'supplier_payment' => 'bg-amber-50 text-amber-700 border-amber-200',
                                        'transfer_out' => 'bg-amber-50 text-amber-700 border-amber-200',
                                        'expense' => 'bg-rose-50 text-rose-700 border-rose-200',
                                        'customer_refund' => 'bg-rose-50 text-rose-700 border-rose-200',
                                        'reversal' => 'bg-slate-100 text-slate-700 border-slate-300',
                                    ];
                                    $color = $badgeColors[$txn->transaction_type] ?? 'bg-slate-50 text-slate-700 border-slate-200';
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold border {{ $color }}">
                                    {{ ucwords(str_replace('_', ' ', $txn->transaction_type)) }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="font-medium text-slate-800">{{ $txn->description }}</div>
                                @if($txn->reference_number)
                                    <div class="text-xs text-slate-400 mt-0.5 font-mono">Ref: {{ $txn->reference_number }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right font-mono font-bold text-emerald-600 whitespace-nowrap">
                                @if(bccomp((string) $txn->debit_amount, '0.00', 2) > 0)
                                    + PKR {{ number_format((float) $txn->debit_amount, 2) }}
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right font-mono font-bold text-rose-600 whitespace-nowrap">
                                @if(bccomp((string) $txn->credit_amount, '0.00', 2) > 0)
                                    - PKR {{ number_format((float) $txn->credit_amount, 2) }}
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right font-mono font-bold text-slate-900 whitespace-nowrap">
                                PKR {{ number_format((float) $txn->balance_after, 2) }}
                            </td>
                            <td class="px-5 py-3.5 text-center text-xs text-slate-500 whitespace-nowrap">
                                {{ $txn->creator ? $txn->creator->name : 'System' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-8 text-center text-slate-400">
                                <div class="flex flex-col items-center justify-center">
                                    <i data-lucide="file-spreadsheet" class="w-10 h-10 text-slate-300 mb-2"></i>
                                    <span>No transactions found in this period.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($transactions->hasPages())
            <div class="p-4 border-t border-slate-100 bg-slate-50">
                {{ $transactions->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
