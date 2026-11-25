<?php

namespace App\Http\Controllers;

use App\Http\Requests\PostPurchaseReturnRequest;
use App\Http\Requests\StorePurchaseReturnRequest;
use App\Models\GoodsReceipt;
use App\Models\Location;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Services\Purchasing\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class PurchaseReturnController extends Controller
{
    public function __construct(
        protected PurchaseService $purchaseService
    ) {}

    /**
     * Display a listing of purchase returns.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $status = $request->query('status', 'all');
        $locationId = $request->query('location_id');
        $supplierId = $request->query('supplier_id');
        $search = $request->query('search');

        if ($locationId && $locationId !== 'all') {
            if (!$user->hasLocationAccess($locationId)) {
                abort(403, 'Forbidden: You do not have authorization for this location.');
            }
        } elseif ($locationId === 'all' && !$user->isOwner()) {
            abort(403, 'Forbidden: Only the owner can view cross-location purchase returns.');
        }

        $query = PurchaseReturn::authorizedFor($user)
            ->with(['goodsReceipt', 'purchaseOrder', 'supplier', 'location', 'creator'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($locationId && $locationId !== 'all', fn ($q) => $q->where('location_id', $locationId))
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->when($search, function ($q) use ($search) {
                $q->where('return_number', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function ($sq) use ($search) {
                      $sq->where('business_name', 'like', "%{$search}%")
                         ->orWhere('supplier_code', 'like', "%{$search}%");
                  })
                  ->orWhereHas('goodsReceipt', function ($gq) use ($search) {
                      $gq->where('receipt_number', 'like', "%{$search}%");
                  });
            })
            ->latest('return_date')
            ->latest('id');

        $returns = $query->paginate(20)->withQueryString();

        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->get()
            : $user->locations()->wherePivot('can_view', true)->get();

        $suppliers = Supplier::authorizedFor($user)->where('is_active', true)->orderBy('business_name')->get();

        return view('purchases.returns.index', compact(
            'returns',
            'authorizedLocations',
            'suppliers',
            'status',
            'locationId',
            'supplierId',
            'search'
        ));
    }

    /**
     * Show the form to create a purchase return against a goods receipt.
     */
    public function create(Request $request, GoodsReceipt $goodsReceipt): View
    {
        $user = $request->user();

        if (!$user->hasLocationAccess($goodsReceipt->location_id, true)) {
            abort(403, 'Forbidden: You do not have authorization to create returns for this location.');
        }

        $goodsReceipt->load([
            'supplier',
            'location',
            'order',
            'items.productVariant.product',
            'items.orderItem',
        ]);

        return view('purchases.returns.create', compact('goodsReceipt'));
    }

    /**
     * Store a draft purchase return.
     */
    public function store(StorePurchaseReturnRequest $request, GoodsReceipt $goodsReceipt): RedirectResponse
    {
        $user = $request->user();

        try {
            $purchaseReturn = $this->purchaseService->createDraftReturn(
                $goodsReceipt,
                $request->validated(),
                $user
            );

            return redirect()->route('purchases.returns.show', $purchaseReturn)
                ->with('success', "Draft Purchase Return #{$purchaseReturn->return_number} created successfully.");
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withInput()->withErrors(['items' => $e->getMessage()]);
        }
    }

    /**
     * Display a specific purchase return.
     */
    public function show(Request $request, PurchaseReturn $purchaseReturn): View
    {
        $user = $request->user();

        if (!$user->hasLocationAccess($purchaseReturn->location_id)) {
            abort(403, 'Forbidden: You do not have authorization to view this purchase return.');
        }

        $purchaseReturn->load([
            'goodsReceipt',
            'purchaseOrder',
            'supplier',
            'location',
            'creator',
            'postedBy',
            'debitNote',
            'items.productVariant.product',
            'items.goodsReceiptItem',
        ]);

        return view('purchases.returns.show', compact('purchaseReturn'));
    }

    /**
     * Post a draft purchase return (deduct stock, credit supplier return debit).
     */
    public function post(PostPurchaseReturnRequest $request, PurchaseReturn $purchaseReturn): RedirectResponse
    {
        $user = $request->user();

        try {
            $postedReturn = $this->purchaseService->postReturn(
                $purchaseReturn,
                $request->validated(),
                $user
            );

            return redirect()->route('purchases.returns.show', $postedReturn)
                ->with('success', "Purchase Return #{$postedReturn->return_number} posted successfully. Inventory deducted and Supplier Khata debited.");
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withInput()->withErrors(['post' => $e->getMessage()]);
        }
    }
}
