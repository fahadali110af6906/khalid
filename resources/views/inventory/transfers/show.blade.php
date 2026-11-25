@extends('layouts.app')

@section('title', 'Transfer #' . $transfer->transfer_number)

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Breadcrumb / Back -->
    <div class="flex items-center justify-between">
        <a href="{{ route('inventory.transfers.index') }}" class="inline-flex items-center gap-2 text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to Stock Transfers</span>
        </a>
        <div class="flex items-center gap-2">
            <button onclick="window.print()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 transition">
                <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                <span>Print Delivery Challan</span>
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center gap-2">
            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold">
            {{ $errors->first() }}
        </div>
    @endif

    <!-- Main Transfer Card -->
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider
                    @if($transfer->status === 'completed') bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300
                    @elseif($transfer->status === 'in_transit') bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 animate-pulse
                    @elseif($transfer->status === 'draft') bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300
                    @else bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300 @endif">
                    {{ ucfirst(str_replace('_', ' ', $transfer->status)) }}
                </span>
                <h1 class="text-xl font-bold font-mono text-slate-900 dark:text-white mt-2">
                    {{ $transfer->transfer_number }}
                </h1>
                <p class="text-xs text-slate-500 mt-0.5">Delivery Challan & Inter-Branch Stock Movement Note</p>
            </div>
            <div class="text-right text-xs text-slate-500 font-mono">
                <div>Date: {{ $transfer->transfer_date->format('d M Y') }}</div>
                <div>Created by: {{ $transfer->creator->name ?? 'System' }}</div>
            </div>
        </div>

        <!-- Locations Route Overview -->
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50/30">
            <!-- Source -->
            <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                <span class="text-[10px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400 block mb-1">
                    Origin / Source Location (Dispatched From)
                </span>
                <div class="text-base font-bold text-slate-900 dark:text-white">{{ $transfer->sourceLocation->name }}</div>
                <div class="text-xs text-slate-500 mt-1 font-mono">Type: {{ ucfirst($transfer->sourceLocation->type) }}</div>
                @if($transfer->dispatched_at)
                    <div class="text-[11px] text-slate-400 mt-2 font-mono">
                        Dispatched: {{ $transfer->dispatched_at->format('d M Y, h:i A') }} (by {{ $transfer->dispatcher?->name ?? 'Staff' }})
                    </div>
                @endif
            </div>

            <!-- Destination -->
            <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 block mb-1">
                    Destination Location (Received At)
                </span>
                <div class="text-base font-bold text-slate-900 dark:text-white">{{ $transfer->destinationLocation->name }}</div>
                <div class="text-xs text-slate-500 mt-1 font-mono">Type: {{ ucfirst($transfer->destinationLocation->type) }}</div>
                @if($transfer->received_at)
                    <div class="text-[11px] text-slate-400 mt-2 font-mono">
                        Received: {{ $transfer->received_at->format('d M Y, h:i A') }} (by {{ $transfer->receiver?->name ?? 'Staff' }})
                    </div>
                @endif
            </div>
        </div>

        @if($transfer->dispatch_notes)
            <div class="px-6 py-3 bg-amber-50/50 dark:bg-amber-950/20 border-b border-slate-200 dark:border-slate-700 text-xs text-amber-900 dark:text-amber-200">
                <span class="font-bold">Dispatch Notes / Transport:</span> {{ $transfer->dispatch_notes }}
            </div>
        @endif

        @if($transfer->cancellation_reason)
            <div class="px-6 py-3 bg-rose-50 dark:bg-rose-950/20 border-b border-rose-200 dark:border-rose-800 text-xs text-rose-900 dark:text-rose-200">
                <span class="font-bold">Cancellation Reason:</span> {{ $transfer->cancellation_reason }}
                <span class="block text-[11px] text-slate-400 mt-0.5 font-mono">Cancelled at: {{ $transfer->cancelled_at?->format('d M Y, h:i A') }}</span>
            </div>
        @endif

        <!-- Items Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-500 font-bold uppercase">
                        <th class="py-3 px-4">#</th>
                        <th class="py-3 px-4">Article Code & Product</th>
                        <th class="py-3 px-4">Size & Color</th>
                        <th class="py-3 px-4 text-center">Cartons</th>
                        <th class="py-3 px-4 text-center">Pack Ratio</th>
                        <th class="py-3 px-4 text-right">Pairs Qty</th>
                        <th class="py-3 px-4 text-right">Unit Cost</th>
                        <th class="py-3 px-4 text-right">Line Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 font-medium">
                    @foreach($transfer->items as $idx => $item)
                        <tr>
                            <td class="py-3 px-4 text-slate-400">{{ $idx + 1 }}</td>
                            <td class="py-3 px-4">
                                <div class="font-bold text-slate-900 dark:text-white">
                                    {{ $item->productVariant->product->article_code }}
                                </div>
                                <div class="text-[11px] text-slate-500">
                                    {{ $item->productVariant->product->name }}
                                </div>
                            </td>
                            <td class="py-3 px-4 font-mono text-slate-700 dark:text-slate-300">
                                Size: {{ $item->productVariant->size }} | Color: {{ $item->productVariant->color }}
                            </td>
                            <td class="py-3 px-4 text-center font-mono">
                                {{ $item->quantity_cartons }}
                            </td>
                            <td class="py-3 px-4 text-center font-mono text-slate-400">
                                {{ $item->carton_pack_qty }} pairs/ctn
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-slate-900 dark:text-white">
                                {{ number_format($item->quantity_pairs, 0) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-slate-600 dark:text-slate-300">
                                Rs. {{ number_format($item->unit_cost, 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-slate-900 dark:text-white">
                                Rs. {{ number_format($item->total_cost, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-slate-50/80 dark:bg-slate-700/60 font-bold border-t border-slate-200 dark:border-slate-700 text-xs">
                        <td colspan="3" class="py-3 px-4 text-slate-700 dark:text-slate-300 uppercase">Transfer Totals:</td>
                        <td class="py-3 px-4 text-center font-mono">{{ $transfer->total_cartons }} Ctns</td>
                        <td></td>
                        <td class="py-3 px-4 text-right font-mono text-indigo-600 dark:text-indigo-400">{{ number_format($transfer->total_pairs, 0) }} Pairs</td>
                        <td></td>
                        <td class="py-3 px-4 text-right font-mono text-slate-900 dark:text-white">Rs. {{ number_format($transfer->total_cost, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Operational Actions Bar -->
        <div class="p-6 bg-slate-50/50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-700 flex flex-wrap items-center justify-between gap-4">
            <div>
                @if($transfer->status === 'draft')
                    <span class="text-xs text-slate-500">Draft transfer is ready for warehouse/factory dispatch.</span>
                @elseif($transfer->status === 'in_transit')
                    <span class="text-xs text-amber-600 font-semibold flex items-center gap-1.5">
                        <i data-lucide="truck" class="w-4 h-4"></i>
                        <span>Stock is physically in transit. Awaiting destination shop confirmation.</span>
                    </span>
                @elseif($transfer->status === 'completed')
                    <span class="text-xs text-emerald-600 font-semibold flex items-center gap-1.5">
                        <i data-lucide="check-check" class="w-4 h-4"></i>
                        <span>Stock successfully received and credited to destination balance.</span>
                    </span>
                @endif
            </div>

            <div class="flex items-center gap-3">
                @if($transfer->status === 'draft')
                    <form action="{{ route('inventory.transfers.cancel', $transfer) }}" method="POST" class="m-0" onsubmit="return confirm('Cancel this draft transfer?')">
                        @csrf
                        <input type="hidden" name="reason" value="Cancelled by operator">
                        <button type="submit" class="px-4 py-2 rounded-xl border border-rose-300 text-rose-600 hover:bg-rose-50 text-xs font-semibold transition">
                            Cancel Draft
                        </button>
                    </form>

                    <form action="{{ route('inventory.transfers.dispatch', $transfer) }}" method="POST" class="m-0" onsubmit="return confirm('Confirm dispatch of stock transfer? Inventory will be deducted immediately.')">
                        @csrf
                        <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-md transition flex items-center gap-1.5">
                            <i data-lucide="send" class="w-4 h-4"></i>
                            <span>Dispatch Stock Transfer</span>
                        </button>
                    </form>
                @elseif($transfer->status === 'in_transit')
                    <form action="{{ route('inventory.transfers.cancel', $transfer) }}" method="POST" class="m-0" onsubmit="return confirm('Cancel in-transit transfer? Deducted stock will be safely reversed back to source location.')">
                        @csrf
                        <input type="hidden" name="reason" value="Returned / In-transit cancellation">
                        <button type="submit" class="px-4 py-2 rounded-xl border border-rose-300 text-rose-600 hover:bg-rose-50 text-xs font-semibold transition">
                            Cancel & Reverse to Source
                        </button>
                    </form>

                    <form action="{{ route('inventory.transfers.receive', $transfer) }}" method="POST" class="m-0" onsubmit="return confirm('Confirm receipt of transfer at destination? Stock will be added to inventory.')">
                        @csrf
                        <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-md transition flex items-center gap-1.5">
                            <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                            <span>Confirm Receipt at Destination</span>
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
