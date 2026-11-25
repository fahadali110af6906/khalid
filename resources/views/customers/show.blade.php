@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $customer->business_name }}</h1>
                <span class="font-mono text-xs font-bold px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-200">
                    {{ $customer->customer_code }}
                </span>
                @if($customer->is_active)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                        {{ __('erp.customers.active') }}
                    </span>
                @else
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800">
                        {{ __('erp.customers.inactive') }}
                    </span>
                @endif
            </div>
            <p class="text-sm text-slate-500 mt-1">
                {{ $customer->contact_person }} â€¢ {{ $customer->phone }} â€¢
                <span class="text-slate-700 font-semibold">{{ $customer->primaryLocation?->name }}</span>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('customers.ledger', $customer) }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition shadow-xs">
                <i data-lucide="book-open" class="w-4 h-4"></i>
                <span>{{ __('erp.customers.khata_statement') }}</span>
            </a>

            <a href="{{ route('customers.edit', $customer) }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-white hover:bg-slate-50 border border-slate-300 text-slate-700 text-sm font-bold transition">
                <i data-lucide="pencil" class="w-4 h-4"></i>
                <span>{{ __('erp.customers.edit_details') }}</span>
            </a>

            <form method="POST" action="{{ route('customers.toggle-status', $customer) }}" class="inline">
                @csrf
                <button type="submit"
                        onclick="return confirm('Are you sure you want to change this customer account status?')"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl border text-sm font-bold transition {{ $customer->is_active ? 'bg-rose-50 hover:bg-rose-100 text-rose-700 border-rose-200' : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border-emerald-200' }}">
                    <i data-lucide="{{ $customer->is_active ? 'user-x' : 'user-check' }}" class="w-4 h-4"></i>
                    <span>{{ $customer->is_active ? __('erp.customers.deactivate') : __('erp.customers.activate') }}</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Alert Success -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium flex items-center gap-2">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Khata Financial Overview Cards -->
    @php
        $balance = (float) $khataSummary['closing_balance'];
        $status = $khataSummary['balance_status'];
        $creditLimit = (float) $customer->credit_limit;
        $utilizationPct = ($creditLimit > 0 && $balance > 0) ? min(100, round(($balance / $creditLimit) * 100)) : 0;
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Outstanding Balance Card -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">{{ __('erp.customers.closing_balance') }}</span>
            <div class="text-2xl font-extrabold mt-2 {{ $status === 'receivable' ? 'text-amber-600' : ($status === 'payable' ? 'text-emerald-600' : 'text-slate-700') }}">
                â‚¨ {{ number_format(abs($balance), 2) }}
            </div>
            <div class="text-xs font-semibold mt-1">
                @if($status === 'receivable')
                    <span class="text-amber-700">â— {{ __('erp.customers.receivable') }} (Ø¨Ù†Ø§Ù…)</span>
                @elseif($status === 'payable')
                    <span class="text-emerald-700">â— {{ __('erp.customers.payable') }} (Ø¬Ù…Ø¹)</span>
                @else
                    <span class="text-slate-500">â— {{ __('erp.customers.settled') }} (Ø¨Û’ Ø¨Ø§Ù‚)</span>
                @endif
            </div>
        </div>

        <!-- Total Debits (Sales) -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">{{ __('erp.customers.total_debit') }}</span>
            <div class="text-2xl font-extrabold text-slate-900 mt-2 font-mono">
                â‚¨ {{ number_format((float) $khataSummary['total_debit'], 2) }}
            </div>
            <div class="text-xs text-slate-500 mt-1">Wholesale credit goods & debit charges</div>
        </div>

        <!-- Total Credits (Payments/Returns) -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">{{ __('erp.customers.total_credit') }}</span>
            <div class="text-2xl font-extrabold text-emerald-600 mt-2 font-mono">
                â‚¨ {{ number_format((float) $khataSummary['total_credit'], 2) }}
            </div>
            <div class="text-xs text-slate-500 mt-1">Cash, bank collections & credit returns</div>
        </div>

        <!-- Credit Limit Card -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">{{ __('erp.customers.credit_limit') }}</span>
            <div class="text-2xl font-extrabold text-slate-900 mt-2 font-mono">
                â‚¨ {{ number_format($creditLimit, 2) }}
            </div>
            @if($creditLimit > 0)
                <div class="mt-2">
                    <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                        <div class="h-1.5 rounded-full {{ $utilizationPct > 90 ? 'bg-rose-500' : 'bg-indigo-600' }}" style="width: {{ $utilizationPct }}%"></div>
                    </div>
                    <span class="text-[11px] text-slate-500 mt-1 block">{{ $utilizationPct }}% Credit Utilized</span>
                </div>
            @else
                <div class="text-xs text-slate-400 mt-1">No formal limit set (Uncapped)</div>
            @endif
        </div>
    </div>

    <!-- Contact Details & Profile Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Details Card -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6">
            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-3 mb-4">
                Party Contact & Shipping Profile
            </h2>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs text-slate-400 font-bold uppercase">{{ __('erp.customers.contact_person') }}</dt>
                    <dd class="font-semibold text-slate-800">{{ $customer->contact_person }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400 font-bold uppercase">{{ __('erp.customers.phone') }}</dt>
                    <dd class="font-mono text-slate-800">{{ $customer->phone }}</dd>
                </div>
                @if($customer->alternate_phone)
                    <div>
                        <dt class="text-xs text-slate-400 font-bold uppercase">{{ __('erp.customers.alternate_phone') }}</dt>
                        <dd class="font-mono text-slate-800">{{ $customer->alternate_phone }}</dd>
                    </div>
                @endif
                @if($customer->city)
                    <div>
                        <dt class="text-xs text-slate-400 font-bold uppercase">{{ __('erp.customers.city') }}</dt>
                        <dd class="text-slate-800">{{ $customer->city }}</dd>
                    </div>
                @endif
                @if($customer->address)
                    <div>
                        <dt class="text-xs text-slate-400 font-bold uppercase">{{ __('erp.customers.address') }}</dt>
                        <dd class="text-slate-700 text-xs">{{ $customer->address }}</dd>
                    </div>
                @endif
                @if($customer->tax_number)
                    <div>
                        <dt class="text-xs text-slate-400 font-bold uppercase">{{ __('erp.customers.tax_number') }}</dt>
                        <dd class="font-mono text-slate-800 text-xs">{{ $customer->tax_number }}</dd>
                    </div>
                @endif
                @if($customer->notes)
                    <div class="pt-2 border-t border-slate-100">
                        <dt class="text-xs text-slate-400 font-bold uppercase">Commercial Notes</dt>
                        <dd class="text-slate-600 text-xs italic mt-0.5">{{ $customer->notes }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        <!-- Recent Khata Transactions -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Recent Khata Transactions</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Last 5 ledger postings for this party</p>
                </div>
                <a href="{{ route('customers.ledger', $customer) }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-700 transition">
                    View Full Ledger &rarr;
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left rtl:text-right text-xs">
                    <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500 font-bold border-b border-slate-100">
                        <tr>
                            <th class="py-3 px-4">{{ __('erp.date') }}</th>
                            <th class="py-3 px-4">Ref #</th>
                            <th class="py-3 px-4">{{ __('erp.customers.description') }}</th>
                            <th class="py-3 px-4 text-right rtl:text-left">{{ __('erp.customers.debit') }}</th>
                            <th class="py-3 px-4 text-right rtl:text-left">{{ __('erp.customers.credit') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                        @forelse($recentEntries as $entry)
                            <tr class="hover:bg-slate-50/60">
                                <td class="py-3 px-4 font-mono text-slate-600">{{ $entry->entry_date->format('Y-m-d') }}</td>
                                <td class="py-3 px-4 font-mono font-bold text-indigo-600">{{ $entry->reference_number ?? 'â€”' }}</td>
                                <td class="py-3 px-4 text-slate-800">{{ $entry->description ?? ucfirst(str_replace('_', ' ', $entry->entry_type)) }}</td>
                                <td class="py-3 px-4 text-right rtl:text-left font-mono font-bold text-slate-900">
                                    {{ (float) $entry->debit_amount > 0 ? 'â‚¨ ' . number_format((float) $entry->debit_amount, 2) : 'â€”' }}
                                </td>
                                <td class="py-3 px-4 text-right rtl:text-left font-mono font-bold text-emerald-600">
                                    {{ (float) $entry->credit_amount > 0 ? 'â‚¨ ' . number_format((float) $entry->credit_amount, 2) : 'â€”' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-slate-400">
                                    {{ __('erp.customers.no_entries') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
