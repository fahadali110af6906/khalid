<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\SupplierPayment;
use App\Services\Accounting\SupplierKhataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function __construct(
        protected SupplierKhataService $khataService
    ) {}

    /**
     * Display a listing of authorized suppliers.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $search = $request->query('search');
        $locationId = $request->query('location_id');
        $status = $request->query('status', 'active');

        if ($locationId && $locationId !== 'all') {
            if (!$user->hasLocationAccess($locationId)) {
                abort(403, 'Forbidden: You do not have authorization to view suppliers for this location.');
            }
        } elseif ($locationId === 'all' && !$user->isOwner()) {
            abort(403, 'Forbidden: Only the owner can view consolidated cross-location suppliers.');
        }

        $query = Supplier::authorizedFor($user)
            ->with(['primaryLocation'])
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($locationId && $locationId !== 'all', fn ($q) => $q->where('primary_location_id', $locationId))
            ->search($search)
            ->orderBy('business_name');

        $suppliers = $query->paginate(15)->withQueryString();

        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->get()
            : $user->locations()->wherePivot('can_view', true)->get();

        return view('suppliers.index', compact('suppliers', 'authorizedLocations', 'search', 'locationId', 'status'));
    }

    /**
     * Show form to create a new supplier.
     */
    public function create(Request $request): View
    {
        $user = $request->user();

        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->get()
            : $user->locations()->wherePivot('can_operate', true)->get();

        return view('suppliers.create', compact('authorizedLocations'));
    }

    /**
     * Store a newly created supplier.
     */
    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $supplier = DB::transaction(function () use ($validated, $user) {
            $supplier = Supplier::create([
                ...$validated,
                'created_by' => $user->id,
            ]);

            // If non-zero opening balance is provided, record opening ledger entry
            $openingBal = (float) ($validated['opening_balance'] ?? 0);
            if ($openingBal != 0) {
                // Opening payable is a Credit if positive (KTM owes supplier), Debit if negative (advance)
                $credit = $openingBal > 0 ? $openingBal : 0.00;
                $debit = $openingBal < 0 ? abs($openingBal) : 0.00;

                $this->khataService->recordEntry(
                    supplier: $supplier,
                    locationId: $supplier->primary_location_id,
                    entryType: 'opening_balance',
                    debitAmount: $debit,
                    creditAmount: $credit,
                    entryDate: now()->toDateString(),
                    user: $user,
                    description: 'Initial Opening Balance / ابتدائی کھاتہ بیلنس'
                );
            }

            return $supplier;
        });

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', "Supplier {$supplier->business_name} created successfully.");
    }

    /**
     * Display the specified supplier profile and Khata summary.
     */
    public function show(Request $request, Supplier $supplier): View
    {
        $user = $request->user();

        if (!$user->hasLocationAccess($supplier->primary_location_id)) {
            abort(403, 'Forbidden: You do not have authorization to view this supplier.');
        }

        $khataSummary = $this->khataService->calculateBalance($supplier);

        $recentOrders = PurchaseOrder::where('supplier_id', $supplier->id)
            ->with('location')
            ->latest('id')
            ->take(5)
            ->get();

        $recentPayments = SupplierPayment::where('supplier_id', $supplier->id)
            ->with('location')
            ->latest('id')
            ->take(5)
            ->get();

        return view('suppliers.show', compact('supplier', 'khataSummary', 'recentOrders', 'recentPayments'));
    }

    /**
     * Show form to edit supplier.
     */
    public function edit(Request $request, Supplier $supplier): View
    {
        $user = $request->user();

        if (!$user->hasLocationAccess($supplier->primary_location_id, true)) {
            abort(403, 'Forbidden: You do not have authorization to edit this supplier.');
        }

        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->get()
            : $user->locations()->wherePivot('can_operate', true)->get();

        return view('suppliers.edit', compact('supplier', 'authorizedLocations'));
    }

    /**
     * Update supplier master data.
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', "Supplier {$supplier->business_name} updated successfully.");
    }

    /**
     * Toggle active/inactive status without deleting data.
     */
    public function toggleStatus(Request $request, Supplier $supplier): RedirectResponse
    {
        $user = $request->user();

        if (!$user->hasLocationAccess($supplier->primary_location_id, true)) {
            abort(403, 'Forbidden: You do not have authorization to change status for this supplier.');
        }

        $supplier->is_active = !$supplier->is_active;
        $supplier->save();

        $statusLabel = $supplier->is_active ? 'activated' : 'deactivated';

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', "Supplier {$supplier->business_name} has been {$statusLabel}.");
    }

    /**
     * Full supplier Khata (ledger statement) with date and location filtering.
     */
    public function ledger(Request $request, Supplier $supplier): View
    {
        $user = $request->user();

        if (!$user->hasLocationAccess($supplier->primary_location_id)) {
            abort(403, 'Forbidden: You do not have authorization to access this supplier Khata.');
        }

        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $locationId = $request->query('location_id');

        if ($locationId && $locationId !== 'all') {
            if (!$user->hasLocationAccess($locationId)) {
                abort(403, 'Forbidden: You do not have authorization to view ledger for this location.');
            }
        } else {
            $locationId = null;
        }

        $khataSummary = $this->khataService->calculateBalance($supplier, $locationId, $fromDate, $toDate);

        // Fetch entries ordered by date and id
        $entries = SupplierLedgerEntry::where('supplier_id', $supplier->id)
            ->with(['location', 'creator'])
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->when($fromDate, fn ($q) => $q->where('entry_date', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->where('entry_date', '<=', $toDate))
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        // Calculate running balance per entry: Opening + Credits (purchases) - Debits (payments)
        $runningBalance = $khataSummary['period_opening_balance'];
        $calculatedEntries = [];

        foreach ($entries as $entry) {
            $runningBalance = bcsub(
                bcadd((string) $runningBalance, (string) $entry->credit_amount, 2),
                (string) $entry->debit_amount,
                2
            );

            $calculatedEntries[] = [
                'entry' => $entry,
                'running_balance' => $runningBalance,
            ];
        }

        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->get()
            : $user->locations()->wherePivot('can_view', true)->get();

        return view('suppliers.ledger', compact(
            'supplier',
            'khataSummary',
            'calculatedEntries',
            'authorizedLocations',
            'fromDate',
            'toDate',
            'locationId'
        ));
    }
}
