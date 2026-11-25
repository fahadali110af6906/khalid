@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $supplier->business_name }}</h1>
                <span class="font-mono text-xs font-bold px-2.5 py-1 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200">
                    {{ $supplier->supplier_code }}
                </span>
                @if($supplier->is_active)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                        Active
                    </span>
                @else
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800">
                        Inactive
                    </span>
                @endif
            </div>
            <p class="text-sm text-slate-500 mt-1">
                Contact: <strong class="text-slate-700">{{ $supplier->contact_person }}</strong> | 
                Phone: <span class="font-mono text-slate-700">{{ $supplier->phone }}</span> | 
                Dealing Location: <span class="font-semibold text-slate-700">{{ $supplier->primaryLocation->name }}</span>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="{{ route('supplier-payments.create', ['supplier_id' => $supplier->id]) }}"
               class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-xs">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>Record Payment</span>
            </a>
            <a href="{{ route('purchases.create', ['supplier_id' => $supplier->id]) }}"
               class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition shadow-xs">
                <i data-lucide="shopping-cart" class="w-4 h-4"></i>
                <span>New Purchase Order</span>
            </a>
            <a href="{{ route('suppliers.ledger', $supplier) }}"
               class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold transition shadow-xs">
                <i data-lucide="book-open" class="w-4 h-4"></i>
                <span>Full Khata Ledger</span>
            </a>
            <a href="{{ route('suppliers.edit', $supplier) }}"
               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
                <i data-lucide="edit-3" class="w-4 h-4"></i>
                <span>Edit</span>
            </a>
        </div>
    </div>

    <!-- Alert Success -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium flex items-center gap-2">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Financial Khata Balance Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Opening Payable -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="flex items-center justify-between text-slate-400 mb-2">
                <span class="text-xs font-bold uppercase tracking-wider">Opening Payable</span>
                <i data-lucide="calendar" class="w-4 h-4"></i>
            </div>
            <div class="text-xl font-bold font-mono text-slate-900">
                PKR {{ number_format((float) $khataSummary['base_opening_balance'], 2) }}
            </div>
            <p class="text-[11px] text-slate-400 mt-1">Initial starting ledger balance</p>
        </div>

        <!-- Card 2: Total Purchases (Credits) -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="flex items-center justify-between text-amber-500 mb-2">
                <span class="text-xs font-bold uppercase tracking-wider">Total Purchases (Credit)</span>
                <i data-lucide="package" class="w-4 h-4"></i>
            </div>
            <div class="text-xl font-bold font-mono text-amber-600">
                PKR {{ number_format((float) $khataSummary['total_credit'], 2) }}
            </div>
            <p class="text-[11px] text-slate-400 mt-1">Goods received (increases payable)</p>
        </div>

        <!-- Card 3: Total Paid (Debits) -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="flex items-center justify-between text-emerald-500 mb-2">
                <span class="text-xs font-bold uppercase tracking-wider">Total Paid (Debit)</span>
                <i data-lucide="arrow-down-left" class="w-4 h-4"></i>
            </div>
            <div class="text-xl font-bold font-mono text-emerald-600">
                PKR {{ number_format((float) $khataSummary['total_debit'], 2) }}
            </div>
            <p class="text-[11px] text-slate-400 mt-1">Payments made (reduces payable)</p>
        </div>

        <!-- Card 4: Current Net Closing Payable -->
        @php
            $netBalance = (float) $khataSummary['closing_balance'];
        @endphp
        <div class="p-5 rounded-2xl border shadow-xs {{ $netBalance > 0 ? 'bg-rose-50/60 border-rose-200 text-rose-900' : ($netBalance < 0 ? 'bg-emerald-50/60 border-emerald-200 text-emerald-900' : 'bg-slate-50 border-slate-200 text-slate-900') }}">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold uppercase tracking-wider">
                    Current Net Balance
                </span>
                <i data-lucide="wallet" class="w-4 h-4"></i>
            </div>
            <div class="text-xl font-bold font-mono">
                PKR {{ number_format(abs($netBalance), 2) }}
            </div>
            <div class="mt-1">
                @if($netBalance > 0)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-rose-200 text-rose-800 uppercase tracking-wide">
                        Payable (واجب الادا)
                    </span>
                @elseif($netBalance < 0)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-200 text-emerald-800 uppercase tracking-wide">
                        Advance Paid (پیشگی)
                    </span>
                @else
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-slate-200 text-slate-700 uppercase tracking-wide">
                        Settled (حساب برابر)
                    </span>
                @endif
            </div>
        </div>
    </div>

    <!-- Details & Tables Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Purchase Orders -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <i data-lucide="shopping-bag" class="w-5 h-5 text-indigo-600"></i>
                    <h3 class="font-bold text-slate-900 text-sm">Recent Purchase Orders</h3>
                </div>
                <a href="{{ route('purchases.index', ['supplier_id' => $supplier->id]) }}" class="text-xs font-bold text-indigo-600 hover:underline">
                    View All POs
                </a>
            </div>

            <div class="divide-y divide-slate-100 text-xs">
                @forelse($recentOrders as $order)
                    <div class="py-3 flex items-center justify-between">
                        <div>
                            <a href="{{ route('purchases.show', $order) }}" class="font-bold font-mono text-indigo-600 hover:underline">
                                {{ $order->purchase_number }}
                            </a>
                            <div class="text-slate-400 mt-0.5">
                                {{ $order->order_date->format('d M Y') }} • {{ $order->total_pairs }} pairs ({{ $order->total_cartons }} ctn)
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="font-bold font-mono text-slate-800">PKR {{ number_format($order->total_amount, 2) }}</span>
                            <div>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold
                                    {{ $order->status === 'received' ? 'bg-emerald-100 text-emerald-800' : ($order->status === 'partially_received' ? 'bg-amber-100 text-amber-800' : ($order->status === 'ordered' ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-700')) }}">
                                    {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                </span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="py-6 text-center text-slate-400">
                        No purchase orders recorded yet.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Recent Payments Made -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <i data-lucide="receipt" class="w-5 h-5 text-emerald-600"></i>
                    <h3 class="font-bold text-slate-900 text-sm">Recent Payments to Supplier</h3>
                </div>
                <a href="{{ route('supplier-payments.index', ['supplier_id' => $supplier->id]) }}" class="text-xs font-bold text-indigo-600 hover:underline">
                    View All Payments
                </a>
            </div>

            <div class="divide-y divide-slate-100 text-xs">
                @forelse($recentPayments as $payment)
                    <div class="py-3 flex items-center justify-between">
                        <div>
                            <a href="{{ route('supplier-payments.show', $payment) }}" class="font-bold font-mono text-indigo-600 hover:underline">
                                {{ $payment->payment_number }}
                            </a>
                            <div class="text-slate-400 mt-0.5">
                                {{ $payment->payment_date->format('d M Y') }} • {{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}
                                @if($payment->reference_number)
                                    (Ref: {{ $payment->reference_number }})
                                @endif
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="font-bold font-mono text-emerald-600">PKR {{ number_format($payment->amount, 2) }}</span>
                            <span class="block text-[10px] text-slate-400">{{ $payment->location->name }}</span>
                        </div>
                    </div>
                @empty
                    <div class="py-6 text-center text-slate-400">
                        No payments recorded yet.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
