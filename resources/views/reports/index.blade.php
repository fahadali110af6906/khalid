@extends('layouts.app')

@section('title', __('erp.reports.title') . ' — ' . __('erp.brand_name'))

@section('content')
<div class="space-y-6">

    <!-- Header & Filters -->
    <div class="bg-white border border-slate-200/80 rounded-xl p-6 shadow-xs">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ __('erp.reports.title') }}</h1>
                <p class="text-sm text-slate-500 mt-1">{{ __('erp.reports.subtitle') }}</p>
            </div>
            
            <!-- Filter Bar -->
            <form method="GET" action="{{ route('reports.index') }}" class="flex flex-wrap items-center gap-3">
                @if(auth()->user()->isOwner())
                <div>
                    <select name="location_id" class="text-xs bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 font-medium text-slate-700 focus:ring-2 focus:ring-indigo-500">
                        <option value="">🏢 {{ __('erp.reports.select_location') }}</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}" {{ $currentLocationId == $loc->id ? 'selected' : '' }}>
                                {{ $loc->name }} ({{ $loc->code }})
                            </option>
                        @endforeach
                    </select>
                </div>
                @endif

                <div class="flex items-center gap-2">
                    <input type="date" name="from_date" value="{{ $fromDate }}" class="text-xs bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-2 font-medium text-slate-700">
                    <span class="text-xs text-slate-400">to</span>
                    <input type="date" name="to_date" value="{{ $toDate }}" class="text-xs bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-2 font-medium text-slate-700">
                </div>

                <button type="submit" class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2 rounded-lg shadow-xs transition">
                    {{ __('erp.reports.filter_apply') }}
                </button>
                <a href="{{ route('reports.index') }}" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold px-3 py-2 rounded-lg transition">
                    {{ __('erp.reports.filter_reset') }}
                </a>
            </form>
        </div>
    </div>

    <!-- Executive KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <!-- Confirmed Net Sales -->
        <div class="bg-white border border-slate-200/80 rounded-xl p-5 shadow-xs">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-xs font-bold uppercase tracking-wider">{{ __('erp.reports.net_sales') }}</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <i data-lucide="trending-up" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="text-2xl font-black text-slate-900 tracking-tight">PKR {{ number_format((float) $summary['net_sales'], 2) }}</div>
            <div class="text-xs text-slate-500 mt-2 flex items-center justify-between">
                <span>{{ $summary['sales_count'] }} Invoices</span>
                <span class="text-rose-600">Ret: PKR {{ number_format((float) $summary['sales_returns'], 2) }}</span>
            </div>
        </div>

        <!-- Collections / Payments -->
        <div class="bg-white border border-slate-200/80 rounded-xl p-5 shadow-xs">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-xs font-bold uppercase tracking-wider">Collections</span>
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <i data-lucide="banknote" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="text-2xl font-black text-slate-900 tracking-tight">PKR {{ number_format((float) $summary['collections'], 2) }}</div>
            <div class="text-xs text-slate-500 mt-2">Cash & Bank customer receipts</div>
        </div>

        <!-- Purchases Received -->
        <div class="bg-white border border-slate-200/80 rounded-xl p-5 shadow-xs">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-xs font-bold uppercase tracking-wider">Purchases Received</span>
                <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                    <i data-lucide="truck" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="text-2xl font-black text-slate-900 tracking-tight">PKR {{ number_format((float) $summary['purchases_received'], 2) }}</div>
            <div class="text-xs text-slate-500 mt-2">{{ $summary['purchases_count'] }} Goods Receipts (GRN)</div>
        </div>

        <!-- Operating Expenses -->
        <div class="bg-white border border-slate-200/80 rounded-xl p-5 shadow-xs">
            <div class="flex items-center justify-between text-slate-500 mb-2">
                <span class="text-xs font-bold uppercase tracking-wider">Operating Expenses</span>
                <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center">
                    <i data-lucide="file-minus" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="text-2xl font-black text-slate-900 tracking-tight">PKR {{ number_format((float) $summary['operating_expenses'], 2) }}</div>
            <div class="text-xs text-slate-500 mt-2">Posted business vouchers</div>
        </div>
    </div>

    <!-- Secondary Balance Snapshot -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <!-- Cash & Bank Liquidity -->
        <div class="bg-slate-900 text-white rounded-xl p-5 shadow-md">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">Cash & Bank Liquidity</div>
            <div class="text-xl font-bold text-white">PKR {{ number_format((float) $summary['cash_bank_liquidity'], 2) }}</div>
            <div class="text-xs text-emerald-400 mt-1">Live active drawer & bank balances</div>
        </div>

        <!-- Stock Valuation at WAC -->
        <div class="bg-slate-900 text-white rounded-xl p-5 shadow-md">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">Inventory Valuation (WAC)</div>
            <div class="text-xl font-bold text-white">PKR {{ number_format((float) $summary['inventory_valuation_wac'], 2) }}</div>
            <div class="text-xs text-indigo-300 mt-1">{{ number_format((float) $summary['total_pairs_in_stock'], 0) }} pairs in stock</div>
        </div>

        <!-- Customer Receivables -->
        <div class="bg-slate-900 text-white rounded-xl p-5 shadow-md">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">Customer Receivables</div>
            <div class="text-xl font-bold text-white">PKR {{ number_format((float) $summary['customer_receivables'], 2) }}</div>
            <div class="text-xs text-amber-300 mt-1">Outstanding customer khata balance</div>
        </div>

        <!-- Supplier Payables -->
        <div class="bg-slate-900 text-white rounded-xl p-5 shadow-md">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">Supplier Payables</div>
            <div class="text-xl font-bold text-white">PKR {{ number_format((float) $summary['supplier_payables'], 2) }}</div>
            <div class="text-xs text-rose-300 mt-1">Outstanding supplier khata debt</div>
        </div>
    </div>

    <!-- Navigation Cards to Specialized Reports -->
    <div>
        <h2 class="text-lg font-bold text-slate-900 mb-4 tracking-tight">Specialized Business Reports & Statements</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">

            <!-- 1. Sales Report -->
            <a href="{{ route('reports.sales') }}" class="bg-white border border-slate-200/80 rounded-xl p-5 shadow-xs hover:border-indigo-500 hover:shadow-md transition group">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition">
                        <i data-lucide="receipt" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 group-hover:text-indigo-600 transition">{{ __('erp.reports.sales_report') }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Wholesale invoices, discounts, returns & customer breakdown</p>
                    </div>
                </div>
            </a>

            <!-- 2. Purchase Report -->
            <a href="{{ route('reports.purchases') }}" class="bg-white border border-slate-200/80 rounded-xl p-5 shadow-xs hover:border-indigo-500 hover:shadow-md transition group">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center group-hover:bg-amber-600 group-hover:text-white transition">
                        <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 group-hover:text-amber-600 transition">{{ __('erp.reports.purchases_report') }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Goods receipts (GRN), PO tracking, returns & debit notes</p>
                    </div>
                </div>
            </a>

            <!-- 3. Khata & Aging -->
            <a href="{{ route('reports.khata') }}" class="bg-white border border-slate-200/80 rounded-xl p-5 shadow-xs hover:border-indigo-500 hover:shadow-md transition group">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center group-hover:bg-emerald-600 group-hover:text-white transition">
                        <i data-lucide="users" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 group-hover:text-emerald-600 transition">{{ __('erp.reports.khata_report') }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Customer & supplier balances with 0-30, 31-60, 90+ aging</p>
                    </div>
                </div>
            </a>

            <!-- 4. Cash & Bank -->
            <a href="{{ route('reports.cash-bank') }}" class="bg-white border border-slate-200/80 rounded-xl p-5 shadow-xs hover:border-indigo-500 hover:shadow-md transition group">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center group-hover:bg-purple-600 group-hover:text-white transition">
                        <i data-lucide="wallet" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 group-hover:text-purple-600 transition">{{ __('erp.reports.cash_bank_report') }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Cash book, bank book, transfers & ledger reconciliation</p>
                    </div>
                </div>
            </a>

            <!-- 5. Expenses -->
            <a href="{{ route('reports.expenses') }}" class="bg-white border border-slate-200/80 rounded-xl p-5 shadow-xs hover:border-indigo-500 hover:shadow-md transition group">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center group-hover:bg-rose-600 group-hover:text-white transition">
                        <i data-lucide="tag" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 group-hover:text-rose-600 transition">{{ __('erp.reports.expenses_report') }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Operating expenses categorized with posted vs draft breakdown</p>
                    </div>
                </div>
            </a>

            <!-- 6. Inventory & Valuation -->
            <a href="{{ route('reports.inventory') }}" class="bg-white border border-slate-200/80 rounded-xl p-5 shadow-xs hover:border-indigo-500 hover:shadow-md transition group">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-cyan-50 text-cyan-600 flex items-center justify-center group-hover:bg-cyan-600 group-hover:text-white transition">
                        <i data-lucide="boxes" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 group-hover:text-cyan-600 transition">{{ __('erp.reports.inventory_report') }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Stock on hand, low-stock threshold alerts & WAC valuation</p>
                    </div>
                </div>
            </a>

            <!-- 7. Operational Profit & Loss -->
            <a href="{{ route('reports.profit-loss') }}" class="bg-white border border-slate-200/80 rounded-xl p-5 shadow-xs hover:border-indigo-500 hover:shadow-md transition group">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition">
                        <i data-lucide="pie-chart" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 group-hover:text-blue-600 transition">{{ __('erp.reports.profit_loss_report') }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Sales, COGS at WAC, gross profit & operating net profit</p>
                    </div>
                </div>
            </a>

            <!-- 8. Cash Flow Statement -->
            <a href="{{ route('reports.cash-flow') }}" class="bg-white border border-slate-200/80 rounded-xl p-5 shadow-xs hover:border-indigo-500 hover:shadow-md transition group">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-teal-50 text-teal-600 flex items-center justify-center group-hover:bg-teal-600 group-hover:text-white transition">
                        <i data-lucide="arrow-left-right" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 group-hover:text-teal-600 transition">{{ __('erp.reports.cash_flow_report') }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Inflows, outflows, non-operating transfer audit & liquidity</p>
                    </div>
                </div>
            </a>

            <!-- 9. Financial Position & Disclosures -->
            <a href="{{ route('reports.financial-position') }}" class="bg-white border border-slate-200/80 rounded-xl p-5 shadow-xs hover:border-indigo-500 hover:shadow-md transition group">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center group-hover:bg-slate-800 group-hover:text-white transition">
                        <i data-lucide="shield-alert" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 group-hover:text-slate-800 transition">{{ __('erp.reports.financial_position_report') }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Verified current assets, working capital & GL audit disclosure</p>
                    </div>
                </div>
            </a>

        </div>
    </div>

</div>
@endsection
