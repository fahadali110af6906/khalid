<?php

use App\Http\Controllers\AccountTransferController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BusinessSettingController;
use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\GeneralLedgerReportController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FinancialAccountController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductionOrderController;
use App\Http\Controllers\ProductionRecipeController;
use App\Http\Controllers\ProductionReportController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\SalesInvoiceController;
use App\Http\Controllers\SalesReturnController;
use App\Http\Controllers\CustomerCreditNoteController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierDebitNoteController;
use App\Http\Controllers\SupplierPaymentController;
use App\Http\Controllers\SystemHealthController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnforceLocationAccess;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public Locale Switcher (English / Urdu)
Route::get('/locale/{lang}', function (string $lang) {
    if (in_array($lang, ['en', 'ur'])) {
        session(['locale' => $lang]);
    }
    return redirect()->back();
})->name('locale.switch');

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
    Route::get('/register', [LoginController::class, 'disabledRegistration'])->name('register');
    Route::post('/register', [LoginController::class, 'disabledRegistration']);
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// Protected ERP Application Routes
Route::middleware(['auth', EnforceLocationAccess::class])->group(function () {
    // Default Home redirects to Owner Dashboard (or user home)
    Route::get('/', function (Request $request) {
        $user = $request->user();
        if ($user->hasRole('factory_manager')) {
            $locId = $user->default_location_id ?? $user->locations()->where('type', 'factory')->value('locations.id');
            return redirect()->route('dashboard.factory', array_filter(['location_id' => $locId]));
        }
        if ($user->hasRole(['shop_manager', 'shop_cashier'])) {
            $locId = $user->default_location_id ?? $user->locations()->where('type', 'shop')->value('locations.id');
            return redirect()->route('dashboard.shop', array_filter(['location_id' => $locId]));
        }
        if ($user->hasRole('warehouse_manager')) {
            $locId = $user->default_location_id ?? $user->locations()->where('type', 'warehouse')->value('locations.id');
            return redirect()->route('inventory.index', array_filter(['location_id' => $locId]));
        }
        return redirect()->route('dashboard.owner');
    });

    // Dashboards
    Route::prefix('dashboard')->group(function () {
        // 1. Owner Consolidated Dashboard (Restricted to Owner)
        Route::get('/owner', function (Request $request) {
            $user = $request->user();
            if (!$user->isOwner()) {
                abort(403, 'Forbidden: You do not have owner authorization to access consolidated reports.');
            }
            $shops = Location::where('type', 'shop')->where('is_active', true)->orderBy('id')->get();
            $factories = Location::where('type', 'factory')->where('is_active', true)->orderBy('id')->get();
            $warehouses = Location::where('type', 'warehouse')->where('is_active', true)->orderBy('id')->get();

            return view('dashboard.owner', compact('shops', 'factories', 'warehouses'));
        })->name('dashboard.owner');

        // 2. Shop Manager View
        Route::get('/shop', function (Request $request) {
            $user = $request->user();
            $hasShop = $user->isOwner() || $user->locations()->where('type', 'shop')->exists();
            if (!$hasShop) {
                abort(403, 'Forbidden: You are not assigned to any shop location.');
            }

            $shops = $user->isOwner()
                ? Location::where('type', 'shop')->where('is_active', true)->orderBy('id')->get()
                : $user->locations()->where('type', 'shop')->where('is_active', true)->orderBy('id')->get();

            $locationId = $request->query('location_id') ?? $request->query('location');
            $currentLocation = null;
            if ($locationId) {
                if (is_numeric($locationId)) {
                    $currentLocation = $shops->firstWhere('id', (int) $locationId);
                } else {
                    $currentLocation = $shops->first(function ($l) use ($locationId) {
                        return $l->code === $locationId 
                            || strtolower($l->code) === strtolower($locationId)
                            || str_contains(strtolower($l->name), strtolower(str_replace('-', ' ', $locationId)));
                    });
                }
            }
            $currentLocation ??= $shops->first();

            return view('dashboard.shop', compact('shops', 'currentLocation'));
        })->name('dashboard.shop');

        // 3. Factory Manager View
        Route::get('/factory', function (Request $request) {
            $user = $request->user();
            $hasFactory = $user->isOwner() || $user->locations()->where('type', 'factory')->exists();
            if (!$hasFactory) {
                abort(403, 'Forbidden: You are not assigned to any factory location.');
            }

            $factories = $user->isOwner()
                ? Location::where('type', 'factory')->where('is_active', true)->orderBy('id')->get()
                : $user->locations()->where('type', 'factory')->where('is_active', true)->orderBy('id')->get();

            $locationId = $request->query('location_id') ?? $request->query('location');
            $currentLocation = null;
            if ($locationId) {
                if (is_numeric($locationId)) {
                    $currentLocation = $factories->firstWhere('id', (int) $locationId);
                } else {
                    $currentLocation = $factories->first(function ($l) use ($locationId) {
                        return $l->code === $locationId 
                            || strtolower($l->code) === strtolower($locationId)
                            || str_contains(strtolower($l->name), strtolower(str_replace('-', ' ', $locationId)));
                    });
                }
            }
            $currentLocation ??= $factories->first();

            return view('dashboard.factory', compact('factories', 'currentLocation'));
        })->name('dashboard.factory');
    });

    // Specific Location-Scoped Data Route
    Route::get('/locations/{location}/view', function (string $location) {
        return response()->json([
            'status' => 'authorized',
            'location' => $location,
            'message' => 'Access granted to location data.',
        ]);
    })->name('locations.view');

    // Product Master Catalog
    Route::resource('products', ProductController::class)->except(['destroy']);

    // Inventory & Stock Balances
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/adjustments', [InventoryController::class, 'adjustments'])->name('inventory.adjustments');
    Route::get('/inventory/adjustments/create', [InventoryController::class, 'createAdjustment'])->name('inventory.adjustments.create');
    Route::post('/inventory/adjustments', [InventoryController::class, 'storeAdjustment'])->name('inventory.adjustments.store');
    Route::post('/inventory/adjustments/{adjustment}/approve', [InventoryController::class, 'approveAdjustment'])->name('inventory.adjustments.approve');

    // Inter-Location Stock Transfers
    Route::prefix('inventory/transfers')->name('inventory.transfers.')->group(function () {
        Route::get('/', [StockTransferController::class, 'index'])->name('index');
        Route::get('/create', [StockTransferController::class, 'create'])->name('create');
        Route::post('/', [StockTransferController::class, 'store'])->name('store');
        Route::get('/{transfer}', [StockTransferController::class, 'show'])->name('show');
        Route::post('/{transfer}/dispatch', [StockTransferController::class, 'dispatch'])->name('dispatch');
        Route::post('/{transfer}/receive', [StockTransferController::class, 'receive'])->name('receive');
        Route::post('/{transfer}/cancel', [StockTransferController::class, 'cancel'])->name('cancel');
    });

    // Customer Management & Khata (Ledger)
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::post('/customers/{customer}/toggle-status', [CustomerController::class, 'toggleStatus'])->name('customers.toggle-status');
    Route::get('/customers/{customer}/ledger', [CustomerController::class, 'ledger'])->name('customers.ledger');

    // Sales Module & Wholesale Invoicing
    Route::get('/sales', [SalesController::class, 'index'])->name('sales.index');
    Route::get('/sales/invoices', [SalesInvoiceController::class, 'index'])->name('sales.invoices.index');
    Route::get('/sales/invoices/create', [SalesInvoiceController::class, 'create'])->name('sales.invoices.create');
    Route::post('/sales/invoices', [SalesInvoiceController::class, 'store'])->name('sales.invoices.store');
    Route::get('/sales/invoices/{invoice}', [SalesInvoiceController::class, 'show'])->name('sales.invoices.show');
    Route::get('/sales/invoices/{invoice}/print', [SalesInvoiceController::class, 'print'])->name('sales.invoices.print');
    Route::post('/sales/invoices/{invoice}/post', [SalesInvoiceController::class, 'post'])->name('sales.invoices.post');
    Route::post('/sales/invoices/{invoice}/cancel', [SalesInvoiceController::class, 'cancel'])->name('sales.invoices.cancel');
    Route::post('/sales/invoices/{invoice}/payments', [PaymentController::class, 'storeForInvoice'])->name('sales.invoices.payments.store');

    // Sales Returns
    Route::get('/sales/returns', [SalesReturnController::class, 'index'])->name('sales.returns.index');
    Route::get('/sales/invoices/{salesInvoice}/returns/create', [SalesReturnController::class, 'create'])->name('sales.returns.create');
    Route::get('/sales/returns/create-from/{salesInvoice}', [SalesReturnController::class, 'create'])->name('sales.invoices.returns.create');
    Route::post('/sales/invoices/{salesInvoice}/returns', [SalesReturnController::class, 'store'])->name('sales.returns.store');
    Route::post('/sales/returns/store-from/{salesInvoice}', [SalesReturnController::class, 'store'])->name('sales.invoices.returns.store');
    Route::get('/sales/returns/{salesReturn}', [SalesReturnController::class, 'show'])->name('sales.returns.show');
    Route::post('/sales/returns/{salesReturn}/post', [SalesReturnController::class, 'post'])->name('sales.returns.post');
    Route::post('/sales/returns/{salesReturn}/refund', [SalesReturnController::class, 'refund'])->name('sales.returns.refund');

    // Customer Credit Notes
    Route::get('/customer-credit-notes', [CustomerCreditNoteController::class, 'index'])->name('customer_credit_notes.index');
    Route::get('/customer-credit-notes/create', [CustomerCreditNoteController::class, 'create'])->name('customer_credit_notes.create');
    Route::post('/customer-credit-notes', [CustomerCreditNoteController::class, 'store'])->name('customer_credit_notes.store');
    Route::get('/customer-credit-notes/{customerCreditNote}', [CustomerCreditNoteController::class, 'show'])->name('customer_credit_notes.show');

    // Customer Payments & Receipts
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');

    // Supplier Management & Khata (Ledger)
    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::get('/suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
    Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
    Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
    Route::post('/suppliers/{supplier}/toggle-status', [SupplierController::class, 'toggleStatus'])->name('suppliers.toggle-status');
    Route::get('/suppliers/{supplier}/ledger', [SupplierController::class, 'ledger'])->name('suppliers.ledger');

    // Purchase Returns (must precede /purchases/{purchaseOrder} wildcard)
    Route::get('/purchases/returns', [PurchaseReturnController::class, 'index'])->name('purchases.returns.index');
    Route::get('/purchases/receipts/{goodsReceipt}/returns/create', [PurchaseReturnController::class, 'create'])
        ->name('purchases.returns.create');
    Route::get('/purchases/returns/create-from/{goodsReceipt}', [PurchaseReturnController::class, 'create'])
        ->name('purchases.receipts.returns.create');
    Route::post('/purchases/receipts/{goodsReceipt}/returns', [PurchaseReturnController::class, 'store'])
        ->name('purchases.returns.store');
    Route::post('/purchases/returns/store-from/{goodsReceipt}', [PurchaseReturnController::class, 'store'])
        ->name('purchases.receipts.returns.store');
    Route::get('/purchases/returns/{purchaseReturn}', [PurchaseReturnController::class, 'show'])->name('purchases.returns.show');
    Route::post('/purchases/returns/{purchaseReturn}/post', [PurchaseReturnController::class, 'post'])->name('purchases.returns.post');

    // Purchase Orders & Receiving
    Route::get('/purchases', [PurchaseOrderController::class, 'index'])->name('purchases.index');
    Route::get('/purchases/create', [PurchaseOrderController::class, 'create'])->name('purchases.create');
    Route::post('/purchases', [PurchaseOrderController::class, 'store'])->name('purchases.store');
    Route::get('/purchases/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchases.show');
    Route::get('/purchases/{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])->name('purchases.edit');
    Route::put('/purchases/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('purchases.update');
    Route::post('/purchases/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->name('purchases.approve');
    Route::post('/purchases/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchases.cancel');

    // Goods Receipts (GRN)
    Route::get('/purchases/{purchaseOrder}/receive', [GoodsReceiptController::class, 'create'])->name('purchases.receive');
    Route::post('/purchases/{purchaseOrder}/receive', [GoodsReceiptController::class, 'store'])->name('purchases.receive.store');
    Route::get('/goods-receipts/{goodsReceipt}', [GoodsReceiptController::class, 'show'])->name('goods-receipts.show');
    Route::get('/purchases/grn/{goodsReceipt}', [GoodsReceiptController::class, 'show'])->name('purchases.grn.show');

    // Supplier Payments & Vouchers
    Route::get('/supplier-payments', [SupplierPaymentController::class, 'index'])->name('supplier-payments.index');
    Route::get('/supplier-payments/create', [SupplierPaymentController::class, 'create'])->name('supplier-payments.create');
    Route::post('/supplier-payments', [SupplierPaymentController::class, 'store'])->name('supplier-payments.store');
    Route::get('/supplier-payments/{supplierPayment}', [SupplierPaymentController::class, 'show'])->name('supplier-payments.show');

    // Supplier Debit Notes
    Route::get('/supplier-debit-notes', [SupplierDebitNoteController::class, 'index'])->name('supplier-debit-notes.index');
    Route::get('/supplier-debit-notes/create', [SupplierDebitNoteController::class, 'create'])->name('supplier-debit-notes.create');
    Route::post('/supplier-debit-notes', [SupplierDebitNoteController::class, 'store'])->name('supplier-debit-notes.store');
    Route::get('/supplier-debit-notes/{supplierDebitNote}', [SupplierDebitNoteController::class, 'show'])->name('supplier-debit-notes.show');

    // Phase 8: Cash & Bank Accounts
    Route::get('/financial-accounts', [FinancialAccountController::class, 'index'])->name('financial-accounts.index');
    Route::get('/financial-accounts/create', [FinancialAccountController::class, 'create'])->name('financial-accounts.create');
    Route::post('/financial-accounts', [FinancialAccountController::class, 'store'])->name('financial-accounts.store');
    Route::get('/financial-accounts/{financialAccount}', [FinancialAccountController::class, 'show'])->name('financial-accounts.show');
    Route::get('/financial-accounts/{financialAccount}/edit', [FinancialAccountController::class, 'edit'])->name('financial-accounts.edit');
    Route::put('/financial-accounts/{financialAccount}', [FinancialAccountController::class, 'update'])->name('financial-accounts.update');

    // Phase 8: Account Transfers
    Route::get('/account-transfers', [AccountTransferController::class, 'index'])->name('account-transfers.index');
    Route::get('/account-transfers/create', [AccountTransferController::class, 'create'])->name('account-transfers.create');
    Route::post('/account-transfers', [AccountTransferController::class, 'store'])->name('account-transfers.store');
    Route::get('/account-transfers/{accountTransfer}', [AccountTransferController::class, 'show'])->name('account-transfers.show');

    // Phase 8: Expense Categories
    Route::get('/expense-categories', [ExpenseCategoryController::class, 'index'])->name('expense-categories.index');
    Route::post('/expense-categories', [ExpenseCategoryController::class, 'store'])->name('expense-categories.store');
    Route::put('/expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'update'])->name('expense-categories.update');
    Route::post('/expense-categories/{expenseCategory}/toggle-status', [ExpenseCategoryController::class, 'toggleStatus'])->name('expense-categories.toggle-status');

    // Phase 8: Expenses
    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('/expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
    Route::post('/expenses/{expense}/post', [ExpenseController::class, 'post'])->name('expenses.post');
    Route::post('/expenses/{expense}/reverse', [ExpenseController::class, 'reverse'])->name('expenses.reverse');

    // Employees, Staff & Payroll / Advances System (ملازمین، تنخواہیں اور پیشگی خرچہ)
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
    Route::post('/employees/{employee}/transactions', [EmployeeController::class, 'storeTransaction'])->name('employees.transactions.store');

    // Phase 9: Reports & Business Analytics
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/sales', [ReportController::class, 'sales'])->name('sales');
        Route::get('/sales/export', [ReportController::class, 'exportSales'])->name('sales.export');
        Route::get('/purchases', [ReportController::class, 'purchases'])->name('purchases');
        Route::get('/purchases/export', [ReportController::class, 'exportPurchases'])->name('purchases.export');
        Route::get('/khata', [ReportController::class, 'khata'])->name('khata');
        Route::get('/khata/customers/export', [ReportController::class, 'exportCustomerKhata'])->name('khata.customer.export');
        Route::get('/khata/suppliers/export', [ReportController::class, 'exportSupplierKhata'])->name('khata.supplier.export');
        Route::get('/cash-bank', [ReportController::class, 'cashBank'])->name('cash-bank');
        Route::get('/cash-bank/export', [ReportController::class, 'exportCashBank'])->name('cash-bank.export');
        Route::get('/expenses', [ReportController::class, 'expenses'])->name('expenses');
        Route::get('/expenses/export', [ReportController::class, 'exportExpenses'])->name('expenses.export');
        Route::get('/inventory', [ReportController::class, 'inventory'])->name('inventory');
        Route::get('/inventory/export', [ReportController::class, 'exportInventory'])->name('inventory.export');
        Route::get('/profit-loss', [ReportController::class, 'profitLoss'])->name('profit-loss');
        Route::get('/profit-loss/export', [ReportController::class, 'exportProfitLoss'])->name('profit-loss.export');
        Route::get('/cash-flow', [ReportController::class, 'cashFlow'])->name('cash-flow');
        Route::get('/cash-flow/export', [ReportController::class, 'exportCashFlow'])->name('cash-flow.export');
        Route::get('/financial-position', [ReportController::class, 'financialPosition'])->name('financial-position');
    });

    // Phase 10: Factory & Production Management
    Route::prefix('production')->name('production.')->group(function () {
        // BOM Recipes
        Route::get('/recipes', [ProductionRecipeController::class, 'index'])->name('recipes.index');
        Route::get('/recipes/create', [ProductionRecipeController::class, 'create'])->name('recipes.create');
        Route::post('/recipes', [ProductionRecipeController::class, 'store'])->name('recipes.store');
        Route::get('/recipes/{recipe}', [ProductionRecipeController::class, 'show'])->name('recipes.show');

        // Production Orders
        Route::get('/orders', [ProductionOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/create', [ProductionOrderController::class, 'create'])->name('orders.create');
        Route::post('/orders', [ProductionOrderController::class, 'store'])->name('orders.store');
        Route::get('/orders/{productionOrder}', [ProductionOrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{productionOrder}/start', [ProductionOrderController::class, 'start'])->name('orders.start');
        Route::post('/orders/{productionOrder}/cancel', [ProductionOrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('/orders/{productionOrder}/issue-materials', [ProductionOrderController::class, 'issueMaterials'])->name('orders.issue-materials');
        Route::post('/orders/{productionOrder}/return-materials', [ProductionOrderController::class, 'returnMaterials'])->name('orders.return-materials');
        Route::post('/orders/{productionOrder}/complete', [ProductionOrderController::class, 'complete'])->name('orders.complete');

        // Production Reports
        Route::get('/reports', [ProductionReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ProductionReportController::class, 'exportCsv'])->name('reports.export');
    });

    // Phase 11: General Ledger & Double-Entry Accounting
    Route::prefix('accounting')->name('accounting.')->group(function () {
        // Chart of Accounts
        Route::resource('chart-of-accounts', ChartOfAccountController::class)->except(['show']);

        // Journal Entries & Manual Vouchers
        Route::get('/journals', [JournalEntryController::class, 'index'])->name('journals.index');
        Route::get('/journals/create', [JournalEntryController::class, 'create'])->name('journals.create');
        Route::post('/journals', [JournalEntryController::class, 'store'])->name('journals.store');
        Route::get('/journals/{journal}', [JournalEntryController::class, 'show'])->name('journals.show');
        Route::post('/journals/{journal}/reverse', [JournalEntryController::class, 'reverse'])->name('journals.reverse');

        // General Ledger Statements & Reports
        Route::get('/ledger', [GeneralLedgerReportController::class, 'ledger'])->name('ledger');
        Route::get('/trial-balance', [GeneralLedgerReportController::class, 'trialBalance'])->name('trial-balance');
        Route::get('/trial-balance/export', [GeneralLedgerReportController::class, 'exportTrialBalanceCsv'])->name('trial-balance.export');
        Route::get('/trial-balance/csv', [GeneralLedgerReportController::class, 'exportTrialBalanceCsv'])->name('trial-balance.csv');
        Route::get('/profit-loss', [GeneralLedgerReportController::class, 'profitLoss'])->name('profit-loss');
        Route::get('/balance-sheet', [GeneralLedgerReportController::class, 'balanceSheet'])->name('balance-sheet');
        Route::get('/reconciliation', [GeneralLedgerReportController::class, 'reconciliation'])->name('reconciliation');

        // Opening Balances Setup
        Route::get('/opening-balances', [GeneralLedgerReportController::class, 'openingBalances'])->name('opening-balances.index');
        Route::post('/opening-balances', [GeneralLedgerReportController::class, 'storeOpeningBalances'])->name('opening-balances.store');
    });

    // Phase 12: Business Settings, Audit Logs, Backup Management & System Administration
    Route::prefix('admin')->name('admin.')->group(function () {
        // Business Settings
        Route::get('/settings', [BusinessSettingController::class, 'index'])->name('settings.index');
        Route::post('/settings', [BusinessSettingController::class, 'update'])->name('settings.update');

        // Audit Trail
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');

        // Database Backups & Recovery Guidelines
        Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('/backups', [BackupController::class, 'create'])->name('backups.create');
        Route::get('/backups/restore-guidelines', [BackupController::class, 'restoreGuidelines'])->name('backups.restore-guidelines');
        Route::get('/backups/{backup}/download', [BackupController::class, 'download'])->name('backups.download');
        Route::delete('/backups/{backup}', [BackupController::class, 'destroy'])->name('backups.destroy');

        // User Management & Security
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');

        // Location Management (Shops, Factories, Warehouses)
        Route::get('/locations', [LocationController::class, 'index'])->name('locations.index');
        Route::get('/locations/create', [LocationController::class, 'create'])->name('locations.create');
        Route::post('/locations', [LocationController::class, 'store'])->name('locations.store');
        Route::get('/locations/{location}/edit', [LocationController::class, 'edit'])->name('locations.edit');
        Route::put('/locations/{location}', [LocationController::class, 'update'])->name('locations.update');
        Route::patch('/locations/{location}/toggle-active', [LocationController::class, 'toggleActive'])->name('locations.toggle-active');

        // System Health & Diagnostics
        Route::get('/health', [SystemHealthController::class, 'index'])->name('health.index');
    });
});
