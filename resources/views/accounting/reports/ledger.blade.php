@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.general_ledger.ledger') }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                    Account Running Balances
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">Audit-proof individual ledger statement with opening balances, chronological debits/credits, and running balance.</p>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
        <form method="GET" action="{{ route('accounting.ledger') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="lg:col-span-2">
                <label for="account_id" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.general_ledger.select_account') }} *</label>
                <select id="account_id" name="account_id" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 font-medium">
                    @foreach($postableAccounts as $acc)
                        <option value="{{ $acc->id }}" {{ ($selectedAccount && $selectedAccount->id == $acc->id) ? 'selected' : '' }}>
                            {{ $acc->code }} — {{ $acc->name }} ({{ ucfirst($acc->type) }})
                        </option>
                    @endforeach
                </select>
            </div>

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

            <div class="flex items-end gap-2">
                <button type="submit" class="w-full py-2 bg-slate-900 text-white rounded-lg text-sm font-semibold hover:bg-slate-800 transition flex items-center justify-center gap-1.5">
                    <i data-lucide="filter" class="w-4 h-4"></i>
                    <span>{{ __('erp.filter') }}</span>
                </button>
            </div>
        </form>
    </div>

    @if($ledgerData && $selectedAccount)
        <!-- Account Overview Card -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Account Code & Name</div>
                <div class="mt-2 text-lg font-bold text-slate-900 font-mono">{{ $selectedAccount->code }}</div>
                <div class="text-xs text-slate-500 font-semibold">{{ $selectedAccount->name }}</div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">{{ __('erp.general_ledger.opening_balance') }}</div>
                <div class="mt-2 text-xl font-bold font-mono text-slate-800">
                    Rs. {{ number_format($ledgerData['opening_balance'], 2) }}
                </div>
                <div class="text-xs text-slate-400">Before {{ \Carbon\Carbon::parse($fromDate)->format('M d, Y') }}</div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">Period Activity (DR / CR)</div>
                <div class="mt-2 text-sm font-bold font-mono text-slate-800">
                    <span class="text-blue-700">+{{ number_format($ledgerData['total_debit'], 2) }}</span> /
                    <span class="text-indigo-700">-{{ number_format($ledgerData['total_credit'], 2) }}</span>
                </div>
                <div class="text-xs text-slate-400">Net movement</div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">{{ __('erp.general_ledger.closing_balance') }}</div>
                <div class="mt-2 text-xl font-bold font-mono text-indigo-700">
                    Rs. {{ number_format($ledgerData['closing_balance'], 2) }}
                </div>
                <div class="text-xs text-slate-400">As of {{ \Carbon\Carbon::parse($toDate)->format('M d, Y') }}</div>
            </div>
        </div>

        <!-- Ledger Entries Table -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
            <div class="p-4 border-b border-slate-200/80 flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="list" class="w-4 h-4 text-indigo-600"></i>
                    Chronological Ledger Entries
                </h2>
                <span class="text-xs text-slate-500 font-mono">{{ count($ledgerData['entries']) }} movements</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50/75 border-b border-slate-200/80 text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-bold">Date</th>
                            <th class="px-4 py-3 font-bold">Voucher #</th>
                            <th class="px-4 py-3 font-bold">Branch</th>
                            <th class="px-4 py-3 font-bold">Particulars / Narration</th>
                            <th class="px-4 py-3 font-bold text-right" style="width: 15%;">Debit (PKR)</th>
                            <th class="px-4 py-3 font-bold text-right" style="width: 15%;">Credit (PKR)</th>
                            <th class="px-4 py-3 font-bold text-right" style="width: 18%;">{{ __('erp.general_ledger.running_balance') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <!-- Opening Balance Row -->
                        <tr class="bg-slate-50/75 font-semibold text-slate-700">
                            <td class="px-4 py-3 font-mono text-xs">{{ \Carbon\Carbon::parse($fromDate)->format('M d, Y') }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-400">—</td>
                            <td class="px-4 py-3 text-xs text-slate-400">—</td>
                            <td class="px-4 py-3 text-xs uppercase font-bold text-slate-600">{{ __('erp.general_ledger.opening_balance') }}</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-400">—</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-400">—</td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">
                                Rs. {{ number_format($ledgerData['opening_balance'], 2) }}
                            </td>
                        </tr>

                        @forelse($ledgerData['entries'] as $ent)
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="px-4 py-3 font-mono text-xs text-slate-800">
                                    {{ \Carbon\Carbon::parse($ent['entry_date'])->format('M d, Y') }}
                                </td>
                                <td class="px-4 py-3 font-mono font-bold text-indigo-700 text-xs">
                                    <a href="{{ route('accounting.journals.show', $ent['entry_id']) }}" class="hover:underline">
                                        {{ $ent['entry_number'] }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-500">
                                    {{ $ent['location'] }}
                                </td>
                                <td class="px-4 py-3 text-slate-800">
                                    {{ $ent['description'] }}
                                    @if(!empty($ent['reference_number']))
                                        <span class="text-xs text-slate-400 font-mono ms-1">({{ $ent['reference_number'] }})</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">
                                    @if((float)$ent['debit'] > 0)
                                        Rs. {{ number_format($ent['debit'], 2) }}
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">
                                    @if((float)$ent['credit'] > 0)
                                        Rs. {{ number_format($ent['credit'], 2) }}
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-indigo-700">
                                    Rs. {{ number_format($ent['running_balance'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-slate-400 text-sm">
                                    No transaction movements recorded for this account in the selected date range.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-slate-50/75 border-t border-slate-200/80 font-bold">
                        <tr>
                            <td colspan="4" class="px-4 py-3 text-right uppercase text-xs text-slate-700">
                                Period Totals:
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-base text-slate-900">
                                Rs. {{ number_format($ledgerData['total_debit'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-base text-slate-900">
                                Rs. {{ number_format($ledgerData['total_credit'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-base text-indigo-700">
                                Rs. {{ number_format($ledgerData['closing_balance'], 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
