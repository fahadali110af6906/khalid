<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockBalance;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Display stock balances strictly scoped to authorized locations.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $locationId = $request->query('location_id');

        if ($locationId !== null && $locationId !== '' && $locationId !== 'all') {
            if (!$user->hasLocationAccess($locationId)) {
                abort(403, 'Forbidden: You do not have authorization to view stock for this location.');
            }
        } elseif ($locationId === 'all' && !$user->isOwner()) {
            abort(403, 'Forbidden: Only the owner can view consolidated cross-location inventory.');
        }

        $query = StockBalance::authorizedFor($user)
            ->with(['location', 'productVariant.product.category'])
            ->when($locationId && $locationId !== 'all', fn ($q) => $q->where('location_id', $locationId));

        $search = $request->query('search');
        if ($search) {
            $query->whereHas('productVariant', function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                  ->orWhere('size', 'like', "%{$search}%")
                  ->orWhere('color', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($p) use ($search) {
                      $p->where('name', 'like', "%{$search}%")
                        ->orWhere('article_code', 'like', "%{$search}%");
                  });
            });
        }

        $balances = $query->paginate(20)->withQueryString();

        $authorizedLocations = $user->isOwner() 
            ? Location::where('is_active', true)->get() 
            : $user->locations()->wherePivot('can_view', true)->get();

        return view('inventory.index', compact('balances', 'authorizedLocations', 'locationId', 'search'));
    }

    /**
     * List inventory adjustments for authorized locations.
     */
    public function adjustments(Request $request): View
    {
        $user = $request->user();

        $adjustments = StockAdjustment::authorizedFor($user)
            ->with(['location', 'creator', 'approver', 'items.productVariant.product'])
            ->latest()
            ->paginate(15);

        return view('inventory.adjustments', compact('adjustments'));
    }

    /**
     * Show form for creating a new stock count or adjustment draft.
     */
    public function createAdjustment(Request $request): View
    {
        $user = $request->user();

        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->orderBy('name')->get()
            : $user->locations()->wherePivot('can_operate', true)->get();

        $products = Product::where('is_active', true)
            ->with(['variants' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get();

        return view('inventory.adjustments_create', compact('authorizedLocations', 'products'));
    }

    /**
     * Store a proposed stock adjustment draft.
     */
    public function storeAdjustment(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'location_id' => ['required', 'exists:locations,id'],
            'reason' => ['required', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'exists:product_variants,id'],
            'items.*.quantity_delta_pairs' => ['required', 'numeric', 'not_in:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        if (!$user->hasLocationAccess($validated['location_id'], true)) {
            abort(403, 'Forbidden: You do not have operational authorization to create adjustments for this location.');
        }

        DB::transaction(function () use ($validated, $user) {
            $adjustment = StockAdjustment::create([
                'adjustment_number' => 'ADJ-' . date('Ymd') . '-' . strtoupper(Str::random(4)),
                'location_id' => $validated['location_id'],
                'reason' => $validated['reason'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'draft',
                'created_by' => $user->id,
            ]);

            foreach ($validated['items'] as $itemData) {
                StockAdjustmentItem::create([
                    'stock_adjustment_id' => $adjustment->id,
                    'product_variant_id' => $itemData['product_variant_id'],
                    'quantity_delta_pairs' => $itemData['quantity_delta_pairs'],
                    'unit_cost' => $itemData['unit_cost'],
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }
        });

        return redirect()->route('inventory.adjustments')->with('success', 'Stock adjustment draft created and submitted for owner approval.');
    }

    /**
     * Owner approval and posting of inventory adjustment.
     */
    public function approveAdjustment(Request $request, StockAdjustment $adjustment): RedirectResponse
    {
        $user = $request->user();

        if (!$user->isOwner()) {
            abort(403, 'Forbidden: Only the business owner can approve stock adjustments.');
        }

        $this->inventoryService->postAdjustment($adjustment, $user);

        return redirect()->route('inventory.adjustments')->with('success', "Adjustment {$adjustment->adjustment_number} approved and posted to inventory ledger.");
    }
}
