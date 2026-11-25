@extends('layouts.app')

@section('title', __('erp.reports.financial_position_report') . ' — ' . __('erp.brand_name'))

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="bg-white border border-slate-200/80 rounded-xl p-6 shadow-xs">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold uppercase text-slate-500 tracking-wider mb-1">
                    <a href="{{ route('reports.index') }}" class="hover:underline">Reports</a>
                    <span>/</span>
                    <span>Accounting Governance</span>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ __('erp.reports.financial_position_report') }}</h1>
                <p class="text-xs text-slate-500 mt-1">Verified current operational working capital and statutory general ledger disclosure statement.</p>
            </div>

            @if(auth()->user()->isOwner())
            <form method="GET" action="{{ route('reports.financial-position') }}" class="flex items-center gap-2">
                <select name="location_id" onchange="this.form.submit()" class="text-xs bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 font-medium text-slate-700">
                    <option value="">🏢 {{ __('erp.reports.select_location') }}</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" {{ $currentLocationId == $loc->id ? 'selected' : '' }}>
                            {{ $loc->name }}
                        </option>
                    @endforeach
                </select>
            </form>
            @endif
        </div>
    </div>

    <!-- Formal Audit Blocker & Foundation Disclosure -->
    <div class="bg-amber-50 border-2 border-amber-300 rounded-xl p-6 shadow-xs space-y-3">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-amber-500 text-white flex items-center justify-center font-black">
                <i data-lucide="shield-alert" class="w-5 h-5"></i>
            </div>
            <div>
                <h2 class="text-base font-bold text-amber-950">Statutory Trial Balance & Balance Sheet Blocker Disclosure</h2>
                <p class="text-xs text-amber-900 font-medium">Independent Financial Audit & Integrity Mandate</p>
            </div>
        </div>
        <p class="text-xs text-amber-900 leading-relaxed">
            {{ $accounting_foundation_status['audit_blocker_reason'] }}
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2 text-xs">
            <div class="bg-white/80 p-2.5 rounded-lg border border-amber-200">
                <span class="font-bold text-slate-700 block">General Ledger Status:</span>
                <span class="text-amber-800 font-semibold">Not Installed in Schema</span>
            </div>
            <div class="bg-white/80 p-2.5 rounded-lg border border-amber-200">
                <span class="font-bold text-slate-700 block">Chart of Accounts:</span>
                <span class="text-amber-800 font-semibold">Operational Vouchers Only</span>
            </div>
            <div class="bg-white/80 p-2.5 rounded-lg border border-amber-200">
                <span class="font-bold text-slate-700 block">Fabricated Figures:</span>
                <span class="text-emerald-700 font-semibold">Strictly Prohibited (Zero Fiction)</span>
            </div>
        </div>
    </div>

    <!-- Verified Current Operational Assets & Liabilities -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <!-- Current Operational Assets -->
        <div class="bg-white border border-slate-200/80 rounded-xl shadow-xs overflow-hidden">
            <div class="px-6 py-4 bg-emerald-50/70 border-b border-emerald-100 flex items-center justify-between">
                <h3 class="text-sm font-bold text-emerald-950 uppercase tracking-wider flex items-center gap-2">
                    <i data-lucide="arrow-up-right" class="w-4 h-4 text-emerald-600"></i>
                    <span>Verified Current Operational Assets</span>
                </h3>
            </div>
            <div class="p-6 space-y-4 text-sm">
                <div class="flex justify-between items-center text-slate-700">
                    <div>
                        <span class="font-semibold block text-slate-900">Cash & Bank Liquidity</span>
                        <span class="text-xs text-slate-500">Live reconciled balances in drawers & banks</span>
                    </div>
                    <span class="font-mono font-bold text-slate-900">PKR {{ number_format((float) $assets['cash_and_bank'], 2) }}</span>
                </div>

                <div class="flex justify-between items-center text-slate-700 pt-3 border-t border-slate-100">
                    <div>
                        <span class="font-semibold block text-slate-900">Trade Accounts Receivable</span>
                        <span class="text-xs text-slate-500">Outstanding balances in Customer Khata</span>
                    </div>
                    <span class="font-mono font-bold text-slate-900">PKR {{ number_format((float) $assets['accounts_receivable'], 2) }}</span>
                </div>

                <div class="flex justify-between items-center text-slate-700 pt-3 border-t border-slate-100">
                    <div>
                        <span class="font-semibold block text-slate-900">Merchandise Inventory at WAC</span>
                        <span class="text-xs text-slate-500">Valuation of finished footwear stock on hand</span>
                    </div>
                    <span class="font-mono font-bold text-slate-900">PKR {{ number_format((float) $assets['merchandise_inventory_wac'], 2) }}</span>
                </div>

                <div class="flex justify-between items-center font-bold text-slate-900 pt-3 border-t-2 border-emerald-200 bg-emerald-50/50 p-3 rounded-lg">
                    <span>TOTAL CURRENT ASSETS</span>
                    <span class="font-mono text-emerald-700 text-lg">PKR {{ number_format((float) $assets['total_current_assets'], 2) }}</span>
                </div>
            </div>
        </div>

        <!-- Current Operational Liabilities -->
        <div class="bg-white border border-slate-200/80 rounded-xl shadow-xs overflow-hidden flex flex-col justify-between">
            <div>
                <div class="px-6 py-4 bg-rose-50/70 border-b border-rose-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-rose-950 uppercase tracking-wider flex items-center gap-2">
                        <i data-lucide="arrow-down-left" class="w-4 h-4 text-rose-600"></i>
                        <span>Verified Current Operational Liabilities</span>
                    </h3>
                </div>
                <div class="p-6 space-y-4 text-sm">
                    <div class="flex justify-between items-center text-slate-700">
                        <div>
                            <span class="font-semibold block text-slate-900">Trade Accounts Payable</span>
                            <span class="text-xs text-slate-500">Outstanding debt in Supplier Khata for goods receipts</span>
                        </div>
                        <span class="font-mono font-bold text-slate-900">PKR {{ number_format((float) $liabilities['accounts_payable'], 2) }}</span>
                    </div>

                    <div class="flex justify-between items-center font-bold text-slate-900 pt-3 border-t-2 border-rose-200 bg-rose-50/50 p-3 rounded-lg">
                        <span>TOTAL CURRENT LIABILITIES</span>
                        <span class="font-mono text-rose-700 text-lg">PKR {{ number_format((float) $liabilities['total_current_liabilities'], 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Net Working Capital Card -->
            <div class="p-6 pt-0">
                <div class="bg-slate-900 text-white rounded-xl p-5 shadow-md flex justify-between items-center">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-indigo-400">Net Operational Capital</span>
                        <h4 class="text-base font-bold text-white">OPERATIONAL WORKING CAPITAL</h4>
                        <p class="text-xs text-slate-400">Current Assets minus Current Liabilities</p>
                    </div>
                    <div class="text-2xl font-mono font-black text-emerald-400">
                        PKR {{ number_format((float) $working_capital, 2) }}
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>
@endsection
