@extends('layouts.app')

@section('content')
<div class="space-y-8">
    <!-- Header Title -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.nav.owner_view') }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                    {{ __('erp.all_locations') }}
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">Real-time consolidated operational and financial pulse across all 5 shops and 2 factories.</p>
        </div>

        <div class="flex items-center gap-3">
            <button class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg bg-white border border-slate-300 text-slate-700 text-xs font-bold hover:bg-slate-50 transition shadow-xs">
                <i data-lucide="download" class="w-4 h-4"></i>
                <span>{{ __('erp.export') }} Excel</span>
            </button>
            <a href="{{ route('sales.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition shadow-sm shadow-indigo-600/30">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>{{ __('erp.nav.new_invoice') }}</span>
            </a>
        </div>
    </div>

    <!-- 6 Executive KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
        <!-- Today's Sales -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">{{ __('erp.kpis.todays_sales') }}</span>
                <div class="p-2 bg-indigo-50 text-indigo-600 rounded-lg">
                    <i data-lucide="trending-up" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="mt-3">
                <div class="text-xl font-extrabold text-slate-900">PKR 1,485,000</div>
                <div class="flex items-center gap-1.5 text-xs text-emerald-600 font-semibold mt-1">
                    <i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i>
                    <span>+14.2% vs yesterday</span>
                </div>
            </div>
        </div>

        <!-- Cash in Hand -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">{{ __('erp.kpis.todays_cash') }}</span>
                <div class="p-2 bg-emerald-50 text-emerald-600 rounded-lg">
                    <i data-lucide="banknote" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="mt-3">
                <div class="text-xl font-extrabold text-slate-900">PKR 642,500</div>
                <div class="text-xs text-slate-500 font-medium mt-1">Across 5 Shop Drawers</div>
            </div>
        </div>

        <!-- Customer Receivables (Khata) -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">{{ __('erp.kpis.customer_receivables') }}</span>
                <div class="p-2 bg-amber-50 text-amber-600 rounded-lg">
                    <i data-lucide="users" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="mt-3">
                <div class="text-xl font-extrabold text-amber-600">PKR 8,240,000</div>
                <div class="text-xs text-amber-700/80 font-medium mt-1">42 Wholesale Dealers</div>
            </div>
        </div>

        <!-- Supplier Payables -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">{{ __('erp.kpis.supplier_payables') }}</span>
                <div class="p-2 bg-rose-50 text-rose-600 rounded-lg">
                    <i data-lucide="credit-card" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="mt-3">
                <div class="text-xl font-extrabold text-slate-900">PKR 4,115,000</div>
                <div class="text-xs text-slate-500 font-medium mt-1">16 Raw Material Suppliers</div>
            </div>
        </div>

        <!-- Finished Stock (Cartons / Pairs) -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">{{ __('erp.kpis.finished_stock') }}</span>
                <div class="p-2 bg-blue-50 text-blue-600 rounded-lg">
                    <i data-lucide="package" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="mt-3">
                <div class="text-xl font-extrabold text-slate-900">3,480 <span class="text-xs font-bold text-slate-500">CTN</span></div>
                <div class="text-xs text-blue-600 font-semibold mt-1">41,760 Total Pairs</div>
            </div>
        </div>

        <!-- Stock in Transit -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">{{ __('erp.kpis.in_transit_stock') }}</span>
                <div class="p-2 bg-purple-50 text-purple-600 rounded-lg">
                    <i data-lucide="truck" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="mt-3">
                <div class="text-xl font-extrabold text-purple-700">420 <span class="text-xs font-bold text-purple-600">CTN</span></div>
                <div class="text-xs text-purple-600 font-medium mt-1">5 Dispatches Moving</div>
            </div>
        </div>
    </div>

    <!-- Shop-Wise Performance Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Branch & Shop Comparative Performance</h2>
                <p class="text-xs text-slate-500">Live operational standing across all 5 retail and wholesale shops in Punjab</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.locations.create', ['type' => 'shop']) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-600 hover:text-white font-bold text-xs transition border border-indigo-200">
                    <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>
                    <span>+ Add New Shop (دکان شامل کریں)</span>
                </a>
                <div class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 bg-slate-100 px-3 py-1.5 rounded-lg">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Live Feed</span>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left rtl:text-right text-sm">
                <thead class="bg-slate-50/80 text-[11px] uppercase tracking-wider text-slate-500 font-bold border-b border-slate-100">
                    <tr>
                        <th class="py-3 px-6">Branch Location</th>
                        <th class="py-3 px-6">Manager In-Charge</th>
                        <th class="py-3 px-6 text-right rtl:text-left">Today's Sales (PKR)</th>
                        <th class="py-3 px-6 text-right rtl:text-left">MTD Sales (PKR)</th>
                        <th class="py-3 px-6 text-right rtl:text-left">Customer Khata (PKR)</th>
                        <th class="py-3 px-6 text-center">Available Stock</th>
                        <th class="py-3 px-6 text-center">Cash Closing Status</th>
                        <th class="py-3 px-6 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="py-4 px-6 font-bold text-slate-900 flex items-center gap-2.5">
                            <span class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs font-black">01</span>
                            <div>
                                <a href="{{ route('dashboard.shop', ['location_id' => 1]) }}" class="leading-tight hover:text-indigo-600 transition">Shop 1 — Anarkali Bazaar</a>
                                <p class="text-[11px] font-normal text-slate-400">Lahore Central Wholesale</p>
                            </div>
                        </td>
                        <td class="py-4 px-6 text-xs text-slate-600">Tariq Mehmood</td>
                        <td class="py-4 px-6 text-right rtl:text-left font-mono font-bold text-slate-900">₨ 485,000</td>
                        <td class="py-4 px-6 text-right rtl:text-left font-mono text-slate-700">₨ 6,420,000</td>
                        <td class="py-4 px-6 text-right rtl:text-left font-mono text-amber-600 font-bold">₨ 2,890,000</td>
                        <td class="py-4 px-6 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-800">
                                980 CTN (11,760 PR)
                            </span>
                        </td>
                        <td class="py-4 px-6 text-center">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                Drawer Open
                            </span>
                        </td>
                        <td class="py-4 px-6 text-center">
                            <a href="{{ route('dashboard.shop', ['location_id' => 1]) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-600 hover:text-white font-bold text-xs transition">
                                <span>Open Shop</span>
                                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                            </a>
                        </td>
                    </tr>

                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="py-4 px-6 font-bold text-slate-900 flex items-center gap-2.5">
                            <span class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs font-black">02</span>
                            <div>
                                <a href="{{ route('dashboard.shop', ['location_id' => 2]) }}" class="leading-tight hover:text-indigo-600 transition">Shop 2 — Shah Alam Market</a>
                                <p class="text-[11px] font-normal text-slate-400">Lahore Main Shoe Market</p>
                            </div>
                        </td>
                        <td class="py-4 px-6 text-xs text-slate-600">M. Irfan Gujjar</td>
                        <td class="py-4 px-6 text-right rtl:text-left font-mono font-bold text-slate-900">₨ 540,000</td>
                        <td class="py-4 px-6 text-right rtl:text-left font-mono text-slate-700">₨ 7,810,000</td>
                        <td class="py-4 px-6 text-right rtl:text-left font-mono text-amber-600 font-bold">₨ 3,450,000</td>
                        <td class="py-4 px-6 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-800">
                                1,120 CTN (13,440 PR)
                            </span>
                        </td>
                        <td class="py-4 px-6 text-center">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                Drawer Open
                            </span>
                        </td>
                        <td class="py-4 px-6 text-center">
                            <a href="{{ route('dashboard.shop', ['location_id' => 2]) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-600 hover:text-white font-bold text-xs transition">
                                <span>Open Shop</span>
                                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                            </a>
                        </td>
                    </tr>

                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="py-4 px-6 font-bold text-slate-900 flex items-center gap-2.5">
                            <span class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs font-black">03</span>
                            <div>
                                <a href="{{ route('dashboard.shop', ['location_id' => 3]) }}" class="leading-tight hover:text-indigo-600 transition">Shop 3 — Rail Bazaar</a>
                                <p class="text-[11px] font-normal text-slate-400">Faisalabad Distribution Hub</p>
                            </div>
                        </td>
                        <td class="py-4 px-6 text-xs text-slate-600">Haji Bilal</td>
                        <td class="py-4 px-6 text-right rtl:text-left font-mono font-bold text-slate-900">₨ 290,000</td>
                        <td class="py-4 px-6 text-right rtl:text-left font-mono text-slate-700">₨ 4,150,000</td>
                        <td class="py-4 px-6 text-right rtl:text-left font-mono text-amber-600 font-bold">₨ 1,220,000</td>
                        <td class="py-4 px-6 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-800">
                                740 CTN (8,880 PR)
                            </span>
                        </td>
                        <td class="py-4 px-6 text-center">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                Verified & Closed
                            </span>
                        </td>
                        <td class="py-4 px-6 text-center">
                            <a href="{{ route('dashboard.shop', ['location_id' => 3]) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 font-bold text-xs transition shadow-xs">
                                <span>Open Shop 3</span>
                                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Factory Production & In-Transit Transfers Overview -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Factory Production Summary -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Footwear Manufacturing Factories</h3>
                    <p class="text-xs text-slate-500">Production orders & daily finishing output</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.locations.create', ['type' => 'factory']) }}" class="text-xs font-bold text-purple-600 hover:text-purple-700 flex items-center gap-1 px-2.5 py-1 rounded-md bg-purple-50 border border-purple-200 transition">
                        <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>
                        <span>+ Add Factory</span>
                    </a>
                    <a href="{{ route('dashboard.factory') }}" class="text-xs font-bold text-indigo-600 hover:text-indigo-700 flex items-center gap-1">
                        <span>Manage Factory</span>
                        <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            </div>

            <div class="mt-4 space-y-4">
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/70 hover:border-purple-300 transition">
                    <div class="flex items-center justify-between">
                        <a href="{{ route('dashboard.factory', ['location_id' => 4]) }}" class="font-bold text-sm text-slate-900 hover:text-purple-700 flex items-center gap-1.5 transition">
                            <span>Factory 1 — Sheikhupura (Leather Unit)</span>
                            <span class="text-xs text-purple-600 font-semibold">Open Unit →</span>
                        </a>
                        <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-purple-100 text-purple-800">Batch #849</span>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">Article: <strong>ART-9020 Executive Oxford</strong> (Colors: Black, Dark Brown)</p>
                    <div class="mt-3">
                        <div class="flex justify-between text-xs font-semibold text-slate-600 mb-1">
                            <span>Output Progress</span>
                            <span>650 / 800 Cartons (81%)</span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2">
                            <div class="bg-purple-600 h-2 rounded-full" style="width: 81%"></div>
                        </div>
                    </div>
                </div>

                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/70 hover:border-purple-300 transition">
                    <div class="flex items-center justify-between">
                        <a href="{{ route('dashboard.factory', ['location_id' => 5]) }}" class="font-bold text-sm text-slate-900 hover:text-purple-700 flex items-center gap-1.5 transition">
                            <span>Factory 2 — Gujranwala (PU / Casual Unit)</span>
                            <span class="text-xs text-purple-600 font-semibold">Open Unit →</span>
                        </a>
                        <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-purple-100 text-purple-800">Batch #850</span>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">Article: <strong>ART-3015 Lightweight Jogger</strong> (Sizes: 40-45)</p>
                    <div class="mt-3">
                        <div class="flex justify-between text-xs font-semibold text-slate-600 mb-1">
                            <span>Output Progress</span>
                            <span>420 / 1,000 Cartons (42%)</span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2">
                            <div class="bg-purple-600 h-2 rounded-full" style="width: 42%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- In-Transit Stock Transfers Tracker -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Dispatched Transfers In-Transit</h3>
                    <p class="text-xs text-slate-500">Live consignments between factories, warehouse, and shops</p>
                </div>
                <span class="px-2 py-1 rounded-full text-xs font-bold bg-purple-100 text-purple-800">5 Consignments Active</span>
            </div>

            <div class="mt-4 space-y-3">
                <div class="p-3.5 rounded-xl border border-slate-200 flex items-center justify-between text-xs font-medium">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-purple-50 text-purple-600 rounded-lg">
                            <i data-lucide="truck" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <p class="font-bold text-slate-900">TRF-2026-0042 (180 Cartons)</p>
                            <p class="text-slate-500">From: Factory 1 $\to$ To: Shop 1 (Anarkali)</p>
                        </div>
                    </div>
                    <div class="text-right rtl:text-left">
                        <span class="inline-block px-2 py-0.5 rounded bg-amber-100 text-amber-800 font-bold text-[11px]">On Road (Al-Madina Cargo)</span>
                        <p class="text-slate-400 font-mono text-[10px] mt-0.5">Bilty: 89410</p>
                    </div>
                </div>

                <div class="p-3.5 rounded-xl border border-slate-200 flex items-center justify-between text-xs font-medium">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-purple-50 text-purple-600 rounded-lg">
                            <i data-lucide="truck" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <p class="font-bold text-slate-900">TRF-2026-0043 (120 Cartons)</p>
                            <p class="text-slate-500">From: Central Warehouse $\to$ To: Shop 3 (Faisalabad)</p>
                        </div>
                    </div>
                    <div class="text-right rtl:text-left">
                        <span class="inline-block px-2 py-0.5 rounded bg-amber-100 text-amber-800 font-bold text-[11px]">Dispatched Today</span>
                        <p class="text-slate-400 font-mono text-[10px] mt-0.5">Bilty: 55219</p>
                    </div>
                </div>

                <div class="p-3.5 rounded-xl border border-slate-200 flex items-center justify-between text-xs font-medium">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-emerald-50 text-emerald-600 rounded-lg">
                            <i data-lucide="check-circle" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <p class="font-bold text-slate-900">TRF-2026-0041 (150 Cartons)</p>
                            <p class="text-slate-500">From: Factory 2 $\to$ To: Central Warehouse</p>
                        </div>
                    </div>
                    <div class="text-right rtl:text-left">
                        <span class="inline-block px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-bold text-[11px]">Delivered & Verified</span>
                        <p class="text-slate-400 font-mono text-[10px] mt-0.5">Verified by Store In-charge</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
