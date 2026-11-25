@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.customers.title') }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                    Accounts Receivable
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">{{ __('erp.customers.subtitle') }}</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('customers.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition shadow-sm">
                <i data-lucide="user-plus" class="w-4 h-4"></i>
                <span>{{ __('erp.customers.add_customer') }}</span>
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

    <!-- Search & Filter Card -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
        <form method="GET" action="{{ route('customers.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label for="search" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.search') }}</label>
                <div class="relative">
                    <input type="text" id="search" name="search" value="{{ $search }}"
                           placeholder="{{ __('erp.customers.business_name') }}, {{ __('erp.customers.phone') }}..."
                           class="w-full pl-9 pr-3 py-2 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"></i>
                </div>
            </div>

            <div>
                <label for="location_id" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.location') }}</label>
                <select id="location_id" name="location_id" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    @if(auth()->user()->isOwner())
                        <option value="all" {{ $locationId === 'all' || !$locationId ? 'selected' : '' }}>🏢 {{ __('erp.all_locations') }}</option>
                    @endif
                    @foreach($authorizedLocations as $loc)
                        <option value="{{ $loc->id }}" {{ $locationId == $loc->id ? 'selected' : '' }}>
                            {{ $loc->type === 'factory' ? '🏭' : '🏪' }} {{ $loc->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="status" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.status') }}</label>
                <select id="status" name="status" class="w-full py-2 px-3 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:bg-white focus:outline-none">
                    <option value="active" {{ $status === 'active' ? 'selected' : '' }}>{{ __('erp.customers.active') }}</option>
                    <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>{{ __('erp.customers.inactive') }}</option>
                    <option value="all" {{ $status === 'all' ? 'selected' : '' }}>{{ __('erp.customers.all_status') }}</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2 px-4 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-bold transition flex items-center justify-center gap-1.5">
                    <i data-lucide="filter" class="w-4 h-4"></i>
                    <span>{{ __('erp.filter') }}</span>
                </button>
                <a href="{{ route('customers.index') }}" class="py-2 px-3 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold transition" title="{{ __('erp.customers.reset_filter') }}">
                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Customers Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left rtl:text-right text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500 font-bold border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-5">{{ __('erp.customers.customer_code') }}</th>
                        <th class="py-3 px-5">{{ __('erp.customers.business_name') }}</th>
                        <th class="py-3 px-5">{{ __('erp.customers.contact_person') }} & {{ __('erp.customers.phone') }}</th>
                        <th class="py-3 px-5">{{ __('erp.customers.primary_location') }}</th>
                        <th class="py-3 px-5 text-right rtl:text-left">{{ __('erp.customers.credit_limit') }}</th>
                        <th class="py-3 px-5 text-right rtl:text-left">{{ __('erp.customers.closing_balance') }}</th>
                        <th class="py-3 px-5 text-center">{{ __('erp.status') }}</th>
                        <th class="py-3 px-5 text-center">{{ __('erp.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                    @forelse($customers as $customer)
                        @php
                            $summary = $customer->khata_summary;
                            $balance = (float) $summary['closing_balance'];
                            $status = $summary['balance_status'];
                        @endphp
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="py-3.5 px-5 font-mono text-xs font-bold text-indigo-700">
                                {{ $customer->customer_code }}
                            </td>
                            <td class="py-3.5 px-5">
                                <a href="{{ route('customers.show', $customer) }}" class="font-bold text-slate-900 hover:text-indigo-600 transition">
                                    {{ $customer->business_name }}
                                </a>
                                @if($customer->city)
                                    <span class="block text-xs text-slate-500">{{ $customer->city }}</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-5">
                                <div class="font-medium text-slate-900">{{ $customer->contact_person }}</div>
                                <div class="text-xs text-slate-500 font-mono">{{ $customer->phone }}</div>
                            </td>
                            <td class="py-3.5 px-5 text-xs text-slate-600">
                                {{ $customer->primaryLocation?->name ?? 'â€”' }}
                            </td>
                            <td class="py-3.5 px-5 text-right rtl:text-left font-mono text-xs text-slate-600">
                                â‚¨ {{ number_format((float) $customer->credit_limit, 2) }}
                            </td>
                            <td class="py-3.5 px-5 text-right rtl:text-left font-mono font-bold">
                                @if($status === 'receivable')
                                    <span class="text-amber-600">â‚¨ {{ number_format($balance, 2) }}</span>
                                    <span class="block text-[10px] text-amber-700 font-sans font-semibold">{{ __('erp.customers.receivable') }}</span>
                                @elseif($status === 'payable')
                                    <span class="text-emerald-600">â‚¨ {{ number_format(abs($balance), 2) }}</span>
                                    <span class="block text-[10px] text-emerald-700 font-sans font-semibold">{{ __('erp.customers.payable') }}</span>
                                @else
                                    <span class="text-slate-400">â‚¨ 0.00</span>
                                    <span class="block text-[10px] text-slate-400 font-sans">{{ __('erp.customers.settled') }}</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-5 text-center">
                                @if($customer->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">
                                        {{ __('erp.customers.active') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800">
                                        {{ __('erp.customers.inactive') }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-5 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <a href="{{ route('customers.ledger', $customer) }}"
                                       class="p-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 transition"
                                       title="{{ __('erp.customers.view_khata') }}">
                                        <i data-lucide="book-open" class="w-4 h-4"></i>
                                    </a>
                                    <a href="{{ route('customers.show', $customer) }}"
                                       class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition"
                                       title="{{ __('erp.customers.edit_details') }}">
                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                    </a>
                                    <a href="{{ route('customers.edit', $customer) }}"
                                       class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition"
                                       title="{{ __('erp.customers.edit_customer') }}">
                                        <i data-lucide="pencil" class="w-4 h-4"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-slate-500">
                                <i data-lucide="users" class="w-10 h-10 mx-auto text-slate-300 mb-2"></i>
                                <p class="font-bold text-slate-700">{{ __('erp.customers.no_customers') }}</p>
                                <p class="text-xs text-slate-400 mt-1">Try adjusting search filters or register a new customer account.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($customers->hasPages())
            <div class="p-4 border-t border-slate-100 bg-slate-50/50">
                {{ $customers->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
