@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.general_ledger.profit_loss') }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ (float)$report['net_profit'] >= 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                    Net {{ (float)$report['net_profit'] >= 0 ? 'Profit' : 'Loss' }}: Rs. {{ number_format((float)$report['net_profit'], 2) }}
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">Formal double-entry income statement computed directly from posted general ledger transactions.</p>
        </div>

        <div class="flex items-center gap-2.5">
            <button onclick="window.print()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-900 text-white text-sm font-semibold rounded-lg hover:bg-slate-800 transition shadow-xs">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span>{{ __('erp.print') }}</span>
            </button>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs print:hidden">
        <form method="GET" action="{{ route('accounting.profit-loss') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label for="from_date" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.reports.from_date') }}</label>
                <input type="date" id="from_date" name="from_date" value="{{ $fromDate }}"
                       class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 font-mono">
            </div>

            <div>
                <label for="to_date" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.reports.to_date') }}</label>
                <input type="date" id="to_date" name="to_date" value="{{ $toDate }}"
                       class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 font-mono">
            </div>

            <div>
                <label for="location_id" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.location') }}</label>
                <select id="location_id" name="location_id" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                    <option value="">{{ __('erp.reports.all_locations') }}</option>
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" {{ $locationId == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end">
                <button type="submit" class="w-full py-2 bg-slate-900 text-white rounded-lg text-sm font-semibold hover:bg-slate-800 transition flex items-center justify-center gap-1.5">
                    <i data-lucide="filter" class="w-4 h-4"></i>
                    <span>{{ __('erp.filter') }}</span>
                </button>
            </div>
        </form>
    </div>

    <!-- P&L KPI Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Gross Operating Revenue</div>
            <div class="mt-2 text-xl font-bold font-mono text-emerald-700">
                Rs. {{ number_format((float)$report['total_revenue'], 2) }}
            </div>
            <div class="text-xs text-slate-400">Total net revenue</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Cost of Goods Sold (COGS)</div>
            <div class="mt-2 text-xl font-bold font-mono text-amber-700">
                Rs. {{ number_format((float)$report['total_cogs'], 2) }}
            </div>
            <div class="text-xs text-slate-400">Direct production & inventory</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Gross Profit</div>
            <div class="mt-2 text-xl font-bold font-mono {{ (float)$report['gross_profit'] >= 0 ? 'text-indigo-700' : 'text-rose-700' }}">
                Rs. {{ number_format((float)$report['gross_profit'], 2) }}
            </div>
            <div class="text-xs text-slate-400">
                @if((float)$report['total_revenue'] > 0)
                    Margin: {{ number_format(((float)$report['gross_profit'] / (float)$report['total_revenue']) * 100, 1) }}%
                @else
                    Margin: 0.0%
                @endif
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Net Operating Profit</div>
            <div class="mt-2 text-xl font-bold font-mono {{ (float)$report['net_profit'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                Rs. {{ number_format((float)$report['net_profit'], 2) }}
            </div>
            <div class="text-xs {{ (float)$report['net_profit'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }} font-semibold">
                After Rs. {{ number_format((float)$report['total_operating_expenses'], 2) }} operating costs
            </div>
        </div>
    </div>

    <!-- Formal Statement View -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="p-6 border-b border-slate-200/80 bg-slate-50/50 flex flex-col md:flex-row justify-between items-start md:items-center gap-2">
            <div>
                <h2 class="text-lg font-bold text-slate-900 tracking-tight">Statement of Profit and Loss</h2>
                <p class="text-xs text-slate-500">Period: {{ \Carbon\Carbon::parse($fromDate)->format('M d, Y') }} — {{ \Carbon\Carbon::parse($toDate)->format('M d, Y') }}</p>
            </div>
            <div class="text-xs font-mono font-semibold px-2.5 py-1 bg-white border border-slate-300 rounded text-slate-700">
                Reporting Currency: PKR (Rs.)
            </div>
        </div>

        <div class="p-6 space-y-8">
            <!-- 1. Operating Revenue -->
            <div>
                <div class="flex items-center justify-between pb-2 border-b-2 border-slate-900">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900">1. Operating Revenue</h3>
                    <span class="text-xs font-bold text-slate-400 uppercase">Amount (PKR)</span>
                </div>
                <table class="w-full text-sm mt-2">
                    <tbody class="divide-y divide-slate-100">
                        @forelse($report['revenues'] as $row)
                            <tr class="hover:bg-slate-50/50">
                                <td class="py-2.5 px-3 font-mono text-xs text-slate-500 w-24">
                                    <a href="{{ route('accounting.ledger', ['account_id' => $row['account']->id, 'from_date' => $fromDate, 'to_date' => $toDate]) }}" class="hover:underline text-indigo-600">
                                        {{ $row['account']->code }}
                                    </a>
                                </td>
                                <td class="py-2.5 px-3 font-medium text-slate-800">{{ $row['account']->name }}</td>
                                <td class="py-2.5 px-3 text-right font-mono font-semibold text-slate-900 w-48">
                                    {{ number_format((float)$row['amount'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-3 px-3 text-center text-xs text-slate-400">No revenue accounts recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-50 font-bold text-sm border-t border-slate-200">
                            <td colspan="2" class="py-3 px-3 text-slate-900">Total Revenue</td>
                            <td class="py-3 px-3 text-right font-mono text-emerald-700">
                                Rs. {{ number_format((float)$report['total_revenue'], 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- 2. Cost of Goods Sold -->
            <div>
                <div class="flex items-center justify-between pb-2 border-b-2 border-slate-900">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900">2. Cost of Goods Sold & Production</h3>
                    <span class="text-xs font-bold text-slate-400 uppercase">Amount (PKR)</span>
                </div>
                <table class="w-full text-sm mt-2">
                    <tbody class="divide-y divide-slate-100">
                        @forelse($report['cogs'] as $row)
                            <tr class="hover:bg-slate-50/50">
                                <td class="py-2.5 px-3 font-mono text-xs text-slate-500 w-24">
                                    <a href="{{ route('accounting.ledger', ['account_id' => $row['account']->id, 'from_date' => $fromDate, 'to_date' => $toDate]) }}" class="hover:underline text-indigo-600">
                                        {{ $row['account']->code }}
                                    </a>
                                </td>
                                <td class="py-2.5 px-3 font-medium text-slate-800">{{ $row['account']->name }}</td>
                                <td class="py-2.5 px-3 text-right font-mono font-semibold text-slate-900 w-48">
                                    {{ number_format((float)$row['amount'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-3 px-3 text-center text-xs text-slate-400">No cost of goods sold recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-50 font-bold text-sm border-t border-slate-200">
                            <td colspan="2" class="py-3 px-3 text-slate-900">Total Cost of Goods Sold</td>
                            <td class="py-3 px-3 text-right font-mono text-amber-800">
                                Rs. {{ number_format((float)$report['total_cogs'], 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Gross Profit Highlight Line -->
            <div class="p-4 rounded-xl bg-indigo-50/60 border border-indigo-100 flex items-center justify-between font-bold text-base">
                <span class="text-indigo-950 uppercase tracking-wider text-sm">Gross Profit (1 - 2)</span>
                <span class="font-mono text-indigo-900 text-lg">Rs. {{ number_format((float)$report['gross_profit'], 2) }}</span>
            </div>

            <!-- 3. Operating Expenses -->
            <div>
                <div class="flex items-center justify-between pb-2 border-b-2 border-slate-900">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900">3. Operating & Administrative Expenses</h3>
                    <span class="text-xs font-bold text-slate-400 uppercase">Amount (PKR)</span>
                </div>
                <table class="w-full text-sm mt-2">
                    <tbody class="divide-y divide-slate-100">
                        @forelse($report['operating_expenses'] as $row)
                            <tr class="hover:bg-slate-50/50">
                                <td class="py-2.5 px-3 font-mono text-xs text-slate-500 w-24">
                                    <a href="{{ route('accounting.ledger', ['account_id' => $row['account']->id, 'from_date' => $fromDate, 'to_date' => $toDate]) }}" class="hover:underline text-indigo-600">
                                        {{ $row['account']->code }}
                                    </a>
                                </td>
                                <td class="py-2.5 px-3 font-medium text-slate-800">{{ $row['account']->name }}</td>
                                <td class="py-2.5 px-3 text-right font-mono font-semibold text-slate-900 w-48">
                                    {{ number_format((float)$row['amount'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-3 px-3 text-center text-xs text-slate-400">No operating expenses recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-50 font-bold text-sm border-t border-slate-200">
                            <td colspan="2" class="py-3 px-3 text-slate-900">Total Operating Expenses</td>
                            <td class="py-3 px-3 text-right font-mono text-rose-800">
                                Rs. {{ number_format((float)$report['total_operating_expenses'], 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Net Operating Profit / Loss Master Box -->
            <div class="p-6 rounded-2xl {{ (float)$report['net_profit'] >= 0 ? 'bg-emerald-500 text-white' : 'bg-rose-500 text-white' }} shadow-md flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-bold uppercase tracking-wider">
                        Net Operating {{ (float)$report['net_profit'] >= 0 ? 'Profit' : 'Loss' }}
                    </h3>
                    <p class="text-xs opacity-90 mt-0.5">Bottom line financial return for the selected reporting period</p>
                </div>
                <div class="text-2xl md:text-3xl font-extrabold font-mono tracking-tight">
                    Rs. {{ number_format((float)$report['net_profit'], 2) }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
