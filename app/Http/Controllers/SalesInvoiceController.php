<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Http\Requests\StoreSalesInvoiceRequest;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Services\Sales\SalesPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class SalesInvoiceController extends Controller
{
    public function __construct(
        protected SalesPostingService $salesService
    ) {}

    /**
     * Display list of wholesale sales invoices.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $status = $request->query('status', 'all');
        $paymentStatus = $request->query('payment_status', 'all');
        $locationId = $request->query('location_id');
        $customerId = $request->query('customer_id');
        $search = $request->query('search');

        if ($locationId && $locationId !== 'all') {
            if (!$user->hasLocationAccess($locationId)) {
                abort(403, 'Forbidden: You do not have authorization to view invoices for this location.');
            }
        } elseif ($locationId === 'all' && !$user->isOwner()) {
            abort(403, 'Forbidden: Only the owner can view consolidated cross-location invoices.');
        }

        $query = SalesInvoice::authorizedFor($user)
            ->with(['customer', 'location', 'creator'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($paymentStatus !== 'all', fn ($q) => $q->where('payment_status', $paymentStatus))
            ->when($locationId && $locationId !== 'all', fn ($q) => $q->where('location_id', $locationId))
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->when($search, function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('business_name', 'like', "%{$search}%")
                         ->orWhere('customer_code', 'like', "%{$search}%");
                  });
            })
            ->latest('invoice_date')
            ->latest('id');

        $invoices = $query->paginate(15)->withQueryString();

        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->get()
            : $user->locations()->wherePivot('can_view', true)->get();

        $customers = Customer::authorizedFor($user)->active()->orderBy('business_name')->get();

        return view('sales.invoices.index', compact(
            'invoices',
            'authorizedLocations',
            'customers',
            'status',
            'paymentStatus',
            'locationId',
            'customerId',
            'search'
        ));
    }

    /**
     * Show the form for creating a new wholesale sales invoice.
     */
    public function create(Request $request): View
    {
        $user = $request->user();

        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->get()
            : $user->locations()->wherePivot('can_operate', true)->get();

        $customers = Customer::authorizedFor($user)->active()->orderBy('business_name')->get();

        $products = Product::where('is_active', true)
            ->with(['variants' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get();

        return view('sales.invoices.create', compact('authorizedLocations', 'customers', 'products'));
    }

    /**
     * Store a newly created sales invoice (Draft or Immediately Posted).
     */
    public function store(StoreSalesInvoiceRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        try {
            // 1. Create the draft invoice
            $invoice = $this->salesService->createDraft($validated, $user);

            // 2. If immediate posting requested, post it
            if ($validated['action'] === 'post_immediately') {
                $initialPayment = $validated['initial_payment'] ?? null;
                $this->salesService->postInvoice($invoice, $user, $initialPayment);

                return redirect()->route('sales.invoices.show', $invoice)
                    ->with('success', "Sales Invoice #{$invoice->invoice_number} successfully posted. Stock deducted and Customer Khata debited.");
            }

            return redirect()->route('sales.invoices.show', $invoice)
                ->with('success', "Draft Invoice #{$invoice->invoice_number} saved. (No inventory or ledger changes made).");

        } catch (InsufficientStockException $e) {
            return back()->withInput()->withErrors(['stock' => $e->getMessage()]);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withInput()->withErrors(['general' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified invoice details.
     */
    public function show(SalesInvoice $invoice, Request $request): View
    {
        $user = $request->user();

        if (!$user->hasLocationAccess($invoice->location_id)) {
            abort(403, 'Forbidden: You do not have authorization to view this invoice.');
        }

        $invoice->load([
            'customer',
            'location',
            'items.variant.product',
            'payments.creator',
            'creator',
            'poster',
        ]);

        return view('sales.invoices.show', compact('invoice'));
    }

    /**
     * Print view for thermal or A4 invoice format.
     */
    public function print(SalesInvoice $invoice, Request $request): View
    {
        $user = $request->user();

        if (!$user->hasLocationAccess($invoice->location_id)) {
            abort(403, 'Forbidden: You do not have authorization to view this invoice.');
        }

        $invoice->load([
            'customer',
            'location',
            'items.variant.product',
            'payments',
            'creator',
        ]);

        return view('sales.invoices.print', compact('invoice'));
    }

    /**
     * Post a previously saved draft invoice.
     */
    public function post(SalesInvoice $invoice, Request $request): RedirectResponse
    {
        $user = $request->user();

        if (!$user->hasLocationAccess($invoice->location_id)) {
            abort(403, 'Forbidden: You do not have authorization to post this invoice.');
        }

        try {
            $initialPayment = null;
            if ($request->filled('payment_amount') && (float) $request->input('payment_amount') > 0) {
                $initialPayment = [
                    'amount' => $request->input('payment_amount'),
                    'payment_method' => $request->input('payment_method', 'cash'),
                    'reference_number' => $request->input('reference_number'),
                    'notes' => $request->input('notes'),
                ];
            }

            $this->salesService->postInvoice($invoice, $user, $initialPayment);

            return redirect()->route('sales.invoices.show', $invoice)
                ->with('success', "Invoice #{$invoice->invoice_number} successfully posted to inventory and customer Khata.");

        } catch (InsufficientStockException $e) {
            return back()->withErrors(['stock' => $e->getMessage()]);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }
    }

    /**
     * Cancel a draft sales invoice.
     */
    public function cancel(SalesInvoice $invoice, Request $request): RedirectResponse
    {
        $user = $request->user();

        if (!$user->hasLocationAccess($invoice->location_id)) {
            abort(403, 'Forbidden: You do not have authorization to cancel this invoice.');
        }

        if ($invoice->status !== 'draft') {
            return redirect()->route('sales.invoices.show', $invoice)
                ->with('error', "Posted or completed sales invoices cannot be cancelled directly; please issue a Sales Return or Credit Note.");
        }

        $invoice->update([
            'status' => 'cancelled',
        ]);

        return redirect()->route('sales.invoices.index')
            ->with('success', "Draft Sales Invoice #{$invoice->invoice_number} has been cancelled.");
    }
}
