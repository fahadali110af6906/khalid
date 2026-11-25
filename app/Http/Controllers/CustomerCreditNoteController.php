<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerCreditNoteRequest;
use App\Models\Customer;
use App\Models\CustomerCreditNote;
use App\Models\Location;
use App\Models\SalesInvoice;
use App\Services\Sales\SalesReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class CustomerCreditNoteController extends Controller
{
    public function __construct(
        protected SalesReturnService $salesReturnService
    ) {}

    /**
     * Display a listing of customer credit notes.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $type = $request->query('type', 'all');
        $locationId = $request->query('location_id');
        $customerId = $request->query('customer_id');
        $search = $request->query('search');

        if ($locationId && $locationId !== 'all') {
            if (!$user->hasLocationAccess($locationId)) {
                abort(403, 'Forbidden: You do not have authorization for this location.');
            }
        } elseif ($locationId === 'all' && !$user->isOwner()) {
            abort(403, 'Forbidden: Only the owner can view cross-location credit notes.');
        }

        $query = CustomerCreditNote::authorizedFor($user)
            ->with(['customer', 'location', 'salesReturn', 'salesInvoice', 'creator'])
            ->when($type !== 'all', fn ($q) => $q->where('credit_note_type', $type))
            ->when($locationId && $locationId !== 'all', fn ($q) => $q->where('location_id', $locationId))
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->when($search, function ($q) use ($search) {
                $q->where('credit_note_number', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('business_name', 'like', "%{$search}%")
                         ->orWhere('customer_code', 'like', "%{$search}%");
                  });
            })
            ->latest('note_date')
            ->latest('id');

        $creditNotes = $query->paginate(20)->withQueryString();

        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->get()
            : $user->locations()->wherePivot('can_view', true)->get();

        $customers = Customer::authorizedFor($user)->where('is_active', true)->orderBy('business_name')->get();

        return view('customer_credit_notes.index', compact(
            'creditNotes',
            'authorizedLocations',
            'customers',
            'type',
            'locationId',
            'customerId',
            'search'
        ));
    }

    /**
     * Show the form to record a financial-only credit note.
     */
    public function create(Request $request): View
    {
        $user = $request->user();
        $customerId = $request->query('customer_id');
        $selectedCustomer = $customerId ? Customer::find($customerId) : null;

        if ($selectedCustomer && !$user->hasLocationAccess($selectedCustomer->primary_location_id)) {
            abort(403, 'Forbidden: You do not have authorization for this customer.');
        }

        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->get()
            : $user->locations()->wherePivot('can_operate', true)->get();

        $customers = Customer::authorizedFor($user)->where('is_active', true)->orderBy('business_name')->get();

        $invoices = collect();
        if ($selectedCustomer) {
            $invoices = SalesInvoice::where('customer_id', $selectedCustomer->id)
                ->where('status', 'posted')
                ->latest('invoice_date')
                ->take(30)
                ->get();
        }

        return view('customer_credit_notes.create', compact(
            'authorizedLocations',
            'customers',
            'selectedCustomer',
            'invoices'
        ));
    }

    /**
     * Store a financial-only customer credit note.
     */
    public function store(StoreCustomerCreditNoteRequest $request): RedirectResponse
    {
        $user = $request->user();

        try {
            $customer = Customer::findOrFail($request->validated('customer_id'));

            $creditNote = $this->salesReturnService->recordFinancialCreditNote(
                $customer,
                $request->validated(),
                $user
            );

            return redirect()->route('customer_credit_notes.show', $creditNote)
                ->with('success', "Customer Credit Note #{$creditNote->credit_note_number} recorded successfully. Customer Khata credited.");
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }
    }

    /**
     * Display a specific customer credit note.
     */
    public function show(Request $request, CustomerCreditNote $customerCreditNote): View
    {
        $user = $request->user();

        if (!$user->hasLocationAccess($customerCreditNote->location_id)) {
            abort(403, 'Forbidden: You do not have authorization to view this credit note.');
        }

        $customerCreditNote->load([
            'customer',
            'location',
            'salesReturn',
            'salesInvoice',
            'creator',
            'postedBy',
        ]);

        return view('customer_credit_notes.show', compact('customerCreditNote'));
    }
}
