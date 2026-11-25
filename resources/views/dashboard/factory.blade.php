@extends('layouts.app')

@section('content')
<div class="space-y-8">
    <!-- Factory Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('erp.nav.factory_view') }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-800">
                    🏭 {{ $currentLocation->name ?? 'Factory 1 — Sheikhupura Road' }} ({{ $currentLocation->city ?? 'Sheikhupura' }})
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">Footwear manufacturing lines, raw materials consumption, BoM recipes, and finished shoes dispatch for <strong>{{ $currentLocation->name ?? 'Active Unit' }}</strong>.</p>
        </div>

        <div class="flex items-center gap-3">
            <button class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg bg-white border border-slate-300 text-slate-700 text-xs font-bold hover:bg-slate-50 transition shadow-xs">
                <i data-lucide="clipboard-list" class="w-4 h-4"></i>
                <span>Log Scrap / Wastage</span>
            </button>
            <button class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold transition shadow-xs">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>Create Production Order</span>
            </button>
        </div>
    </div>

    <!-- Factory Direct Switcher Tabs (Strictly Owner Only - Factory Managers cannot see other units) -->
    @if(auth()->check() && auth()->user()->isOwner() && isset($factories) && $factories->count() > 1)
    <div class="flex flex-wrap items-center gap-2 p-2.5 bg-slate-100/90 rounded-2xl border border-slate-200/80">
        <span class="text-xs font-bold text-slate-500 uppercase px-2 flex items-center gap-1.5">
            <i data-lucide="factory" class="w-3.5 h-3.5 text-purple-600"></i>
            <span>Select Factory Unit:</span>
        </span>
        @foreach($factories as $fct)
            @php $isCurrent = ($currentLocation && $currentLocation->id === $fct->id); @endphp
            <a href="{{ route('dashboard.factory', ['location_id' => $fct->id]) }}" 
               class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition {{ $isCurrent ? 'bg-purple-600 text-white shadow-sm ring-2 ring-purple-400/40' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200' }}">
                <span>🏭 {{ $fct->name }}</span>
                @if($isCurrent)
                    <span class="w-2 h-2 rounded-full bg-emerald-300 animate-pulse"></span>
                @endif
            </a>
        @endforeach
    </div>
    @endif

    <!-- Factory KPIs -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Active Batch Orders</span>
            <div class="text-2xl font-extrabold text-purple-700 mt-2">3 In Progress</div>
            <div class="text-xs text-slate-500 mt-1">Batch #848, #849, #851</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Today's Finished Output</span>
            <div class="text-2xl font-extrabold text-emerald-600 mt-2">240 Cartons</div>
            <div class="text-xs text-slate-500 mt-1">2,880 Finished Pairs boxed</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Raw Material Valuation</span>
            <div class="text-2xl font-extrabold text-slate-900 mt-2">PKR 3,850,000</div>
            <div class="text-xs text-slate-500 mt-1">Upper Leather, Soles, Adhesives</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Monthly Wastage Rate</span>
            <div class="text-2xl font-extrabold text-rose-600 mt-2">2.1%</div>
            <div class="text-xs text-slate-500 mt-1">Within standard 3.0% allowance</div>
        </div>
    </div>

    <!-- Active Production Batch Status Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Active Production Batches on Factory Floor</h2>
                <p class="text-xs text-slate-500">Track target pairs, consumed materials, and completed finished goods</p>
            </div>
            <span class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-purple-50 text-purple-700">WAC Costing Active</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left rtl:text-right text-sm">
                <thead class="bg-slate-50/80 text-[11px] uppercase tracking-wider text-slate-500 font-bold border-b border-slate-100">
                    <tr>
                        <th class="py-3 px-6">Batch #</th>
                        <th class="py-3 px-6">Target Footwear Article</th>
                        <th class="py-3 px-6 text-center">Target Output</th>
                        <th class="py-3 px-6 text-center">Completed Output</th>
                        <th class="py-3 px-6 text-right rtl:text-left">Est. Cost / Pair</th>
                        <th class="py-3 px-6 text-center">Status</th>
                        <th class="py-3 px-6 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="py-4 px-6 font-mono font-bold text-purple-700">PO-849</td>
                        <td class="py-4 px-6">
                            <p class="font-bold text-slate-900">ART-9020 (Executive Leather Oxford)</p>
                            <p class="text-xs text-slate-500">BoM Recipe: Genuine Cow Leather + PVC Sole</p>
                        </td>
                        <td class="py-4 px-6 text-center font-mono font-bold">800 CTN (9,600 PR)</td>
                        <td class="py-4 px-6 text-center">
                            <div class="font-mono font-bold text-emerald-600">650 CTN (81%)</div>
                            <div class="w-24 bg-slate-200 h-1.5 rounded-full mx-auto mt-1">
                                <div class="bg-emerald-500 h-1.5 rounded-full" style="width: 81%"></div>
                            </div>
                        </td>
                        <td class="py-4 px-6 text-right rtl:text-left font-mono font-bold text-slate-900">₨ 1,420</td>
                        <td class="py-4 px-6 text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                In Assembly & Lasting
                            </span>
                        </td>
                        <td class="py-4 px-6 text-center">
                            <button class="px-3 py-1 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 text-xs font-bold">
                                Log Output
                            </button>
                        </td>
                    </tr>

                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="py-4 px-6 font-mono font-bold text-purple-700">PO-851</td>
                        <td class="py-4 px-6">
                            <p class="font-bold text-slate-900">ART-2044 (Comfort Slip-On Loafer)</p>
                            <p class="text-xs text-slate-500">BoM Recipe: Soft Milled Leather + TPR Sole</p>
                        </td>
                        <td class="py-4 px-6 text-center font-mono font-bold">500 CTN (6,000 PR)</td>
                        <td class="py-4 px-6 text-center">
                            <div class="font-mono font-bold text-amber-600">120 CTN (24%)</div>
                            <div class="w-24 bg-slate-200 h-1.5 rounded-full mx-auto mt-1">
                                <div class="bg-amber-500 h-1.5 rounded-full" style="width: 24%"></div>
                            </div>
                        </td>
                        <td class="py-4 px-6 text-right rtl:text-left font-mono font-bold text-slate-900">₨ 1,180</td>
                        <td class="py-4 px-6 text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                Cutting & Stitching
                            </span>
                        </td>
                        <td class="py-4 px-6 text-center">
                            <button class="px-3 py-1 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 text-xs font-bold">
                                Log Output
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Raw Material Inventory Status in Factory -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs">
            <h3 class="text-base font-bold text-slate-900 pb-3 border-b border-slate-100">Factory Raw Materials Warehouse</h3>
            <div class="mt-4 space-y-3">
                <div class="p-3.5 rounded-xl border border-slate-200 flex items-center justify-between text-xs">
                    <div>
                        <p class="font-bold text-slate-900">Upper Cow Leather (Black & Tan)</p>
                        <p class="text-slate-500">Stock: <strong>4,200 Sq. Ft</strong> | Reorder Level: 1,500 Sq. Ft</p>
                    </div>
                    <span class="px-2 py-1 rounded bg-emerald-100 text-emerald-800 font-bold text-[11px]">Adequate</span>
                </div>

                <div class="p-3.5 rounded-xl border border-slate-200 flex items-center justify-between text-xs">
                    <div>
                        <p class="font-bold text-slate-900">PVC & TPR Pre-Molded Soles (Sizes 40-45)</p>
                        <p class="text-slate-500">Stock: <strong>8,900 Pairs</strong> | Reorder Level: 3,000 Pairs</p>
                    </div>
                    <span class="px-2 py-1 rounded bg-emerald-100 text-emerald-800 font-bold text-[11px]">Adequate</span>
                </div>

                <div class="p-3.5 rounded-xl border border-slate-200 flex items-center justify-between text-xs">
                    <div>
                        <p class="font-bold text-slate-900">Adhesive Glue (Dunlop / Super Bond)</p>
                        <p class="text-slate-500">Stock: <strong>140 Liters</strong> | Reorder Level: 200 Liters</p>
                    </div>
                    <span class="px-2 py-1 rounded bg-rose-100 text-rose-800 font-bold text-[11px]">Low Stock Warning</span>
                </div>
            </div>
        </div>

        <!-- Finished Footwear Dispatch to Shops / Warehouse -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h3 class="text-base font-bold text-slate-900">Dispatch Finished Goods Consignment</h3>
                <span class="text-xs text-purple-700 font-bold">New Consignment</span>
            </div>

            <form class="mt-4 space-y-4 text-xs font-semibold">
                <div>
                    <label class="block text-slate-700 uppercase tracking-wider mb-1">Destination Location</label>
                    <select class="w-full rounded-lg border-slate-300 bg-slate-50 p-2.5">
                        <option>Central Distribution Warehouse</option>
                        <option>Shop 1 — Anarkali Bazaar, Lahore</option>
                        <option>Shop 2 — Shah Alam Market, Lahore</option>
                        <option>Shop 3 — Rail Bazaar, Faisalabad</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-slate-700 uppercase tracking-wider mb-1">Footwear Article</label>
                        <select class="w-full rounded-lg border-slate-300 bg-slate-50 p-2.5">
                            <option>ART-9020 Executive Oxford (180 CTN Available)</option>
                            <option>ART-2044 Slip-On Loafer (95 CTN Available)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-700 uppercase tracking-wider mb-1">Quantity (Cartons)</label>
                        <input type="number" value="100" class="w-full rounded-lg border-slate-300 bg-slate-50 p-2.5">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-slate-700 uppercase tracking-wider mb-1">Transport / Cargo Carrier</label>
                        <input type="text" placeholder="e.g. Al-Madina Cargo" value="Al-Madina Cargo Lahore" class="w-full rounded-lg border-slate-300 bg-slate-50 p-2.5">
                    </div>
                    <div>
                        <label class="block text-slate-700 uppercase tracking-wider mb-1">Bilty Number</label>
                        <input type="text" placeholder="e.g. 98124" value="BLT-89410" class="w-full rounded-lg border-slate-300 bg-slate-50 p-2.5">
                    </div>
                </div>

                <button type="button" class="w-full py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-xs transition">
                    Dispatch Consignment (Move to In-Transit Stock)
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
