@php
    $user = auth()->user();
    $isOwner = $user && $user->isOwner();
    $rawLoc = request('location_id') ?? request('location');
    $activeLocId = ($rawLoc instanceof \App\Models\Location) ? $rawLoc->id : $rawLoc;
    
    // Resolve active location record for badge display
    $activeLocation = null;
    if (isset($currentLocation) && $currentLocation instanceof \App\Models\Location) {
        $activeLocation = $currentLocation;
    } elseif ($activeLocId && $activeLocId !== 'all') {
        $activeLocation = \App\Models\Location::find($activeLocId);
    } elseif ($user && !$isOwner) {
        $activeLocation = $user->defaultLocation ?? $user->locations->first();
    }
    
    $locParam = ($activeLocId && $activeLocId !== 'all') ? ['location_id' => $activeLocId] : ($activeLocation ? ['location_id' => $activeLocation->id] : []);
    $isUr = app()->getLocale() === 'ur';

    // Role-based visibility
    $canSales = $isOwner || ($user && $user->hasRole(['shop_manager', 'shop_cashier']));
    $canFactory = $isOwner || ($user && $user->hasRole('factory_manager'));
    $canPurchases = $isOwner || ($user && $user->hasRole(['shop_manager', 'factory_manager', 'warehouse_manager']));
    $canInventory = true;
    $canAccounting = $isOwner || ($user && $user->hasRole(['shop_manager', 'factory_manager']));
    $canStaff = true;
@endphp

