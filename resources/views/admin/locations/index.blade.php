@extends('layouts.app')

@section('title', __('erp.locations_management.title'))

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                    <i data-lucide="building-2" class="w-5 h-5"></i>
                </div>
                <span>{{ __('erp.locations_management.title') }}</span>
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('erp.locations_management.subtitle') }}
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('admin.locations.create', ['type' => 'shop']) }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>+ {{ __('erp.locations_management.shop') }}</span>
            </a>
            <a href="{{ route('admin.locations.create', ['type' => 'factory']) }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold shadow-sm transition">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>+ {{ __('erp.locations_management.factory') }}</span>
            </a>
            <a href="{{ route('admin.locations.create', ['type' => 'warehouse']) }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold shadow-sm transition">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>+ {{ __('erp.locations_management.warehouse') }}</span>
            </a>
        </div>
    </div>

    <!-- Quick Stat Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">All Locations</span>
            <div class="text-2xl font-black text-slate-900 mt-1">{{ $stats['total'] }}</div>
            <span class="text-[11px] text-emerald-600 font-semibold mt-0.5 block">{{ $stats['active'] }} Active Branches</span>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-emerald-600 uppercase tracking-wider block">Shops / Counters</span>
                <i data-lucide="store" class="w-4 h-4 text-emerald-500"></i>
            </div>
            <div class="text-2xl font-black text-slate-900 mt-1">{{ $stats['shops'] }}</div>
            <span class="text-[11px] text-slate-400 font-medium mt-0.5 block">Wholesale & Retail</span>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-purple-600 uppercase tracking-wider block">Factory Units</span>
                <i data-lucide="factory" class="w-4 h-4 text-purple-500"></i>
            </div>
            <div class="text-2xl font-black text-slate-900 mt-1">{{ $stats['factories'] }}</div>
            <span class="text-[11px] text-slate-400 font-medium mt-0.5 block">Manufacturing Plants</span>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-amber-600 uppercase tracking-wider block">Warehouses</span>
                <i data-lucide="boxes" class="w-4 h-4 text-amber-500"></i>
            </div>
            <div class="text-2xl font-black text-slate-900 mt-1">{{ $stats['warehouses'] }}</div>
            <span class="text-[11px] text-slate-400 font-medium mt-0.5 block">Distribution Centers</span>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-xs">
        <form method="GET" action="{{ route('admin.locations.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Search Location</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name, code, city, phone..."
                       class="w-full text-xs rounded-lg border-slate-300 py-2 px-3 focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Filter by Type</label>
                <select name="type" class="w-full text-xs rounded-lg border-slate-300 py-2 px-3 focus:ring-2 focus:ring-indigo-500">
                    <option value="">All Types (شاپس، فیکٹریز، گودام)</option>
                    <option value="shop" {{ request('type') == 'shop' ? 'selected' : '' }}>🏪 Shops Only</option>
                    <option value="factory" {{ request('type') == 'factory' ? 'selected' : '' }}>🏭 Factories Only</option>
                    <option value="warehouse" {{ request('type') == 'warehouse' ? 'selected' : '' }}>📦 Warehouses Only</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">Operational Status</label>
                <select name="status" class="w-full text-xs rounded-lg border-slate-300 py-2 px-3 focus:ring-2 focus:ring-indigo-500">
                    <option value="">All Statuses</option>
                    <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active Only</option>
                    <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive Only</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg py-2 px-3 text-xs font-bold flex items-center justify-center gap-1.5 transition">
                    <i data-lucide="filter" class="w-3.5 h-3.5"></i>
                    <span>Apply Filter</span>
                </button>
                <a href="{{ route('admin.locations.index') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg py-2 px-3 text-xs font-semibold flex items-center justify-center transition">
                    <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Locations Table -->
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left rtl:text-right text-xs">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider">
                    <tr>
                        <th class="p-4">Location Code & Name</th>
                        <th class="p-4">Type</th>
                        <th class="p-4">City & Address</th>
                        <th class="p-4">Phone / Contact</th>
                        <th class="p-4 text-center">Assigned Staff</th>
                        <th class="p-4 text-center">Status</th>
                        <th class="p-4 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                    @forelse($locations as $loc)
                    <tr class="hover:bg-slate-50/70 transition">
                        <td class="p-4">
                            <div class="flex items-center gap-3">
                                @php
                                    $iconBg = match($loc->type) {
                                        'shop' => 'bg-emerald-50 text-emerald-600 border-emerald-200',
                                        'factory' => 'bg-purple-50 text-purple-600 border-purple-200',
                                        'warehouse' => 'bg-amber-50 text-amber-600 border-amber-200',
                                        default => 'bg-slate-50 text-slate-600 border-slate-200',
                                    };
                                    $iconName = match($loc->type) {
                                        'shop' => 'store',
                                        'factory' => 'factory',
                                        'warehouse' => 'boxes',
                                        default => 'map-pin',
                                    };
                                @endphp
                                <div class="w-9 h-9 rounded-xl flex items-center justify-center border {{ $iconBg }} shrink-0">
                                    <i data-lucide="{{ $iconName }}" class="w-4 h-4"></i>
                                </div>
                                <div>
                                    <div class="font-bold text-slate-900 text-sm">{{ $loc->name }}</div>
                                    <span class="font-mono text-[11px] text-slate-400 uppercase tracking-wide">{{ $loc->code }}</span>
                                </div>
                            </div>
                        </td>

                        <td class="p-4">
                            @php
                                $badgeStyle = match($loc->type) {
                                    'shop' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    'factory' => 'bg-purple-50 text-purple-700 border-purple-200',
                                    'warehouse' => 'bg-amber-50 text-amber-700 border-amber-200',
                                    default => 'bg-slate-100 text-slate-700 border-slate-200',
                                };
                                $typeLabel = match($loc->type) {
                                    'shop' => '🏪 Shop (دکان)',
                                    'factory' => '🏭 Factory (فیکٹری)',
                                    'warehouse' => '📦 Warehouse (گودام)',
                                    default => ucfirst($loc->type),
                                };
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold border {{ $badgeStyle }}">
                                {{ $typeLabel }}
                            </span>
                        </td>

                        <td class="p-4">
                            <div class="font-semibold text-slate-900">{{ $loc->city ?: '—' }}</div>
                            <div class="text-[11px] text-slate-500 max-w-xs truncate">{{ $loc->address ?: 'No address specified' }}</div>
                        </td>

                        <td class="p-4">
                            <span class="font-mono font-medium text-slate-700">{{ $loc->phone ?: '—' }}</span>
                        </td>

                        <td class="p-4 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700">
                                {{ $loc->users_count }} Staff
                            </span>
                        </td>

                        <td class="p-4 text-center">
                            @if($loc->is_active)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    <span>Active</span>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-500 border border-slate-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                    <span>Inactive</span>
                                </span>
                            @endif
                        </td>

                        <td class="p-4 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                @php
                                    $openUrl = match($loc->type) {
                                        'shop' => route('dashboard.shop', ['location_id' => $loc->id]),
                                        'factory' => route('dashboard.factory', ['location_id' => $loc->id]),
                                        'warehouse' => route('inventory.index', ['location_id' => $loc->id]),
                                        default => route('dashboard.owner', ['location_id' => $loc->id]),
                                    };
                                @endphp
                                <a href="{{ $openUrl }}" 
                                   title="Open Dashboard"
                                   class="p-2 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-600 hover:text-white transition">
                                    <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                                </a>

                                <a href="{{ route('admin.locations.edit', $loc) }}" 
                                   title="Edit Location"
                                   class="p-2 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 hover:text-slate-900 transition">
                                    <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                                </a>

                                <form method="POST" action="{{ route('admin.locations.toggle-active', $loc) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" 
                                            title="{{ $loc->is_active ? 'Deactivate Location' : 'Activate Location' }}"
                                            onclick="return confirm('Are you sure you want to change the status of this location?')"
                                            class="p-2 rounded-lg {{ $loc->is_active ? 'bg-amber-50 text-amber-700 hover:bg-amber-600 hover:text-white' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white' }} transition">
                                        <i data-lucide="{{ $loc->is_active ? 'power' : 'check' }}" class="w-3.5 h-3.5"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="p-8 text-center text-slate-400">
                            <i data-lucide="building" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                            <p>No locations found matching your filter criteria.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($locations->hasPages())
        <div class="p-4 border-t border-slate-200">
            {{ $locations->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
