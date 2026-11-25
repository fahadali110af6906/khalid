<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\Location;
use App\Services\Accounting\CustomerKhataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerKhataService $khataService
    ) {}

    /**
     * Display a listing of authorized customers.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $search = $request->query('search');
        $locationId = $request->query('location_id');
        $status = $request->query('status', 'active');

        // Verify requested location filter
        if ($locationId && $locationId !== 'all') {
            if (!$user->hasLocationAccess($locationId)) {
                abort(403, 'Forbidden: You do not have authorization to view customers for this location.');
            }
        } elseif ($locationId === 'all' && !$user->isOwner()) {
            abort(403, 'Forbidden: Only the owner can view consolidated cross-location customers.');
        }

        $query = Customer::authorizedFor($user)
            ->with(['primaryLocation'])
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($locationId && $locationId !== 'all', fn ($q) => $q->where('primary_location_id', $locationId))
            ->search($search)
            ->orderBy('business_name');

        $customers = $query->paginate(15)->withQueryString();

        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->get()
            : $user->locations()->wherePivot('can_view', true)->get();

        return view('customers.index', compact('customers', 'authorizedLocations', 'search', 'locationId', 'status'));
    }

    /**
     * Show form to create a new customer.
     */
    public function create(Request $request): View
    {
        $user = $request->user();

        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->get()
            : $user->locations()->wherePivot('can_operate', true)->get();

        return view('customers.create', compact('authorizedLocations'));
    }

    /**
     * Store a newly created customer.
     */
    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $customer = DB::transaction(function () use ($validated, $user) {
            $customer = Customer::create([
                ...$validated,
                'created_by' => $user->id,
            ]);

            // If non-zero opening balance is provided, record initial auditable ledger entry
            $openingBal = (float) ($validated['opening_balance'] ?? 0);
            if ($openingBal > 0) {
                $this->khataService->recordEntry(
                    customer: $customer,
                    locationId: $customer->primary_location_id,
                    entryType: 'opening_balance',
                    debitAmount: $openingBal,
                    creditAmount: 0.00,
                    entryDate: now()->toDateString(),
                    user: $user,
                    description: 'Initial Opening Receivable Balance upon registration',
                    referenceType: Customer::class,
                    referenceId: $customer->id,
                    referenceNumber: 'OPN-' . $customer->customer_code
                );
            } elseif ($openingBal < 0) {
                $this->khataService->recordEntry(
                    customer: $customer,
                    locationId: $customer->primary_location_id,
                    entryType: 'opening_balance',
                    debitAmount: 0.00,
                    creditAmount: abs($openingBal),
                    entryDate: now()->toDateString(),
                    user: $user,
                    description: 'Initial Advance Credit Balance upon registration',
                    referenceType: Customer::class,
                    referenceId: $customer->id,
                    referenceNumber: 'OPN-' . $customer->customer_code
                );
            }

            return $customer;
        });

        return redirect()->route('customers.show', $customer)
            ->with('success', "Customer {$customer->business_name} ({$customer->customer_code}) created successfully.");
    }

    /**
     * Display customer detail and Khata overview.
     */
    public function show(Request $request, Customer $customer): View
    {
        $user = $request->user();

        if (!$user->hasLocationAccess($customer->primary_location_id)) {
            abort(403, 'Forbidden: You do not have authorization to view this customer.');
        }

        $customer->load(['primaryLocation', 'creator']);
        $khataSummary = $this->khataService->calculateBalance($customer);
        $recentEntries = $customer->ledgerEntries()->with(['location', 'creator'])->latest('entry_date')->take(5)->get();

        return view('customers.show', compact('customer', 'khataSummary', 'recentEntries'));
    }

    /**
     * Show form to edit customer.
     */
    public function edit(Request $request, Customer $customer): View
    {
        $user = $request->user();

        if (!$user->hasLocationAccess($customer->primary_location_id, true)) {
            abort(403, 'Forbidden: You do not have authorization to edit this customer.');
        }

        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->get()
            : $user->locations()->wherePivot('can_operate', true)->get();

        return view('customers.edit', compact('customer', 'authorizedLocations'));
    }

    /**
     * Update customer details.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validated();
        $customer->update($validated);

        return redirect()->route('customers.show', $customer)
            ->with('success', "Customer {$customer->business_name} updated successfully.");
    }

    /**
     * Toggle active/inactive status without deleting data.
     */
    public function toggleStatus(Request $request, Customer $customer): RedirectResponse
    {
        $user = $request->user();

        if (!$user->hasLocationAccess($customer->primary_location_id, true)) {
            abort(403, 'Forbidden: You do not have authorization to change status for this customer.');
        }

        $customer->is_active = !$customer->is_active;
        $customer->save();

        $statusLabel = $customer->is_active ? 'activated' : 'deactivated';

        return redirect()->route('customers.show', $customer)
            ->with('success', "Customer {$customer->business_name} has been {$statusLabel}.");
    }

    /**
     * Full customer Khata (ledger statement) with date and location filtering.
     */
    public function ledger(Request $request, Customer $customer): View
    {
        $user = $request->user();

        if (!$user->hasLocationAccess($customer->primary_location_id)) {
            abort(403, 'Forbidden: You do not have authorization to access this customer Khata.');
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

        $khataSummary = $this->khataService->calculateBalance($customer, $locationId, $fromDate, $toDate);

        // Fetch entries ordered by date and id
        $entries = CustomerLedgerEntry::where('customer_id', $customer->id)
            ->with(['location', 'creator'])
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->when($fromDate, fn ($q) => $q->where('entry_date', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->where('entry_date', '<=', $toDate))
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        // Calculate running balance per entry
        $runningBalance = $khataSummary['period_opening_balance'];
        $calculatedEntries = [];

        foreach ($entries as $entry) {
            $runningBalance = bcsub(
                bcadd((string) $runningBalance, (string) $entry->debit_amount, 2),
                (string) $entry->credit_amount,
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

        return view('customers.ledger', compact(
            'customer',
            'khataSummary',
            'calculatedEntries',
            'fromDate',
            'toDate',
            'locationId',
            'authorizedLocations'
        ));
    }
}
