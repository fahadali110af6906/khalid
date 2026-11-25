@extends('layouts.app')

@section('title', __('erp.reports.cash_bank_report') . ' — ' . __('erp.brand_name'))

@section('content')
<div class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="bg-white border border-slate-200/80 rounded-xl p-6 shadow-xs">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold uppercase text-purple-600 tracking-wider mb-1">
                    <a href="{{ route('reports.index') }}" class="hover:underline">Reports</a>
                    <span>/</span>
                    <span>Treasury & Banking</span>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ __('erp.reports.cash_bank_report') }}</h1>
                <p class="text-xs text-slate-500 mt-1">Cash book, bank statements, inflows/outflows, inter-account transfers, and reconciliation.</p>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('reports.cash-bank.export', request()->query()) }}" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs px-4 py-2.5 rounded-lg shadow-xs transition">
                    <i data-lucide="download" class="w-4 h-4"></i>
                    <span>{{ __('erp.reports.export_csv') }}</span>
                </a>
            </div>
        </div>

        <!-- Filter Form -->
        <form method="GET" action="{{ route('reports.cash-bank') }}" class="mt-6 pt-5 border-t border-slate-100 flex flex-wrap items-center gap-3">
            @if(auth()->user()->isOwner())
            <div>
                <select name="location_id" class="text-xs bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 font-medium text-slate-700">
                    <option value="">🏢 {{ __('erp.reports.select_location') }}</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" {{ ($filters['location_id'] ?? '') == $loc->id ? 'selected' : '' }}>
                            {{ $loc->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif

            <div class="flex items-center gap-2">
                <input type="date" name="from_date" value="{{ $filters['from_date'] }}" class="text-xs bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-2 font-medium text-slate-700">
                <span class="text-xs text-slate-400">to</span>
                <input type="date" name="to_date" value="{{ $filters['to_date'] }}" class="text-xs bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-2 font-medium text-slate-700">
            </div>

            <button type="submit" class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2 rounded-lg transition shadow-xs">
                {{ __('erp.reports.filter_apply') }}
            </button>
            <a href="{{ route('reports.cash-bank') }}" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold px-3 py-2 rounded-lg transition">
                {{ __('erp.reports.filter_reset') }}
            </a>
        </form>
    </div>

    <!-- Liquidity KPI Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white border border-slate-200/80 rounded-xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase text-slate-400">Total Cash in Hand</span>
            <div class="text-xl font-bold text-emerald-600 mt-1">PKR {{ number_format((float) $summary['total_cash_balance'], 2) }}</div>
            <div class="text-[11px] text-slate-500 mt-1">Shop & factory cash drawers</div>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase text-slate-400">Total Bank Balances</span>
            <div class="text-xl font-bold text-indigo-600 mt-1">PKR {{ number_format((float) $summary['total_bank_balance'], 2) }}</div>
            <div class="text-[11px] text-slate-500 mt-1">Authorized business bank accounts</div>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase text-slate-400">Period Total Inflows</span>
            <div class="text-xl font-bold text-slate-900 mt-1">PKR {{ number_format((float) $summary['total_inflows'], 2) }}</div>
            <div class="text-[11px] text-slate-500 mt-1">Collections & receipts (Debits)</div>
        </div>

        <div class="bg-white border border-slate-200/80 rounded-xl p-4 shadow-xs">
            <span class="text-[11px] font-bold uppercase text-slate-400">Period Total Outflows</span>
            <div class="text-xl font-bold text-rose-600 mt-1">PKR {{ number_format((float) $summary['total_outflows'], 2) }}</div>
            <div class="text-[11px] text-slate-500 mt-1">Payments & expenses (Credits)</div>
        </div>
    </div>

    <!-- Accounts Statement Table -->
    <div class="bg-white border border-slate-200/80 rounded-xl shadow-xs overflow-hidden">
        <div class="px-6 py-4 bg-slate-50/70 border-b border-slate-200/80 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Financial Accounts Liquidity & Statements ({{ count($accounts) }})</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-start">
                <thead class="bg-slate-100 text-slate-600 font-bold uppercase border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 text-start">Account</th>
                        <th class="px-4 py-3 text-start">Type</th>
                        <th class="px-4 py-3 text-start">Location</th>
                        <th class="px-4 py-3 text-end">Opening Balance (PKR)</th>
                        <th class="px-4 py-3 text-end">Inflows (PKR)</th>
                        <th class="px-4 py-3 text-end">Outflows (PKR)</th>
                        <th class="px-4 py-3 text-end">Period Closing (PKR)</th>
                        <th class="px-4 py-3 text-end">Live Balance (PKR)</th>
                        <th class="px-4 py-3 text-center">Reconciliation</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                    @forelse($accounts as $item)
                    @php $acc = $item['account']; @endphp
                    <tr class="hover:bg-slate-50/70 transition">
                        <td class="px-4 py-3 font-semibold text-slate-900">
                            <a href="{{ route('financial-accounts.show', $acc->id) }}" class="font-bold text-indigo-600 hover:underline">
                                {{ $acc->name }}
                            </a>
                            <div class="text-[10px] text-slate-400 font-mono">{{ $acc->account_code }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $acc->isCash() ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800' }}">
                                {{ $acc->account_type }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $acc->location ? $acc->location->name : 'Central (All)' }}</td>
                        <td class="px-4 py-3 text-end font-mono">PKR {{ number_format((float) $item['opening_balance'], 2) }}</td>
                        <td class="px-4 py-3 text-end text-emerald-600 font-mono font-semibold">+{{ number_format((float) $item['total_inflow'], 2) }}</td>
                        <td class="px-4 py-3 text-end text-rose-600 font-mono font-semibold">-{{ number_format((float) $item['total_outflow'], 2) }}</td>
                        <td class="px-4 py-3 text-end font-bold text-slate-900 font-mono">PKR {{ number_format((float) $item['closing_balance'], 2) }}</td>
                        <td class="px-4 py-3 text-end font-bold text-indigo-700 font-mono">PKR {{ number_format((float) $item['live_balance'], 2) }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($item['is_reconciled'])
                                <span class="inline-flex items-center gap-1 text-[10px] bg-emerald-100 text-emerald-800 font-bold px-2 py-0.5 rounded">
                                    <i data-lucide="check" class="w-3 h-3"></i> Matched
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-[10px] bg-rose-100 text-rose-800 font-bold px-2 py-0.5 rounded">
                                    <i data-lucide="alert-triangle" class="w-3 h-3"></i> Discrepancy
                                </span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-6 py-12 text-center text-slate-400">
                            {{ __('erp.reports.no_data') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Inter-Account Transfers Audit Table -->
    <div class="bg-white border border-slate-200/80 rounded-xl shadow-xs overflow-hidden">
        <div class="px-6 py-4 bg-slate-50/70 border-b border-slate-200/80 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Inter-Account Transfers Audit ({{ $transfers->count() }})</h2>
                <p class="text-[11px] text-slate-500 mt-0.5">Transfers between accounts are internal reallocations and carry zero net effect on business profit/loss.</p>
            </div>
            <span class="text-xs font-bold text-slate-700">Total Transferred: PKR {{ number_format((float) $summary['total_transfers_amount'], 2) }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-start">
                <thead class="bg-slate-100 text-slate-600 font-bold uppercase border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 text-start">Transfer #</th>
                        <th class="px-4 py-3 text-start">Date</th>
                        <th class="px-4 py-3 text-start">Source Account (Outflow)</th>
                        <th class="px-4 py-3 text-start">Destination Account (Inflow)</th>
                        <th class="px-4 py-3 text-start">Reason / Notes</th>
                        <th class="px-4 py-3 text-end">Amount (PKR)</th>
                        <th class="px-4 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                    @forelse($transfers as $tr)
                    <tr class="hover:bg-slate-50/70 transition">
                        <td class="px-4 py-3 font-mono font-bold text-purple-600">
                            <a href="{{ route('account-transfers.show', $tr->id) }}" class="hover:underline">
                                {{ $tr->transfer_number }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $tr->transfer_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 font-semibold text-rose-700">{{ $tr->sourceAccount ? $tr->sourceAccount->name : 'N/A' }}</td>
                        <td class="px-4 py-3 font-semibold text-emerald-700">{{ $tr->destinationAccount ? $tr->destinationAccount->name : 'N/A' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $tr->reason ?: $tr->notes }}</td>
                        <td class="px-4 py-3 text-end font-bold text-slate-900 font-mono">PKR {{ number_format((float) $tr->amount, 2) }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800">
                                {{ $tr->status }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-slate-400">
                            No inter-account transfers in the selected period.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
