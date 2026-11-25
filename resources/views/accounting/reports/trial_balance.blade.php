@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.general_ledger.trial_balance') }}</h1>
                @if($report['totals']['is_balanced'])
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                        <i data-lucide="check-circle-2" class="w-3.5 h-3.5 mr-1 text-emerald-600"></i>
                        {{ __('erp.general_ledger.balanced') }}
                    </span>
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800">
                        <i data-lucide="alert-triangle" class="w-3.5 h-3.5 mr-1 text-rose-600"></i>
                        Unbalanced: Rs. {{ number_format(abs((float)$report['totals']['discrepancy']), 2) }}
                    </span>
                @endif
            </div>
            <p class="text-sm text-slate-500 mt-1">Double-entry accounting verification statement ensuring total debit balances equal total credit balances.</p>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('accounting.trial-balance.csv', request()->query()) }}"
               class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-semibold rounded-lg hover:bg-slate-50 transition shadow-xs">
                <i data-lucide="download" class="w-4 h-4 text-slate-500"></i>
                <span>{{ __('erp.export_csv') }}</span>
            </a>
            <button onclick="window.print()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-900 text-white text-sm font-semibold rounded-lg hover:bg-slate-800 transition shadow-xs">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span>{{ __('erp.print') }}</span>
            </button>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs print:hidden">
        <form method="GET" action="{{ route('accounting.trial-balance') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
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

    <!-- Trial Balance KPI Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Opening (DR / CR)</div>
            <div class="mt-2 text-sm font-bold font-mono text-slate-800">
                Rs. {{ number_format((float)$report['totals']['opening_debit'], 2) }}
            </div>
            <div class="text-xs text-slate-400">Balancing: Rs. {{ number_format((float)$report['totals']['opening_credit'], 2) }}</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Period Activity Debit</div>
            <div class="mt-2 text-lg font-bold font-mono text-blue-700">
                Rs. {{ number_format((float)$report['totals']['period_debit'], 2) }}
            </div>
            <div class="text-xs text-slate-400">Total debit movements</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Period Activity Credit</div>
            <div class="mt-2 text-lg font-bold font-mono text-indigo-700">
                Rs. {{ number_format((float)$report['totals']['period_credit'], 2) }}
            </div>
            <div class="text-xs text-slate-400">Total credit movements</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Closing Balance Verification</div>
            <div class="mt-2 text-lg font-bold font-mono {{ $report['totals']['is_balanced'] ? 'text-emerald-700' : 'text-rose-700' }}">
                Rs. {{ number_format((float)$report['totals']['closing_debit'], 2) }}
            </div>
            <div class="text-xs {{ $report['totals']['is_balanced'] ? 'text-emerald-600' : 'text-rose-600' }}">
                {{ $report['totals']['is_balanced'] ? 'Debits equal Credits' : 'Discrepancy: ' . number_format((float)$report['totals']['discrepancy'], 2) }}
            </div>
        </div>
    </div>

    <!-- Trial Balance Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="p-4 border-b border-slate-200/80 flex items-center justify-between">
            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                <i data-lucide="scale" class="w-4 h-4 text-indigo-600"></i>
                Account Ledger Trial Balances
            </h2>
            <span class="text-xs text-slate-500 font-mono">{{ count($report['rows']) }} Active Accounts</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50/75 border-b border-slate-200/80 text-xs uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="py-3 px-4 font-semibold">Account Code</th>
                        <th class="py-3 px-4 font-semibold">Account Name</th>
                        <th class="py-3 px-4 font-semibold">Type</th>
                        <th class="py-3 px-4 font-semibold text-right">Opening DR</th>
                        <th class="py-3 px-4 font-semibold text-right">Opening CR</th>
                        <th class="py-3 px-4 font-semibold text-right">Period DR</th>
                        <th class="py-3 px-4 font-semibold text-right">Period CR</th>
                        <th class="py-3 px-4 font-semibold text-right text-indigo-900">Closing Debit</th>
                        <th class="py-3 px-4 font-semibold text-right text-indigo-900">Closing Credit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($report['rows'] as $row)
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="py-3 px-4 font-mono font-bold text-xs text-slate-900">
                                <a href="{{ route('accounting.ledger', ['account_id' => $row['account_id'], 'from_date' => $fromDate, 'to_date' => $toDate, 'location_id' => $locationId]) }}" class="hover:underline text-indigo-600">
                                    {{ $row['code'] }}
                                </a>
                            </td>
                            <td class="py-3 px-4 font-semibold text-slate-900">
                                {{ $row['name'] }}
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold
                                    @if($row['type'] === 'asset') bg-blue-50 text-blue-700
                                    @elseif($row['type'] === 'liability') bg-amber-50 text-amber-700
                                    @elseif($row['type'] === 'equity') bg-purple-50 text-purple-700
                                    @elseif($row['type'] === 'revenue') bg-emerald-50 text-emerald-700
                                    @else bg-rose-50 text-rose-700
                                    @endif">
                                    {{ ucfirst($row['type']) }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-xs text-slate-500">
                                {{ number_format((float)$row['opening_debit'], 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-xs text-slate-500">
                                {{ number_format((float)$row['opening_credit'], 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-xs text-blue-700 font-semibold">
                                {{ number_format((float)$row['period_debit'], 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-xs text-indigo-700 font-semibold">
                                {{ number_format((float)$row['period_credit'], 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-xs {{ (float)$row['closing_debit'] > 0 ? 'text-slate-900' : 'text-slate-300' }}">
                                {{ number_format((float)$row['closing_debit'], 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-xs {{ (float)$row['closing_credit'] > 0 ? 'text-slate-900' : 'text-slate-300' }}">
                                {{ number_format((float)$row['closing_credit'], 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center text-slate-400">
                                No journal activity recorded for the selected date range.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-slate-100/75 border-t-2 border-slate-300 font-bold text-xs text-slate-900 font-mono">
                    <tr>
                        <td colspan="3" class="py-3.5 px-4 uppercase tracking-wider text-slate-700">
                            Grand Totals
                        </td>
                        <td class="py-3.5 px-4 text-right">
                            {{ number_format((float)$report['totals']['opening_debit'], 2) }}
                        </td>
                        <td class="py-3.5 px-4 text-right">
                            {{ number_format((float)$report['totals']['opening_credit'], 2) }}
                        </td>
                        <td class="py-3.5 px-4 text-right text-blue-800">
                            {{ number_format((float)$report['totals']['period_debit'], 2) }}
                        </td>
                        <td class="py-3.5 px-4 text-right text-indigo-800">
                            {{ number_format((float)$report['totals']['period_credit'], 2) }}
                        </td>
                        <td class="py-3.5 px-4 text-right text-sm {{ $report['totals']['is_balanced'] ? 'text-emerald-700' : 'text-rose-700' }}">
                            {{ number_format((float)$report['totals']['closing_debit'], 2) }}
                        </td>
                        <td class="py-3.5 px-4 text-right text-sm {{ $report['totals']['is_balanced'] ? 'text-emerald-700' : 'text-rose-700' }}">
                            {{ number_format((float)$report['totals']['closing_credit'], 2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
