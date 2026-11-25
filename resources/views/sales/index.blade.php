@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.nav.sales') }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                    Phase 5B Active
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">Wholesale commercial invoicing, carton/pair inventory reduction, and party Khata.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('sales.invoices.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold transition shadow-xs">
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>{{ __('erp.invoices.create_invoice') }}</span>
            </a>
            <a href="{{ route('sales.invoices.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-bold transition shadow-xs">
                <i data-lucide="receipt" class="w-4 h-4 text-indigo-600"></i>
                <span>{{ __('erp.invoices.title') }}</span>
            </a>
            <a href="{{ route('customers.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-bold transition shadow-xs">
                <i data-lucide="users" class="w-4 h-4 text-emerald-600"></i>
                <span>{{ __('erp.nav.customers_khata') }}</span>
            </a>
        </div>
    </div>

    <!-- Khata Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Customer Receivables (Khata)</span>
            <div class="text-2xl font-extrabold text-amber-600 mt-2 font-mono">
                ₨ {{ number_format(\App\Models\Customer::sum('opening_balance'), 2) }}
            </div>
            <div class="text-xs text-slate-500 mt-1">Across {{ \App\Models\Customer::count() }} Active Wholesale Accounts</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Today's Collections (Cash & Bank)</span>
            <div class="text-2xl font-extrabold text-emerald-600 mt-2 font-mono">₨ 0.00</div>
            <div class="text-xs text-slate-500 mt-1">Collections will activate in Phase 5B</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Wholesale Challan Reference Standard</span>
            <div class="text-sm font-bold text-slate-800 mt-2 font-mono">Sample: INV-SH01-00128</div>
            <div class="text-xs text-slate-500 mt-1">Standard party format: Al-Rehman Footwear</div>
        </div>
    </div>

    <!-- Phase 5A Architectural Notice Card -->
    <div class="bg-indigo-50/70 border border-indigo-200/80 rounded-2xl p-5 flex items-start gap-4">
        <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center shrink-0 shadow-xs">
            <i data-lucide="info" class="w-5 h-5"></i>
        </div>
        <div>
            <h3 class="text-sm font-bold text-indigo-950">Commercial Sales & Invoicing</h3>
            <p class="text-xs text-indigo-800 mt-0.5 leading-relaxed">
                Wholesale commercial sales invoices, carton/pair inventory reduction, cash/credit receipts, and customer Khata accounting are active in <strong>Phase 5B</strong>.
            </p>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
        <form method="GET" action="{{ route('sales.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label for="search" class="block text-xs font-bold text-slate-600 uppercase mb-1">{{ __('erp.search') }}</label>
                <div class="relative">
                    <input type="text" id="search" name="search" value="{{ $search }}"
                           placeholder="Order #, Customer name, Code..."
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
                    <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All Statuses</option>
                    <option value="draft" {{ $status === 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="confirmed" {{ $status === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                    <option value="dispatched" {{ $status === 'dispatched' ? 'selected' : '' }}>Dispatched</option>
                    <option value="cancelled" {{ $status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2 px-4 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-bold transition flex items-center justify-center gap-1.5 shadow-xs">
                    <i data-lucide="filter" class="w-4 h-4"></i>
                    <span>{{ __('erp.filter') }}</span>
                </button>
                <a href="{{ route('sales.index') }}" class="py-2 px-3 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold transition">
                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Sales Orders Foundation Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Wholesale Sales Orders</h2>
            <span class="text-xs text-slate-500">Sorted by Order Date (Latest First)</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left rtl:text-right text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500 font-bold border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-5">Order #</th>
                        <th class="py-3 px-5">{{ __('erp.date') }}</th>
                        <th class="py-3 px-5">Customer (Wholesale Party)</th>
                        <th class="py-3 px-5">Branch / Location</th>
                        <th class="py-3 px-5 text-center">Volume (CTN / PR)</th>
                        <th class="py-3 px-5 text-right rtl:text-left">Net Amount</th>
                        <th class="py-3 px-5 text-center">{{ __('erp.status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                    @forelse($orders as $order)
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="py-3.5 px-5 font-mono text-xs font-bold text-indigo-700">
                                {{ $order->order_number }}
                            </td>
                            <td class="py-3.5 px-5 font-mono text-xs text-slate-600">
                                {{ $order->order_date->format('Y-m-d') }}
                            </td>
                            <td class="py-3.5 px-5">
                                <a href="{{ route('customers.show', $order->customer) }}" class="font-bold text-slate-900 hover:text-indigo-600">
                                    {{ $order->customer->business_name }}
                                </a>
                                <span class="block text-xs text-slate-400 font-mono">{{ $order->customer->customer_code }}</span>
                            </td>
                            <td class="py-3.5 px-5 text-xs text-slate-600">
                                {{ $order->location?->name }}
                            </td>
                            <td class="py-3.5 px-5 text-center font-mono text-xs">
                                <span class="font-bold text-slate-900">{{ $order->total_cartons }} CTN</span>
                                <span class="text-[10px] text-slate-400 block">({{ number_format((float) $order->total_pairs, 0) }} Pairs)</span>
                            </td>
                            <td class="py-3.5 px-5 text-right rtl:text-left font-mono font-bold text-slate-900">
                                ₨ {{ number_format((float) $order->net_amount, 2) }}
                            </td>
                            <td class="py-3.5 px-5 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider
                                    {{ $order->status === 'confirmed' ? 'bg-indigo-100 text-indigo-800' :
                                       ($order->status === 'dispatched' ? 'bg-emerald-100 text-emerald-800' :
                                       ($order->status === 'cancelled' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800')) }}">
                                    {{ $order->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-500">
                                <i data-lucide="receipt" class="w-10 h-10 mx-auto text-slate-300 mb-2"></i>
                                <p class="font-bold text-slate-700">No Wholesale Sales Orders Found</p>
                                <p class="text-xs text-slate-400 mt-1 max-w-md mx-auto">
                                    Phase 5A has established the customer Khata and sales structure. Live invoice generation, carton dispatch, and payment recording will be enabled in Phase 5B.
                                </p>
                                <div class="mt-4">
                                    <a href="{{ route('customers.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold transition">
                                        <i data-lucide="users" class="w-4 h-4"></i>
                                        <span>Manage Customer Khata Profiles &rarr;</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($orders->hasPages())
            <div class="p-4 border-t border-slate-100 bg-slate-50/50">
                {{ $orders->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
