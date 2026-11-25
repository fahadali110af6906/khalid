@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('purchases.index') }}" class="text-xs font-semibold text-slate-500 hover:text-indigo-600 transition">
                    Purchases
                </a>
                <span class="text-slate-400 text-xs">/</span>
                <span class="text-xs font-bold text-slate-800">{{ $purchaseOrder->purchase_number }}</span>
            </div>
            <div class="flex items-center gap-3 mt-1">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                    PO #{{ $purchaseOrder->purchase_number }}
                </h1>
                @php
                    $badgeStyle = match($purchaseOrder->status) {
                        'received' => 'bg-emerald-100 text-emerald-800',
                        'partially_received' => 'bg-amber-100 text-amber-800',
                        'ordered' => 'bg-blue-100 text-blue-800',
                        'cancelled' => 'bg-rose-100 text-rose-800',
                        default => 'bg-slate-100 text-slate-700',
                    };
                @endphp
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $badgeStyle }}">
                    {{ ucfirst(str_replace('_', ' ', $purchaseOrder->status)) }}
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-1">
                Supplier: <a href="{{ route('suppliers.show', $purchaseOrder->supplier) }}" class="font-bold text-indigo-600 hover:underline">{{ $purchaseOrder->supplier->business_name }}</a> | 
                Location: <span class="font-semibold text-slate-700">{{ $purchaseOrder->location->name }}</span> | 
                Date: <span class="font-mono text-slate-700">{{ $purchaseOrder->order_date->format('d M Y') }}</span>
            </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap items-center gap-2">
            <button onclick="window.print()" class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition flex items-center gap-1.5 shadow-xs">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span>Print PO</span>
            </button>

            @if($purchaseOrder->status === 'draft')
                <a href="{{ route('purchases.edit', $purchaseOrder) }}" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition flex items-center gap-1.5">
                    <i data-lucide="edit-3" class="w-4 h-4"></i>
                    <span>Edit Draft</span>
                </a>
                <form method="POST" action="{{ route('purchases.approve', $purchaseOrder) }}" onsubmit="return confirm('Approve and issue this purchase order to supplier?');">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-xs">
                        <i data-lucide="send" class="w-4 h-4"></i>
                        <span>Approve & Place Order</span>
                    </button>
                </form>
            @endif

            @if(in_array($purchaseOrder->status, ['ordered', 'partially_received']))
                <a href="{{ route('purchases.receive', $purchaseOrder) }}" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-xs">
                    <i data-lucide="box" class="w-4 h-4"></i>
                    <span>Receive Goods (GRN)</span>
                </a>
            @endif

            <a href="{{ route('supplier-payments.create', ['supplier_id' => $purchaseOrder->supplier_id, 'purchase_order_id' => $purchaseOrder->id]) }}"
               class="px-3.5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-xs">
                <i data-lucide="credit-card" class="w-4 h-4"></i>
                <span>Pay Supplier</span>
            </a>

            @if(in_array($purchaseOrder->status, ['draft', 'ordered']))
                <form method="POST" action="{{ route('purchases.cancel', $purchaseOrder) }}" onsubmit="return confirm('Are you sure you want to cancel this purchase order?');">
                    @csrf
                    <button type="submit" class="px-3 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold transition flex items-center gap-1.5">
                        <i data-lucide="x-circle" class="w-4 h-4"></i>
                        <span>Cancel</span>
                    </button>
                </form>
            @endif
        </div>
    </div>

    <!-- Alert Success / Error -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium flex items-center gap-2">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-medium flex items-center gap-2">
            <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- PO Overview Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
            <span class="text-xs font-bold uppercase text-slate-400 block mb-1">Total Ordered</span>
            <div class="text-xl font-bold font-mono text-slate-900">
                {{ number_format($purchaseOrder->total_pairs, 0) }} prs
            </div>
            <span class="text-xs text-slate-500 font-mono">{{ $purchaseOrder->total_cartons }} cartons</span>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
            <span class="text-xs font-bold uppercase text-emerald-500 block mb-1">Total Received</span>
            @php
                $totalRecvPairs = $purchaseOrder->items->sum('received_pairs');
            @endphp
            <div class="text-xl font-bold font-mono text-emerald-600">
                {{ number_format($totalRecvPairs, 0) }} prs
            </div>
            <span class="text-xs text-slate-500 font-mono">
                {{ number_format(max(0, $purchaseOrder->total_pairs - $totalRecvPairs), 0) }} pairs remaining
            </span>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
            <span class="text-xs font-bold uppercase text-slate-400 block mb-1">Order Subtotal</span>
            <div class="text-xl font-bold font-mono text-slate-900">
                PKR {{ number_format($purchaseOrder->subtotal_amount, 2) }}
            </div>
            <span class="text-xs text-slate-400">Before disc & freight</span>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-indigo-200 shadow-xs bg-indigo-50/30">
            <span class="text-xs font-bold uppercase text-indigo-600 block mb-1">Grand Total</span>
            <div class="text-xl font-bold font-mono text-indigo-700">
                PKR {{ number_format($purchaseOrder->total_amount, 2) }}
            </div>
            <span class="text-xs text-slate-500">Freight: PKR {{ number_format($purchaseOrder->additional_charges, 2) }}</span>
        </div>
    </div>

    <!-- PO Line Items Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-bold text-slate-900 text-sm flex items-center gap-2">
                <i data-lucide="package" class="w-4 h-4 text-indigo-600"></i>
                <span>Ordered Footwear Articles</span>
            </h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left rtl:text-right text-xs">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500 font-bold border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">#</th>
                        <th class="py-3 px-4">Article & Variant</th>
                        <th class="py-3 px-4">Pack Ratio</th>
                        <th class="py-3 px-4 text-end">Ordered Qty</th>
                        <th class="py-3 px-4 text-end text-emerald-700">Received (Pairs)</th>
                        <th class="py-3 px-4 text-end text-amber-700">Remaining (Pairs)</th>
                        <th class="py-3 px-4 text-end">Rate / Pair (PKR)</th>
                        <th class="py-3 px-4 text-end">Line Total (PKR)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @foreach($purchaseOrder->items as $idx => $item)
                        @php
                            $variant = $item->productVariant;
                            $remPairs = max(0, $item->ordered_pairs - $item->received_pairs);
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3 px-4 text-slate-400 font-mono">{{ $idx + 1 }}</td>
                            <td class="py-3 px-4">
                                <div class="font-bold text-slate-900">{{ $variant->product->name }}</div>
                                <div class="text-[11px] text-slate-500 font-mono">
                                    {{ $variant->sku }} • Size: {{ $variant->size }} • Color: {{ $variant->color }}
                                </div>
                            </td>
                            <td class="py-3 px-4 font-mono text-slate-600">
                                {{ $item->carton_pack_qty }} prs/ctn
                            </td>
                            <td class="py-3 px-4 text-end font-mono">
                                <span class="font-bold text-slate-900">{{ number_format($item->ordered_pairs, 0) }} prs</span>
                                <span class="text-slate-400 block text-[10px]">({{ number_format($item->quantity_cartons, 1) }} ctn)</span>
                            </td>
                            <td class="py-3 px-4 text-end font-mono font-bold text-emerald-600">
                                {{ number_format($item->received_pairs, 0) }} prs
                            </td>
                            <td class="py-3 px-4 text-end font-mono font-bold text-amber-600">
                                {{ number_format($remPairs, 0) }} prs
                            </td>
                            <td class="py-3 px-4 text-end font-mono text-slate-700">
                                {{ number_format($item->unit_cost_per_pair, 2) }}
                            </td>
                            <td class="py-3 px-4 text-end font-mono font-bold text-slate-900">
                                {{ number_format($item->line_total, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Goods Receipts (GRN) History -->
    @if($purchaseOrder->goodsReceipts->count() > 0)
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5">
            <h3 class="font-bold text-slate-900 text-sm flex items-center gap-2 mb-3">
                <i data-lucide="truck" class="w-4 h-4 text-emerald-600"></i>
                <span>Goods Receipts (GRN) Processed Against This Order</span>
            </h3>

            <div class="divide-y divide-slate-100 text-xs">
                @foreach($purchaseOrder->goodsReceipts as $grn)
                    <div class="py-3 flex items-center justify-between">
                        <div>
                            <a href="{{ route('goods-receipts.show', $grn) }}" class="font-bold font-mono text-indigo-600 hover:underline">
                                {{ $grn->receipt_number }}
                            </a>
                            <div class="text-slate-400 mt-0.5">
                                Received on {{ $grn->receipt_date->format('d M Y') }} by {{ $grn->receivedBy->name }}
                                @if($grn->challan_number)
                                    • Challan/Bilty: <strong class="text-slate-700">{{ $grn->challan_number }}</strong>
                                @endif
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="font-bold font-mono text-emerald-700">{{ number_format($grn->total_received_pairs, 0) }} pairs</span>
                            <span class="block text-[11px] font-mono text-slate-500">Valued at PKR {{ number_format($grn->total_received_cost, 2) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
