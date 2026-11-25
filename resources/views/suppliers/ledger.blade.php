@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header with Breadcrumbs & Actions -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('suppliers.index') }}" class="text-xs font-semibold text-slate-500 hover:text-indigo-600 transition">
                    Suppliers
                </a>
                <span class="text-slate-400 text-xs">/</span>
                <a href="{{ route('suppliers.show', $supplier) }}" class="text-xs font-semibold text-slate-500 hover:text-indigo-600 transition">
                    {{ $supplier->business_name }}
                </a>
                <span class="text-slate-400 text-xs">/</span>
                <span class="text-xs font-bold text-slate-800">Khata Statement</span>
            </div>
            <div class="flex items-center gap-3 mt-1">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                    {{ $supplier->business_name }} — {{ __('erp.suppliers.khata_statement') }}
                </h1>
                <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-700">
                    {{ $supplier->supplier_code }}
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-1">
                Phone: <span class="font-mono">{{ $supplier->phone }}</span> | Contact: {{ $supplier->contact_person }} | City: {{ $supplier->city ?? 'N/A' }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <button onclick="window.print()" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition flex items-center gap-1.5 shadow-xs">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span>Print Khata</span>
            </button>
            <a href="{{ route('supplier-payments.create', ['supplier_id' => $supplier->id]) }}" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-xs">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>Record Payment</span>
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs print:hidden">
        <form method="GET" action="{{ route('suppliers.ledger', $supplier) }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label for="from_date" class="block text-xs font-bold text-slate-600 uppercase mb-1">From Date</label>
                <input type="date" id="from_date" name="from_date" value="{{ $fromDate }}"
                       class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
            </div>

            <div>
                <label for="to_date" class="block text-xs font-bold text-slate-600 uppercase mb-1">To Date</label>
                <input type="date" id="to_date" name="to_date" value="{{ $toDate }}"
                       class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
            </div>

            <div>
                <label for="location_id" class="block text-xs font-bold text-slate-600 uppercase mb-1">Branch / Location</label>
                <select id="location_id" name="location_id" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    @if(auth()->user()->isOwner())
                        <option value="all" {{ !$locationId ? 'selected' : '' }}>🏢 All Dealing Locations</option>
                    @endif
                    @foreach($authorizedLocations as $loc)
                        <option value="{{ $loc->id }}" {{ $locationId == $loc->id ? 'selected' : '' }}>
                            {{ $loc->type === 'factory' ? '🏭' : '🏪' }} {{ $loc->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2 px-4 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-bold transition flex items-center justify-center gap-1.5">
                    <i data-lucide="filter" class="w-4 h-4"></i>
                    <span>Apply Filter</span>
                </button>
                <a href="{{ route('suppliers.ledger', $supplier) }}" class="py-2 px-3 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold transition" title="Reset filter">
                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Summary Box -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs">
            <span class="text-[11px] font-bold uppercase text-slate-400 block mb-1">Period Opening Balance</span>
            <span class="text-lg font-bold font-mono text-slate-800">
                PKR {{ number_format((float) $khataSummary['period_opening_balance'], 2) }}
            </span>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs">
            <span class="text-[11px] font-bold uppercase text-amber-500 block mb-1">Total Purchases (Credit)</span>
            <span class="text-lg font-bold font-mono text-amber-600">
                PKR {{ number_format((float) $khataSummary['total_credit'], 2) }}
            </span>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs">
            <span class="text-[11px] font-bold uppercase text-emerald-500 block mb-1">Total Payments (Debit)</span>
            <span class="text-lg font-bold font-mono text-emerald-600">
                PKR {{ number_format((float) $khataSummary['total_debit'], 2) }}
            </span>
        </div>
        @php
            $closeBal = (float) $khataSummary['closing_balance'];
        @endphp
        <div class="p-4 rounded-xl border shadow-xs {{ $closeBal > 0 ? 'bg-rose-50 border-rose-200' : ($closeBal < 0 ? 'bg-emerald-50 border-emerald-200' : 'bg-slate-50 border-slate-200') }}">
            <span class="text-[11px] font-bold uppercase text-slate-500 block mb-1">Period Closing Balance</span>
            <span class="text-lg font-bold font-mono {{ $closeBal > 0 ? 'text-rose-700' : ($closeBal < 0 ? 'text-emerald-700' : 'text-slate-800') }}">
                PKR {{ number_format(abs($closeBal), 2) }}
            </span>
            <span class="block text-[10px] font-bold uppercase mt-0.5">
                {{ $closeBal > 0 ? 'Payable (واجب الادا)' : ($closeBal < 0 ? 'Advance Paid (پیشگی)' : 'Settled (حساب برابر)') }}
            </span>
        </div>
    </div>

    <!-- Ledger Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left rtl:text-right text-xs">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500 font-bold border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">Date</th>
                        <th class="py-3 px-4">Entry Type & Particulars</th>
                        <th class="py-3 px-4">Document Ref #</th>
                        <th class="py-3 px-4">Location</th>
                        <th class="py-3 px-4 text-end text-amber-700">Purchases / Credit (+)</th>
                        <th class="py-3 px-4 text-end text-emerald-700">Payments / Debit (-)</th>
                        <th class="py-3 px-4 text-end">Running Balance (PKR)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium font-mono">
                    <!-- Row 0: Period Opening -->
                    <tr class="bg-slate-50/60 font-sans">
                        <td class="py-2.5 px-4 text-slate-400">{{ $fromDate ? \Carbon\Carbon::parse($fromDate)->format('d M Y') : 'Start' }}</td>
                        <td class="py-2.5 px-4 font-bold text-slate-700" colspan="3">
                            Period Opening Payable Balance (سابقہ واجب الادا رقم)
                        </td>
                        <td class="py-2.5 px-4 text-end font-mono text-slate-400">-</td>
                        <td class="py-2.5 px-4 text-end font-mono text-slate-400">-</td>
                        <td class="py-2.5 px-4 text-end font-mono font-bold text-slate-900">
                            PKR {{ number_format((float) $khataSummary['period_opening_balance'], 2) }}
                        </td>
                    </tr>

                    @forelse($calculatedEntries as $item)
                        @php
                            $entry = $item['entry'];
                            $running = (float) $item['running_balance'];
                            $isCredit = (float) $entry->credit_amount > 0;
                            $isDebit = (float) $entry->debit_amount > 0;
                        @endphp
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-3 px-4 text-slate-700 whitespace-nowrap">
                                {{ $entry->entry_date->format('d M Y') }}
                            </td>
                            <td class="py-3 px-4 font-sans">
                                <div class="font-bold text-slate-900">
                                    {{ ucfirst(str_replace('_', ' ', $entry->entry_type)) }}
                                </div>
                                @if($entry->description)
                                    <div class="text-[11px] text-slate-500 font-normal mt-0.5">
                                        {{ $entry->description }}
                                    </div>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-slate-600 whitespace-nowrap">
                                @if($entry->reference_type === 'goods_receipt')
                                    <a href="{{ route('goods-receipts.show', $entry->reference_id) }}" class="text-indigo-600 hover:underline font-bold">
                                        {{ $entry->reference_number ?? ('GRN #' . $entry->reference_id) }}
                                    </a>
                                @elseif($entry->reference_type === 'supplier_payment')
                                    <a href="{{ route('supplier-payments.show', $entry->reference_id) }}" class="text-emerald-600 hover:underline font-bold">
                                        {{ $entry->reference_number ?? ('PAY #' . $entry->reference_id) }}
                                    </a>
                                @else
                                    <span class="text-slate-500">{{ $entry->reference_number ?? '-' }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-slate-600 font-sans whitespace-nowrap">
                                {{ $entry->location->name }}
                            </td>
                            <td class="py-3 px-4 text-end text-amber-600 font-bold whitespace-nowrap">
                                @if($isCredit)
                                    {{ number_format((float) $entry->credit_amount, 2) }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="py-3 px-4 text-end text-emerald-600 font-bold whitespace-nowrap">
                                @if($isDebit)
                                    {{ number_format((float) $entry->debit_amount, 2) }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="py-3 px-4 text-end font-bold whitespace-nowrap {{ $running > 0 ? 'text-rose-700' : ($running < 0 ? 'text-emerald-700' : 'text-slate-800') }}">
                                {{ number_format(abs($running), 2) }}
                                <span class="text-[10px] font-sans font-normal text-slate-500 block">
                                    {{ $running > 0 ? 'Dr (Payable)' : ($running < 0 ? 'Cr (Advance)' : 'Settled') }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400 font-sans">
                                No ledger transactions found in the specified date range.
                            </td>
                        </tr>
                    @endforelse

                    <!-- Period Closing Row -->
                    <tr class="bg-slate-100 font-sans border-t-2 border-slate-300">
                        <td class="py-3 px-4 text-slate-500 font-bold" colspan="4">
                            Period Closing Payable Balance (حتمی واجب الادا رقم)
                        </td>
                        <td class="py-3 px-4 text-end font-mono font-bold text-amber-700">
                            {{ number_format((float) $khataSummary['total_credit'], 2) }}
                        </td>
                        <td class="py-3 px-4 text-end font-mono font-bold text-emerald-700">
                            {{ number_format((float) $khataSummary['total_debit'], 2) }}
                        </td>
                        <td class="py-3 px-4 text-end font-mono font-bold text-base {{ $closeBal > 0 ? 'text-rose-700' : ($closeBal < 0 ? 'text-emerald-700' : 'text-slate-900') }}">
                            PKR {{ number_format(abs($closeBal), 2) }}
                            <span class="block text-[10px] uppercase font-bold">
                                {{ $closeBal > 0 ? 'Payable (واجب الادا)' : ($closeBal < 0 ? 'Advance (پیشگی)' : 'Settled') }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
