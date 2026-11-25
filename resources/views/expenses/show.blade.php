@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <!-- Breadcrumb & Top Actions (hidden on print) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 print:hidden">
        <a href="{{ route('expenses.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-900 transition">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to Expenses</span>
        </a>

        <div class="flex items-center gap-2">
            <button onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-bold transition shadow-xs">
                <i data-lucide="printer" class="w-4 h-4 text-slate-500"></i>
                <span>{{ __('erp.print') }} Voucher</span>
            </button>

            @if($expense->status === 'draft')
                <!-- Post Voucher Button -->
                <button type="button" onclick="document.getElementById('post-modal').classList.remove('hidden')"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold transition shadow-sm">
                    <i data-lucide="check-circle" class="w-4 h-4"></i>
                    <span>Post Voucher</span>
                </button>
            @elseif($expense->status === 'posted')
                <!-- Reverse Voucher Button -->
                <button type="button" onclick="document.getElementById('reverse-modal').classList.remove('hidden')"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-sm font-bold transition shadow-sm">
                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                    <span>Reverse Voucher</span>
                </button>
            @endif
        </div>
    </div>

    <!-- Alert Success / Error (hidden on print) -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium flex items-center gap-2 print:hidden">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-medium flex items-center gap-2 print:hidden">
            <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-600"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Official Printable Expense Voucher Card -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden print:border-none print:shadow-none">
        <!-- Header -->
        <div class="p-8 border-b border-slate-100 bg-slate-50/50 print:bg-white print:border-b-2 print:border-slate-800">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-2xl font-black tracking-tight text-slate-900 uppercase">Khalid Traders</h2>
                    <p class="text-xs text-slate-500 font-medium">Wholesale Footwear & Factory ERP • Expense Debit Voucher</p>
                    <p class="text-xs text-slate-500 mt-1 font-semibold">Location: {{ $expense->location ? $expense->location->name : 'Central' }}</p>
                </div>
                <div class="text-right">
                    <span class="text-xs font-mono font-bold uppercase tracking-wider text-slate-400 block">Voucher Number</span>
                    <span class="text-xl font-black font-mono text-slate-900 block">{{ $expense->expense_number }}</span>
                    <div class="mt-2">
                        @if($expense->status === 'posted')
                            <span class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                POSTED (DEDUCTED)
                            </span>
                        @elseif($expense->status === 'draft')
                            <span class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                DRAFT (UNPOSTED)
                            </span>
                        @else
                            <span class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                REVERSED / VOID
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Voucher Body -->
        <div class="p-8 space-y-6 text-sm text-slate-700">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pb-6 border-b border-slate-100">
                <div>
                    <span class="text-xs font-bold uppercase text-slate-400 block mb-1">Voucher Date</span>
                    <span class="font-bold text-slate-900">{{ $expense->expense_date->format('d M Y') }}</span>
                </div>
                <div>
                    <span class="text-xs font-bold uppercase text-slate-400 block mb-1">Category</span>
                    <span class="font-bold text-slate-900">{{ $expense->category ? $expense->category->name : 'General' }}</span>
                </div>
                <div>
                    <span class="text-xs font-bold uppercase text-slate-400 block mb-1">Payment Method</span>
                    <span class="font-semibold text-slate-900 uppercase">{{ str_replace('_', ' ', $expense->payment_method) }}</span>
                </div>
                <div>
                    <span class="text-xs font-bold uppercase text-slate-400 block mb-1">Paid From Account</span>
                    <span class="font-bold text-slate-900 font-mono">
                        {{ $expense->financialAccount ? $expense->financialAccount->name : 'None (Unassigned)' }}
                    </span>
                </div>
            </div>

            <!-- Payee and Amount Highlight -->
            <div class="p-6 bg-slate-50 rounded-xl border border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <span class="text-xs font-bold uppercase text-slate-400 block mb-1">Paid To (Payee)</span>
                    <span class="text-lg font-bold text-slate-900">{{ $expense->payee_name ?: 'Bearer / Cash Payee' }}</span>
                    @if($expense->description)
                        <p class="text-xs text-slate-500 mt-1">{{ $expense->description }}</p>
                    @endif
                </div>

                <div class="sm:text-right">
                    <span class="text-xs font-bold uppercase text-slate-400 block mb-1">Total Voucher Amount</span>
                    <span class="text-3xl font-black font-mono text-slate-900">
                        PKR {{ number_format((float) $expense->amount, 2) }}
                    </span>
                </div>
            </div>

            @if($expense->reference_number || $expense->notes)
                <div class="space-y-2 text-xs">
                    @if($expense->reference_number)
                        <div>
                            <span class="font-bold uppercase text-slate-400">Supporting Instrument / Bill #:</span>
                            <span class="font-mono font-bold text-slate-800 ml-1">{{ $expense->reference_number }}</span>
                        </div>
                    @endif
                    @if($expense->notes)
                        <div>
                            <span class="font-bold uppercase text-slate-400 block mb-1">Notes:</span>
                            <p class="p-3 bg-slate-50 rounded-lg text-slate-600 border border-slate-200/80">{{ $expense->notes }}</p>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Reversal Info if Reversed -->
            @if($expense->status === 'reversed')
                <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-xs space-y-1">
                    <div class="font-bold text-rose-800 uppercase flex items-center gap-1.5">
                        <i data-lucide="info" class="w-4 h-4"></i>
                        <span>Voucher Reversed & Balance Restored</span>
                    </div>
                    <p class="text-rose-700">Reversed at: {{ $expense->reversed_at ? $expense->reversed_at->format('d M Y H:i') : '—' }} by {{ $expense->reverser ? $expense->reverser->name : 'User' }}</p>
                    <p class="text-rose-800 font-semibold">Reason: {{ $expense->reversal_reason }}</p>
                </div>
            @endif

            <!-- Signatures Block -->
            <div class="pt-12 grid grid-cols-3 gap-6 text-center text-xs font-medium text-slate-500">
                <div class="space-y-2">
                    <div class="border-t border-slate-400 pt-2 font-bold text-slate-700">
                        {{ $expense->creator ? $expense->creator->name : 'Cashier' }}
                    </div>
                    <div>Prepared By</div>
                </div>

                <div class="space-y-2">
                    <div class="border-t border-slate-400 pt-2 font-bold text-slate-700">
                        {{ $expense->poster ? $expense->poster->name : 'Manager' }}
                    </div>
                    <div>Approved / Posted By</div>
                </div>

                <div class="space-y-2">
                    <div class="border-t border-slate-400 pt-2 font-bold text-slate-700">
                        {{ $expense->payee_name ?: 'Recipient' }}
                    </div>
                    <div>Receiver's Signature</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Post Modal (hidden) -->
    <div id="post-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-50 hidden">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
            <h3 class="text-lg font-bold text-slate-900">Post Expense Voucher</h3>
            <p class="text-xs text-slate-500">Posting will deduct PKR {{ number_format((float) $expense->amount, 2) }} from the selected account.</p>

            <form action="{{ route('expenses.post', $expense) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="post_account_id" class="block text-xs font-bold text-slate-700 uppercase mb-1">Select Payment Account *</label>
                    <select id="post_account_id" name="financial_account_id" required class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:bg-white focus:outline-none">
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}" {{ $expense->financial_account_id == $acc->id ? 'selected' : '' }}>
                                {{ $acc->type === 'cash' ? '💵' : '🏦' }} {{ $acc->name }} (PKR {{ number_format((float) $acc->current_balance, 2) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('post-modal').classList.add('hidden')"
                            class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 text-xs font-bold">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">
                        Confirm & Post
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reverse Modal (hidden) -->
    <div id="reverse-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-50 hidden">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
            <h3 class="text-lg font-bold text-slate-900 text-rose-600">Reverse Expense Voucher</h3>
            <p class="text-xs text-slate-500">Reversing will post a counter-entry refunding PKR {{ number_format((float) $expense->amount, 2) }} back to the account. This action cannot be repeated.</p>

            <form action="{{ route('expenses.reverse', $expense) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="reversal_reason" class="block text-xs font-bold text-slate-700 uppercase mb-1">Reason for Reversal *</label>
                    <textarea id="reversal_reason" name="reversal_reason" required rows="3" placeholder="Explain why this expense voucher is being reversed..."
                              class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-rose-500 focus:bg-white focus:outline-none"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('reverse-modal').classList.add('hidden')"
                            class="px-4 py-2 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 text-xs font-bold">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold">
                        Confirm Reversal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
