<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Payment;
use App\Models\SalesInvoice;
use App\Services\Sales\SalesPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class PaymentController extends Controller
{
    public function __construct(
        protected SalesPostingService $salesService
    ) {}

    /**
     * Display a listing of payments/receipts.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $locationId = $request->query('location_id');
        $customerId = $request->query('customer_id');
        $paymentMethod = $request->query('payment_method', 'all');
        $search = $request->query('search');

        if ($locationId && $locationId !== 'all') {
            if (!$user->hasLocationAccess($locationId)) {
                abort(403, 'Forbidden: You do not have authorization to view payments for this location.');
            }
        } elseif ($locationId === 'all' && !$user->isOwner()) {
            abort(403, 'Forbidden: Only the owner can view consolidated cross-location payments.');
        }

        $query = Payment::authorizedFor($user)
            ->with(['customer', 'location', 'invoice', 'creator'])
            ->when($paymentMethod !== 'all', fn ($q) => $q->where('payment_method', $paymentMethod))
            ->when($locationId && $locationId !== 'all', fn ($q) => $q->where('location_id', $locationId))
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->when($search, function ($q) use ($search) {
                $q->where('payment_number', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('business_name', 'like', "%{$search}%")
                         ->orWhere('customer_code', 'like', "%{$search}%");
                  });
            })
            ->latest('payment_date')
            ->latest('id');

        $payments = $query->paginate(20)->withQueryString();

        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->get()
            : $user->locations()->wherePivot('can_view', true)->get();

        $customers = Customer::authorizedFor($user)->active()->orderBy('business_name')->get();

        return view('payments.index', compact(
            'payments',
            'authorizedLocations',
            'customers',
            'paymentMethod',
            'locationId',
            'customerId',
            'search'
        ));
    }

    /**
     * Record a payment against an outstanding sales invoice.
     */
    public function storeForInvoice(StorePaymentRequest $request, SalesInvoice $invoice): RedirectResponse
    {
        $user = $request->user();

        try {
            $payment = $this->salesService->recordPayment($invoice, $request->validated(), $user);

            return redirect()->route('sales.invoices.show', $invoice)
                ->with('success', "Payment receipt #{$payment->payment_number} (PKR " . number_format($payment->amount, 2) . ") successfully recorded and credited to Customer Khata.");

        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }
    }

    /**
     * Record a direct on-account customer payment / advance.
     */
    public function store(StorePaymentRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $customerId = $validated['customer_id'];
        $locationId = (int) $validated['location_id'];

        $customer = Customer::findOrFail($customerId);

        try {
            $payment = $this->salesService->recordDirectPayment($customer, $locationId, $validated, $user);

            return redirect()->route('customers.ledger', $customer)
                ->with('success', "On-account payment #{$payment->payment_number} (PKR " . number_format($payment->amount, 2) . ") successfully recorded and credited to {$customer->business_name}'s Khata.");

        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified payment receipt voucher.
     */
    public function show(Payment $payment, Request $request): View
    {
        $user = $request->user();

        if (!$user->hasLocationAccess($payment->location_id)) {
            abort(403, 'Forbidden: You do not have authorization to view this payment receipt.');
        }

        $payment->load(['customer', 'location', 'invoice', 'creator']);

        return view('payments.show', compact('payment'));
    }
}
