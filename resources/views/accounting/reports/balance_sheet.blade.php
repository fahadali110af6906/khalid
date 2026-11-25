@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.general_ledger.balance_sheet') }}</h1>
                @if($report['is_balanced'])
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                        <i data-lucide="check-circle-2" class="w-3.5 h-3.5 mr-1 text-emerald-600"></i>
                        Assets = Liabilities + Equity
                    </span>
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800">
                        <i data-lucide="alert-triangle" class="w-3.5 h-3.5 mr-1 text-rose-600"></i>
                        Discrepancy: Rs. {{ number_format(abs((float)$report['discrepancy']), 2) }}
                    </span>
                @endif
            </div>
            <p class="text-sm text-slate-500 mt-1">Double-entry Statement of Financial Position showing assets, liabilities, and owners' equity as of {{ \Carbon\Carbon::parse($asOfDate)->format('M d, Y') }}.</p>
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
        <form method="GET" action="{{ route('accounting.balance-sheet') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <div>
                <label for="as_of_date" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.reports.as_of_date') }}</label>
                <input type="date" id="as_of_date" name="as_of_date" value="{{ $asOfDate }}"
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

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Assets</div>
            <div class="mt-2 text-xl font-bold font-mono text-blue-700">
                Rs. {{ number_format((float)$report['total_assets'], 2) }}
            </div>
            <div class="text-xs text-slate-400">Cash, receivables, stock</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Liabilities</div>
            <div class="mt-2 text-xl font-bold font-mono text-amber-700">
                Rs. {{ number_format((float)$report['total_liabilities'], 2) }}
            </div>
            <div class="text-xs text-slate-400">Suppliers & advances</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Equity</div>
            <div class="mt-2 text-xl font-bold font-mono text-purple-700">
                Rs. {{ number_format((float)$report['total_equity'], 2) }}
            </div>
            <div class="text-xs text-slate-400">Capital + Retained earnings</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Liabilities + Equity</div>
            <div class="mt-2 text-xl font-bold font-mono {{ $report['is_balanced'] ? 'text-emerald-700' : 'text-rose-700' }}">
                Rs. {{ number_format((float)$report['total_liabilities_and_equity'], 2) }}
            </div>
            <div class="text-xs {{ $report['is_balanced'] ? 'text-emerald-600' : 'text-rose-600' }} font-semibold">
                {{ $report['is_balanced'] ? 'Double-entry in balance' : 'Discrepancy: ' . number_format((float)$report['discrepancy'], 2) }}
            </div>
        </div>
    </div>

    <!-- Formal Two-Column Statement -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
        <!-- LEFT: ASSETS -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
            <div class="p-4 border-b border-slate-200/80 bg-blue-50/50 flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="building" class="w-4 h-4 text-blue-600"></i>
                    Assets (1000 - 1999)
                </h2>
                <span class="text-xs font-mono font-bold text-blue-900">Normal Balance: Debit</span>
            </div>

            <div class="p-4">
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-100">
                        @forelse($report['assets'] as $row)
                            <tr class="hover:bg-slate-50/50">
                                <td class="py-2.5 px-3 font-mono text-xs text-slate-500 w-24">
                                    <a href="{{ route('accounting.ledger', ['account_id' => $row['account']->id, 'to_date' => $asOfDate]) }}" class="hover:underline text-indigo-600">
                                        {{ $row['account']->code }}
                                    </a>
                                </td>
                                <td class="py-2.5 px-3 font-medium text-slate-800">{{ $row['account']->name }}</td>
                                <td class="py-2.5 px-3 text-right font-mono font-semibold text-slate-900 w-40">
                                    {{ number_format((float)$row['balance'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-4 text-center text-xs text-slate-400">No asset accounts recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="bg-blue-50/75 font-bold text-base border-t-2 border-blue-200 text-blue-950">
                            <td colspan="2" class="py-3 px-3 uppercase tracking-wider text-xs">Total Assets</td>
                            <td class="py-3 px-3 text-right font-mono">
                                Rs. {{ number_format((float)$report['total_assets'], 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- RIGHT: LIABILITIES & EQUITY -->
        <div class="space-y-6">
            <!-- LIABILITIES -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="p-4 border-b border-slate-200/80 bg-amber-50/50 flex items-center justify-between">
                    <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <i data-lucide="credit-card" class="w-4 h-4 text-amber-600"></i>
                        Liabilities (2000 - 2999)
                    </h2>
                    <span class="text-xs font-mono font-bold text-amber-900">Normal Balance: Credit</span>
                </div>

                <div class="p-4">
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-slate-100">
                            @forelse($report['liabilities'] as $row)
                                <tr class="hover:bg-slate-50/50">
                                    <td class="py-2.5 px-3 font-mono text-xs text-slate-500 w-24">
                                        <a href="{{ route('accounting.ledger', ['account_id' => $row['account']->id, 'to_date' => $asOfDate]) }}" class="hover:underline text-indigo-600">
                                            {{ $row['account']->code }}
                                        </a>
                                    </td>
                                    <td class="py-2.5 px-3 font-medium text-slate-800">{{ $row['account']->name }}</td>
                                    <td class="py-2.5 px-3 text-right font-mono font-semibold text-slate-900 w-40">
                                        {{ number_format((float)$row['balance'], 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-4 text-center text-xs text-slate-400">No liability accounts recorded.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="bg-amber-50/75 font-bold text-sm border-t-2 border-amber-200 text-amber-950">
                                <td colspan="2" class="py-2.5 px-3 uppercase tracking-wider text-xs">Total Liabilities</td>
                                <td class="py-2.5 px-3 text-right font-mono">
                                    Rs. {{ number_format((float)$report['total_liabilities'], 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- EQUITY -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="p-4 border-b border-slate-200/80 bg-purple-50/50 flex items-center justify-between">
                    <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <i data-lucide="pie-chart" class="w-4 h-4 text-purple-600"></i>
                        Owners' Equity (3000 - 3999)
                    </h2>
                    <span class="text-xs font-mono font-bold text-purple-900">Capital & Earnings</span>
                </div>

                <div class="p-4">
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-slate-100">
                            @forelse($report['equity'] as $row)
                                <tr class="hover:bg-slate-50/50">
                                    <td class="py-2.5 px-3 font-mono text-xs text-slate-500 w-24">
                                        <a href="{{ route('accounting.ledger', ['account_id' => $row['account']->id, 'to_date' => $asOfDate]) }}" class="hover:underline text-indigo-600">
                                            {{ $row['account']->code }}
                                        </a>
                                    </td>
                                    <td class="py-2.5 px-3 font-medium text-slate-800">{{ $row['account']->name }}</td>
                                    <td class="py-2.5 px-3 text-right font-mono font-semibold text-slate-900 w-40">
                                        {{ number_format((float)$row['balance'], 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-2 text-center text-xs text-slate-400">No static equity lines.</td>
                                </tr>
                            @endforelse

                            <!-- Dynamic Retained Earnings Line -->
                            <tr class="bg-purple-50/30">
                                <td class="py-2.5 px-3 font-mono text-xs text-purple-800 font-bold w-24">3300-PL</td>
                                <td class="py-2.5 px-3 font-medium text-purple-950">
                                    Cumulative Retained Earnings (Net Profit/Loss to Date)
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold {{ (float)$report['retained_earnings_cumulative'] >= 0 ? 'text-purple-900' : 'text-rose-700' }} w-40">
                                    {{ number_format((float)$report['retained_earnings_cumulative'], 2) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="bg-purple-50/75 font-bold text-sm border-t-2 border-purple-200 text-purple-950">
                                <td colspan="2" class="py-2.5 px-3 uppercase tracking-wider text-xs">Total Owners' Equity</td>
                                <td class="py-2.5 px-3 text-right font-mono">
                                    Rs. {{ number_format((float)$report['total_equity'], 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- TOTAL LIABILITIES & EQUITY COMBINED BAR -->
            <div class="p-4 rounded-xl {{ $report['is_balanced'] ? 'bg-slate-900 text-white' : 'bg-rose-900 text-white' }} flex items-center justify-between font-bold text-base shadow-sm">
                <div>
                    <span class="uppercase tracking-wider text-xs block text-slate-400">Balance Sheet Equivalence</span>
                    <span class="text-sm">Total Liabilities & Equity</span>
                </div>
                <div class="font-mono text-lg text-emerald-400">
                    Rs. {{ number_format((float)$report['total_liabilities_and_equity'], 2) }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
