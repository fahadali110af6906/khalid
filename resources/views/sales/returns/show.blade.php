@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between print:hidden">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('sales.returns.index') }}" class="text-xs font-semibold text-slate-500 hover:text-indigo-600 transition">
                    {{ __('erp.sales_returns.title') }}
                </a>
                <span class="text-slate-400 text-xs">/</span>
                <span class="text-xs font-bold text-slate-800">{{ $salesReturn->return_number }}</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 mt-1 flex items-center gap-3">
                <span>Sales Return Note (SRN)</span>
                @if($salesReturn->status === 'draft')
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200">
                        Draft (Unposted)
                    </span>
                @elseif($salesReturn->status === 'posted')
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                        Posted & Restocked
                    </span>
                @else
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700">
                        {{ ucfirst($salesReturn->status) }}
                    </span>
                @endif
            </h1>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="window.print()" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition flex items-center gap-1.5 shadow-xs">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span>Print Return Voucher</span>
            </button>
            <a href="{{ route('sales.returns.index') }}" class="px-3.5 py-2 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold transition">
                Back to Returns
            </a>
        </div>
    </div>

    <!-- Alert Success / Error -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium flex items-center gap-2 print:hidden">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if($errors->has('post'))
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-medium flex items-center gap-2 print:hidden">
            <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600"></i>
            <span>{{ $errors->first('post') }}</span>
        </div>
    @endif
    @if($errors->has('refund'))
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-medium flex items-center gap-2 print:hidden">
            <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600"></i>
            <span>{{ $errors->first('refund') }}</span>
        </div>
    @endif

    <!-- Draft Action Banner (Posting Confirmation) -->
    @if($salesReturn->status === 'draft')
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 print:hidden">
            <div class="space-y-1">
                <div class="flex items-center gap-2 text-amber-900 font-bold text-sm">
                    <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600"></i>
                    <span>Ready to Post Return to Inventory & Customer Khata</span>
                </div>
                <p class="text-xs text-amber-800">
                    Posting will restock <strong class="font-mono">{{ number_format($salesReturn->total_returned_pairs, 0) }} pairs</strong> into <strong class="font-semibold">{{ $salesReturn->location->name }}</strong> inventory and credit <strong class="font-mono">PKR {{ number_format($salesReturn->total_return_amount, 2) }}</strong> to {{ $salesReturn->customer->business_name }}'s khata.
                </p>
            </div>
            <form method="POST" action="{{ route('sales.returns.post', $salesReturn) }}"
                  onsubmit="return confirm('Confirm posting sales return #{{ $salesReturn->return_number }}?\n\n- Stock will be restocked into {{ $salesReturn->location->name }}\n- Customer Khata will be credited PKR {{ number_format($salesReturn->total_return_amount, 2) }}\n- A Customer Credit Note will be generated.')">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition flex items-center gap-2 whitespace-nowrap">
                    <i data-lucide="check-circle" class="w-4 h-4"></i>
                    <span>Post Return Now</span>
                </button>
            </form>
        </div>
    @endif

    <!-- Refund Action Card (When posted and return amount remaining to refund) -->
    @if($salesReturn->status === 'posted')
        @php
            $remainingRefundable = bcsub((string) $salesReturn->total_return_amount, (string) $salesReturn->refunded_amount, 2);
        @endphp
        @if(bccomp($remainingRefundable, '0.00', 2) > 0)
            <div class="bg-indigo-50 border border-indigo-200 rounded-2xl p-5 shadow-xs print:hidden">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2 text-indigo-950 font-bold text-sm">
                        <i data-lucide="hand-coins" class="w-4 h-4 text-indigo-600"></i>
                        <span>Optional Cash / Bank Refund to Customer</span>
                    </div>
                    <span class="text-xs font-mono font-bold text-indigo-800 bg-white px-2.5 py-1 rounded-lg border border-indigo-200">
                        Remaining Refundable: ₨ {{ number_format((float)$remainingRefundable, 2) }}
                    </span>
                </div>
                <p class="text-xs text-indigo-800 mb-4">
                    If this customer is paid back in cash or bank transfer instead of keeping the credit in their Khata, record the cash payout here. It will debit customer khata and maintain accurate cash balances.
                </p>
                <form method="POST" action="{{ route('sales.returns.refund', $salesReturn) }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Refund Amount (PKR)</label>
                        <input type="number" step="0.01" min="0.01" max="{{ $remainingRefundable }}" name="amount" value="{{ $remainingRefundable }}" required
                               class="w-full px-3 py-2 text-sm rounded-lg border border-slate-300 bg-white text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Refund Date</label>
                        <input type="date" name="refund_date" value="{{ now()->toDateString() }}" required
                               class="w-full px-3 py-2 text-sm rounded-lg border border-slate-300 bg-white text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Payment Method</label>
                        <select name="payment_method" class="w-full px-3 py-2 text-sm rounded-lg border border-slate-300 bg-white text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="cash" selected>Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cheque">Cheque</option>
                            <option value="online">Online Payout</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="w-full py-2 px-4 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition flex items-center justify-center gap-1.5 shadow-sm">
                            <i data-lucide="banknote" class="w-4 h-4"></i>
                            <span>Pay Cash Refund</span>
                        </button>
                    </div>
                </form>
            </div>
        @endif
    @endif

    <!-- Official SRN Voucher Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 md:p-8 space-y-6 print:border-none print:shadow-none print:p-0">
        <!-- Top Voucher Header -->
        <div class="flex justify-between items-start border-b border-slate-200 pb-6">
            <div>
                <h2 class="text-xl font-black text-slate-900 uppercase tracking-wider">Khalid Traders</h2>
                <p class="text-xs text-slate-500">Footwear Wholesale & Distribution ERP</p>
                <div class="mt-3 text-xs space-y-0.5">
                    <p><strong class="text-slate-700">Receiving Location:</strong> {{ $salesReturn->location->name }}</p>
                    <p><strong class="text-slate-700">Created By:</strong> {{ $salesReturn->creator->name }}</p>
                    @if($salesReturn->postedBy)
                        <p><strong class="text-slate-700">Posted By:</strong> {{ $salesReturn->postedBy->name }} ({{ $salesReturn->posted_at ? $salesReturn->posted_at->format('d M Y, h:i A') : '' }})</p>
                    @endif
                </div>
            </div>
            <div class="text-end">
                <div class="inline-block px-3 py-1 {{ $salesReturn->status === 'posted' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-amber-50 border-amber-200 text-amber-800' }} border font-mono text-sm font-bold rounded-lg mb-2">
                    {{ $salesReturn->return_number }}
                </div>
                <div class="text-xs space-y-0.5 text-slate-600">
                    <p><strong class="text-slate-700">Return Date:</strong> {{ $salesReturn->return_date->format('d M Y') }}</p>
                    <p><strong class="text-slate-700">Original Invoice:</strong>
                        <a href="{{ route('sales.invoices.show', $salesReturn->salesInvoice) }}" class="text-indigo-600 font-mono font-bold hover:underline">
                            {{ $salesReturn->salesInvoice->invoice_number }}
                        </a>
                    </p>
                    @if($salesReturn->creditNote)
                        <p><strong class="text-slate-700">Credit Note:</strong>
                            <a href="{{ route('customer_credit_notes.show', $salesReturn->creditNote) }}" class="text-indigo-600 font-mono font-bold hover:underline">
                                {{ $salesReturn->creditNote->credit_note_number }}
                            </a>
                        </p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Customer & Reason Details -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl text-xs">
            <div>
                <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Customer / Khata Account</p>
                <p class="font-bold text-sm text-slate-900">{{ $salesReturn->customer->business_name }}</p>
                <p class="text-slate-600">{{ $salesReturn->customer->contact_person }} • {{ $salesReturn->customer->phone }}</p>
                <p class="text-slate-500">{{ $salesReturn->customer->city }}</p>
                <a href="{{ route('customers.ledger', $salesReturn->customer) }}" class="inline-flex items-center gap-1 text-indigo-600 hover:underline font-semibold mt-1">
                    <i data-lucide="book-open" class="w-3.5 h-3.5"></i>
                    <span>View Customer Khata</span>
                </a>
            </div>
            <div>
                <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] mb-1">Return Reason & Remarks</p>
                <p class="font-semibold text-slate-900 text-sm">{{ $salesReturn->reason }}</p>
                @if($salesReturn->notes)
                    <p class="text-slate-600 mt-1 italic">{{ $salesReturn->notes }}</p>
                @endif
                <div class="mt-2 flex items-center gap-2">
                    <span class="text-slate-500">Refund Status:</span>
                    <span class="font-bold text-slate-800">{{ ucfirst(str_replace('_', ' ', $salesReturn->refund_status)) }}</span>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div>
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Returned Footwear Articles</h3>
            <div class="border border-slate-200 rounded-xl overflow-hidden">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-100/80 border-b border-slate-200 text-slate-600 font-bold uppercase">
                            <th class="py-2.5 px-3">#</th>
                            <th class="py-2.5 px-3">Article / SKU</th>
                            <th class="py-2.5 px-3">Size / Color</th>
                            <th class="py-2.5 px-3 text-center">Condition</th>
                            <th class="py-2.5 px-3 text-center">Restocked</th>
                            <th class="py-2.5 px-3 text-right">Cartons</th>
                            <th class="py-2.5 px-3 text-right">Pairs</th>
                            <th class="py-2.5 px-3 text-right">Unit Price</th>
                            <th class="py-2.5 px-3 text-right">Amount (PKR)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($salesReturn->items as $idx => $item)
                            <tr>
                                <td class="py-2.5 px-3 font-mono text-slate-400">{{ $idx + 1 }}</td>
                                <td class="py-2.5 px-3">
                                    <span class="font-bold text-slate-800">{{ $item->productVariant->product->name ?? 'Footwear' }}</span>
                                    <span class="block font-mono text-slate-500 text-[11px]">{{ $item->productVariant->sku }}</span>
                                </td>
                                <td class="py-2.5 px-3 text-slate-700">
                                    {{ $item->productVariant->size }} • {{ $item->productVariant->color }}
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold
                                        {{ $item->condition === 'resalable' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                                        {{ ucfirst($item->condition) }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    @if($item->is_restocked)
                                        <span class="text-emerald-700 font-bold">Yes</span>
                                    @else
                                        <span class="text-slate-400">No</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-medium text-slate-700">
                                    {{ number_format($item->returned_cartons, 2) }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900">
                                    {{ number_format($item->returned_pairs, 0) }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono text-slate-700">
                                    ₨ {{ number_format($item->unit_price_per_pair, 2) }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-indigo-700">
                                    ₨ {{ number_format($item->total_amount, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50 font-bold border-t border-slate-200">
                        <tr>
                            <td colspan="5" class="py-3 px-3 text-right uppercase text-[11px] text-slate-600">Total Return:</td>
                            <td class="py-3 px-3 text-right font-mono text-xs">{{ number_format($salesReturn->total_returned_cartons, 2) }}</td>
                            <td class="py-3 px-3 text-right font-mono text-xs text-indigo-700">{{ number_format($salesReturn->total_returned_pairs, 0) }}</td>
                            <td></td>
                            <td class="py-3 px-3 text-right font-mono text-sm text-indigo-700">₨ {{ number_format($salesReturn->total_return_amount, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Refund History Log (If Any) -->
        @if($salesReturn->refunds->isNotEmpty())
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Customer Cash / Bank Refund History</h3>
                <div class="border border-slate-200 rounded-xl overflow-hidden">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-100/80 border-b border-slate-200 text-slate-600 font-bold uppercase">
                                <th class="py-2 px-3">Refund #</th>
                                <th class="py-2 px-3">Date</th>
                                <th class="py-2 px-3">Payment Method</th>
                                <th class="py-2 px-3">Processed By</th>
                                <th class="py-2 px-3 text-right">Amount (PKR)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($salesReturn->refunds as $ref)
                                <tr>
                                    <td class="py-2 px-3 font-mono font-bold text-indigo-600">{{ $ref->refund_number }}</td>
                                    <td class="py-2 px-3 text-slate-600">{{ $ref->refund_date->format('Y-m-d') }}</td>
                                    <td class="py-2 px-3 font-semibold uppercase text-slate-700">{{ $ref->payment_method }}</td>
                                    <td class="py-2 px-3 text-slate-600">{{ $ref->creator->name }}</td>
                                    <td class="py-2 px-3 text-right font-mono font-bold text-slate-900">₨ {{ number_format($ref->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- Signatures for Printable Voucher -->
        <div class="pt-12 grid grid-cols-3 gap-6 text-center text-xs text-slate-500">
            <div class="border-t border-slate-300 pt-2">
                <p class="font-bold text-slate-800">Received By (Warehouse)</p>
            </div>
            <div class="border-t border-slate-300 pt-2">
                <p class="font-bold text-slate-800">Returned By (Customer / Carrier)</p>
            </div>
            <div class="border-t border-slate-300 pt-2">
                <p class="font-bold text-slate-800">Authorized Manager</p>
            </div>
        </div>
    </div>
</div>
@endsection
