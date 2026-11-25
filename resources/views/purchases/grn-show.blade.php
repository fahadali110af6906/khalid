@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between print:hidden">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('purchases.show', $goodsReceipt->purchaseOrder) }}" class="text-xs font-semibold text-slate-500 hover:text-indigo-600 transition">
                    {{ $goodsReceipt->purchaseOrder->purchase_number }}
                </a>
                <span class="text-slate-400 text-xs">/</span>
                <span class="text-xs font-bold text-slate-800">{{ $goodsReceipt->receipt_number }}</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 mt-1">
                Goods Receipt Note (GRN)
            </h1>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('purchases.receipts.returns.create', $goodsReceipt) }}" class="px-3.5 py-2 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-800 text-xs font-bold transition flex items-center gap-1.5 border border-amber-200 shadow-xs">
                <i data-lucide="corner-up-left" class="w-4 h-4"></i>
                <span>Return Items</span>
            </a>
            <button onclick="window.print()" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition flex items-center gap-1.5 shadow-xs">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span>Print GRN Voucher</span>
            </button>
            <a href="{{ route('purchases.show', $goodsReceipt->purchaseOrder) }}" class="px-3.5 py-2 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold transition">
                Back to Purchase Order
            </a>
        </div>
    </div>

    <!-- Official GRN Voucher Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 md:p-8 space-y-6 print:border-none print:shadow-none print:p-0">
        <!-- Top Voucher Header -->
        <div class="flex justify-between items-start border-b border-slate-200 pb-6">
            <div>
                <h2 class="text-xl font-black text-slate-900 uppercase tracking-wider">Khalid Traders</h2>
                <p class="text-xs text-slate-500">Wholesale Footwear & Factory Operations</p>
                <div class="mt-3 text-xs space-y-0.5">
                    <p><strong class="text-slate-700">Receiving Location:</strong> {{ $goodsReceipt->location->name }}</p>
                    <p><strong class="text-slate-700">Received By:</strong> {{ $goodsReceipt->receivedBy->name }}</p>
                </div>
            </div>
            <div class="text-end">
                <div class="inline-block px-3 py-1 bg-emerald-50 border border-emerald-200 text-emerald-800 font-mono text-sm font-bold rounded-lg mb-2">
                    {{ $goodsReceipt->receipt_number }}
                </div>
                <div class="text-xs space-y-0.5 text-slate-600">
                    <p><strong class="text-slate-700">Receipt Date:</strong> {{ $goodsReceipt->receipt_date->format('d M Y') }}</p>
                    <p><strong class="text-slate-700">PO Number:</strong> {{ $goodsReceipt->purchaseOrder->purchase_number }}</p>
                    @if($goodsReceipt->challan_number)
                        <p><strong class="text-slate-700">Bilty/Challan #:</strong> {{ $goodsReceipt->challan_number }}</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Supplier Details -->
        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 text-xs flex justify-between items-center">
            <div>
                <span class="text-slate-400 uppercase font-bold text-[10px] block">Supplier / Vendor</span>
                <span class="text-sm font-bold text-slate-900">{{ $goodsReceipt->supplier->business_name }}</span>
                <span class="text-slate-500 font-mono ms-2">({{ $goodsReceipt->supplier->supplier_code }})</span>
            </div>
            <div class="text-end text-slate-600">
                <p>{{ $goodsReceipt->supplier->contact_person }} • {{ $goodsReceipt->supplier->phone }}</p>
                <p>{{ $goodsReceipt->supplier->city ?? '' }}</p>
            </div>
        </div>

        <!-- Items Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left rtl:text-right text-xs">
                <thead class="bg-slate-100 text-slate-700 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="py-2.5 px-3">#</th>
                        <th class="py-2.5 px-3">Article / Variant</th>
                        <th class="py-2.5 px-3 text-end">Received (Cartons)</th>
                        <th class="py-2.5 px-3 text-end">Received (Pairs)</th>
                        <th class="py-2.5 px-3 text-end">Unit Cost / Pair (PKR)</th>
                        <th class="py-2.5 px-3 text-end">Total Value (PKR)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium font-mono">
                    @foreach($goodsReceipt->items as $idx => $item)
                        @php
                            $variant = $item->productVariant;
                        @endphp
                        <tr>
                            <td class="py-2.5 px-3 text-slate-400">{{ $idx + 1 }}</td>
                            <td class="py-2.5 px-3 font-sans">
                                <span class="font-bold text-slate-900">{{ $variant->product->name }}</span>
                                <span class="block text-[11px] font-mono text-slate-500">{{ $variant->sku }} • Size: {{ $variant->size }} • Color: {{ $variant->color }}</span>
                            </td>
                            <td class="py-2.5 px-3 text-end text-slate-700">
                                {{ number_format($item->received_cartons, 1) }}
                            </td>
                            <td class="py-2.5 px-3 text-end font-bold text-emerald-700">
                                {{ number_format($item->received_pairs, 0) }}
                            </td>
                            <td class="py-2.5 px-3 text-end text-slate-700">
                                {{ number_format($item->unit_cost_per_pair, 2) }}
                            </td>
                            <td class="py-2.5 px-3 text-end font-bold text-slate-900">
                                {{ number_format($item->total_cost, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t-2 border-slate-300 font-bold font-mono">
                    <tr class="bg-slate-50">
                        <td colspan="3" class="py-3 px-3 text-slate-800 font-sans">
                            Total Received Shipment
                        </td>
                        <td class="py-3 px-3 text-end text-emerald-700 text-sm">
                            {{ number_format($goodsReceipt->total_received_pairs, 0) }} prs
                        </td>
                        <td class="py-3 px-3 text-end font-sans text-slate-500">
                            Total Cost:
                        </td>
                        <td class="py-3 px-3 text-end text-slate-900 text-sm">
                            PKR {{ number_format($goodsReceipt->total_received_cost, 2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Khata & Stock Notification -->
        <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center justify-between">
            <span class="flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                <span>Inventory stock added and Supplier Khata credited with <strong>PKR {{ number_format($goodsReceipt->total_received_cost, 2) }}</strong>.</span>
            </span>
            <a href="{{ route('suppliers.ledger', $goodsReceipt->supplier) }}" class="font-bold underline text-emerald-900 print:hidden">
                View Khata
            </a>
        </div>

        <!-- Signature Section -->
        <div class="pt-8 grid grid-cols-2 gap-8 text-center text-xs text-slate-500">
            <div>
                <div class="border-b border-slate-300 pb-12"></div>
                <p class="mt-2 font-semibold">Store Incharge / Receiver Signature</p>
            </div>
            <div>
                <div class="border-b border-slate-300 pb-12"></div>
                <p class="mt-2 font-semibold">Authorized Signatory / Owner</p>
            </div>
        </div>
    </div>
</div>
@endsection
