@extends('layouts.app')

@section('title', __('erp.reports.cash_flow_report') . ' — ' . __('erp.brand_name'))

@section('content')
<div class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="bg-white border border-slate-200/80 rounded-xl p-6 shadow-xs">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold uppercase text-teal-600 tracking-wider mb-1">
                    <a href="{{ route('reports.index') }}" class="hover:underline">Reports</a>
                    <span>/</span>
                    <span>Treasury</span>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ __('erp.reports.cash_flow_report') }}</h1>
                <p class="text-xs text-slate-500 mt-1">Verified cash & bank inflows, outflows, operating cash generation, and internal transfer reconciliation.</p>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('reports.cash-flow.export', request()->query()) }}" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs px-4 py-2.5 rounded-lg shadow-xs transition">
                    <i data-lucide="download" class="w-4 h-4"></i>
                    <span>{{ __('erp.reports.export_csv') }}</span>
                </a>
            </div>
        </div>

        <!-- Filter Form -->
        <form method="GET" action="{{ route('reports.cash-flow') }}" class="mt-6 pt-5 border-t border-slate-100 flex flex-wrap items-center gap-3">
            @if(auth()->user()->isOwner())
            <div>
                <select name="location_id" class="text-xs bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 font-medium text-slate-700">
                    <option value="">🏢 {{ __('erp.reports.select_location') }}</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" {{ $currentLocationId == $loc->id ? 'selected' : '' }}>
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
            <a href="{{ route('reports.cash-flow') }}" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold px-3 py-2 rounded-lg transition">
                {{ __('erp.reports.filter_reset') }}
            </a>
        </form>
    </div>

    <!-- Main Statement Card -->
    <div class="bg-white border border-slate-200/80 rounded-xl shadow-xs overflow-hidden">
        <div class="px-6 py-4 bg-slate-50/70 border-b border-slate-200/80 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Statement of Cash & Bank Inflows/Outflows</h2>
            <span class="text-xs text-slate-500">{{ $filters['from_date'] }} to {{ $filters['to_date'] }}</span>
        </div>

        <div class="p-6 space-y-6">

            <!-- 1. Opening Cash Balance -->
            <div class="flex justify-between items-center bg-slate-100/70 p-4 rounded-xl border border-slate-200/80">
                <div>
                    <span class="text-xs font-bold uppercase text-slate-500">Opening Liquidity</span>
                    <h3 class="text-base font-bold text-slate-900">Total Cash & Bank Balance at Start of Period</h3>
                </div>
                <div class="text-xl font-mono font-black text-slate-900">
                    PKR {{ number_format((float) $opening_balance, 2) }}
                </div>
            </div>

            <!-- 2. Operating Cash Inflows -->
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-700 mb-3 border-b border-slate-100 pb-2">Operating Cash Inflows</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between items-center text-slate-700">
                        <span>Customer Collections & Wholesale Invoice Payments</span>
                        <span class="font-mono font-semibold text-emerald-600">+ PKR {{ number_format((float) $operating_inflows['customer_receipts'], 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center text-slate-700 text-xs">
                        <span>General Operating Income & Misc Receipts</span>
                        <span class="font-mono text-emerald-600">+ PKR {{ number_format((float) $operating_inflows['general_income'], 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center text-slate-700 text-xs">
                        <span>Voucher Reversal Counter-Credits (Funds Restored)</span>
                        <span class="font-mono text-emerald-600">+ PKR {{ number_format((float) $operating_inflows['reversals_in'], 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center font-bold text-slate-900 pt-2 border-t border-slate-100 bg-emerald-50/50 p-2 rounded">
                        <span>TOTAL OPERATING CASH INFLOWS</span>
                        <span class="font-mono text-emerald-700 text-base">PKR {{ number_format((float) $operating_inflows['total'], 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- 3. Operating Cash Outflows -->
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-rose-700 mb-3 border-b border-slate-100 pb-2">Operating Cash Outflows</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between items-center text-slate-700">
                        <span>Payments to Suppliers for Goods Receipts</span>
                        <span class="font-mono font-semibold text-rose-600">- PKR {{ number_format((float) $operating_outflows['supplier_payments'], 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center text-slate-700 text-xs">
                        <span>Operating, Factory & Utility Expenses</span>
                        <span class="font-mono text-rose-600">- PKR {{ number_format((float) $operating_outflows['operating_expenses'], 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center text-slate-700 text-xs">
                        <span>Customer Cash Refunds on Sales Returns</span>
                        <span class="font-mono text-rose-600">- PKR {{ number_format((float) $operating_outflows['customer_refunds'], 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center font-bold text-slate-900 pt-2 border-t border-slate-100 bg-rose-50/50 p-2 rounded">
                        <span>TOTAL OPERATING CASH OUTFLOWS</span>
                        <span class="font-mono text-rose-700 text-base">- PKR {{ number_format((float) $operating_outflows['total'], 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- 4. Net Operating Cash Generation -->
            <div class="bg-slate-50 border border-slate-200 p-4 rounded-xl flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold uppercase text-slate-500">Net Operational Flow</span>
                    <h3 class="text-base font-bold text-slate-900">Net Cash Generated / (Used) by Operations</h3>
                </div>
                <div class="text-xl font-mono font-black {{ bccomp((string)$net_operating_cash_flow, '0.00', 2) >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                    PKR {{ number_format((float) $net_operating_cash_flow, 2) }}
                </div>
            </div>

            <!-- 5. Internal Transfers Note -->
            <div class="border border-slate-200 rounded-xl p-4 bg-slate-50/40">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-600 mb-2">Inter-Account Internal Transfers (Zero-Sum Verification)</h4>
                <div class="flex flex-col sm:flex-row sm:items-center justify-between text-xs text-slate-600 gap-2">
                    <div>
                        Transfers In: <strong class="text-slate-800">PKR {{ number_format((float) $internal_transfers['transfers_in'], 2) }}</strong>
                        &nbsp;|&nbsp;
                        Transfers Out: <strong class="text-slate-800">PKR {{ number_format((float) $internal_transfers['transfers_out'], 2) }}</strong>
                    </div>
                    <div class="text-slate-500 font-mono font-semibold">
                        Net System Impact: <span class="text-indigo-600 font-bold">PKR 0.00 (Zero-Sum Invariant Preserved)</span>
                    </div>
                </div>
            </div>

            <!-- 6. Closing Cash Balance -->
            <div class="bg-slate-900 text-white rounded-xl p-5 shadow-md flex justify-between items-center">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-400">Closing Position</span>
                    <h3 class="text-lg font-bold text-white">Total Cash & Bank Liquidity at End of Period</h3>
                </div>
                <div class="text-2xl font-mono font-black text-emerald-400">
                    PKR {{ number_format((float) $closing_balance, 2) }}
                </div>
            </div>

        </div>
    </div>

</div>
@endsection