<!-- Hero Quick Action Command Center (بغیر قطار لیبلز کے تمام فیچر بٹنز) -->
<div class="mb-6 bg-white/95 backdrop-blur-xs rounded-2xl border border-slate-200/90 shadow-xs p-4 sm:p-5 space-y-3">
    
    <!-- Header Title Bar for Hero Actions -->
    <div class="flex flex-wrap items-center justify-between gap-3 pb-2.5 border-b border-slate-100">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-indigo-600 to-indigo-500 text-white flex items-center justify-center shadow-xs">
                <i data-lucide="layout-grid" class="w-4 h-4"></i>
            </div>
            <div>
                <h3 class="text-xs sm:text-sm font-extrabold text-slate-900 tracking-tight flex items-center gap-2">
                    <span>{{ $isUr ? 'کوئیک فیچرز و ایکشن سینٹر' : 'Quick Actions & Module Hub' }}</span>
                    @if($activeLocation)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-300">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span>{{ $activeLocation->name }}</span>
                        </span>
                    @elseif($isOwner)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-800 border border-indigo-300">
                            <span>{{ __('erp.all_locations') }}</span>
                        </span>
                    @endif
                </h3>
                <p class="text-[11px] text-slate-500">{{ $isUr ? 'ڈراپ ڈاؤن مینیوز کے تمام فیچرز باہر بٹنز میں دستیاب ہیں' : 'All dropdown menu features arranged in clean, responsive rows' }}</p>
            </div>
        </div>

        <!-- Global Quick Actions -->
        <div class="flex items-center gap-2">
            <button type="button" onclick="window.location.reload()" 
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-slate-700 hover:text-slate-950 bg-slate-100 hover:bg-slate-200 border border-slate-200 hover:border-slate-300 transition-all cursor-pointer">
                <i data-lucide="rotate-cw" class="w-3.5 h-3.5 text-slate-600"></i>
                <span class="hidden sm:inline">{{ $isUr ? 'ریفریش' : 'Refresh' }}</span>
            </button>
            <a href="{{ route('employees.create', $locParam) }}" 
               class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-teal-50 text-teal-800 border border-teal-300 hover:bg-teal-100 hover:text-teal-950 hover:border-teal-400 text-xs font-bold transition-all shadow-2xs hover:shadow-xs cursor-pointer">
                <i data-lucide="user-plus" class="w-3.5 h-3.5 text-teal-700"></i>
                <span>{{ $isUr ? '➕ نیا ملازم شامل کریں' : '+ Add Employee' }}</span>
            </a>
        </div>
    </div>

    <!-- Commercial & Sales (سیلز و کسٹمرز) -->
    @if($canSales)
    <div class="flex flex-wrap items-center gap-2 pb-2.5 border-b border-slate-100">
        <a href="{{ route('sales.invoices.index', $locParam) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 hover:text-blue-950 hover:border-blue-400 transition-all shadow-2xs hover:shadow-xs hover:-translate-y-0.5">
            <i data-lucide="receipt" class="w-3.5 h-3.5 text-blue-600"></i>
            <span>{{ __('erp.invoices.title') }}</span>
        </a>
        <a href="{{ route('customers.index', $locParam) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 hover:text-blue-950 hover:border-blue-400 transition-all shadow-2xs hover:shadow-xs hover:-translate-y-0.5">
            <i data-lucide="users" class="w-3.5 h-3.5 text-blue-600"></i>
            <span>{{ __('erp.nav.customers_khata') }}</span>
        </a>
        <a href="{{ route('payments.index', $locParam) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 hover:text-emerald-950 hover:border-emerald-400 transition-all shadow-2xs hover:shadow-xs hover:-translate-y-0.5">
            <i data-lucide="banknote" class="w-3.5 h-3.5 text-emerald-600"></i>
            <span>{{ __('erp.payments.title') }}</span>
        </a>
        <a href="{{ route('sales.returns.index', $locParam) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-sky-50 text-sky-700 border border-sky-200 hover:bg-sky-100 hover:text-sky-950 hover:border-sky-400 transition-all shadow-2xs hover:shadow-xs hover:-translate-y-0.5">
            <i data-lucide="rotate-ccw" class="w-3.5 h-3.5 text-sky-600"></i>
            <span>{{ __('erp.sales_returns.title') }}</span>
        </a>
        <a href="{{ route('customer_credit_notes.index', $locParam) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-sky-50 text-sky-700 border border-sky-200 hover:bg-sky-100 hover:text-sky-950 hover:border-sky-400 transition-all shadow-2xs hover:shadow-xs hover:-translate-y-0.5">
            <i data-lucide="file-text" class="w-3.5 h-3.5 text-sky-600"></i>
            <span>{{ __('erp.customer_credit_notes.title') }}</span>
        </a>
    </div>
    @endif

    <!-- Inventory, Footwear Articles & Stock (اسٹاک و گودام) -->
    @if($canInventory)
    <div class="flex flex-wrap items-center gap-2 pb-2.5 border-b border-slate-100">
        <a href="{{ route('products.index', $locParam) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-purple-50 text-purple-700 border border-purple-200 hover:bg-purple-100 hover:text-purple-950 hover:border-purple-400 transition-all shadow-2xs hover:shadow-xs hover:-translate-y-0.5">
            <i data-lucide="layers" class="w-3.5 h-3.5 text-purple-600"></i>
            <span>{{ __('erp.nav.articles') }}</span>
        </a>
        <a href="{{ route('inventory.index', $locParam) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-purple-50 text-purple-700 border border-purple-200 hover:bg-purple-100 hover:text-purple-950 hover:border-purple-400 transition-all shadow-2xs hover:shadow-xs hover:-translate-y-0.5">
            <i data-lucide="boxes" class="w-3.5 h-3.5 text-purple-600"></i>
            <span>{{ $isUr ? 'گودام و اسٹاک ویلیو' : 'Warehouse Stock' }}</span>
        </a>
        <a href="{{ route('inventory.transfers.index', $locParam) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 hover:text-indigo-950 hover:border-indigo-400 transition-all shadow-2xs hover:shadow-xs hover:-translate-y-0.5">
            <i data-lucide="arrow-left-right" class="w-3.5 h-3.5 text-indigo-600"></i>
            <span>{{ __('erp.nav.stock_transfers') }}</span>
        </a>
        <a href="{{ route('inventory.adjustments', $locParam) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-fuchsia-50 text-fuchsia-700 border border-fuchsia-200 hover:bg-fuchsia-100 hover:text-fuchsia-950 hover:border-fuchsia-400 transition-all shadow-2xs hover:shadow-xs hover:-translate-y-0.5">
            <i data-lucide="scale" class="w-3.5 h-3.5 text-fuchsia-600"></i>
            <span>{{ $isUr ? 'اسٹاک ایڈجسٹمنٹ و گنتی' : 'Stock Adjustments' }}</span>
        </a>
    </div>
    @endif

    <!-- Factory & Manufacturing (فیکٹری و پروڈکشن) -->
    @if($canFactory)
    <div class="flex flex-wrap items-center gap-2 pb-2.5 border-b border-slate-100">
        <a href="{{ route('production.orders.index', $locParam) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-orange-50 text-orange-800 border border-orange-200 hover:bg-orange-100 hover:text-orange-950 hover:border-orange-400 transition-all shadow-2xs hover:shadow-xs hover:-translate-y-0.5">
            <i data-lucide="clipboard-list" class="w-3.5 h-3.5 text-orange-600"></i>
            <span>{{ __('erp.nav.production_orders') }}</span>
        </a>
        <a href="{{ route('production.recipes.index', $locParam) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200 hover:bg-amber-100 hover:text-amber-950 hover:border-amber-400 transition-all shadow-2xs hover:shadow-xs hover:-translate-y-0.5">
            <i data-lucide="scroll" class="w-3.5 h-3.5 text-amber-600"></i>
            <span>{{ __('erp.nav.bom_recipes') }}</span>
        </a>
        <a href="{{ route('production.reports.index', $locParam) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-rose-50 text-rose-800 border border-rose-200 hover:bg-rose-100 hover:text-rose-950 hover:border-rose-400 transition-all shadow-2xs hover:shadow-xs hover:-translate-y-0.5">
            <i data-lucide="alert-triangle" class="w-3.5 h-3.5 text-rose-600"></i>
            <span>{{ __('erp.nav.wastage_defects') }}</span>
        </a>
    </div>
    @endif

    <!-- Purchasing & Suppliers (خریداری و سپلائر) -->
    @if($canPurchases)
    <div class="flex flex-wrap items-center gap-2 pb-2.5 border-b border-slate-100">
        <a href="{{ route('suppliers.index', $locParam) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 hover:bg-emerald-100 hover:text-emerald-950 hover:border-emerald-400 transition-all shadow-2xs hover:shadow-xs hover:-translate-y-0.5">
            <i data-lucide="truck" class="w-3.5 h-3.5 text-emerald-600"></i>
            <span>{{ __('erp.suppliers.title') }}</span>
        </a>
        <a href="{{ route('purchases.index', $locParam) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 hover:bg-emerald-100 hover:text-emerald-950 hover:border-emerald-400 transition-all shadow-2xs hover:shadow-xs hover:-translate-y-0.5">
            <i data-lucide="shopping-bag" class="w-3.5 h-3.5 text-emerald-600"></i>
            <span>{{ __('erp.purchases.title') }}</span>
        </a>
        <a href="{{ route('purchases.create', $locParam) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-teal-50 text-teal-800 border border-teal-200 hover:bg-teal-100 hover:text-teal-950 hover:border-teal-400 transition-all shadow-2xs hover:shadow-xs hover:-translate-y-0.5">
            <i data-lucide="plus-circle" class="w-3.5 h-3.5 text-teal-600"></i>
            <span>{{ $isUr ? '➕ نیا پرچیز آرڈر' : '+ New PO' }}</span>
        </a>
        <a href="{{ route('supplier-payments.index', $locParam) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-green-50 text-green-800 border border-green-200 hover:bg-green-100 hover:text-green-950 hover:border-green-400 transition-all shadow-2xs hover:shadow-xs hover:-translate-y-0.5">
            <i data-lucide="credit-card" class="w-3.5 h-3.5 text-green-600"></i>
            <span>{{ __('erp.supplier_payments.title') }}</span>
        </a>
        <a href="{{ route('purchases.returns.index', $locParam) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-rose-50 text-rose-800 border border-rose-200 hover:bg-rose-100 hover:text-rose-950 hover:border-rose-400 transition-all shadow-2xs hover:shadow-xs hover:-translate-y-0.5">
            <i data-lucide="corner-up-left" class="w-3.5 h-3.5 text-rose-600"></i>
            <span>{{ __('erp.purchase_returns.title') }}</span>
        </a>
        <a href="{{ route('supplier-debit-notes.index', $locParam) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-slate-50 text-slate-800 border border-slate-200 hover:bg-slate-100 hover:text-slate-950 hover:border-slate-400 transition-all shadow-2xs hover:shadow-xs hover:-translate-y-0.5">
            <i data-lucide="file-minus" class="w-3.5 h-3.5 text-slate-600"></i>
            <span>{{ __('erp.supplier_debit_notes.title') }}</span>
        </a>
    </div>
    @endif

    <!-- Staff & Employees (ملازمین، تنخواہیں و پیشگی خرچہ) -->
    @if($canStaff)
    <div class="flex flex-wrap items-center gap-2 pb-2.5 border-b border-slate-100">
        <a href="{{ route('employees.index', $locParam) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-cyan-50 text-cyan-800 border border-cyan-200 hover:bg-cyan-100 hover:text-cyan-950 hover:border-cyan-400 transition-all shadow-2xs hover:shadow-xs hover:-translate-y-0.5">
            <i data-lucide="users" class="w-3.5 h-3.5 text-cyan-600"></i>
            <span>{{ $isUr ? 'ملازمین لسٹ و مکمل کھاتہ' : 'All Staff & Khata' }}</span>
        </a>
        <a href="{{ route('employees.create', $locParam) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-teal-50 text-teal-800 border border-teal-200 hover:bg-teal-100 hover:text-teal-950 hover:border-teal-400 transition-all shadow-2xs hover:shadow-xs hover:-translate-y-0.5">
            <i data-lucide="user-plus" class="w-3.5 h-3.5 text-teal-600"></i>
            <span>{{ $isUr ? '➕ نیا ملازم رجسٹر کریں' : '+ Add New Employee' }}</span>
        </a>
        <a href="{{ route('expenses.index', $locParam) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-rose-50 text-rose-800 border border-rose-200 hover:bg-rose-100 hover:text-rose-950 hover:border-rose-400 transition-all shadow-2xs hover:shadow-xs hover:-translate-y-0.5">
            <i data-lucide="receipt" class="w-3.5 h-3.5 text-rose-600"></i>
            <span>{{ $isUr ? 'روزنامچہ خرچہ و واؤچرز' : 'Expenses & Vouchers' }}</span>
        </a>
    </div>
    @endif

    <!-- Accounting, Financials & Reports (کھاتہ جات و رپورٹس) -->
    @if($canAccounting)
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('financial-accounts.index', $locParam) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-slate-50 text-slate-800 border border-slate-200 hover:bg-slate-100 hover:text-slate-950 hover:border-slate-400 transition-all shadow-2xs hover:shadow-xs hover:-translate-y-0.5">
            <i data-lucide="wallet" class="w-3.5 h-3.5 text-slate-600"></i>
            <span>{{ __('erp.financial_accounts.title') }}</span>
        </a>
        <a href="{{ route('account-transfers.index', $locParam) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200 hover:bg-amber-100 hover:text-amber-950 hover:border-amber-400 transition-all shadow-2xs hover:shadow-xs hover:-translate-y-0.5">
            <i data-lucide="arrow-left-right" class="w-3.5 h-3.5 text-amber-600"></i>
            <span>{{ __('erp.account_transfers.title') }}</span>
        </a>
        <a href="{{ route('reports.index', $locParam) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 hover:text-indigo-950 hover:border-indigo-400 transition-all shadow-2xs hover:shadow-xs hover:-translate-y-0.5">
            <i data-lucide="line-chart" class="w-3.5 h-3.5 text-indigo-600"></i>
            <span>{{ __('erp.nav.reports') }}</span>
        </a>
        @if($isOwner)
        <a href="{{ route('accounting.chart-of-accounts.index', $locParam) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-slate-50 text-slate-800 border border-slate-200 hover:bg-slate-100 hover:text-slate-950 hover:border-slate-400 transition-all shadow-2xs hover:shadow-xs hover:-translate-y-0.5">
            <i data-lucide="folder-tree" class="w-3.5 h-3.5 text-slate-600"></i>
            <span>{{ __('erp.general_ledger.chart_of_accounts') }}</span>
        </a>
        <a href="{{ route('accounting.profit-loss', $locParam) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 hover:bg-emerald-100 hover:text-emerald-950 hover:border-emerald-400 transition-all shadow-2xs hover:shadow-xs hover:-translate-y-0.5">
            <i data-lucide="trending-up" class="w-3.5 h-3.5 text-emerald-600"></i>
            <span>{{ __('erp.nav.profit_loss') }}</span>
        </a>
        <a href="{{ route('admin.locations.index') }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-slate-100 text-slate-800 border border-slate-300 hover:bg-slate-200 hover:text-slate-950 hover:border-slate-400 transition-all shadow-2xs hover:shadow-xs hover:-translate-y-0.5">
            <i data-lucide="building-2" class="w-3.5 h-3.5 text-slate-600"></i>
            <span>{{ __('erp.locations_management.title') }}</span>
        </a>
        @endif
    </div>
    @endif

</div>
