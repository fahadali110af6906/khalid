<?php

namespace App\Http\Controllers;

use App\Http\Requests\PostSalesReturnRequest;
use App\Http\Requests\StoreCustomerRefundRequest;
use App\Http\Requests\StoreSalesReturnRequest;
use App\Models\Customer;
use App\Models\Location;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Services\Sales\SalesReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class SalesReturnController extends Controller
{
    public function __construct(
        protected SalesReturnService $salesReturnService
    ) {}

    /**
     * Display a listing of sales returns.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $status = $request->query('status', 'all');
        $locationId = $request->query('location_id');
        $customerId = $request->query('customer_id');
        $search = $request->query('search');

        if ($locationId && $locationId !== 'all') {
            if (!$user->hasLocationAccess($locationId)) {
                abort(403, 'Forbidden: You do not have authorization for this location.');
            }
        } elseif ($locationId === 'all' && !$user->isOwner()) {
            abort(403, 'Forbidden: Only the owner can view cross-location sales returns.');
        }

        $query = SalesReturn::authorizedFor($user)
            ->with(['salesInvoice', 'customer', 'location', 'creator'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($locationId && $locationId !== 'all', fn ($q) => $q->where('location_id', $locationId))
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->when($search, function ($q) use ($search) {
                $q->where('return_number', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('business_name', 'like', "%{$search}%")
                         ->orWhere('customer_code', 'like', "%{$search}%");
                  })
                  ->orWhereHas('salesInvoice', function ($iq) use ($search) {
                      $iq->where('invoice_number', 'like', "%{$search}%");
                  });
            })
            ->latest('return_date')
            ->latest('id');

        $returns = $query->paginate(20)->withQueryString();

        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->get()
            : $user->locations()->wherePivot('can_view', true)->get();

        $customers = Customer::authorizedFor($user)->where('is_active', true)->orderBy('business_name')->get();

        return view('sales.returns.index', compact(
            'returns',
            'authorizedLocations',
            'customers',
            'status',
            'locationId',
            'customerId',
            'search'
        ));
    }

    /**
     * Show the form to create a sales return against a sales invoice.
     */
    public function create(Request $request, SalesInvoice $salesInvoice): View
    {
        $user = $request->user();

        if (!$user->hasLocationAccess($salesInvoice->location_id, true)) {
            abort(403, 'Forbidden: You do not have authorization to create returns for this location.');
        }

        if ($salesInvoice->status !== 'posted') {
            return redirect()->route('sales.invoices.show', $salesInvoice)
                ->withErrors(['return' => 'Sales returns can only be created for posted invoices.']);
        }

        $salesInvoice->load([
            'customer',
            'location',
            'items.variant.product',
        ]);

        return view('sales.returns.create', compact('salesInvoice'));
    }

    /**
     * Store a draft sales return.
     */
    public function store(StoreSalesReturnRequest $request, SalesInvoice $salesInvoice): RedirectResponse
    {
        $user = $request->user();

        try {
            $salesReturn = $this->salesReturnService->createDraftReturn(
                $salesInvoice,
                $request->validated(),
                $user
            );

            return redirect()->route('sales.returns.show', $salesReturn)
                ->with('success', "Draft Sales Return #{$salesReturn->return_number} created successfully.");
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withInput()->withErrors(['items' => $e->getMessage()]);
        }
    }

    /**
     * Display a specific sales return.
     */
    public function show(Request $request, SalesReturn $salesReturn): View
    {
        $user = $request->user();

        if (!$user->hasLocationAccess($salesReturn->location_id)) {
            abort(403, 'Forbidden: You do not have authorization to view this sales return.');
        }

        $salesReturn->load([
            'salesInvoice',
            'customer',
            'location',
            'creator',
            'postedBy',
            'creditNote',
            'refunds.creator',
            'items.productVariant.product',
            'items.invoiceItem',
        ]);

        return view('sales.returns.show', compact('salesReturn'));
    }

    /**
     * Post a draft sales return (restock inventory, credit customer khata, generate credit note).
     */
    public function post(PostSalesReturnRequest $request, SalesReturn $salesReturn): RedirectResponse
    {
        $user = $request->user();

        try {
            $postedReturn = $this->salesReturnService->postReturn(
                $salesReturn,
                $request->validated(),
                $user
            );

            return redirect()->route('sales.returns.show', $postedReturn)
                ->with('success', "Sales Return #{$postedReturn->return_number} posted successfully. Stock restocked and Customer Khata credited.");
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withInput()->withErrors(['post' => $e->getMessage()]);
        }
    }

    /**
     * Record a customer cash/bank refund for returned goods.
     */
    public function refund(StoreCustomerRefundRequest $request, SalesReturn $salesReturn): RedirectResponse
    {
        $user = $request->user();

        try {
            $refund = $this->salesReturnService->recordCustomerRefund(
                $salesReturn,
                $request->validated(),
                $user
            );

            return redirect()->route('sales.returns.show', $salesReturn)
                ->with('success', "Customer Refund #{$refund->refund_number} recorded successfully. Customer Khata debited.");
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withInput()->withErrors(['refund' => $e->getMessage()]);
        }
    }
}
