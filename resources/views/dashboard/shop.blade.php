@extends('layouts.app')

@section('content')
<div class="space-y-8">
    <!-- Header with Active Shop Badge -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.nav.shop_view') }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                    🏪 {{ $currentLocation->name ?? 'Shop 1 — Anarkali Bazaar' }}, {{ $currentLocation->city ?? 'Lahore' }}
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">Wholesale sales counter, carton dispatch, customer khata ledger, and cash drawer (Rooznamcha) for <strong>{{ $currentLocation->name ?? 'Active Shop' }}</strong>.</p>
        </div>

        <div class="flex items-center gap-3">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                <span>Drawer Status: <strong>Open</strong></span>
            </span>
            <button class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition shadow-xs">
                <i data-lucide="lock" class="w-4 h-4"></i>
                <span>Close Cash Drawer (Rooznamcha)</span>
            </button>
        </div>
    </div>

    <!-- Shop Direct Switcher Tabs (Strictly Owner Only - Branch Managers cannot see other shops) -->
    @if(auth()->check() && auth()->user()->isOwner() && isset($shops) && $shops->count() > 1)
    <div class="flex flex-wrap items-center gap-2 p-2.5 bg-slate-100/90 rounded-2xl border border-slate-200/80">
        <span class="text-xs font-bold text-slate-500 uppercase px-2 flex items-center gap-1.5">
            <i data-lucide="store" class="w-3.5 h-3.5 text-indigo-600"></i>
            <span>Select Shop Branch:</span>
        </span>
        @foreach($shops as $shp)
            @php $isCurrent = ($currentLocation && $currentLocation->id === $shp->id); @endphp
            <a href="{{ route('dashboard.shop', ['location_id' => $shp->id]) }}" 
               class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition {{ $isCurrent ? 'bg-indigo-600 text-white shadow-sm ring-2 ring-indigo-400/40' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200' }}">
                <span>🏪 {{ $shp->name }}</span>
                @if($isCurrent)
                    <span class="w-2 h-2 rounded-full bg-emerald-300 animate-pulse"></span>
                @endif
            </a>
        @endforeach
    </div>
    @endif

    <!-- Shop KPI Row -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Today's Total Counter Sales</span>
            <div class="text-2xl font-extrabold text-slate-900 mt-2">PKR 485,000</div>
            <div class="text-xs text-slate-500 mt-1">18 Wholesale Invoices Issued</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Cash in Counter Drawer</span>
            <div class="text-2xl font-extrabold text-emerald-600 mt-2">PKR 195,000</div>
            <div class="text-xs text-slate-500 mt-1">Opening Cash: PKR 25,000</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Credit Sales (Customer Khata)</span>
            <div class="text-2xl font-extrabold text-amber-600 mt-2">PKR 290,000</div>
            <div class="text-xs text-slate-500 mt-1">Udhar to 7 regular parties</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Shop Floor Stock</span>
            <div class="text-2xl font-extrabold text-indigo-600 mt-2">980 Cartons</div>
            <div class="text-xs text-slate-500 mt-1">11,760 Total Pairs on Shelves</div>
        </div>
    </div>

    <!-- Interactive Fast POS / Wholesale Invoicing Form -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="p-6 bg-slate-900 text-white flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-indigo-500/20 rounded-xl text-indigo-300">
                    <i data-lucide="receipt" class="w-5 h-5"></i>
                </div>
                <div>
                    <h2 class="text-base font-bold">Fast Wholesale Billing & Invoice Creation</h2>
                    <p class="text-xs text-slate-400">Carton packaging conversion with automatic Khata update & Bilty details</p>
                </div>
            </div>
            <span class="font-mono text-xs text-amber-400 font-bold">INVOICE: INV-SH01-2026-00128</span>
        </div>

        <form class="p-6 space-y-6">
            <!-- Row 1: Customer Selection & Transport Details -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Wholesale Customer (Party)</label>
                    <select class="w-full text-sm font-semibold rounded-lg border-slate-300 bg-slate-50 p-2.5 focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                        <option>Al-Rehman Footwear Gujranwala (Khata: ₨ 185,000)</option>
                        <option>Bismillah Shoes Multan (Khata: ₨ 340,000)</option>
                        <option>Kashmir Boot House Rawalpindi (Khata: ₨ 95,000)</option>
                        <option>Walk-In Wholesale Client (Cash Only)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">{{ __('erp.bilty_no') }}</label>
                    <input type="text" placeholder="e.g. 78491-BLT" value="BLT-99214" class="w-full text-sm font-semibold rounded-lg border-slate-300 bg-slate-50 p-2.5 focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">{{ __('erp.transport') }}</label>
                    <input type="text" placeholder="e.g. Faisal Goods Transport" value="New Subhan Goods Adda" class="w-full text-sm font-semibold rounded-lg border-slate-300 bg-slate-50 p-2.5 focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">{{ __('erp.payment_mode') }}</label>
                    <select class="w-full text-sm font-semibold rounded-lg border-slate-300 bg-slate-50 p-2.5 focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                        <option>{{ __('erp.credit') }} — Full Khata</option>
                        <option>{{ __('erp.cash') }} — Counter Cash</option>
                        <option>{{ __('erp.bank') }} — Online Transfer</option>
                        <option>Split (Partial Cash + Balance Khata)</option>
                    </select>
                </div>
            </div>

            <!-- Row 2: Shoe Line Item Table -->
            <div class="border border-slate-200 rounded-xl overflow-hidden">
                <table class="w-full text-left rtl:text-right text-xs">
                    <thead class="bg-slate-100 text-slate-600 font-bold uppercase tracking-wider">
                        <tr>
                            <th class="p-3">Article / Shoe Model</th>
                            <th class="p-3">Size Assortment</th>
                            <th class="p-3">Color</th>
                            <th class="p-3">Packaging Unit</th>
                            <th class="p-3 text-center">Cartons</th>
                            <th class="p-3 text-center">Pairs (Total)</th>
                            <th class="p-3 text-right rtl:text-left">Price / Pair (PKR)</th>
                            <th class="p-3 text-right rtl:text-left">Subtotal (PKR)</th>
                            <th class="p-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 font-medium">
                        <tr>
                            <td class="p-3 font-bold text-slate-900">ART-9020 (Executive Leather Oxford)</td>
                            <td class="p-3 text-slate-600 font-mono">Assorted (40-45) [1-2-3-3-2-1]</td>
                            <td class="p-3"><span class="px-2 py-0.5 rounded bg-slate-200 text-slate-800 font-bold">Black</span></td>
                            <td class="p-3">Carton (12 Pairs)</td>
                            <td class="p-3 text-center font-bold font-mono">15</td>
                            <td class="p-3 text-center font-mono text-indigo-600 font-bold">180 PR</td>
                            <td class="p-3 text-right rtl:text-left font-mono">₨ 2,200</td>
                            <td class="p-3 text-right rtl:text-left font-mono font-bold text-slate-900">₨ 396,000</td>
                            <td class="p-3 text-center">
                                <button type="button" class="text-rose-500 hover:text-rose-700"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                            </td>
                        </tr>
                        <tr>
                            <td class="p-3 font-bold text-slate-900">ART-2044 (Comfort Slip-On Loafer)</td>
                            <td class="p-3 text-slate-600 font-mono">Assorted (40-44) [2-3-4-2-1]</td>
                            <td class="p-3"><span class="px-2 py-0.5 rounded bg-amber-100 text-amber-900 font-bold">Dark Tan</span></td>
                            <td class="p-3">Carton (12 Pairs)</td>
                            <td class="p-3 text-center font-bold font-mono">10</td>
                            <td class="p-3 text-center font-mono text-indigo-600 font-bold">120 PR</td>
                            <td class="p-3 text-right rtl:text-left font-mono">₨ 1,850</td>
                            <td class="p-3 text-right rtl:text-left font-mono font-bold text-slate-900">₨ 222,000</td>
                            <td class="p-3 text-center">
                                <button type="button" class="text-rose-500 hover:text-rose-700"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer Invoice Calculation -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-slate-200">
                <div class="text-xs text-slate-500 space-y-1">
                    <p>Total Cartons: <strong class="text-slate-800">25 Cartons</strong> | Total Footwear: <strong class="text-slate-800">300 Pairs</strong></p>
                    <p class="text-emerald-700 font-semibold">Stock Invariant: All 300 pairs are in-stock and reserved for this invoice.</p>
                </div>

                <div class="flex items-center gap-6">
                    <div class="text-right rtl:text-left">
                        <span class="text-xs text-slate-500 block">Grand Total Amount</span>
                        <span class="text-2xl font-black text-slate-900 font-mono">PKR 618,000</span>
                    </div>

                    <button type="button" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm shadow-md shadow-indigo-600/30 transition">
                        <i data-lucide="printer" class="w-4 h-4"></i>
                        <span>Save & Print Invoice</span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Incoming Consignments / In-Transit to this Shop -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div>
                <h3 class="text-base font-bold text-slate-900">Incoming Stock Consignments (In-Transit)</h3>
                <p class="text-xs text-slate-500">Verify and receive consignments arriving from Factory 1 or Central Warehouse</p>
            </div>
            <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800">1 Consignment Awaiting Receipt</span>
        </div>

        <div class="mt-4 p-4 rounded-xl border border-amber-200 bg-amber-50/40 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="font-bold text-slate-900 text-sm">TRF-2026-0042</span>
                    <span class="text-xs px-2 py-0.5 rounded bg-purple-100 text-purple-800 font-bold">180 Cartons (2,160 Pairs)</span>
                </div>
                <p class="text-xs text-slate-600">Dispatched from: <strong>Factory 1 (Sheikhupura)</strong> via <strong>Al-Madina Cargo (Bilty #89410)</strong></p>
            </div>

            <div class="flex items-center gap-3">
                <button class="px-3.5 py-2 rounded-lg bg-white border border-slate-300 text-slate-700 font-bold text-xs hover:bg-slate-50 transition">
                    View Manifest
                </button>
                <button class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs transition">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    <span>Confirm & Receive into Stock</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
