@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between print:hidden">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('purchases.returns.index') }}" class="text-xs font-semibold text-slate-500 hover:text-indigo-600 transition">
                    {{ __('erp.purchase_returns.title') }}
                </a>
                <span class="text-slate-400 text-xs">/</span>
                <span class="text-xs font-bold text-slate-800">{{ $purchaseReturn->return_number }}</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 mt-1 flex items-center gap-3">
                <span>Purchase Return Note (PRN)</span>
                @if($purchaseReturn->status === 'draft')
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200">
                        Draft (Unposted)
                    </span>
                @elseif($purchaseReturn->status === 'posted')
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                        Posted & Deducted
                    </span>
                @else
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700">
                        {{ ucfirst($purchaseReturn->status) }}
                    </span>
                @endif
            </h1>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="window.print()" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition flex items-center gap-1.5 shadow-xs">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span>Print Return Voucher</span>
            </button>
            <a href="{{ route('purchases.returns.index') }}" class="px-3.5 py-2 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold transition">
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

    <!-- Draft Action Banner (Posting Confirmation) -->
    @if($purchaseReturn->status === 'draft')
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 print:hidden">
            <div class="space-y-1">
                <div class="flex items-center gap-2 text-amber-900 font-bold text-sm">
                    <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600"></i>
                    <span>Ready to Post Return to Inventory & Khata</span>
                </div>
                <p class="text-xs text-amber-800">
                    Posting will deduct <strong class="font-mono">{{ number_format($purchaseReturn->total_return_pairs, 0) }} pairs</strong> from <strong class="font-semibold">{{ $purchaseReturn->location->name }}</strong> inventory and debit <strong class="font-mono">PKR {{ number_format($purchaseReturn->total_return_amount, 2) }}</strong> to {{ $purchaseReturn->supplier->business_name }}'s khata.
                </p>
            </div>
            <form method="POST" action="{{ route('purchases.returns.post', $purchaseReturn) }}"
                  onsubmit="return confirm('Confirm posting return #{{ $purchaseReturn->return_number }}?\n\n- Stock will be deducted from {{ $purchaseReturn->location->name }}\n- Supplier Khata will be debited PKR {{ number_format($purchaseReturn->total_return_amount, 2) }}\n- A formal Supplier Debit Note will be generated.')">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition flex items-center gap-2 whitespace-nowrap">
                    <i data-lucide="check-circle" class="w-4 h-4"></i>
                    <span>Post Return Now</span>
                </button>
            </form>
        </div>
    @endif

    <!-- Official PRN Voucher Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 md:p-8 space-y-6 print:border-none print:shadow-none print:p-0">
        <!-- Top Voucher Header -->
        <div class="flex justify-between items-start border-b border-slate-200 pb-6">
            <div>
                <h2 class="text-xl font-black text-slate-900 uppercase tracking-wider">Khalid Traders</h2>
                <p class="text-xs text-slate-500">Footwear Wholesale & Distribution ERP</p>
                <div class="mt-3 text-xs space-y-0.5">
                    <p><strong class="text-slate-700">Dispatch Location:</strong> {{ $purchaseReturn->location->name }}</p>
                    <p><strong class="text-slate-700">Created By:</strong> {{ $purchaseReturn->creator->name }}</p>
                    @if($purchaseReturn->postedBy)
                        <p><strong class="text-slate-700">Posted By:</strong> {{ $purchaseReturn->postedBy->name }} ({{ $purchaseReturn->posted_at ? $purchaseReturn->posted_at->format('d M Y, h:i A') : '' }})</p>
                    @endif
                </div>
            </div>
            <div class="text-end">
                <div class="inline-block px-3 py-1 {{ $purchaseReturn->status === 'posted' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-amber-50 border-amber-200 text-amber-800' }} border font-mono text-sm font-bold rounded-lg mb-2">
                    {{ $purchaseReturn->return_number }}
                </div>
                <div class="text-xs space-y-0.5 text-slate-600">
                    <p><strong class="text-slate-700">Return Date:</strong> {{ $purchaseReturn->return_date ? $purchaseReturn->return_date->format('d M Y') : '-' }}</p>
                    <p><strong class="text-slate-700">Source GRN:</strong>
                        <a href="{{ route('goods-receipts.show', $purchaseReturn->goodsReceipt) }}" class="text-indigo-600 font-mono underline hover:text-indigo-800 print:no-underline print:text-slate-800">
                            {{ $purchaseReturn->goodsReceipt->receipt_number }}
                        </a>
                    </p>
                    <p><strong class="text-slate-700">PO Number:</strong>
                        <a href="{{ route('purchases.show', $purchaseReturn->purchaseOrder) }}" class="text-indigo-600 font-mono underline hover:text-indigo-800 print:no-underline print:text-slate-800">
                            {{ $purchaseReturn->purchaseOrder->purchase_number }}
                        </a>
                    </p>
                    @if($purchaseReturn->debitNote)
                        <p><strong class="text-slate-700">Debit Note #:</strong>
                            <a href="{{ route('supplier-debit-notes.show', $purchaseReturn->debitNote) }}" class="text-emerald-700 font-mono font-bold underline hover:text-emerald-900 print:no-underline print:text-slate-800">
                                {{ $purchaseReturn->debitNote->debit_note_number }}
                            </a>
                        </p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Supplier Details -->
        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 text-xs flex justify-between items-center">
            <div>
                <span class="text-slate-400 uppercase font-bold text-[10px] block">Returned To Supplier</span>
                <span class="text-sm font-bold text-slate-900">{{ $purchaseReturn->supplier->business_name }}</span>
                <span class="text-slate-500 font-mono ms-2">({{ $purchaseReturn->supplier->supplier_code }})</span>
            </div>
            <div class="text-end text-slate-600">
                <p>{{ $purchaseReturn->supplier->contact_person }} • {{ $purchaseReturn->supplier->phone }}</p>
                <p>{{ $purchaseReturn->supplier->city ?? '' }}</p>
            </div>
        </div>

        <!-- Reason for Return -->
        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs">
            <span class="text-slate-400 uppercase font-bold text-[10px] block">Return Reason / Justification</span>
            <span class="font-semibold text-slate-800">{{ $purchaseReturn->reason }}</span>
        </div>

        <!-- Returned Items Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left rtl:text-right text-xs">
                <thead class="bg-slate-100 text-slate-700 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="py-2.5 px-3">#</th>
                        <th class="py-2.5 px-3">Article / Variant</th>
                        <th class="py-2.5 px-3 text-end">Cartons</th>
                        <th class="py-2.5 px-3 text-end">Pairs Returned</th>
                        <th class="py-2.5 px-3 text-end">Unit Cost / Pair (PKR)</th>
                        <th class="py-2.5 px-3 text-end">Total Value (PKR)</th>
                        <th class="py-2.5 px-3">Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium font-mono">
                    @foreach($purchaseReturn->items as $idx => $item)
                        @php
                            $variant = $item->productVariant;
                        @endphp
                        <tr>
                            <td class="py-2.5 px-3 text-slate-400">{{ $idx + 1 }}</td>
                            <td class="py-2.5 px-3 font-sans">
                                <span class="font-bold text-slate-900">{{ $variant->product->name }}</span>
                                <span class="block text-[11px] font-mono text-slate-500">
                                    {{ $variant->sku }} • Size: {{ $variant->size }} • Color: {{ $variant->color }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 text-end text-slate-700">
                                {{ number_format($item->return_cartons, 1) }}
                            </td>
                            <td class="py-2.5 px-3 text-end font-bold text-amber-700">
                                {{ number_format($item->return_pairs, 0) }}
                            </td>
                            <td class="py-2.5 px-3 text-end text-slate-700">
                                {{ number_format($item->unit_cost_per_pair, 2) }}
                            </td>
                            <td class="py-2.5 px-3 text-end font-bold text-slate-900">
                                {{ number_format($item->total_cost, 2) }}
                            </td>
                            <td class="py-2.5 px-3 text-slate-500 font-sans text-[11px]">
                                {{ $item->notes ?? '-' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t-2 border-slate-300 font-bold font-mono">
                    <tr class="bg-slate-50">
                        <td colspan="3" class="py-3 px-3 text-slate-800 font-sans">
                            Total Return Shipment
                        </td>
                        <td class="py-3 px-3 text-end text-amber-700 text-sm">
                            {{ number_format($purchaseReturn->total_return_pairs, 0) }} prs
                        </td>
                        <td class="py-3 px-3 text-end font-sans text-slate-500">
                            Total Return Value:
                        </td>
                        <td class="py-3 px-3 text-end text-slate-900 text-sm">
                            PKR {{ number_format($purchaseReturn->total_return_amount, 2) }}
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Khata & Stock Notification -->
        @if($purchaseReturn->status === 'posted')
            <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center justify-between">
                <span class="flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                    <span>Inventory stock deducted and Supplier Khata debited with <strong>PKR {{ number_format($purchaseReturn->total_return_amount, 2) }}</strong>.</span>
                </span>
                <div class="flex items-center gap-3 print:hidden">
                    @if($purchaseReturn->debitNote)
                        <a href="{{ route('supplier-debit-notes.show', $purchaseReturn->debitNote) }}" class="font-bold underline text-emerald-900">
                            View Debit Note
                        </a>
                    @endif
                    <a href="{{ route('suppliers.ledger', $purchaseReturn->supplier) }}" class="font-bold underline text-emerald-900">
                        View Khata
                    </a>
                </div>
            </div>
        @else
            <div class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs flex items-center gap-2">
                <i data-lucide="info" class="w-4 h-4 text-amber-600"></i>
                <span>Draft Return Note: Stock has not been deducted from warehouses or shops, and supplier ledger has not been updated yet.</span>
            </div>
        @endif

        <!-- Signature Section -->
        <div class="pt-8 grid grid-cols-3 gap-6 text-center text-xs text-slate-500">
            <div>
                <div class="border-b border-slate-300 pb-12"></div>
                <p class="mt-2 font-semibold">Store Incharge / Dispatcher</p>
            </div>
            <div>
                <div class="border-b border-slate-300 pb-12"></div>
                <p class="mt-2 font-semibold">Authorized Signatory / Owner</p>
            </div>
            <div>
                <div class="border-b border-slate-300 pb-12"></div>
                <p class="mt-2 font-semibold">Supplier / Driver Acknowledgment</p>
            </div>
        </div>
    </div>
</div>
@endsection
