<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierPaymentRequest;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\Accounting\SupplierKhataService;
use App\Services\Purchasing\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class SupplierPaymentController extends Controller
{
    public function __construct(
        protected PurchaseService $purchaseService,
        protected SupplierKhataService $khataService
    ) {}

    /**
     * Display listing of supplier payments.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $locationId = $request->query('location_id');
        $supplierId = $request->query('supplier_id');
        $paymentMethod = $request->query('payment_method', 'all');
        $search = $request->query('search');

        if ($locationId && $locationId !== 'all') {
            if (!$user->hasLocationAccess($locationId)) {
                abort(403, 'Forbidden: You do not have authorization to view payments for this location.');
            }
        } elseif ($locationId === 'all' && !$user->isOwner()) {
            abort(403, 'Forbidden: Only the owner can view cross-location supplier payments.');
        }

        $query = SupplierPayment::authorizedFor($user)
            ->with(['supplier', 'location', 'purchaseOrder', 'creator'])
            ->when($paymentMethod !== 'all', fn ($q) => $q->where('payment_method', $paymentMethod))
            ->when($locationId && $locationId !== 'all', fn ($q) => $q->where('location_id', $locationId))
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->when($search, function ($q) use ($search) {
                $q->where('payment_number', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function ($sq) use ($search) {
                      $sq->where('business_name', 'like', "%{$search}%")
                         ->orWhere('supplier_code', 'like', "%{$search}%");
                  });
            })
            ->latest('payment_date')
            ->latest('id');

        $payments = $query->paginate(20)->withQueryString();

        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->get()
            : $user->locations()->wherePivot('can_view', true)->get();

        $suppliers = Supplier::authorizedFor($user)->where('is_active', true)->orderBy('business_name')->get();

        return view('supplier_payments.index', compact(
            'payments',
            'authorizedLocations',
            'suppliers',
            'paymentMethod',
            'locationId',
            'supplierId',
            'search'
        ));
    }

    /**
     * Show form to record a payment to a supplier.
     */
    public function create(Request $request): View
    {
        $user = $request->user();
        $supplierId = $request->query('supplier_id');
        $purchaseOrderId = $request->query('purchase_order_id');

        $selectedSupplier = $supplierId ? Supplier::find($supplierId) : null;
        $selectedOrder = $purchaseOrderId ? PurchaseOrder::find($purchaseOrderId) : null;

        if ($selectedSupplier && !$user->hasLocationAccess($selectedSupplier->primary_location_id)) {
            abort(403, 'Forbidden: You do not have authorization for this supplier.');
        }

        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->get()
            : $user->locations()->wherePivot('can_operate', true)->get();

        $suppliers = Supplier::authorizedFor($user)->where('is_active', true)->orderBy('business_name')->get();

        $supplierBalance = null;
        if ($selectedSupplier) {
            $supplierBalance = $this->khataService->calculateBalance($selectedSupplier);
        }

        return view('supplier_payments.create', compact(
            'authorizedLocations',
            'suppliers',
            'selectedSupplier',
            'selectedOrder',
            'supplierBalance'
        ));
    }

    /**
     * Store a supplier payment.
     */
    public function store(StoreSupplierPaymentRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $supplier = Supplier::findOrFail($validated['supplier_id']);

        try {
            $payment = $this->purchaseService->recordPayment($supplier, $validated, $user);

            return redirect()->route('supplier-payments.show', $payment)
                ->with('success', "Payment voucher #{$payment->payment_number} (PKR " . number_format($payment->amount, 2) . ") successfully recorded and debited to Supplier Khata.");
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }
    }

    /**
     * Display payment voucher.
     */
    public function show(Request $request, SupplierPayment $supplierPayment): View
    {
        $user = $request->user();

        if (!$user->hasLocationAccess($supplierPayment->location_id)) {
            abort(403, 'Forbidden: You do not have authorization to view this payment.');
        }

        $supplierPayment->load(['supplier', 'location', 'purchaseOrder', 'creator']);

        return view('supplier_payments.show', compact('supplierPayment'));
    }
}
