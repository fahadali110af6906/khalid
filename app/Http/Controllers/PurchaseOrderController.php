<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseOrderRequest;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\Purchasing\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function __construct(
        protected PurchaseService $purchaseService
    ) {}

    /**
     * Display a listing of purchase orders.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $status = $request->query('status');
        $locationId = $request->query('location_id');
        $supplierId = $request->query('supplier_id');
        $search = $request->query('search');

        if ($locationId && $locationId !== 'all') {
            if (!$user->hasLocationAccess($locationId)) {
                abort(403, 'Forbidden: You do not have authorization for this location.');
            }
        } elseif ($locationId === 'all' && !$user->isOwner()) {
            abort(403, 'Forbidden: Only the owner can view cross-location purchase orders.');
        }

        $query = PurchaseOrder::authorizedFor($user)
            ->with(['supplier', 'location', 'creator'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($locationId && $locationId !== 'all', fn ($q) => $q->where('location_id', $locationId))
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('purchase_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn ($s) => $s->where('business_name', 'like', "%{$search}%"));
                });
            })
            ->latest('order_date')
            ->latest('id');

        $orders = $query->paginate(15)->withQueryString();

        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->get()
            : $user->locations()->wherePivot('can_view', true)->get();

        $suppliers = Supplier::authorizedFor($user)->where('is_active', true)->orderBy('business_name')->get();

        return view('purchases.index', compact(
            'orders',
            'authorizedLocations',
            'suppliers',
            'status',
            'locationId',
            'supplierId',
            'search'
        ));
    }

    /**
     * Show form to create a new purchase order.
     */
    public function create(Request $request): View
    {
        $user = $request->user();

        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->get()
            : $user->locations()->wherePivot('can_operate', true)->get();

        $suppliers = Supplier::authorizedFor($user)->where('is_active', true)->orderBy('business_name')->get();

        $products = Product::where('is_active', true)
            ->with(['variants' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get();

        return view('purchases.create', compact('authorizedLocations', 'suppliers', 'products'));
    }

    /**
     * Store a newly created purchase order (draft).
     */
    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $user = $request->user();
        $order = $this->purchaseService->createDraft($request->validated(), $user);

        return redirect()->route('purchases.show', $order)
            ->with('success', "Purchase Order {$order->purchase_number} created as draft.");
    }

    /**
     * Display purchase order details.
     */
    public function show(Request $request, PurchaseOrder $purchaseOrder): View
    {
        $user = $request->user();

        if (!$user->hasLocationAccess($purchaseOrder->location_id)) {
            abort(403, 'Forbidden: You do not have authorization to view this purchase order.');
        }

        $purchaseOrder->load([
            'supplier',
            'location',
            'creator',
            'orderer',
            'items.productVariant.product',
            'goodsReceipts.items',
            'goodsReceipts.receivedBy',
            'payments.creator',
        ]);

        return view('purchases.show', compact('purchaseOrder'));
    }

    /**
     * Show form to edit a draft purchase order.
     */
    public function edit(Request $request, PurchaseOrder $purchaseOrder): View
    {
        $user = $request->user();

        if (!$user->hasLocationAccess($purchaseOrder->location_id, true)) {
            abort(403, 'Forbidden: You do not have authorization to edit this purchase order.');
        }

        if ($purchaseOrder->status !== 'draft') {
            return redirect()->route('purchases.show', $purchaseOrder)
                ->with('error', "Only draft purchase orders can be edited.");
        }

        $purchaseOrder->load(['items.productVariant.product']);

        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->get()
            : $user->locations()->wherePivot('can_operate', true)->get();

        $suppliers = Supplier::authorizedFor($user)->where('is_active', true)->orderBy('business_name')->get();

        $products = Product::where('is_active', true)
            ->with(['variants' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get();

        return view('purchases.edit', compact('purchaseOrder', 'authorizedLocations', 'suppliers', 'products'));
    }

    /**
     * Update draft purchase order.
     */
    public function update(StorePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $user = $request->user();

        if ($purchaseOrder->status !== 'draft') {
            return redirect()->route('purchases.show', $purchaseOrder)
                ->with('error', "Only draft purchase orders can be updated.");
        }

        $this->purchaseService->updateDraft($purchaseOrder, $request->validated(), $user);

        return redirect()->route('purchases.show', $purchaseOrder)
            ->with('success', "Purchase Order {$purchaseOrder->purchase_number} updated successfully.");
    }

    /**
     * Approve a draft purchase order.
     */
    public function approve(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $user = $request->user();

        if (!$user->hasLocationAccess($purchaseOrder->location_id, true)) {
            abort(403, 'Forbidden: You do not have authorization to approve this purchase order.');
        }

        $this->purchaseService->approveOrder($purchaseOrder, $user);

        return redirect()->route('purchases.show', $purchaseOrder)
            ->with('success', "Purchase Order {$purchaseOrder->purchase_number} approved and ordered.");
    }

    /**
     * Cancel a purchase order.
     */
    public function cancel(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $user = $request->user();

        if (!$user->hasLocationAccess($purchaseOrder->location_id, true)) {
            abort(403, 'Forbidden: You do not have authorization to cancel this purchase order.');
        }

        $reason = $request->input('reason');
        $this->purchaseService->cancelOrder($purchaseOrder, $user, $reason);

        return redirect()->route('purchases.show', $purchaseOrder)
            ->with('success', "Purchase Order {$purchaseOrder->purchase_number} has been cancelled.");
    }
}
