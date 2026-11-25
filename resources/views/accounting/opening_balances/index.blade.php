@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.general_ledger.opening_balances') }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                    One-Time Inception Setup
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">Configure initial double-entry opening balances for assets, liabilities, and owner capital.</p>
        </div>
    </div>

    @if($existing)
        <!-- Already Established Banner & Details -->
        <div class="p-5 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-950 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="flex items-start gap-3">
                <div class="p-2 bg-emerald-100 text-emerald-700 rounded-xl mt-0.5">
                    <i data-lucide="check-circle-2" class="w-6 h-6"></i>
                </div>
                <div>
                    <h3 class="font-bold text-base text-emerald-900">Opening Balances Established</h3>
                    <p class="text-xs text-emerald-800 mt-1">
                        Recorded on {{ $existing->entry_date->format('M d, Y') }} under Journal Voucher
                        <span class="font-mono font-bold">{{ $existing->entry_number }}</span>.
                        Status: <span class="uppercase font-bold">{{ $existing->status }}</span>.
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('accounting.journals.show', $existing) }}" class="px-4 py-2 bg-emerald-700 text-white text-xs font-semibold rounded-lg hover:bg-emerald-800 transition">
                    View Journal Voucher
                </a>
            </div>
        </div>

        <!-- Voucher Details Table -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
            <div class="p-4 border-b border-slate-200/80 bg-slate-50/50 flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-900">Recorded Opening Balance Lines</h3>
                <span class="text-xs font-mono font-bold text-slate-500">Total: Rs. {{ number_format($existing->total_debit, 2) }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-4">Account</th>
                            <th class="py-3 px-4">Location</th>
                            <th class="py-3 px-4">Description</th>
                            <th class="py-3 px-4 text-right">Debit (Rs.)</th>
                            <th class="py-3 px-4 text-right">Credit (Rs.)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($existing->lines as $line)
                            <tr>
                                <td class="py-3 px-4 font-mono font-semibold text-slate-900">
                                    {{ $line->account->code }} — {{ $line->account->name }}
                                </td>
                                <td class="py-3 px-4 text-xs text-slate-500">
                                    {{ $line->location ? $line->location->name : 'Global' }}
                                </td>
                                <td class="py-3 px-4 text-xs text-slate-500">
                                    {{ $line->description ?: '-' }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-semibold text-slate-900">
                                    {{ (float)$line->debit > 0 ? number_format($line->debit, 2) : '-' }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-semibold text-slate-900">
                                    {{ (float)$line->credit > 0 ? number_format($line->credit, 2) : '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <!-- Setup Form -->
        <form method="POST" action="{{ route('accounting.opening-balances.store') }}" id="openingBalanceForm" class="space-y-6">
            @csrf

            <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-4">
                <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3 flex items-center gap-2">
                    <i data-lucide="calendar" class="w-4 h-4 text-indigo-600"></i>
                    Inception Details
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="opening_date" class="block text-xs font-bold text-slate-600 uppercase mb-1">Inception / Opening Date *</label>
                        <input type="date" id="opening_date" name="opening_date" value="{{ old('opening_date', now()->startOfYear()->toDateString()) }}"
                               required class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 font-mono">
                    </div>

                    <div>
                        <label for="notes" class="block text-xs font-bold text-slate-600 uppercase mb-1">Notes / Inception Reference</label>
                        <input type="text" id="notes" name="notes" value="{{ old('notes', 'Initial ERP Chart of Accounts Opening Balance Setup') }}"
                               placeholder="e.g. Audited opening balances as of FY start"
                               class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
            </div>

            <!-- Balances Table -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="p-4 border-b border-slate-200/80 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                            <i data-lucide="scale" class="w-4 h-4 text-indigo-600"></i>
                            Account Opening Balances
                        </h2>
                        <p class="text-xs text-slate-500">Total debits must strictly equal total credits before posting.</p>
                    </div>
                    <button type="button" id="addRowBtn" class="px-3 py-1.5 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-lg text-xs font-bold transition flex items-center gap-1">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i> Add Account Line
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600" id="linesTable">
                        <thead class="bg-slate-50/75 border-b border-slate-200 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="py-3 px-4 w-5/12">Account *</th>
                                <th class="py-3 px-4 w-2/12">Location</th>
                                <th class="py-3 px-4 w-2/12 text-right">Debit (Rs.)</th>
                                <th class="py-3 px-4 w-2/12 text-right">Credit (Rs.)</th>
                                <th class="py-3 px-4 w-1/12 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="linesContainer" class="divide-y divide-slate-100">
                            <!-- Line 1: Default Cash In Hand (Debit) -->
                            <tr class="line-row">
                                <td class="py-2.5 px-4">
                                    <select name="lines[0][chart_of_account_id]" required class="w-full py-1.5 px-2 text-xs bg-slate-50 border border-slate-300 rounded-md focus:ring-1 focus:ring-indigo-500 font-medium">
                                        <option value="">Select Account</option>
                                        @foreach($postableAccounts as $acc)
                                            <option value="{{ $acc->id }}" {{ $acc->system_identifier === 'cash_in_hand' ? 'selected' : '' }}>
                                                {{ $acc->code }} — {{ $acc->name }} ({{ ucfirst($acc->type) }})
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-2.5 px-4">
                                    <select name="lines[0][location_id]" class="w-full py-1.5 px-2 text-xs bg-slate-50 border border-slate-300 rounded-md focus:ring-1 focus:ring-indigo-500">
                                        <option value="">Global / All</option>
                                        @foreach($locations as $loc)
                                            <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-2.5 px-4">
                                    <input type="number" step="0.01" min="0" name="lines[0][debit]" value="0.00"
                                           class="debit-input w-full py-1.5 px-2 text-xs text-right font-mono bg-slate-50 border border-slate-300 rounded-md focus:ring-1 focus:ring-indigo-500">
                                </td>
                                <td class="py-2.5 px-4">
                                    <input type="number" step="0.01" min="0" name="lines[0][credit]" value="0.00"
                                           class="credit-input w-full py-1.5 px-2 text-xs text-right font-mono bg-slate-50 border border-slate-300 rounded-md focus:ring-1 focus:ring-indigo-500">
                                </td>
                                <td class="py-2.5 px-4 text-center">
                                    <button type="button" class="remove-row-btn text-slate-400 hover:text-rose-600 p-1">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </td>
                            </tr>

                            <!-- Line 2: Default Owner's Capital / Opening Balance Equity (Credit) -->
                            <tr class="line-row">
                                <td class="py-2.5 px-4">
                                    <select name="lines[1][chart_of_account_id]" required class="w-full py-1.5 px-2 text-xs bg-slate-50 border border-slate-300 rounded-md focus:ring-1 focus:ring-indigo-500 font-medium">
                                        <option value="">Select Account</option>
                                        @foreach($postableAccounts as $acc)
                                            <option value="{{ $acc->id }}" {{ $acc->system_identifier === 'opening_balance_equity' || $acc->system_identifier === 'owners_capital' ? 'selected' : '' }}>
                                                {{ $acc->code }} — {{ $acc->name }} ({{ ucfirst($acc->type) }})
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-2.5 px-4">
                                    <select name="lines[1][location_id]" class="w-full py-1.5 px-2 text-xs bg-slate-50 border border-slate-300 rounded-md focus:ring-1 focus:ring-indigo-500">
                                        <option value="">Global / All</option>
                                        @foreach($locations as $loc)
                                            <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-2.5 px-4">
                                    <input type="number" step="0.01" min="0" name="lines[1][debit]" value="0.00"
                                           class="debit-input w-full py-1.5 px-2 text-xs text-right font-mono bg-slate-50 border border-slate-300 rounded-md focus:ring-1 focus:ring-indigo-500">
                                </td>
                                <td class="py-2.5 px-4">
                                    <input type="number" step="0.01" min="0" name="lines[1][credit]" value="0.00"
                                           class="credit-input w-full py-1.5 px-2 text-xs text-right font-mono bg-slate-50 border border-slate-300 rounded-md focus:ring-1 focus:ring-indigo-500">
                                </td>
                                <td class="py-2.5 px-4 text-center">
                                    <button type="button" class="remove-row-btn text-slate-400 hover:text-rose-600 p-1">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-slate-50/90 border-t border-slate-200 font-bold text-xs">
                            <tr>
                                <td colspan="2" class="py-3 px-4 text-right uppercase tracking-wider text-slate-700">
                                    Balance Verification Check
                                </td>
                                <td class="py-3 px-4 text-right font-mono text-blue-700" id="totalDebitDisplay">0.00</td>
                                <td class="py-3 px-4 text-right font-mono text-indigo-700" id="totalCreditDisplay">0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="p-4 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div id="balanceAlert" class="text-xs font-semibold flex items-center gap-1.5 text-rose-700">
                        <i data-lucide="alert-circle" class="w-4 h-4"></i>
                        <span>Unbalanced: Total Debits must equal Total Credits before posting.</span>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" id="submitBtn" disabled
                                class="px-5 py-2.5 bg-slate-900 text-white text-xs font-bold rounded-lg hover:bg-slate-800 transition disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-2">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                            <span>Post Opening Balances</span>
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <!-- Template for Dynamic Rows -->
        <template id="rowTemplate">
            <tr class="line-row">
                <td class="py-2.5 px-4">
                    <select name="lines[INDEX][chart_of_account_id]" required class="w-full py-1.5 px-2 text-xs bg-slate-50 border border-slate-300 rounded-md focus:ring-1 focus:ring-indigo-500 font-medium">
                        <option value="">Select Account</option>
                        @foreach($postableAccounts as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->code }} — {{ $acc->name }} ({{ ucfirst($acc->type) }})</option>
                        @endforeach
                    </select>
                </td>
                <td class="py-2.5 px-4">
                    <select name="lines[INDEX][location_id]" class="w-full py-1.5 px-2 text-xs bg-slate-50 border border-slate-300 rounded-md focus:ring-1 focus:ring-indigo-500">
                        <option value="">Global / All</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </td>
                <td class="py-2.5 px-4">
                    <input type="number" step="0.01" min="0" name="lines[INDEX][debit]" value="0.00"
                           class="debit-input w-full py-1.5 px-2 text-xs text-right font-mono bg-slate-50 border border-slate-300 rounded-md focus:ring-1 focus:ring-indigo-500">
                </td>
                <td class="py-2.5 px-4">
                    <input type="number" step="0.01" min="0" name="lines[INDEX][credit]" value="0.00"
                           class="credit-input w-full py-1.5 px-2 text-xs text-right font-mono bg-slate-50 border border-slate-300 rounded-md focus:ring-1 focus:ring-indigo-500">
                </td>
                <td class="py-2.5 px-4 text-center">
                    <button type="button" class="remove-row-btn text-slate-400 hover:text-rose-600 p-1">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </td>
            </tr>
        </template>

        @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                let rowIndex = 2;
                const container = document.getElementById('linesContainer');
                const addRowBtn = document.getElementById('addRowBtn');
                const rowTemplate = document.getElementById('rowTemplate');
                const totalDebitDisplay = document.getElementById('totalDebitDisplay');
                const totalCreditDisplay = document.getElementById('totalCreditDisplay');
                const balanceAlert = document.getElementById('balanceAlert');
                const submitBtn = document.getElementById('submitBtn');

                function calculateTotals() {
                    let totalDebit = 0;
                    let totalCredit = 0;

                    container.querySelectorAll('.line-row').forEach(row => {
                        const deb = parseFloat(row.querySelector('.debit-input')?.value || 0);
                        const cred = parseFloat(row.querySelector('.credit-input')?.value || 0);
                        if (!isNaN(deb)) totalDebit += deb;
                        if (!isNaN(cred)) totalCredit += cred;
                    });

                    totalDebitDisplay.textContent = totalDebit.toFixed(2);
                    totalCreditDisplay.textContent = totalCredit.toFixed(2);

                    const diff = Math.abs(totalDebit - totalCredit);
                    const isBalanced = diff < 0.001 && totalDebit > 0;

                    if (isBalanced) {
                        balanceAlert.className = 'text-xs font-semibold flex items-center gap-1.5 text-emerald-700';
                        balanceAlert.innerHTML = '<i data-lucide="check-circle-2" class="w-4 h-4"></i><span>Debits and Credits are perfectly balanced. Ready to post.</span>';
                        submitBtn.disabled = false;
                    } else {
                        balanceAlert.className = 'text-xs font-semibold flex items-center gap-1.5 text-rose-700';
                        if (totalDebit === 0 && totalCredit === 0) {
                            balanceAlert.innerHTML = '<i data-lucide="alert-circle" class="w-4 h-4"></i><span>Enter opening debit and credit amounts.</span>';
                        } else {
                            balanceAlert.innerHTML = `<i data-lucide="alert-circle" class="w-4 h-4"></i><span>Unbalanced: Difference of Rs. ${diff.toFixed(2)}. Total Debits must equal Credits.</span>`;
                        }
                        submitBtn.disabled = true;
                    }

                    if (window.lucide) {
                        window.lucide.createIcons();
                    }
                }

                addRowBtn.addEventListener('click', function () {
                    const html = rowTemplate.innerHTML.replaceAll('INDEX', rowIndex);
                    container.insertAdjacentHTML('beforeend', html);
                    rowIndex++;
                    if (window.lucide) window.lucide.createIcons();
                    calculateTotals();
                });

                container.addEventListener('click', function (e) {
                    const removeBtn = e.target.closest('.remove-row-btn');
                    if (removeBtn) {
                        const row = removeBtn.closest('.line-row');
                        if (container.querySelectorAll('.line-row').length > 2) {
                            row.remove();
                            calculateTotals();
                        } else {
                            alert('At least two balancing lines are required.');
                        }
                    }
                });

                container.addEventListener('input', function (e) {
                    if (e.target.classList.contains('debit-input') || e.target.classList.contains('credit-input')) {
                        calculateTotals();
                    }
                });

                calculateTotals();
            });
        </script>
        @endpush
    @endif
</div>
@endsection
