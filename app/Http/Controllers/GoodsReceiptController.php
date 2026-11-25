<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReceiveGoodsRequest;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Services\Purchasing\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class GoodsReceiptController extends Controller
{
    public function __construct(
        protected PurchaseService $purchaseService
    ) {}

    /**
     * Show form to receive goods against an approved purchase order.
     */
    public function create(Request $request, PurchaseOrder $purchaseOrder): View
    {
        $user = $request->user();

        if (!$user->hasLocationAccess($purchaseOrder->location_id, true)) {
            abort(403, 'Forbidden: You do not have authorization to receive goods for this location.');
        }

        if (!in_array($purchaseOrder->status, ['ordered', 'partially_received'])) {
            return redirect()->route('purchases.show', $purchaseOrder)
                ->with('error', "Cannot receive goods for purchase order in status '{$purchaseOrder->status}'.");
        }

        $purchaseOrder->load(['supplier', 'location', 'items.productVariant.product']);

        return view('purchases.receive', compact('purchaseOrder'));
    }

    /**
     * Store a goods receipt (GRN).
     */
    public function store(ReceiveGoodsRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $user = $request->user();

        try {
            $goodsReceipt = $this->purchaseService->receiveGoods(
                $purchaseOrder,
                $request->validated(),
                $user
            );

            return redirect()->route('purchases.show', $purchaseOrder)
                ->with('success', "Goods Receipt {$goodsReceipt->receipt_number} processed. Stock updated and Supplier Khata credited.");
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withInput()->withErrors(['items' => $e->getMessage()]);
        }
    }

    /**
     * Display a specific Goods Receipt (GRN Voucher).
     */
    public function show(Request $request, GoodsReceipt $goodsReceipt): View
    {
        $user = $request->user();

        if (!$user->hasLocationAccess($goodsReceipt->location_id)) {
            abort(403, 'Forbidden: You do not have authorization to view this goods receipt.');
        }

        $goodsReceipt->load([
            'purchaseOrder',
            'supplier',
            'location',
            'receivedBy',
            'items.productVariant.product',
            'items.purchaseOrderItem',
        ]);

        return view('purchases.grn-show', compact('goodsReceipt'));
    }
}
