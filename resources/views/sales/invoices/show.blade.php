@extends('layouts.app')

@section('title', "Invoice #{$invoice->invoice_number} â€” Khalid Traders ERP")

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800">
        <div>
            <div class="flex items-center gap-3">
                <span class="text-2xl font-bold font-mono text-slate-900 dark:text-white">{{ $invoice->invoice_number }}</span>
                @if ($invoice->isPosted())
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">
                        <i data-lucide="check-circle" class="w-3.5 h-3.5"></i>
                        {{ __('erp.invoices.posted') }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400 border border-amber-200/60 dark:border-amber-800/60">
                        <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                        {{ __('erp.invoices.draft') }}
                    </span>
                @endif
                <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $invoice->payment_status === 'paid' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300' : ($invoice->payment_status === 'partial' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-300') }}">
                    {{ __('erp.invoices.' . $invoice->payment_status) }}
                </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Issued on {{ $invoice->invoice_date->format('M d, Y') }} at {{ $invoice->location->name }} ({{ $invoice->location->code }})
            </p>
        </div>

        <div class="flex items-center gap-2.5 w-full sm:w-auto">
            <a href="{{ route('sales.invoices.print', $invoice) }}" target="_blank" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-200 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 rounded-xl transition-colors">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span>{{ __('erp.print') }}</span>
            </a>
            @if ($invoice->isPosted())
                <a href="{{ route('sales.returns.create', $invoice) }}" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold text-amber-800 dark:text-amber-200 bg-amber-100 hover:bg-amber-200 dark:bg-amber-950/70 rounded-xl transition-colors">
                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                    <span>Return Items</span>
                </a>
            @endif
            <a href="{{ route('sales.invoices.index') }}" class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors">
                Back to Invoices
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-xl text-emerald-800 dark:text-emerald-300 text-sm flex items-center gap-3">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0"></i>
            <div>{{ session('success') }}</div>
        </div>
    @endif

    @if ($errors->any())
        <div class="p-4 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded-xl text-rose-800 dark:text-rose-300 text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Invoice Details Card -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden p-6 space-y-6">
        <!-- Customer & Location Header Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pb-6 border-b border-slate-100 dark:border-slate-800">
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Customer / Khata Account</h3>
                <div class="text-lg font-bold text-slate-900 dark:text-white">{{ $invoice->customer->business_name }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 space-y-0.5 mt-1 font-mono">
                    <div>Party Code: {{ $invoice->customer->customer_code }}</div>
                    <div>Contact: {{ $invoice->customer->contact_person }} ({{ $invoice->customer->phone }})</div>
                    @if ($invoice->customer->address)
                        <div>Address: {{ $invoice->customer->address }}, {{ $invoice->customer->city }}</div>
                    @endif
                </div>
                <div class="mt-3">
                    <a href="{{ route('customers.ledger', $invoice->customer) }}" class="inline-flex items-center gap-1.5 text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-semibold">
                        <i data-lucide="book-open" class="w-3.5 h-3.5"></i>
                        <span>View Party Khata Statement</span>
                    </a>
                </div>
            </div>

            <div class="md:text-end space-y-1 text-xs text-slate-500 dark:text-slate-400">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Selling Entity</h3>
                <div class="text-base font-bold text-slate-900 dark:text-white">Khalid Traders â€” {{ $invoice->location->name }}</div>
                <div>Location Code: <span class="font-mono font-semibold">{{ $invoice->location->code }}</span></div>
                <div>Created By: {{ $invoice->creator->name }} on {{ $invoice->created_at->format('Y-m-d H:i') }}</div>
                @if ($invoice->isPosted())
                    <div class="text-emerald-600 dark:text-emerald-400 font-semibold">
                        Posted By: {{ $invoice->poster?->name ?? 'System' }} on {{ $invoice->posted_at?->format('Y-m-d H:i') }}
                    </div>
                @endif
            </div>
        </div>

        <!-- Line Items Table -->
        <div>
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">{{ __('erp.invoices.items') }}</h3>
            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                <table class="w-full text-start text-sm text-slate-600 dark:text-slate-300">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 text-xs uppercase font-bold tracking-wider">
                        <tr>
                            <th class="px-4 py-3 text-start">#</th>
                            <th class="px-4 py-3 text-start">{{ __('erp.invoices.product') }} / SKU</th>
                            <th class="px-4 py-3 text-start">{{ __('erp.invoices.variant') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('erp.invoices.quantity') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('erp.invoices.unit_price') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('erp.invoices.discount') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('erp.invoices.line_total') }} (PKR)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($invoice->items as $idx => $item)
                            <tr>
                                <td class="px-4 py-3 text-xs text-slate-400">{{ $idx + 1 }}</td>
                                <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">
                                    {{ $item->variant->product->name }}
                                    <span class="text-xs text-slate-400 font-mono block">{{ $item->variant->sku }}</span>
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    Size: <span class="font-bold font-mono">{{ $item->variant->size }}</span> |
                                    Color: <span class="font-bold">{{ $item->variant->color }}</span>
                                </td>
                                <td class="px-4 py-3 text-end text-xs font-mono">
                                    <span class="font-bold text-slate-900 dark:text-white">{{ (float)$item->quantity_cartons }} ctn</span>
                                    <span class="text-slate-400 block">({{ number_format($item->quantity_pairs, 0) }} pairs)</span>
                                </td>
                                <td class="px-4 py-3 text-end font-mono text-xs">
                                    {{ number_format($item->unit_price, 2) }}
                                </td>
                                <td class="px-4 py-3 text-end font-mono text-xs text-rose-500">
                                    {{ number_format($item->line_discount, 2) }}
                                </td>
                                <td class="px-4 py-3 text-end font-mono font-bold text-slate-900 dark:text-white">
                                    {{ number_format($item->line_total, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Totals Breakdown -->
        <div class="flex flex-col sm:flex-row justify-between items-start gap-4 pt-4 border-t border-slate-100 dark:border-slate-800">
            <div class="text-xs text-slate-500 dark:text-slate-400 max-w-sm">
                @if ($invoice->notes)
                    <span class="font-bold uppercase text-slate-400 block mb-1">Notes:</span>
                    <p class="italic bg-slate-50 dark:bg-slate-800/40 p-2.5 rounded-lg border border-slate-100 dark:border-slate-800">{{ $invoice->notes }}</p>
                @endif
            </div>

            <div class="w-full sm:w-72 space-y-2 text-sm">
                <div class="flex justify-between text-slate-600 dark:text-slate-400">
                    <span>{{ __('erp.invoices.subtotal') }}:</span>
                    <span class="font-mono font-bold text-slate-900 dark:text-white">{{ number_format($invoice->subtotal_amount, 2) }} PKR</span>
                </div>
                @if ((float)$invoice->discount_amount > 0)
                    <div class="flex justify-between text-rose-600 dark:text-rose-400">
                        <span>{{ __('erp.invoices.discount') }}:</span>
                        <span class="font-mono font-bold">-{{ number_format($invoice->discount_amount, 2) }} PKR</span>
                    </div>
                @endif
                @if ((float)$invoice->tax_amount > 0)
                    <div class="flex justify-between text-slate-600 dark:text-slate-400">
                        <span>{{ __('erp.invoices.tax') }}:</span>
                        <span class="font-mono font-bold">+{{ number_format($invoice->tax_amount, 2) }} PKR</span>
                    </div>
                @endif
                <div class="border-t border-slate-200 dark:border-slate-700 pt-2 flex justify-between text-base font-bold text-slate-900 dark:text-white">
                    <span>{{ __('erp.invoices.total') }}:</span>
                    <span class="font-mono text-indigo-600 dark:text-indigo-400">{{ number_format($invoice->total_amount, 2) }} PKR</span>
                </div>
                <div class="flex justify-between text-emerald-600 dark:text-emerald-400 font-semibold">
                    <span>{{ __('erp.invoices.paid_amount') }}:</span>
                    <span class="font-mono">{{ number_format($invoice->paid_amount, 2) }} PKR</span>
                </div>
                <div class="flex justify-between text-base font-bold {{ (float)$invoice->balance_amount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400' }}">
                    <span>{{ __('erp.invoices.balance_amount') }}:</span>
                    <span class="font-mono">{{ number_format($invoice->balance_amount, 2) }} PKR</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Draft Posting Action Bar (If Draft) -->
    @if ($invoice->isDraft())
        <div class="bg-amber-50 dark:bg-amber-950/40 p-6 rounded-2xl border border-amber-200 dark:border-amber-800 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
            <div>
                <h4 class="font-bold text-amber-900 dark:text-amber-300 flex items-center gap-2">
                    <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                    <span>This invoice is currently in DRAFT status.</span>
                </h4>
                <p class="text-xs text-amber-800/80 dark:text-amber-400/80 mt-1">
                    Inventory has NOT been deducted, and customer Khata has NOT been debited yet.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <form method="POST" action="{{ route('sales.invoices.cancel', $invoice) }}" onsubmit="return confirm('Cancel this draft sales invoice?')">
                    @csrf
                    <button type="submit" class="px-4 py-2.5 border border-rose-300 hover:bg-rose-50 text-rose-600 font-bold text-sm rounded-xl transition-colors">
                        Cancel Draft
                    </button>
                </form>
                <form method="POST" action="{{ route('sales.invoices.post', $invoice) }}" onsubmit="return confirm('{{ __('erp.invoices.post_confirmation') }}')">
                    @csrf
                    <button type="submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm rounded-xl shadow-sm shadow-emerald-600/30 transition-colors flex items-center gap-2">
                        <i data-lucide="check-check" class="w-4 h-4"></i>
                        <span>{{ __('erp.invoices.post_now') }}</span>
                    </button>
                </form>
            </div>
        </div>
    @endif

    <!-- Payment Collection & History Section (If Posted) -->
    @if ($invoice->isPosted())
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Collect Payment Form -->
            @if ((float)$invoice->balance_amount > 0)
                <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-2">
                        <i data-lucide="hand-coins" class="w-4 h-4 text-emerald-500"></i>
                        <span>{{ __('erp.invoices.collect_payment') }}</span>
                    </h3>

                    <form method="POST" action="{{ route('sales.invoices.payments.store', $invoice) }}" class="space-y-3.5">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">
                                Amount (PKR) <span class="text-rose-500">*</span>
                            </label>
                            <input type="number" step="0.01" min="0.01" max="{{ $invoice->balance_amount }}" name="amount" value="{{ $invoice->balance_amount }}" required
                                   class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white font-mono font-bold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <span class="text-[11px] text-slate-400 mt-0.5 block">Max: {{ number_format($invoice->balance_amount, 2) }} PKR</span>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">
                                Payment Date <span class="text-rose-500">*</span>
                            </label>
                            <input type="date" name="payment_date" value="{{ now()->toDateString() }}" required
                                   class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">
                                Payment Method <span class="text-rose-500">*</span>
                            </label>
                            <select name="payment_method" required class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white">
                                <option value="cash">{{ __('erp.cash') }}</option>
                                <option value="bank_transfer">{{ __('erp.bank') }}</option>
                                <option value="cheque">{{ __('erp.cheque') }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">
                                Cheque / Transaction Ref #
                            </label>
                            <input type="text" name="reference_number" placeholder="Optional reference"
                                   class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white">
                        </div>

                        <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm rounded-xl shadow-sm transition-colors flex items-center justify-center gap-2">
                            <i data-lucide="check" class="w-4 h-4"></i>
                            <span>Save Payment Receipt</span>
                        </button>
                    </form>
                </div>
            @else
                <div class="bg-emerald-50 dark:bg-emerald-950/30 p-6 rounded-2xl border border-emerald-200 dark:border-emerald-800 shadow-sm text-center flex flex-col items-center justify-center">
                    <div class="p-3 bg-emerald-100 dark:bg-emerald-900/60 text-emerald-600 dark:text-emerald-400 rounded-full mb-3">
                        <i data-lucide="check-circle-2" class="w-8 h-8"></i>
                    </div>
                    <h4 class="font-bold text-emerald-900 dark:text-emerald-300">Invoice Fully Settled</h4>
                    <p class="text-xs text-emerald-700 dark:text-emerald-400 mt-1">Zero balance remaining on this invoice.</p>
                </div>
            @endif

            <!-- Payments History List -->
            <div class="{{ (float)$invoice->balance_amount > 0 ? 'md:col-span-2' : 'md:col-span-2' }} bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-2">
                    <i data-lucide="history" class="w-4 h-4 text-indigo-500"></i>
                    <span>{{ __('erp.invoices.payment_history') }}</span>
                </h3>

                <div class="overflow-x-auto">
                    <table class="w-full text-start text-xs text-slate-600 dark:text-slate-300">
                        <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 font-bold uppercase">
                            <tr>
                                <th class="px-3 py-2 text-start">Receipt #</th>
                                <th class="px-3 py-2 text-start">Date</th>
                                <th class="px-3 py-2 text-start">Mode</th>
                                <th class="px-3 py-2 text-start">Reference</th>
                                <th class="px-3 py-2 text-end">Amount (PKR)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($invoice->payments as $pay)
                                <tr>
                                    <td class="px-3 py-2.5 font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                        <a href="{{ route('payments.show', $pay) }}" class="hover:underline">
                                            {{ $pay->payment_number }}
                                        </a>
                                    </td>
                                    <td class="px-3 py-2.5">{{ $pay->payment_date->format('Y-m-d') }}</td>
                                    <td class="px-3 py-2.5">
                                        <span class="px-2 py-0.5 bg-slate-100 dark:bg-slate-800 rounded font-semibold capitalize">
                                            {{ str_replace('_', ' ', $pay->payment_method) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5 text-slate-400 font-mono">{{ $pay->reference_number ?? 'â€”' }}</td>
                                    <td class="px-3 py-2.5 text-end font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                        {{ number_format($pay->amount, 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-slate-400 italic">
                                        No payments recorded yet for this invoice.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
