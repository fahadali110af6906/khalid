<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ur' ? 'rtl' : 'ltr' }}" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Khalid Traders') }} — {{ __('erp.app_title') }}</title>

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Noto+Nastaliq+Urdu:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    @vite(['resources/css/app.css'])

    <style>
        [dir="rtl"] {
            font-family: 'Noto Nastaliq Urdu', Tahoma, Arial, sans-serif;
        }
        [dir="ltr"] {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
        }
    </style>
    @livewireStyles
</head>
<body class="h-full text-slate-800 antialiased font-normal selection:bg-indigo-600 selection:text-white" x-data="{ mobileOpen: false }">
    <div class="min-h-full flex flex-col">

        @php
            $user = auth()->user();
            $isOwner = $user && $user->isOwner();

            // Strict Location Isolation: Non-owners ONLY get their explicitly assigned locations!
            if ($isOwner) {
                $navLocations = \App\Models\Location::where('is_active', true)
                    ->where('type', '!=', 'transit')
                    ->orderBy('type')
                    ->orderBy('id')
                    ->get();
            } else {
                $navLocations = $user ? $user->locations()->where('is_active', true)->where('type', '!=', 'transit')->orderBy('id')->get() : collect();
                if ($navLocations->isEmpty() && $user && $user->defaultLocation) {
                    $navLocations = collect([$user->defaultLocation]);
                }
            }

            $rawLocParam = request('location_id') ?? request('location');
            $currentLocId = ($rawLocParam instanceof \App\Models\Location) ? $rawLocParam->id : $rawLocParam;

            // Enforce currentLocId for non-owners: cannot be 'all' or an unassigned location
            if (!$isOwner && $user) {
                $userLocIds = $navLocations->pluck('id')->all();
                if (!$currentLocId || $currentLocId === 'all' || !in_array((int)$currentLocId, $userLocIds)) {
                    $currentLocId = $user->default_location_id ?? ($userLocIds[0] ?? null);
                }
            }

            // User home dashboard URL based on role
            $userHomeUrl = route('dashboard.owner');
            if ($user && !$isOwner) {
                if ($user->hasRole('factory_manager')) {
                    $userHomeUrl = route('dashboard.factory', array_filter(['location_id' => $currentLocId]));
                } elseif ($user->hasRole(['shop_manager', 'shop_cashier'])) {
                    $userHomeUrl = route('dashboard.shop', array_filter(['location_id' => $currentLocId]));
                } elseif ($user->hasRole('warehouse_manager')) {
                    $userHomeUrl = route('inventory.index', array_filter(['location_id' => $currentLocId]));
                }
            }
        @endphp

        <!-- Top Header Navigation (Full Width Front Layout) -->
        <header class="bg-slate-950 text-white shadow-xl sticky top-0 z-40 border-b border-slate-800">
            <!-- Top Utility & Brand Bar -->
            <div class="px-4 sm:px-6 lg:px-8 border-b border-slate-800/80">
                <div class="h-16 flex items-center justify-between gap-4">
                    
                    <!-- Left: Brand Logo & Title -->
                    <div class="flex items-center gap-4">
                        <a href="{{ $userHomeUrl }}" class="flex items-center gap-3 group">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-amber-500 to-indigo-600 flex items-center justify-center text-white font-black shadow-md shadow-indigo-900/40 text-lg group-hover:scale-105 transition-transform">
                                KT
                            </div>
                            <div class="leading-tight">
                                <h1 class="text-base sm:text-lg font-bold text-white tracking-tight flex items-center gap-2">
                                    {{ __('erp.brand_name') }}
                                    <span class="hidden md:inline-block text-[10px] bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 px-2 py-0.5 rounded-full font-mono font-normal">v1.0</span>
                                </h1>
                                <p class="text-[11px] text-amber-400 font-medium tracking-wide">Footwear ERP & Factory</p>
                            </div>
                        </a>

                        <!-- Location Context: Strict Isolation -->
                        @if($isOwner)
                            <!-- Owner Switcher: Consolidated + All Branches -->
                            <div class="hidden xl:flex items-center gap-2 ms-4 ps-4 border-s border-slate-800 text-xs">
                                <span class="text-slate-400 font-medium uppercase tracking-wider">{{ __('erp.location') }}:</span>
                                <div class="relative">
                                    <select onchange="handleLocationContextSwitch(this)" 
                                            class="bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-200 text-xs font-semibold rounded-lg px-2.5 py-1.5 pe-8 focus:ring-2 focus:ring-indigo-500 focus:outline-none transition cursor-pointer">
                                        <option value="all" data-type="all" data-url="{{ route('dashboard.owner') }}" {{ ($currentLocId === 'all' || !$currentLocId) ? 'selected' : '' }}>
                                            🏢 {{ __('erp.all_locations') }}
                                        </option>
                                        @foreach($navLocations as $loc)
                                            @php
                                                $icon = match($loc->type) {
                                                    'shop' => '🏪',
                                                    'factory' => '🏭',
                                                    'warehouse' => '📦',
                                                    default => '📍',
                                                };
                                                $targetUrl = match($loc->type) {
                                                    'shop' => route('dashboard.shop', ['location_id' => $loc->id]),
                                                    'factory' => route('dashboard.factory', ['location_id' => $loc->id]),
                                                    'warehouse' => route('inventory.index', ['location_id' => $loc->id]),
                                                    default => route('dashboard.owner', ['location_id' => $loc->id]),
                                                };
                                                $isSelected = ($currentLocId && ($currentLocId == $loc->id || $currentLocId === $loc->code || (isset($currentLocation) && $currentLocation instanceof \App\Models\Location && $currentLocation->id == $loc->id)));
                                            @endphp
                                            <option value="{{ $loc->id }}" 
                                                    data-type="{{ $loc->type }}" 
                                                    data-url="{{ $targetUrl }}" 
                                                    {{ $isSelected ? 'selected' : '' }}>
                                                {{ $icon }} {{ $loc->name }}
                                            </option>
                                        @endforeach
                                        <option value="add_new_loc" data-type="action" data-url="{{ route('admin.locations.create') }}" class="font-bold text-indigo-400 bg-slate-800">
                                            ➕ {{ __('erp.locations_management.add_location') }}
                                        </option>
                                        <option value="view_all_locs" data-type="action" data-url="{{ route('admin.locations.index') }}" class="font-semibold text-slate-300 bg-slate-800">
                                            ⚙️ Manage All Locations...
                                        </option>
                                    </select>
                                </div>
                            </div>
                        @elseif($navLocations->isNotEmpty())
                            <!-- Non-Owner Isolation: Shows ONLY their assigned location (No dropdown, No other branches) -->
                            <div class="hidden xl:flex items-center gap-2 ms-4 ps-4 border-s border-slate-800 text-xs">
                                <span class="text-slate-400 font-medium uppercase tracking-wider">{{ __('erp.location') }}:</span>
                                @if($navLocations->count() === 1)
                                    @php 
                                        $myLoc = $navLocations->first();
                                        $locIcon = match($myLoc->type) {
                                            'shop' => '🏪',
                                            'factory' => '🏭',
                                            'warehouse' => '📦',
                                            default => '📍',
                                        };
                                    @endphp
                                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-700/80 text-emerald-400 font-bold text-xs shadow-xs">
                                        <span>{{ $locIcon }}</span>
                                        <span>{{ $myLoc->name }}</span>
                                    </div>
                                @else
                                    <!-- Multi-assigned non-owner: ONLY their specific assigned branches -->
                                    <div class="relative">
                                        <select onchange="handleLocationContextSwitch(this)" 
                                                class="bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-200 text-xs font-semibold rounded-lg px-2.5 py-1.5 pe-8 focus:ring-2 focus:ring-indigo-500 focus:outline-none transition cursor-pointer">
                                            @foreach($navLocations as $loc)
                                                @php
                                                    $icon = match($loc->type) {
                                                        'shop' => '🏪',
                                                        'factory' => '🏭',
                                                        'warehouse' => '📦',
                                                        default => '📍',
                                                    };
                                                    $targetUrl = match($loc->type) {
                                                        'shop' => route('dashboard.shop', ['location_id' => $loc->id]),
                                                        'factory' => route('dashboard.factory', ['location_id' => $loc->id]),
                                                        'warehouse' => route('inventory.index', ['location_id' => $loc->id]),
                                                        default => url()->current(),
                                                    };
                                                    $isSelected = ($currentLocId == $loc->id || (isset($currentLocation) && $currentLocation instanceof \App\Models\Location && $currentLocation->id == $loc->id));
                                                @endphp
                                                <option value="{{ $loc->id }}" 
                                                        data-type="{{ $loc->type }}" 
                                                        data-url="{{ $targetUrl }}" 
                                                        {{ $isSelected ? 'selected' : '' }}>
                                                    {{ $icon }} {{ $loc->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>

                    <!-- Right Controls: Date, Language, User Profile & Mobile Toggle -->
                    <div class="flex items-center gap-3">
                        <!-- Date & Currency -->
                        <div class="hidden lg:flex flex-col text-right rtl:text-left text-[11px] leading-tight pe-3 border-e border-slate-800">
                            <span class="font-bold text-slate-200">{{ now()->translatedFormat('l, d F Y') }}</span>
                            <span class="text-slate-400 font-mono">Currency: PKR (₨)</span>
                        </div>

                        <!-- Language Switcher -->
                        <div class="inline-flex rounded-lg border border-slate-800 p-0.5 bg-slate-900 text-xs font-bold">
                            <a href="{{ route('locale.switch', 'en') }}" 
                               class="px-2.5 py-1 rounded-md transition-colors {{ app()->getLocale() == 'en' ? 'bg-indigo-600 shadow-xs text-white' : 'text-slate-400 hover:text-white' }}">
                                EN
                            </a>
                            <a href="{{ route('locale.switch', 'ur') }}" 
                               class="px-2.5 py-1 rounded-md transition-colors {{ app()->getLocale() == 'ur' ? 'bg-indigo-600 shadow-xs text-white' : 'text-slate-400 hover:text-white' }}">
                                اردو
                            </a>
                        </div>

                        <!-- Authenticated User Profile & Logout -->
                        <div class="relative" x-data="{ userMenu: false }" @click.outside="userMenu = false">
                            <button @click="userMenu = !userMenu" class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-slate-800/80 transition-colors">
                                <div class="w-8 h-8 rounded-full bg-indigo-600/30 border border-indigo-400/30 flex items-center justify-center font-bold text-indigo-300 text-xs">
                                    {{ substr(auth()->user()->name ?? 'KT', 0, 2) }}
                                </div>
                                <div class="hidden sm:flex flex-col text-start rtl:text-end text-xs leading-tight">
                                    <span class="font-bold text-slate-200">{{ auth()->user()->name ?? 'Khalid Malik (Owner)' }}</span>
                                    <span class="text-[10px] text-emerald-400 flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                        {{ auth()->user()->isOwner() ? 'Super Admin' : (auth()->user()->roles->first()?->display_name ?? 'User') }}
                                    </span>
                                </div>
                                <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-slate-400"></i>
                            </button>

                            <!-- User Dropdown Menu -->
                            <div x-show="userMenu" 
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="opacity-100 scale-100"
                                 x-transition:leave-end="opacity-0 scale-95"
                                 class="absolute end-0 mt-2 w-56 bg-slate-900 border border-slate-800 rounded-xl shadow-2xl py-2 z-50 text-xs">
                                <div class="px-4 py-2 border-b border-slate-800">
                                    <p class="font-bold text-white">{{ auth()->user()->name ?? 'Khalid Malik' }}</p>
                                    <p class="text-slate-400 truncate">{{ auth()->user()->email ?? 'khalid.owner@khalidtraders.pk' }}</p>
                                </div>
                                @if(auth()->check() && auth()->user()->isOwner())
                                <a href="{{ route('admin.settings.index') }}" class="flex items-center gap-2.5 px-4 py-2 hover:bg-slate-800 text-slate-300 hover:text-white">
                                    <i data-lucide="settings" class="w-4 h-4 text-slate-400"></i>
                                    <span>{{ __('erp.settings.title') }}</span>
                                </a>
                                <a href="{{ route('admin.users.index') }}" class="flex items-center gap-2.5 px-4 py-2 hover:bg-slate-800 text-slate-300 hover:text-white">
                                    <i data-lucide="users" class="w-4 h-4 text-slate-400"></i>
                                    <span>{{ __('erp.users.title') }}</span>
                                </a>
                                <a href="{{ route('admin.locations.index') }}" class="flex items-center gap-2.5 px-4 py-2 hover:bg-slate-800 text-slate-300 hover:text-white">
                                    <i data-lucide="building-2" class="w-4 h-4 text-slate-400"></i>
                                    <span>{{ __('erp.locations_management.title') }}</span>
                                </a>
                                @endif
                                <div class="border-t border-slate-800 my-1"></div>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="w-full flex items-center gap-2.5 px-4 py-2 hover:bg-rose-950/40 text-rose-400 hover:text-rose-300 transition-colors">
                                        <i data-lucide="log-out" class="w-4 h-4"></i>
                                        <span>Logout</span>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Mobile Hamburger Button -->
                        <button @click="mobileOpen = !mobileOpen" class="xl:hidden p-2 rounded-lg bg-slate-900 border border-slate-800 text-slate-300 hover:text-white hover:bg-slate-800">
                            <i data-lucide="menu" class="w-5 h-5" x-show="!mobileOpen"></i>
                            <i data-lucide="x" class="w-5 h-5" x-show="mobileOpen" style="display: none;"></i>
                        </button>
                    </div>

                </div>
            </div>

            <!-- Horizontal Module Navigation Bar (Desktop) -->
            <nav class="hidden xl:block px-4 sm:px-6 lg:px-8 bg-slate-900/95 border-b border-slate-800/80">
                <div class="flex items-center gap-1 py-1 text-xs font-semibold">

                    <!-- 1. DASHBOARDS (ڈیش بورڈ) -->
                    @if($isOwner)
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = !open" 
                                class="flex items-center gap-2 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('dashboard.*') ? 'bg-indigo-600 text-white shadow-sm' : 'hover:bg-slate-800/90 text-slate-200' }}">
                            <i data-lucide="layout-dashboard" class="w-4 h-4 text-amber-400"></i>
                            <span>{{ __('erp.nav.dashboard') }}</span>
                            <i data-lucide="chevron-down" class="w-3.5 h-3.5 opacity-70 transition-transform duration-200" :class="{ 'rotate-180': open }"></i>
                        </button>

                        <div x-show="open" 
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 translate-y-1"
                             style="display: none;"
                             class="absolute top-full start-0 mt-1 w-64 bg-slate-900 border border-slate-800 rounded-xl shadow-2xl py-2 z-50">
                            <div class="px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-800">
                                Dashboards & Live Feeds
                            </div>
                            <a href="{{ route('dashboard.owner') }}" 
                               class="flex items-center justify-between px-3.5 py-2 text-xs hover:bg-slate-800 text-slate-200 hover:text-white {{ request()->routeIs('dashboard.owner') ? 'bg-indigo-600/30 text-indigo-300 font-bold' : '' }}">
                                <span class="flex items-center gap-2">
                                    <i data-lucide="building-2" class="w-3.5 h-3.5 text-amber-400"></i>
                                    <span>{{ __('erp.nav.owner_view') }}</span>
                                </span>
                                <span class="text-[10px] bg-amber-500/20 text-amber-300 border border-amber-500/30 px-1.5 py-0.5 rounded font-mono">HQ</span>
                            </a>
                            <a href="{{ route('dashboard.shop') }}" 
                               class="flex items-center justify-between px-3.5 py-2 text-xs hover:bg-slate-800 text-slate-200 hover:text-white {{ request()->routeIs('dashboard.shop') ? 'bg-indigo-600/30 text-indigo-300 font-bold' : '' }}">
                                <span class="flex items-center gap-2">
                                    <i data-lucide="store" class="w-3.5 h-3.5 text-emerald-400"></i>
                                    <span>{{ __('erp.nav.shop_view') }}</span>
                                </span>
                                <span class="text-[10px] bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 px-1.5 py-0.5 rounded font-mono">Shops</span>
                            </a>
                            <a href="{{ route('dashboard.factory') }}" 
                               class="flex items-center justify-between px-3.5 py-2 text-xs hover:bg-slate-800 text-slate-200 hover:text-white {{ request()->routeIs('dashboard.factory') ? 'bg-indigo-600/30 text-indigo-300 font-bold' : '' }}">
                                <span class="flex items-center gap-2">
                                    <i data-lucide="factory" class="w-3.5 h-3.5 text-purple-400"></i>
                                    <span>{{ __('erp.nav.factory_view') }}</span>
                                </span>
                                <span class="text-[10px] bg-purple-500/20 text-purple-300 border border-purple-500/30 px-1.5 py-0.5 rounded font-mono">Factory</span>
                            </a>

                            <div class="px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 border-t border-slate-800 mt-1 pt-1.5">
                                Direct Shop & Factory Links
                            </div>
                            @foreach($navLocations as $nl)
                                @php
                                    $nUrl = match($nl->type) {
                                        'shop' => route('dashboard.shop', ['location_id' => $nl->id]),
                                        'factory' => route('dashboard.factory', ['location_id' => $nl->id]),
                                        'warehouse' => route('inventory.index', ['location_id' => $nl->id]),
                                        default => route('dashboard.owner'),
                                    };
                                    $nIcon = match($nl->type) {
                                        'shop' => 'store',
                                        'factory' => 'factory',
                                        'warehouse' => 'boxes',
                                        default => 'map-pin',
                                    };
                                    $nColor = match($nl->type) {
                                        'shop' => 'text-emerald-400',
                                        'factory' => 'text-purple-400',
                                        'warehouse' => 'text-amber-400',
                                        default => 'text-slate-400',
                                    };
                                @endphp
                                <a href="{{ $nUrl }}" class="flex items-center justify-between px-3.5 py-1.5 text-xs hover:bg-slate-800 text-slate-300 hover:text-white">
                                    <span class="flex items-center gap-2 truncate">
                                        <i data-lucide="{{ $nIcon }}" class="w-3.5 h-3.5 {{ $nColor }}"></i>
                                        <span class="truncate">{{ $nl->name }}</span>
                                    </span>
                                    <span class="text-[9px] uppercase px-1 py-0.2 rounded bg-slate-800 text-slate-400 font-mono">{{ $nl->type }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                    @else
                    <!-- Non-Owner Direct Dashboard: Only their own shop/factory (No dropdown, No other branches) -->
                    @php
                        $dashIcon = ($user && $user->hasRole('factory_manager')) ? 'factory' : (($user && $user->hasRole('warehouse_manager')) ? 'boxes' : 'store');
                        $dashColor = ($user && $user->hasRole('factory_manager')) ? 'text-purple-400' : 'text-emerald-400';
                    @endphp
                    <a href="{{ $userHomeUrl }}" 
                       class="flex items-center gap-2 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('dashboard.*') ? 'bg-indigo-600 text-white shadow-sm' : 'hover:bg-slate-800/90 text-slate-200' }}">
                        <i data-lucide="{{ $dashIcon }}" class="w-4 h-4 {{ $dashColor }}"></i>
                        <span>{{ __('erp.nav.dashboard') }}</span>
                    </a>
                    @endif

                    <!-- 2. INVENTORY (اسٹاک) -->
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = !open" 
                                class="flex items-center gap-2 px-3 py-2 rounded-lg transition-colors {{ (request()->routeIs('inventory.*') || request()->routeIs('products.*')) ? 'bg-indigo-600 text-white shadow-sm' : 'hover:bg-slate-800/90 text-slate-200' }}">
                            <i data-lucide="boxes" class="w-4 h-4 text-cyan-400"></i>
                            <span>{{ __('erp.nav.inventory') }}</span>
                            <i data-lucide="chevron-down" class="w-3.5 h-3.5 opacity-70 transition-transform duration-200" :class="{ 'rotate-180': open }"></i>
                        </button>

                        <div x-show="open" 
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 translate-y-1"
                             style="display: none;"
                             class="absolute top-full start-0 mt-1 w-64 bg-slate-900 border border-slate-800 rounded-xl shadow-2xl py-2 z-50">
                            <a href="{{ route('inventory.index') }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="package" class="w-3.5 h-3.5 text-cyan-400"></i>
                                <span>{{ __('erp.nav.articles') }} & {{ __('erp.nav.variants') }}</span>
                            </a>
                            <a href="{{ route('inventory.transfers.index') }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="arrow-left-right" class="w-3.5 h-3.5 text-cyan-400"></i>
                                <span>{{ __('erp.nav.stock_transfers') }}</span>
                            </a>
                            <a href="{{ route('inventory.adjustments') }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="scale" class="w-3.5 h-3.5 text-cyan-400"></i>
                                <span>Stock Adjustments / Counts</span>
                            </a>
                        </div>
                    </div>

                    <!-- 3. COMMERCIAL & SALES (سیلز اور کھاتہ) -->
                    @if($isOwner || ($user && $user->hasRole(['shop_manager', 'shop_cashier'])))
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = !open" 
                                class="flex items-center gap-2 px-3 py-2 rounded-lg transition-colors {{ (request()->routeIs('sales.*') || request()->routeIs('customers.*') || request()->routeIs('payments.*') || request()->routeIs('customer_credit_notes.*')) ? 'bg-indigo-600 text-white shadow-sm' : 'hover:bg-slate-800/90 text-slate-200' }}">
                            <i data-lucide="receipt" class="w-4 h-4 text-emerald-400"></i>
                            <span>Commercial & Sales</span>
                            <i data-lucide="chevron-down" class="w-3.5 h-3.5 opacity-70 transition-transform duration-200" :class="{ 'rotate-180': open }"></i>
                        </button>

                        <div x-show="open" 
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 translate-y-1"
                             style="display: none;"
                             class="absolute top-full start-0 mt-1 w-64 bg-slate-900 border border-slate-800 rounded-xl shadow-2xl py-2 z-50">
                            <a href="{{ route('sales.invoices.index') }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="receipt" class="w-3.5 h-3.5 text-emerald-400"></i>
                                <span>{{ __('erp.invoices.title') }}</span>
                            </a>
                            <a href="{{ route('customers.index') }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="users" class="w-3.5 h-3.5 text-emerald-400"></i>
                                <span>{{ __('erp.nav.customers_khata') }}</span>
                            </a>
                            <a href="{{ route('sales.returns.index') }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5 text-emerald-400"></i>
                                <span>{{ __('erp.sales_returns.title') }}</span>
                            </a>
                            <a href="{{ route('payments.index') }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="banknote" class="w-3.5 h-3.5 text-emerald-400"></i>
                                <span>{{ __('erp.payments.title') }}</span>
                            </a>
                            <a href="{{ route('customer_credit_notes.index') }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="file-plus" class="w-3.5 h-3.5 text-emerald-400"></i>
                                <span>{{ __('erp.customer_credit_notes.title') }}</span>
                            </a>
                        </div>
                    </div>
                    @endif

                    <!-- 4. PURCHASING & SUPPLIERS (خریداری) -->
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = !open" 
                                class="flex items-center gap-2 px-3 py-2 rounded-lg transition-colors {{ (request()->routeIs('purchases.*') || request()->routeIs('suppliers.*') || request()->routeIs('goods-receipts.*') || request()->routeIs('supplier-payments.*') || request()->routeIs('supplier-debit-notes.*')) ? 'bg-indigo-600 text-white shadow-sm' : 'hover:bg-slate-800/90 text-slate-200' }}">
                            <i data-lucide="shopping-cart" class="w-4 h-4 text-orange-400"></i>
                            <span>{{ __('erp.nav.purchases') }} & Suppliers</span>
                            <i data-lucide="chevron-down" class="w-3.5 h-3.5 opacity-70 transition-transform duration-200" :class="{ 'rotate-180': open }"></i>
                        </button>

                        <div x-show="open" 
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 translate-y-1"
                             style="display: none;"
                             class="absolute top-full start-0 mt-1 w-64 bg-slate-900 border border-slate-800 rounded-xl shadow-2xl py-2 z-50">
                            <a href="{{ route('suppliers.index') }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="truck" class="w-3.5 h-3.5 text-orange-400"></i>
                                <span>{{ __('erp.suppliers.title') }}</span>
                            </a>
                            <a href="{{ route('purchases.index') }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="shopping-cart" class="w-3.5 h-3.5 text-orange-400"></i>
                                <span>{{ __('erp.purchases.title') }} (POs & GRN)</span>
                            </a>
                            <a href="{{ route('purchases.returns.index') }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="corner-up-left" class="w-3.5 h-3.5 text-orange-400"></i>
                                <span>{{ __('erp.purchase_returns.title') }}</span>
                            </a>
                            <a href="{{ route('supplier-payments.index') }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="credit-card" class="w-3.5 h-3.5 text-orange-400"></i>
                                <span>{{ __('erp.supplier_payments.title') }}</span>
                            </a>
                            <a href="{{ route('supplier-debit-notes.index') }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="file-minus" class="w-3.5 h-3.5 text-orange-400"></i>
                                <span>{{ __('erp.supplier_debit_notes.title') }}</span>
                            </a>
                        </div>
                    </div>

                    <!-- 5. FACTORY & PRODUCTION (پروڈکشن - Strictly Factory Managers & Owner Only) -->
                    @if($isOwner || ($user && $user->hasRole('factory_manager')))
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = !open" 
                                class="flex items-center gap-2 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('production.*') ? 'bg-indigo-600 text-white shadow-sm' : 'hover:bg-slate-800/90 text-slate-200' }}">
                            <i data-lucide="factory" class="w-4 h-4 text-purple-400"></i>
                            <span>{{ __('erp.production.title') }}</span>
                            <i data-lucide="chevron-down" class="w-3.5 h-3.5 opacity-70 transition-transform duration-200" :class="{ 'rotate-180': open }"></i>
                        </button>

                        <div x-show="open" 
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 translate-y-1"
                             style="display: none;"
                             class="absolute top-full start-0 mt-1 w-64 bg-slate-900 border border-slate-800 rounded-xl shadow-2xl py-2 z-50">
                            <a href="{{ route('production.orders.index') }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="cog" class="w-3.5 h-3.5 text-purple-400"></i>
                                <span>{{ __('erp.production.orders') }}</span>
                            </a>
                            <a href="{{ route('production.recipes.index') }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="layers" class="w-3.5 h-3.5 text-purple-400"></i>
                                <span>{{ __('erp.recipes.title') }} (BOM)</span>
                            </a>
                            <a href="{{ route('production.reports.index') }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="pie-chart" class="w-3.5 h-3.5 text-purple-400"></i>
                                <span>{{ __('erp.production.reports') }}</span>
                            </a>
                        </div>
                    </div>
                    @endif

                    <!-- 6. ACCOUNTING & FINANCE (اکاؤنٹنگ - General Ledger & Financials Strictly Restricted) -->
                    @if($isOwner)
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = !open" 
                                class="flex items-center gap-2 px-3 py-2 rounded-lg transition-colors {{ (request()->routeIs('accounting.*') || request()->routeIs('financial-accounts.*') || request()->routeIs('account-transfers.*') || request()->routeIs('expenses.*') || request()->routeIs('expense-categories.*')) ? 'bg-indigo-600 text-white shadow-sm' : 'hover:bg-slate-800/90 text-slate-200' }}">
                            <i data-lucide="scale" class="w-4 h-4 text-yellow-400"></i>
                            <span>{{ __('erp.nav.accounting') }}</span>
                            <i data-lucide="chevron-down" class="w-3.5 h-3.5 opacity-70 transition-transform duration-200" :class="{ 'rotate-180': open }"></i>
                        </button>

                        <div x-show="open" 
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 translate-y-1"
                             style="display: none;"
                             class="absolute top-full start-0 mt-1 w-72 bg-slate-900 border border-slate-800 rounded-xl shadow-2xl py-2 z-50">
                            <div class="px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-800">General Ledger & Statements</div>
                            <a href="{{ route('accounting.chart-of-accounts.index') }}" class="flex items-center gap-2 px-3.5 py-1.5 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="network" class="w-3.5 h-3.5 text-yellow-400"></i>
                                <span>{{ __('erp.nav.chart_of_accounts') }}</span>
                            </a>
                            <a href="{{ route('accounting.journals.index') }}" class="flex items-center gap-2 px-3.5 py-1.5 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="book-open" class="w-3.5 h-3.5 text-yellow-400"></i>
                                <span>{{ __('erp.general_ledger.journals') }}</span>
                            </a>
                            <a href="{{ route('accounting.ledger') }}" class="flex items-center gap-2 px-3.5 py-1.5 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="file-spreadsheet" class="w-3.5 h-3.5 text-yellow-400"></i>
                                <span>{{ __('erp.general_ledger.ledger') }}</span>
                            </a>
                            <a href="{{ route('accounting.trial-balance') }}" class="flex items-center gap-2 px-3.5 py-1.5 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="scale" class="w-3.5 h-3.5 text-yellow-400"></i>
                                <span>{{ __('erp.general_ledger.trial_balance') }}</span>
                            </a>
                            <a href="{{ route('accounting.balance-sheet') }}" class="flex items-center gap-2 px-3.5 py-1.5 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="landmark" class="w-3.5 h-3.5 text-yellow-400"></i>
                                <span>{{ __('erp.general_ledger.balance_sheet') }}</span>
                            </a>
                            <a href="{{ route('accounting.reconciliation') }}" class="flex items-center gap-2 px-3.5 py-1.5 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="check-check" class="w-3.5 h-3.5 text-yellow-400"></i>
                                <span>{{ __('erp.general_ledger.reconciliation') }}</span>
                            </a>
                            <div class="px-3 py-1 mt-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 border-t border-b border-slate-800">Cash, Bank & Expenses</div>
                            <a href="{{ route('financial-accounts.index') }}" class="flex items-center gap-2 px-3.5 py-1.5 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="wallet" class="w-3.5 h-3.5 text-amber-400"></i>
                                <span>{{ __('erp.financial_accounts.title') }}</span>
                            </a>
                            <a href="{{ route('account-transfers.index') }}" class="flex items-center gap-2 px-3.5 py-1.5 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="arrow-left-right" class="w-3.5 h-3.5 text-amber-400"></i>
                                <span>{{ __('erp.account_transfers.title') }}</span>
                            </a>
                            <a href="{{ route('expenses.index') }}" class="flex items-center gap-2 px-3.5 py-1.5 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="file-text" class="w-3.5 h-3.5 text-rose-400"></i>
                                <span>{{ __('erp.expenses.title') }}</span>
                            </a>
                            <a href="{{ route('expense-categories.index') }}" class="flex items-center gap-2 px-3.5 py-1.5 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="tag" class="w-3.5 h-3.5 text-rose-400"></i>
                                <span>{{ __('erp.expense_categories.title') }}</span>
                            </a>
                        </div>
                    </div>
                    @elseif($user && $user->hasRole('shop_manager'))
                    <!-- Shop Manager: Only their shop's local expenses, never General Ledger / Balance Sheet -->
                    <a href="{{ route('expenses.index') }}" 
                       class="flex items-center gap-2 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('expenses.*') ? 'bg-indigo-600 text-white shadow-sm' : 'hover:bg-slate-800/90 text-slate-200' }}">
                        <i data-lucide="receipt" class="w-4 h-4 text-rose-400"></i>
                        <span>{{ __('erp.expenses.title') }}</span>
                    </a>
                    @endif

                    <!-- EMPLOYEES & STAFF (ملازمین و تنخواہیں) -->
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = !open" 
                                class="flex items-center gap-2 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('employees.*') ? 'bg-indigo-600 text-white shadow-sm' : 'hover:bg-slate-800/90 text-slate-200' }}">
                            <i data-lucide="users-2" class="w-4 h-4 text-teal-400"></i>
                            <span>{{ app()->getLocale() === 'ur' ? 'ملازمین و تنخواہیں' : 'Staff & Payroll' }}</span>
                            <i data-lucide="chevron-down" class="w-3.5 h-3.5 opacity-70 transition-transform duration-200" :class="{ 'rotate-180': open }"></i>
                        </button>

                        <div x-show="open" 
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 translate-y-1"
                             style="display: none;"
                             class="absolute top-full start-0 mt-1 w-64 bg-slate-900 border border-slate-800 rounded-xl shadow-2xl py-2 z-50">
                            <a href="{{ route('employees.index', array_filter(['location_id' => $currentLocId])) }}" 
                               class="flex items-center gap-2.5 px-3.5 py-2 text-xs hover:bg-slate-800 text-slate-200 hover:text-white {{ request()->routeIs('employees.index') ? 'bg-indigo-600/30 text-indigo-300 font-bold' : '' }}">
                                <i data-lucide="users" class="w-3.5 h-3.5 text-teal-400"></i>
                                <span>{{ app()->getLocale() === 'ur' ? 'ملازمین کی فہرست و کھاتہ' : 'All Staff & Khata' }}</span>
                            </a>
                            <a href="{{ route('employees.create', array_filter(['location_id' => $currentLocId])) }}" 
                               class="flex items-center gap-2.5 px-3.5 py-2 text-xs hover:bg-slate-800 text-teal-300 hover:text-white font-semibold border-t border-slate-800/80 mt-1 pt-2">
                                <i data-lucide="user-plus" class="w-3.5 h-3.5 text-teal-400"></i>
                                <span>➕ {{ app()->getLocale() === 'ur' ? 'نیا ملازم شامل کریں' : 'Add New Employee' }}</span>
                            </a>
                        </div>
                    </div>

                    <!-- 7. REPORTS (رپورٹس) -->
                    <a href="{{ route('reports.index') }}" 
                       class="flex items-center gap-2 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('reports.*') ? 'bg-indigo-600 text-white shadow-sm' : 'hover:bg-slate-800/90 text-slate-200' }}">
                        <i data-lucide="bar-chart-3" class="w-4 h-4 text-blue-400"></i>
                        <span>{{ __('erp.nav.reports') }}</span>
                    </a>

                    <!-- 8. ADMINISTRATION (انتظامیہ) -->
                    @if(auth()->check() && auth()->user()->isOwner())
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = !open" 
                                class="flex items-center gap-2 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('admin.*') ? 'bg-indigo-600 text-white shadow-sm' : 'hover:bg-slate-800/90 text-slate-200' }}">
                            <i data-lucide="shield-check" class="w-4 h-4 text-emerald-400"></i>
                            <span>Admin</span>
                            <i data-lucide="chevron-down" class="w-3.5 h-3.5 opacity-70 transition-transform duration-200" :class="{ 'rotate-180': open }"></i>
                        </button>

                        <div x-show="open" 
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 translate-y-1"
                             style="display: none;"
                             class="absolute top-full end-0 mt-1 w-64 bg-slate-900 border border-slate-800 rounded-xl shadow-2xl py-2 z-50">
                            <a href="{{ route('admin.settings.index') }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="settings" class="w-3.5 h-3.5 text-slate-400"></i>
                                <span>{{ __('erp.settings.title') }}</span>
                            </a>
                            <a href="{{ route('admin.users.index') }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="users" class="w-3.5 h-3.5 text-slate-400"></i>
                                <span>{{ __('erp.users.title') }}</span>
                            </a>
                            <a href="{{ route('admin.locations.index') }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="building-2" class="w-3.5 h-3.5 text-slate-400"></i>
                                <span>{{ __('erp.locations_management.title') }}</span>
                            </a>
                            <a href="{{ route('admin.backups.index') }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="database" class="w-3.5 h-3.5 text-slate-400"></i>
                                <span>{{ __('erp.backups.title') }}</span>
                            </a>
                            <a href="{{ route('admin.audit-logs.index') }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="shield-check" class="w-3.5 h-3.5 text-slate-400"></i>
                                <span>{{ __('erp.audit_logs.title') }}</span>
                            </a>
                            <a href="{{ route('admin.health.index') }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs hover:bg-slate-800 text-slate-200 hover:text-white">
                                <i data-lucide="activity" class="w-3.5 h-3.5 text-slate-400"></i>
                                <span>{{ __('erp.system_health.title') }}</span>
                            </a>
                        </div>
                    </div>
                    @endif

                </div>
            </nav>

            <!-- Mobile Drawer Menu (Responsive) -->
            <div x-show="mobileOpen" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-2"
                 class="xl:hidden bg-slate-900 border-b border-slate-800 px-4 py-4 space-y-4 max-h-[80vh] overflow-y-auto text-xs"
                 style="display: none;">
                
                <!-- Mobile Location Context: Strict Isolation -->
                @if($isOwner)
                <div class="p-3 rounded-lg bg-slate-950 border border-slate-800">
                    <span class="block text-[11px] font-bold text-slate-400 mb-1.5 uppercase">{{ __('erp.location') }}:</span>
                    <select onchange="handleLocationContextSwitch(this)" 
                            class="w-full bg-slate-900 border border-slate-700 text-slate-200 text-xs rounded-lg px-2.5 py-2">
                        <option value="all" data-type="all" data-url="{{ route('dashboard.owner') }}" {{ ($currentLocId === 'all' || !$currentLocId) ? 'selected' : '' }}>
                            🏢 {{ __('erp.all_locations') }}
                        </option>
                        @foreach($navLocations as $loc)
                            @php
                                $icon = match($loc->type) {
                                    'shop' => '🏪',
                                    'factory' => '🏭',
                                    'warehouse' => '📦',
                                    default => '📍',
                                };
                                $targetUrl = match($loc->type) {
                                    'shop' => route('dashboard.shop', ['location_id' => $loc->id]),
                                    'factory' => route('dashboard.factory', ['location_id' => $loc->id]),
                                    'warehouse' => route('inventory.index', ['location_id' => $loc->id]),
                                    default => route('dashboard.owner', ['location_id' => $loc->id]),
                                };
                                $isSelected = ($currentLocId && ($currentLocId == $loc->id || $currentLocId === $loc->code || (isset($currentLocation) && $currentLocation instanceof \App\Models\Location && $currentLocation->id == $loc->id)));
                            @endphp
                            <option value="{{ $loc->id }}" 
                                    data-type="{{ $loc->type }}" 
                                    data-url="{{ $targetUrl }}" 
                                    {{ $isSelected ? 'selected' : '' }}>
                                {{ $icon }} {{ $loc->name }}
                            </option>
                        @endforeach
                        <option value="add_new_loc" data-type="action" data-url="{{ route('admin.locations.create') }}">
                            ➕ {{ __('erp.locations_management.add_location') }}
                        </option>
                        <option value="view_all_locs" data-type="action" data-url="{{ route('admin.locations.index') }}">
                            ⚙️ Manage All Locations...
                        </option>
                    </select>
                </div>
                @elseif($navLocations->isNotEmpty())
                <div class="p-3 rounded-lg bg-slate-950 border border-slate-800">
                    <span class="block text-[11px] font-bold text-slate-400 mb-1.5 uppercase">{{ __('erp.location') }}:</span>
                    @if($navLocations->count() === 1)
                        <div class="text-xs font-bold text-emerald-400 flex items-center gap-2">
                            <span>{{ $navLocations->first()->type === 'shop' ? '🏪' : ($navLocations->first()->type === 'factory' ? '🏭' : '📦') }}</span>
                            <span>{{ $navLocations->first()->name }}</span>
                        </div>
                    @else
                        <select onchange="handleLocationContextSwitch(this)" 
                                class="w-full bg-slate-900 border border-slate-700 text-slate-200 text-xs rounded-lg px-2.5 py-2">
                            @foreach($navLocations as $loc)
                                @php
                                    $icon = match($loc->type) {
                                        'shop' => '🏪',
                                        'factory' => '🏭',
                                        'warehouse' => '📦',
                                        default => '📍',
                                    };
                                    $targetUrl = match($loc->type) {
                                        'shop' => route('dashboard.shop', ['location_id' => $loc->id]),
                                        'factory' => route('dashboard.factory', ['location_id' => $loc->id]),
                                        'warehouse' => route('inventory.index', ['location_id' => $loc->id]),
                                        default => url()->current(),
                                    };
                                    $isSelected = ($currentLocId == $loc->id || (isset($currentLocation) && $currentLocation instanceof \App\Models\Location && $currentLocation->id == $loc->id));
                                @endphp
                                <option value="{{ $loc->id }}" 
                                        data-type="{{ $loc->type }}" 
                                        data-url="{{ $targetUrl }}" 
                                        {{ $isSelected ? 'selected' : '' }}>
                                    {{ $icon }} {{ $loc->name }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                </div>
                @endif

                <!-- Mobile Dashboards -->
                <div>
                    <span class="block text-[11px] font-bold text-slate-400 mb-1 uppercase tracking-wider">{{ __('erp.nav.dashboard') }}</span>
                    <div class="space-y-1">
                        @if($isOwner)
                            <a href="{{ route('dashboard.owner') }}" class="block px-3 py-2 rounded bg-slate-800/80 text-white font-semibold">{{ __('erp.nav.owner_view') }}</a>
                            <a href="{{ route('dashboard.shop') }}" class="block px-3 py-2 rounded hover:bg-slate-800 text-slate-300">{{ __('erp.nav.shop_view') }}</a>
                            <a href="{{ route('dashboard.factory') }}" class="block px-3 py-2 rounded hover:bg-slate-800 text-slate-300">{{ __('erp.nav.factory_view') }}</a>
                            <div class="pt-1 border-t border-slate-800">
                                @foreach($navLocations as $nl)
                                    @php
                                        $nUrl = match($nl->type) {
                                            'shop' => route('dashboard.shop', ['location_id' => $nl->id]),
                                            'factory' => route('dashboard.factory', ['location_id' => $nl->id]),
                                            'warehouse' => route('inventory.index', ['location_id' => $nl->id]),
                                            default => route('dashboard.owner'),
                                        };
                                        $nIcon = match($nl->type) {
                                            'shop' => '🏪',
                                            'factory' => '🏭',
                                            'warehouse' => '📦',
                                            default => '📍',
                                        };
                                    @endphp
                                    <a href="{{ $nUrl }}" class="flex items-center gap-2 px-3 py-1.5 rounded hover:bg-slate-800 text-slate-400 hover:text-white text-[11px]">
                                        <span>{{ $nIcon }}</span>
                                        <span>{{ $nl->name }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <a href="{{ $userHomeUrl }}" class="block px-3 py-2 rounded bg-slate-800/80 text-emerald-400 font-semibold flex items-center gap-2">
                                <span>{{ ($user && $user->hasRole('factory_manager')) ? '🏭' : '🏪' }}</span>
                                <span>{{ ($user && $user->hasRole('factory_manager')) ? __('erp.nav.factory_view') : __('erp.nav.shop_view') }}</span>
                            </a>
                        @endif
                    </div>
                </div>

                <!-- Inventory -->
                <div>
                    <span class="block text-[11px] font-bold text-slate-400 mb-1 uppercase tracking-wider">{{ __('erp.nav.inventory') }}</span>
                    <div class="space-y-1">
                        <a href="{{ route('inventory.index') }}" class="block px-3 py-1.5 rounded hover:bg-slate-800 text-slate-300">{{ __('erp.nav.articles') }} & {{ __('erp.nav.variants') }}</a>
                        <a href="{{ route('inventory.transfers.index') }}" class="block px-3 py-1.5 rounded hover:bg-slate-800 text-slate-300">{{ __('erp.nav.stock_transfers') }}</a>
                        <a href="{{ route('inventory.adjustments') }}" class="block px-3 py-1.5 rounded hover:bg-slate-800 text-slate-300">Stock Adjustments</a>
                    </div>
                </div>

                <!-- Commercial & Sales (Only for Owner and Shop Staff) -->
                @if($isOwner || ($user && $user->hasRole(['shop_manager', 'shop_cashier'])))
                <div>
                    <span class="block text-[11px] font-bold text-slate-400 mb-1 uppercase tracking-wider">Sales & Khata</span>
                    <div class="space-y-1">
                        <a href="{{ route('sales.invoices.index') }}" class="block px-3 py-1.5 rounded hover:bg-slate-800 text-slate-300">{{ __('erp.invoices.title') }}</a>
                        <a href="{{ route('customers.index') }}" class="block px-3 py-1.5 rounded hover:bg-slate-800 text-slate-300">{{ __('erp.nav.customers_khata') }}</a>
                        <a href="{{ route('sales.returns.index') }}" class="block px-3 py-1.5 rounded hover:bg-slate-800 text-slate-300">{{ __('erp.sales_returns.title') }}</a>
                        <a href="{{ route('payments.index') }}" class="block px-3 py-1.5 rounded hover:bg-slate-800 text-slate-300">{{ __('erp.payments.title') }}</a>
                    </div>
                </div>
                @endif

                <!-- Purchasing -->
                <div>
                    <span class="block text-[11px] font-bold text-slate-400 mb-1 uppercase tracking-wider">{{ __('erp.nav.purchases') }} & Suppliers</span>
                    <div class="space-y-1">
                        <a href="{{ route('purchases.index') }}" class="block px-3 py-1.5 rounded hover:bg-slate-800 text-slate-300">{{ __('erp.purchases.title') }}</a>
                        <a href="{{ route('suppliers.index') }}" class="block px-3 py-1.5 rounded hover:bg-slate-800 text-slate-300">{{ __('erp.suppliers.title') }}</a>
                        <a href="{{ route('purchases.returns.index') }}" class="block px-3 py-1.5 rounded hover:bg-slate-800 text-slate-300">{{ __('erp.purchase_returns.title') }}</a>
                    </div>
                </div>

                <!-- Factory & Production (Strictly Factory and Owner Only) -->
                @if($isOwner || ($user && $user->hasRole('factory_manager')))
                <div>
                    <span class="block text-[11px] font-bold text-slate-400 mb-1 uppercase tracking-wider">{{ __('erp.production.title') }}</span>
                    <div class="space-y-1">
                        <a href="{{ route('production.orders.index') }}" class="block px-3 py-1.5 rounded hover:bg-slate-800 text-slate-300">{{ __('erp.production.orders') }}</a>
                        <a href="{{ route('production.recipes.index') }}" class="block px-3 py-1.5 rounded hover:bg-slate-800 text-slate-300">{{ __('erp.recipes.title') }} (BOM)</a>
                        <a href="{{ route('production.reports.index') }}" class="block px-3 py-1.5 rounded hover:bg-slate-800 text-slate-300">{{ __('erp.production.reports') }}</a>
                    </div>
                </div>
                @endif

                <!-- Accounting & Finance (Strictly Restricted) -->
                @if($isOwner)
                <div>
                    <span class="block text-[11px] font-bold text-slate-400 mb-1 uppercase tracking-wider">{{ __('erp.nav.accounting') }}</span>
                    <div class="space-y-1">
                        <a href="{{ route('accounting.chart-of-accounts.index') }}" class="block px-3 py-1.5 rounded hover:bg-slate-800 text-slate-300">{{ __('erp.nav.chart_of_accounts') }}</a>
                        <a href="{{ route('accounting.journals.index') }}" class="block px-3 py-1.5 rounded hover:bg-slate-800 text-slate-300">{{ __('erp.general_ledger.journals') }}</a>
                        <a href="{{ route('accounting.ledger') }}" class="block px-3 py-1.5 rounded hover:bg-slate-800 text-slate-300">{{ __('erp.general_ledger.ledger') }}</a>
                        <a href="{{ route('accounting.trial-balance') }}" class="block px-3 py-1.5 rounded hover:bg-slate-800 text-slate-300">{{ __('erp.general_ledger.trial_balance') }}</a>
                        <a href="{{ route('financial-accounts.index') }}" class="block px-3 py-1.5 rounded hover:bg-slate-800 text-slate-300">{{ __('erp.financial_accounts.title') }}</a>
                        <a href="{{ route('expenses.index') }}" class="block px-3 py-1.5 rounded hover:bg-slate-800 text-slate-300">{{ __('erp.expenses.title') }}</a>
                    </div>
                </div>
                @elseif($user && $user->hasRole('shop_manager'))
                <div>
                    <a href="{{ route('expenses.index') }}" class="block px-3 py-2 rounded bg-slate-800/80 text-rose-300 font-semibold">{{ __('erp.expenses.title') }}</a>
                </div>
                @endif

                <!-- Staff & Employees (Mobile) -->
                <div class="pt-2 border-t border-slate-800">
                    <span class="block text-[11px] font-bold text-slate-400 mb-1 uppercase tracking-wider">{{ app()->getLocale() === 'ur' ? 'ملازمین و عملہ' : 'Staff & Employees' }}</span>
                    <div class="space-y-1">
                        <a href="{{ route('employees.index', array_filter(['location_id' => $currentLocId])) }}" class="block px-3 py-1.5 rounded hover:bg-slate-800 text-teal-300 font-semibold flex items-center gap-2">
                            <span>👥</span>
                            <span>{{ app()->getLocale() === 'ur' ? 'تمام ملازمین و کھاتہ' : 'All Staff & Khata' }}</span>
                        </a>
                        <a href="{{ route('employees.create', array_filter(['location_id' => $currentLocId])) }}" class="block px-3 py-1.5 rounded hover:bg-slate-800 text-teal-400 font-semibold flex items-center gap-2">
                            <span>➕</span>
                            <span>{{ app()->getLocale() === 'ur' ? 'نیا ملازم شامل کریں' : 'Add New Employee' }}</span>
                        </a>
                    </div>
                </div>

                <!-- Reports -->
                <div class="pt-2 border-t border-slate-800">
                    <a href="{{ route('reports.index') }}" class="block px-3 py-2 rounded bg-indigo-600/30 text-indigo-300 font-bold">{{ __('erp.nav.reports') }}</a>
                </div>

                <!-- Admin & Management (Mobile) -->
                @if(auth()->check() && auth()->user()->isOwner())
                <div class="pt-2 border-t border-slate-800">
                    <span class="block text-[11px] font-bold text-slate-400 mb-1 uppercase tracking-wider">Administration</span>
                    <div class="space-y-1">
                        <a href="{{ route('admin.locations.index') }}" class="block px-3 py-1.5 rounded hover:bg-slate-800 text-indigo-300 font-semibold">{{ __('erp.locations_management.title') }}</a>
                        <a href="{{ route('admin.settings.index') }}" class="block px-3 py-1.5 rounded hover:bg-slate-800 text-slate-300">{{ __('erp.settings.title') }}</a>
                        <a href="{{ route('admin.users.index') }}" class="block px-3 py-1.5 rounded hover:bg-slate-800 text-slate-300">{{ __('erp.users.title') }}</a>
                        <a href="{{ route('admin.backups.index') }}" class="block px-3 py-1.5 rounded hover:bg-slate-800 text-slate-300">{{ __('erp.backups.title') }}</a>
                        <a href="{{ route('admin.audit-logs.index') }}" class="block px-3 py-1.5 rounded hover:bg-slate-800 text-slate-300">{{ __('erp.audit_logs.title') }}</a>
                        <a href="{{ route('admin.health.index') }}" class="block px-3 py-1.5 rounded hover:bg-slate-800 text-slate-300">{{ __('erp.system_health.title') }}</a>
                    </div>
                </div>
                @endif
            </div>
        </header>

        <!-- Main Content Area (Full Front Width) -->
        <div class="flex-1 flex flex-col min-w-0">
            <main class="flex-1 w-full max-w-[1750px] mx-auto p-4 sm:p-6 lg:p-8">
                @if(session('success'))
                    <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium flex items-center gap-3 shadow-xs">
                        <i data-lucide="check-circle" class="w-5 h-5 text-emerald-600"></i>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-medium flex items-center gap-3 shadow-xs">
                        <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600"></i>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                <!-- Hero Section: Categorized Quick Action Rows across all Shops, Factories, and Owner -->
                @include('layouts.partials.hero-quick-actions')

                {{ $slot ?? '' }}
                @yield('content')
            </main>

            <!-- Bottom Status Footer -->
            <footer class="h-10 bg-slate-900 border-t border-slate-800 px-6 flex items-center justify-between text-[11px] text-slate-400 font-mono">
                <div class="flex items-center gap-4">
                    <span>DB: MariaDB 10.4 (Connected)</span>
                    <span>•</span>
                    <span>Multi-Branch Footwear ERP v1.0</span>
                </div>
                <div>
                    <span>Location Isolation: <strong class="text-emerald-400">ENFORCED (Server-side)</strong></span>
                </div>
            </footer>
        </div>

    </div>

    @livewireScripts
    <script>
        lucide.createIcons();
        document.addEventListener('livewire:navigated', () => {
            lucide.createIcons();
        });

        function handleLocationContextSwitch(select) {
            const option = select.options[select.selectedIndex];
            if (!option) return;
            const targetUrl = option.getAttribute('data-url');
            const locId = option.value;
            const locType = option.getAttribute('data-type');
            
            const path = window.location.pathname;
            const isDashboard = (path === '/' || path.startsWith('/dashboard'));

            if (isDashboard || locType === 'shop' || locType === 'factory' || locType === 'warehouse' || locId === 'all') {
                window.location.href = targetUrl;
            } else {
                const url = new URL(window.location.href);
                if (locId === 'all') {
                    url.searchParams.delete('location_id');
                    url.searchParams.delete('location');
                } else {
                    url.searchParams.set('location_id', locId);
                    url.searchParams.delete('location');
                }
                window.location.href = url.toString();
            }
        }
    </script>
</body>
</html>
