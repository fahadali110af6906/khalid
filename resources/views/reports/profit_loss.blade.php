@extends('layouts.app')

@section('title', __('erp.reports.profit_loss_report') . ' — ' . __('erp.brand_name'))

@section('content')
<div class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="bg-white border border-slate-200/80 rounded-xl p-6 shadow-xs">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold uppercase text-blue-600 tracking-wider mb-1">
                    <a href="{{ route('reports.index') }}" class="hover:underline">Reports</a>
                    <span>/</span>
                    <span>Financial Performance</span>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ __('erp.reports.profit_loss_report') }}</h1>
                <p class="text-xs text-slate-500 mt-1">Operational trading profit and loss derived from wholesale sales, inventory WAC, and posted expense vouchers.</p>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('reports.profit-loss.export', request()->query()) }}" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs px-4 py-2.5 rounded-lg shadow-xs transition">
                    <i data-lucide="download" class="w-4 h-4"></i>
                    <span>{{ __('erp.reports.export_csv') }}</span>
                </a>
            </div>
        </div>

        <!-- Filter Form -->
        <form method="GET" action="{{ route('reports.profit-loss') }}" class="mt-6 pt-5 border-t border-slate-100 flex flex-wrap items-center gap-3">
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
            <a href="{{ route('reports.profit-loss') }}" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold px-3 py-2 rounded-lg transition">
                {{ __('erp.reports.filter_reset') }}
            </a>
        </form>
    </div>

    <!-- Prominent Accounting Foundation Disclosure Banner -->
    <div class="bg-indigo-50/80 border border-indigo-200 rounded-xl p-5 shadow-xs">
        <div class="flex items-start gap-3">
            <div class="w-6 h-6 rounded-md bg-indigo-600 text-white flex items-center justify-center flex-shrink-0 mt-0.5">
                <i data-lucide="info" class="w-4 h-4"></i>
            </div>
            <div>
                <h3 class="text-xs font-bold text-indigo-950 uppercase tracking-wide">Operational Sub-Ledger Statement Notice</h3>
                <p class="text-xs text-indigo-900 mt-1 leading-relaxed">
                    {{ $accounting_limitations['disclaimer'] }}
                </p>
            </div>
        </div>
    </div>

    <!-- Main Profit & Loss Statement Card -->
    <div class="bg-white border border-slate-200/80 rounded-xl shadow-xs overflow-hidden">
        <div class="px-6 py-4 bg-slate-50/70 border-b border-slate-200/80 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Trading Statement for Period {{ $filters['from_date'] }} to {{ $filters['to_date'] }}</h2>
            <span class="text-xs font-mono font-bold text-indigo-600">PKR Standard Currency</span>
        </div>

        <div class="p-6 space-y-6">

            <!-- 1. Revenue Section -->
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3 border-b border-slate-100 pb-2">1. Commercial Sales Revenue</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between items-center text-slate-700">
                        <span>Gross Wholesale Sales (Posted Invoices)</span>
                        <span class="font-mono font-semibold text-slate-900">PKR {{ number_format((float) $revenue['gross_sales'], 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center text-slate-500 text-xs">
                        <span>Less: Trade Discounts Allowed</span>
                        <span class="font-mono text-rose-600">- PKR {{ number_format((float) $revenue['trade_discounts'], 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center text-slate-500 text-xs">
                        <span>Less: Sales Returns & Customer Credits</span>
                        <span class="font-mono text-rose-600">- PKR {{ number_format((float) $revenue['sales_returns'], 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center font-bold text-slate-900 pt-2 border-t border-slate-100 bg-slate-50/50 p-2 rounded">
                        <span>NET COMMERCIAL SALES REVENUE</span>
                        <span class="font-mono text-indigo-600 text-base">PKR {{ number_format((float) $revenue['net_sales'], 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- 2. Cost of Goods Sold Section -->
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3 border-b border-slate-100 pb-2">2. Cost of Goods Sold (COGS at WAC)</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between items-center text-slate-700">
                        <span>Cost of Finished Goods Sold (Deducted Stock at WAC)</span>
                        <span class="font-mono font-semibold text-slate-900">PKR {{ number_format((float) $cogs['gross_cogs'], 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center text-slate-500 text-xs">
                        <span>Less: Cost of Restocked Sales Returns</span>
                        <span class="font-mono text-emerald-600">- PKR {{ number_format((float) $cogs['returned_restocked_cogs'], 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center font-bold text-slate-900 pt-2 border-t border-slate-100 bg-slate-50/50 p-2 rounded">
                        <span>NET COST OF GOODS SOLD</span>
                        <span class="font-mono text-rose-600 text-base">PKR {{ number_format((float) $cogs['net_cogs'], 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- 3. Gross Profit Summary Highlight -->
            <div class="bg-gradient-to-r from-emerald-500 to-teal-600 text-white rounded-xl p-5 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-100">Trading Result</span>
                    <h4 class="text-2xl font-black tracking-tight mt-0.5">GROSS PROFIT</h4>
                    <p class="text-xs text-emerald-100 mt-1">Net Commercial Sales minus Net COGS at WAC</p>
                </div>
                <div class="text-end">
                    <div class="text-3xl font-black tracking-tight font-mono">PKR {{ number_format((float) $gross_profit['amount'], 2) }}</div>
                    <div class="text-xs font-semibold text-emerald-100 mt-1">Gross Profit Margin: {{ $gross_profit['margin_percent'] }}%</div>
                </div>
            </div>

            <!-- 4. Operating Expenses Section -->
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3 border-b border-slate-100 pb-2">3. Operating & Administrative Expenses</h3>
                <div class="space-y-2 text-sm">
                    @forelse($operating_expenses['by_category'] as $cat)
                    <div class="flex justify-between items-center text-slate-700">
                        <span>{{ $cat['category_name'] }}</span>
                        <span class="font-mono text-slate-900">PKR {{ number_format((float) $cat['amount'], 2) }}</span>
                    </div>
                    @empty
                    <div class="text-xs text-slate-400 italic">No operating expenses posted in this period.</div>
                    @endforelse

                    <div class="flex justify-between items-center font-bold text-slate-900 pt-2 border-t border-slate-100 bg-slate-50/50 p-2 rounded">
                        <span>TOTAL OPERATING EXPENSES</span>
                        <span class="font-mono text-rose-600 text-base">PKR {{ number_format((float) $operating_expenses['total'], 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- 5. Operating Net Profit (EBIT) Highlight -->
            <div class="bg-slate-900 text-white rounded-xl p-6 shadow-md flex flex-col sm:flex-row items-center justify-between gap-4">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-400">Final Operating Bottom Line</span>
                    <h4 class="text-2xl font-black tracking-tight mt-0.5 text-white">OPERATING NET PROFIT (EBIT)</h4>
                    <p class="text-xs text-slate-400 mt-1">Gross Profit minus Operating Expenses</p>
                </div>
                <div class="text-end">
                    <div class="text-3xl font-black tracking-tight font-mono {{ bccomp((string)$net_profit['operating_net_profit'], '0.00', 2) >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                        PKR {{ number_format((float) $net_profit['operating_net_profit'], 2) }}
                    </div>
                    <div class="text-xs font-semibold text-slate-300 mt-1">Operating Net Margin: {{ $net_profit['margin_percent'] }}%</div>
                </div>
            </div>

        </div>
    </div>

</div>
@endsection
