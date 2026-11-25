@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.suppliers.title') }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                    Accounts Payable
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">{{ __('erp.suppliers.subtitle') }}</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('suppliers.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition shadow-sm">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>{{ __('erp.suppliers.new_supplier') }}</span>
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
        <form method="GET" action="{{ route('suppliers.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label for="search" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.search') }}</label>
                <div class="relative">
                    <input type="text" id="search" name="search" value="{{ $search }}"
                           placeholder="{{ __('erp.suppliers.business_name') }}, {{ __('erp.suppliers.phone') }}..."
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
                    <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All Statuses</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2 px-4 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-bold transition flex items-center justify-center gap-1.5">
                    <i data-lucide="filter" class="w-4 h-4"></i>
                    <span>{{ __('erp.filter') }}</span>
                </button>
                <a href="{{ route('suppliers.index') }}" class="py-2 px-3 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold transition" title="Reset filter">
                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Suppliers Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left rtl:text-right text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500 font-bold border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-5">{{ __('erp.suppliers.supplier_code') }}</th>
                        <th class="py-3 px-5">{{ __('erp.suppliers.business_name') }}</th>
                        <th class="py-3 px-5">{{ __('erp.suppliers.contact_person') }} & {{ __('erp.suppliers.phone') }}</th>
                        <th class="py-3 px-5">{{ __('erp.location') }}</th>
                        <th class="py-3 px-5 text-end">{{ __('erp.suppliers.current_payable') }}</th>
                        <th class="py-3 px-5 text-center">{{ __('erp.status') }}</th>
                        <th class="py-3 px-5 text-end">{{ __('erp.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($suppliers as $supplier)
                        @php
                            $bal = $supplier->current_payable_balance;
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3 px-5 font-mono text-xs text-indigo-700 font-bold">
                                <a href="{{ route('suppliers.show', $supplier) }}" class="hover:underline">
                                    {{ $supplier->supplier_code }}
                                </a>
                            </td>
                            <td class="py-3 px-5">
                                <a href="{{ route('suppliers.show', $supplier) }}" class="font-bold text-slate-900 hover:text-indigo-600 block">
                                    {{ $supplier->business_name }}
                                </a>
                                @if($supplier->city)
                                    <span class="text-xs text-slate-400 font-normal">{{ $supplier->city }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-5">
                                <div class="text-slate-800 font-semibold">{{ $supplier->contact_person }}</div>
                                <div class="text-xs text-slate-500 font-mono">{{ $supplier->phone }}</div>
                            </td>
                            <td class="py-3 px-5">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold bg-slate-100 text-slate-700">
                                    {{ $supplier->primaryLocation->name }}
                                </span>
                            </td>
                            <td class="py-3 px-5 text-end font-mono font-bold">
                                @if($bal > 0)
                                    <span class="text-rose-600">PKR {{ number_format($bal, 2) }}</span>
                                    <span class="block text-[10px] text-rose-500 uppercase font-sans">Payable (واجب الادا)</span>
                                @elseif($bal < 0)
                                    <span class="text-emerald-600">PKR {{ number_format(abs($bal), 2) }}</span>
                                    <span class="block text-[10px] text-emerald-500 uppercase font-sans">Advance (پیشگی)</span>
                                @else
                                    <span class="text-slate-500">PKR 0.00</span>
                                    <span class="block text-[10px] text-slate-400 uppercase font-sans">Settled (بے باق)</span>
                                @endif
                            </td>
                            <td class="py-3 px-5 text-center">
                                @if($supplier->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-200 text-slate-600">
                                        Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-5 text-end">
                                <div class="inline-flex items-center gap-1.5">
                                    <a href="{{ route('suppliers.ledger', $supplier) }}"
                                       class="px-2.5 py-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold transition flex items-center gap-1"
                                       title="View Khata Statement">
                                        <i data-lucide="book-open" class="w-3.5 h-3.5"></i>
                                        <span>Khata</span>
                                    </a>
                                    <a href="{{ route('suppliers.edit', $supplier) }}"
                                       class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-900 transition"
                                       title="Edit Supplier">
                                        <i data-lucide="edit-3" class="w-4 h-4"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400">
                                <i data-lucide="truck" class="w-10 h-10 mx-auto mb-2 text-slate-300"></i>
                                <p class="font-medium">{{ __('erp.suppliers.no_suppliers') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($suppliers->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $suppliers->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
