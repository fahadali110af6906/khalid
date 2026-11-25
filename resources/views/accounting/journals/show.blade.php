@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('accounting.journals.index') }}" class="p-2 rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-slate-800 transition">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <div class="flex items-center gap-2.5">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 font-mono">{{ $journal->entry_number }}</h1>
                    @if($journal->status === 'posted')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                            Posted
                        </span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800">
                            Reversed
                        </span>
                    @endif
                    @if($journal->location)
                        <span class="text-xs px-2 py-0.5 rounded bg-slate-100 border border-slate-200 text-slate-700 font-mono">
                            {{ $journal->location->code }} — {{ $journal->location->name }}
                        </span>
                    @else
                        <span class="text-xs px-2 py-0.5 rounded bg-slate-100 border border-slate-200 text-slate-500 font-mono">
                            Global KT HQ
                        </span>
                    @endif
                </div>
                <p class="text-sm text-slate-500 mt-0.5">
                    Date: {{ $journal->entry_date->format('M d, Y') }} | Source: <span class="capitalize font-medium text-slate-700">{{ str_replace('_', ' ', $journal->source_type ?? 'manual') }}</span>
                    @if($journal->reference_number)
                        | Ref: <span class="font-mono text-slate-700">{{ $journal->reference_number }}</span>
                    @endif
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            @if($journal->status === 'posted' && !$journal->reversed_by_entry_id)
                <button type="button" onclick="openReverseModal()" class="px-4 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 text-sm font-semibold border border-rose-200 transition flex items-center gap-1.5">
                    <i data-lucide="rotate-ccw" class="w-4 h-4 text-rose-600"></i>
                    <span>{{ __('erp.general_ledger.reverse_journal') }}</span>
                </button>
            @endif
        </div>
    </div>

    <!-- Alert Success -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium flex items-center gap-2">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">
            <div class="font-bold mb-1 flex items-center gap-1.5">
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600"></i>
                Error:
            </div>
            <ul class="list-disc list-inside space-y-0.5 text-xs">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Reversal Audit Notice -->
    @if($journal->reversedBy)
        <div class="bg-rose-50 border border-rose-200 p-4 rounded-2xl flex items-center justify-between text-sm text-rose-900">
            <div class="flex items-center gap-2.5">
                <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-600"></i>
                <div>
                    <span class="font-bold">This journal voucher was reversed.</span>
                    <span class="text-xs text-rose-700 block">Reversal Voucher: #{{ $journal->reversedBy->entry_number }} on {{ $journal->reversedBy->entry_date->format('M d, Y') }}</span>
                </div>
            </div>
            <a href="{{ route('accounting.journals.show', $journal->reversedBy) }}" class="text-xs font-bold text-rose-700 underline">
                View Reversal Voucher &rarr;
            </a>
        </div>
    @endif

    @if($journal->reversalOf)
        <div class="bg-blue-50 border border-blue-200 p-4 rounded-2xl flex items-center justify-between text-sm text-blue-900">
            <div class="flex items-center gap-2.5">
                <i data-lucide="info" class="w-5 h-5 text-blue-600"></i>
                <div>
                    <span class="font-bold">This is an automatic reversal entry.</span>
                    <span class="text-xs text-blue-700 block">Original Entry: #{{ $journal->reversalOf->entry_number }}</span>
                </div>
            </div>
            <a href="{{ route('accounting.journals.show', $journal->reversalOf) }}" class="text-xs font-bold text-blue-700 underline">
                View Original Voucher &rarr;
            </a>
        </div>
    @endif

    <!-- Voucher Details Card -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                <i data-lucide="file-check" class="w-4 h-4 text-indigo-600"></i>
                Voucher Particulars & Narration
            </h2>
            <span class="text-xs font-mono text-slate-400">Created by: {{ $journal->creator->name ?? 'System' }}</span>
        </div>
        <p class="text-sm text-slate-800 whitespace-pre-line font-medium">{{ $journal->description }}</p>
    </div>

    <!-- Double-Entry Lines Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="p-4 border-b border-slate-200/80 flex items-center justify-between">
            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                <i data-lucide="scale" class="w-4 h-4 text-indigo-600"></i>
                Journal Lines & Ledger Accounts
            </h2>
            <span class="text-xs font-mono text-slate-500">{{ $journal->lines->count() }} line items</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50/75 border-b border-slate-200/80 text-xs uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-bold">#</th>
                        <th class="px-4 py-3 font-bold">Account</th>
                        <th class="px-4 py-3 font-bold">Line Description</th>
                        <th class="px-4 py-3 font-bold text-right" style="width: 18%;">Debit (PKR)</th>
                        <th class="px-4 py-3 font-bold text-right" style="width: 18%;">Credit (PKR)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($journal->lines as $idx => $line)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-4 py-3 text-xs text-slate-400 font-mono">{{ $idx + 1 }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('accounting.ledger', ['account_id' => $line->account->id]) }}" class="font-bold text-indigo-700 hover:underline">
                                    {{ $line->account->code }} — {{ $line->account->name }}
                                </a>
                                <span class="ms-1.5 text-[10px] px-1.5 py-0.2 rounded bg-slate-100 text-slate-600 uppercase font-mono">
                                    {{ $line->account->type }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600">
                                {{ $line->description ?: '—' }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">
                                @if((float)$line->debit > 0)
                                    Rs. {{ number_format($line->debit, 2) }}
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">
                                @if((float)$line->credit > 0)
                                    Rs. {{ number_format($line->credit, 2) }}
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-slate-50/75 border-t border-slate-200/80 font-bold">
                    <tr>
                        <td colspan="3" class="px-4 py-3 text-right uppercase text-xs text-slate-700">
                            Total:
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-base text-indigo-700">
                            Rs. {{ number_format($journal->total_debit, 2) }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-base text-indigo-700">
                            Rs. {{ number_format($journal->total_credit, 2) }}
                        </td>
                    </tr>
                    <tr>
                        <td colspan="3" class="px-4 py-2 text-right uppercase text-xs text-slate-500">
                            Status:
                        </td>
                        <td colspan="2" class="px-4 py-2 text-center text-xs font-bold text-emerald-700 bg-emerald-50">
                            ✓ Balanced (Debits = Credits)
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Reverse Journal Voucher -->
<div id="reverseModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-lg font-bold text-rose-700 flex items-center gap-2">
                <i data-lucide="rotate-ccw" class="w-5 h-5 text-rose-600"></i>
                Reverse Journal Voucher
            </h3>
            <button type="button" onclick="closeReverseModal()" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('accounting.journals.reverse', $journal) }}" class="space-y-4">
            @csrf
            <p class="text-xs text-slate-600">
                Are you sure you want to reverse voucher <strong class="font-mono text-slate-900">{{ $journal->entry_number }}</strong>? This will create a linked inverse journal entry that cancels all debits and credits.
            </p>

            <div>
                <label for="reason" class="block text-xs font-bold text-slate-700 uppercase mb-1">Reason for Reversal *</label>
                <textarea id="reason" name="reason" required rows="2" placeholder="Specify error or adjustment reason..."
                          class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-rose-500"></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeReverseModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl">
                    Back
                </button>
                <button type="submit" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white text-sm font-bold rounded-xl shadow-sm">
                    Confirm Reversal
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function openReverseModal() { document.getElementById('reverseModal').classList.remove('hidden'); }
    function closeReverseModal() { document.getElementById('reverseModal').classList.add('hidden'); }
</script>
@endpush
@endsection
