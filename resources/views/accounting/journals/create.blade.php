@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('accounting.journals.index') }}" class="p-2 rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-slate-800 transition">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.general_ledger.new_journal') }}</h1>
                <p class="text-sm text-slate-500 mt-0.5">Record a manual double-entry financial voucher. Total Debits must equal Total Credits.</p>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">
            <div class="font-bold mb-1 flex items-center gap-1.5">
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600"></i>
                Please correct the following errors:
            </div>
            <ul class="list-disc list-inside space-y-0.5 text-xs font-semibold">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('accounting.journals.store') }}" id="journalForm" class="space-y-6">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

        <!-- Voucher Header -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-5">
            <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3 flex items-center gap-2">
                <i data-lucide="file-text" class="w-4 h-4 text-indigo-600"></i>
                Voucher Header Details
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="entry_date" class="block text-xs font-bold text-slate-700 uppercase mb-1">{{ __('erp.general_ledger.entry_date') }} *</label>
                    <input type="date" id="entry_date" name="entry_date" value="{{ old('entry_date', now()->toDateString()) }}" required
                           class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white font-mono">
                </div>

                <div>
                    <label for="location_id" class="block text-xs font-bold text-slate-700 uppercase mb-1">Branch / Location Scope</label>
                    <select id="location_id" name="location_id"
                            class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white">
                        <option value="">Global / Head Office</option>
                        @foreach($authorizedLocations as $loc)
                            <option value="{{ $loc->id }}" {{ old('location_id') == $loc->id ? 'selected' : '' }}>
                                {{ $loc->name }} ({{ $loc->code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="reference_number" class="block text-xs font-bold text-slate-700 uppercase mb-1">External Reference #</label>
                    <input type="text" id="reference_number" name="reference_number" value="{{ old('reference_number') }}"
                           placeholder="Cheque #, Bank deposit ref, bill..."
                           class="w-full py-2.5 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white font-mono">
                </div>

                <div class="md:col-span-3">
                    <label for="description" class="block text-xs font-bold text-slate-700 uppercase mb-1">Narration / Description *</label>
                    <textarea id="description" name="description" rows="2" required placeholder="Detailed reason for this manual journal entry..."
                              class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500">{{ old('description') }}</textarea>
                </div>
            </div>
        </div>

        <!-- Voucher Lines (Debits & Credits) -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="scale" class="w-4 h-4 text-indigo-600"></i>
                    Double-Entry Journal Lines
                </h2>
                <button type="button" id="addRowBtn" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold transition">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                    Add Line
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm" id="linesTable">
                    <thead class="bg-slate-50/75 border-b border-slate-200/80 text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-3 py-2.5 font-bold" style="width: 35%;">Account *</th>
                            <th class="px-3 py-2.5 font-bold" style="width: 25%;">Line Description</th>
                            <th class="px-3 py-2.5 font-bold text-right" style="width: 17%;">Debit (PKR)</th>
                            <th class="px-3 py-2.5 font-bold text-right" style="width: 17%;">Credit (PKR)</th>
                            <th class="px-3 py-2.5 text-center" style="width: 6%;"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100" id="linesTbody">
                        <!-- Initial Row 1 (Debit side) -->
                        <tr class="line-row">
                            <td class="px-3 py-2">
                                <select name="lines[0][chart_of_account_id]" required
                                        class="account-select w-full py-2 px-2.5 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white">
                                    <option value="">Select Account</option>
                                    @foreach($postableAccounts as $acc)
                                        <option value="{{ $acc->id }}" data-type="{{ $acc->type }}" data-balance="{{ $acc->normal_balance }}">
                                            {{ $acc->code }} — {{ $acc->name }} ({{ ucfirst($acc->type) }})
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-3 py-2">
                                <input type="text" name="lines[0][description]" placeholder="Line particulars..."
                                       class="w-full py-2 px-2.5 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" step="0.01" min="0" name="lines[0][debit]" value="0.00"
                                       class="debit-input w-full py-2 px-2.5 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white text-right font-mono font-bold">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" step="0.01" min="0" name="lines[0][credit]" value="0.00"
                                       class="credit-input w-full py-2 px-2.5 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white text-right font-mono font-bold">
                            </td>
                            <td class="px-3 py-2 text-center">
                                <button type="button" class="remove-line-btn p-1 text-slate-400 hover:text-rose-600 rounded transition">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </td>
                        </tr>

                        <!-- Initial Row 2 (Credit side) -->
                        <tr class="line-row">
                            <td class="px-3 py-2">
                                <select name="lines[1][chart_of_account_id]" required
                                        class="account-select w-full py-2 px-2.5 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white">
                                    <option value="">Select Account</option>
                                    @foreach($postableAccounts as $acc)
                                        <option value="{{ $acc->id }}" data-type="{{ $acc->type }}" data-balance="{{ $acc->normal_balance }}">
                                            {{ $acc->code }} — {{ $acc->name }} ({{ ucfirst($acc->type) }})
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-3 py-2">
                                <input type="text" name="lines[1][description]" placeholder="Line particulars..."
                                       class="w-full py-2 px-2.5 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" step="0.01" min="0" name="lines[1][debit]" value="0.00"
                                       class="debit-input w-full py-2 px-2.5 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white text-right font-mono font-bold">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" step="0.01" min="0" name="lines[1][credit]" value="0.00"
                                       class="credit-input w-full py-2 px-2.5 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white text-right font-mono font-bold">
                            </td>
                            <td class="px-3 py-2 text-center">
                                <button type="button" class="remove-line-btn p-1 text-slate-400 hover:text-rose-600 rounded transition">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-slate-50/75 border-t border-slate-200/80 font-bold">
                        <tr>
                            <td colspan="2" class="px-3 py-3 text-right uppercase text-xs text-slate-700">
                                Voucher Totals:
                            </td>
                            <td class="px-3 py-3 text-right font-mono text-base text-slate-900" id="totalDebitEl">
                                Rs. 0.00
                            </td>
                            <td class="px-3 py-3 text-right font-mono text-base text-slate-900" id="totalCreditEl">
                                Rs. 0.00
                            </td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="2" class="px-3 py-2 text-right uppercase text-xs text-slate-500">
                                Balance Check:
                            </td>
                            <td colspan="2" class="px-3 py-2 text-center font-mono text-xs font-bold" id="balanceStatusEl">
                                <span class="text-amber-600">Difference: Rs. 0.00</span>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('accounting.journals.index') }}" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl transition">
                {{ __('erp.cancel') }}
            </a>
            <button type="submit" id="submitBtn" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl shadow-sm transition flex items-center gap-2">
                <i data-lucide="check" class="w-4 h-4"></i>
                Post Journal Voucher
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        let lineIdx = 2;
        const tbody = document.getElementById('linesTbody');
        const addBtn = document.getElementById('addRowBtn');
        const totalDebitEl = document.getElementById('totalDebitEl');
        const totalCreditEl = document.getElementById('totalCreditEl');
        const balanceStatusEl = document.getElementById('balanceStatusEl');
        const submitBtn = document.getElementById('submitBtn');

        function recalculate() {
            let totalD = 0;
            let totalC = 0;

            document.querySelectorAll('.line-row').forEach(function (row) {
                const d = parseFloat(row.querySelector('.debit-input').value || 0);
                const c = parseFloat(row.querySelector('.credit-input').value || 0);
                totalD += d;
                totalC += c;
            });

            totalDebitEl.textContent = 'Rs. ' + totalD.toFixed(2);
            totalCreditEl.textContent = 'Rs. ' + totalC.toFixed(2);

            const diff = Math.abs(totalD - totalC);
            if (diff < 0.001 && totalD > 0) {
                balanceStatusEl.innerHTML = '<span class="text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-1 rounded-full">✓ Balanced (Rs. ' + totalD.toFixed(2) + ')</span>';
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                balanceStatusEl.innerHTML = '<span class="text-rose-700 bg-rose-50 border border-rose-200 px-3 py-1 rounded-full">⚠ Unbalanced: Difference Rs. ' + diff.toFixed(2) + '</span>';
            }
        }

        tbody.addEventListener('input', recalculate);

        addBtn.addEventListener('click', function () {
            const firstRow = document.querySelector('.line-row');
            const clone = firstRow.cloneNode(true);

            clone.querySelectorAll('input, select').forEach(function (input) {
                const name = input.getAttribute('name');
                if (name) {
                    input.setAttribute('name', name.replace(/\[\d+\]/, '[' + lineIdx + ']'));
                }
                if (input.classList.contains('debit-input') || input.classList.contains('credit-input')) {
                    input.value = '0.00';
                } else if (input.tagName === 'INPUT') {
                    input.value = '';
                } else if (input.tagName === 'SELECT') {
                    input.selectedIndex = 0;
                }
            });

            tbody.appendChild(clone);
            lineIdx++;
            lucide.createIcons();
            recalculate();
        });

        tbody.addEventListener('click', function (e) {
            const removeBtn = e.target.closest('.remove-line-btn');
            if (removeBtn) {
                const rows = document.querySelectorAll('.line-row');
                if (rows.length > 2) {
                    removeBtn.closest('.line-row').remove();
                    recalculate();
                } else {
                    alert('A journal entry must contain at least 2 lines.');
                }
            }
        });

        recalculate();
    });
</script>
@endpush
@endsection
