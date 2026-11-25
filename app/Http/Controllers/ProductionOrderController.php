<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteProductionOrderRequest;
use App\Http\Requests\IssueProductionMaterialRequest;
use App\Http\Requests\StoreProductionOrderRequest;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\Recipe;
use App\Services\Production\ProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class ProductionOrderController extends Controller
{
    public function __construct(
        protected ProductionService $productionService
    ) {}

    /**
     * Display a listing of production orders.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $status = $request->query('status', 'all');
        $locationId = $request->query('location_id');
        $search = $request->query('search');

        if ($locationId && $locationId !== 'all') {
            if (!$user->hasLocationAccess($locationId)) {
                abort(403, 'Forbidden: You do not have authorization for this factory location.');
            }
        } elseif ($locationId === 'all' && !$user->isOwner()) {
            abort(403, 'Forbidden: Only the owner can view cross-location factory orders.');
        }

        $query = ProductionOrder::authorizedFor($user)
            ->with(['factoryLocation', 'product', 'productVariant', 'recipe', 'creator'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($locationId && $locationId !== 'all', fn ($q) => $q->where('factory_location_id', $locationId))
            ->when($search, function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%")->orWhere('article_code', 'like', "%{$search}%"))
                  ->orWhereHas('productVariant', fn ($vq) => $vq->where('sku', 'like', "%{$search}%"));
            })
            ->latest('start_date')
            ->latest('id');

        $orders = $query->paginate(20)->withQueryString();

        $authorizedLocations = $user->isOwner()
            ? Location::where('type', 'factory')->where('is_active', true)->get()
            : $user->locations()->wherePivot('can_view', true)->where('type', 'factory')->get();

        return view('production.orders.index', compact('orders', 'authorizedLocations', 'status', 'locationId', 'search'));
    }

    /**
     * Show the form to create a new production order.
     */
    public function create(Request $request): View
    {
        $user = $request->user();

        $authorizedLocations = $user->isOwner()
            ? Location::where('type', 'factory')->where('is_active', true)->get()
            : $user->locations()->wherePivot('can_operate', true)->where('type', 'factory')->get();

        if ($authorizedLocations->isEmpty()) {
            // Fallback to all accessible locations if no dedicated factory type assigned
            $authorizedLocations = $user->isOwner()
                ? Location::where('is_active', true)->get()
                : $user->locations()->wherePivot('can_operate', true)->get();
        }

        $recipes = Recipe::active()
            ->with(['product.variants', 'items.rawMaterialVariant.product.baseUnit'])
            ->orderBy('name')
            ->get();

        $selectedRecipeId = $request->query('recipe_id');
        $selectedRecipe = $selectedRecipeId ? Recipe::find($selectedRecipeId) : null;

        return view('production.orders.create', compact('authorizedLocations', 'recipes', 'selectedRecipe'));
    }

    /**
     * Store a new production order in draft or planned status.
     */
    public function store(StoreProductionOrderRequest $request): RedirectResponse
    {
        $user = $request->user();

        try {
            $order = $this->productionService->createOrder($request->validated(), $user);

            return redirect()->route('production.orders.show', $order)
                ->with('success', "Production Order #{$order->order_number} created successfully.");
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Display a specific production order, its material issues, completions, and live costing.
     */
    public function show(Request $request, ProductionOrder $productionOrder): View
    {
        $user = $request->user();

        if (!$user->hasLocationAccess($productionOrder->factory_location_id)) {
            abort(403, 'Forbidden: You do not have authorization to view this production order.');
        }

        $productionOrder->load([
            'factoryLocation',
            'product',
            'productVariant',
            'recipe.items.rawMaterialVariant.product.baseUnit',
            'materialIssues.items.rawMaterialVariant.product.baseUnit',
            'materialIssues.creator',
            'completions.creator',
            'creator',
            'completedBy',
        ]);

        $costing = $this->productionService->calculateOrderCosting($productionOrder);

        return view('production.orders.show', compact('productionOrder', 'costing'));
    }

    /**
     * Start a planned production order (transition to in_progress).
     */
    public function start(Request $request, ProductionOrder $productionOrder): RedirectResponse
    {
        $user = $request->user();

        try {
            $this->productionService->startOrder($productionOrder, $user);

            return redirect()->route('production.orders.show', $productionOrder)
                ->with('success', "Production Order #{$productionOrder->order_number} is now In Progress.");
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Cancel an uncompleted production order.
     */
    public function cancel(Request $request, ProductionOrder $productionOrder): RedirectResponse
    {
        $user = $request->user();
        $reason = $request->input('reason', 'Cancelled by manager');

        try {
            $this->productionService->cancelOrder($productionOrder, $reason, $user);

            return redirect()->route('production.orders.show', $productionOrder)
                ->with('success', "Production Order #{$productionOrder->order_number} cancelled.");
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Issue raw materials for a production order.
     */
    public function issueMaterials(IssueProductionMaterialRequest $request, ProductionOrder $productionOrder): RedirectResponse
    {
        $user = $request->user();

        try {
            $issue = $this->productionService->issueMaterials(
                $productionOrder,
                $request->validated('items'),
                $user,
                $request->validated('notes')
            );

            return redirect()->route('production.orders.show', $productionOrder)
                ->with('success', "Raw materials successfully issued (Issue #{$issue->issue_number}).");
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withInput()->withErrors(['issue' => $e->getMessage()]);
        }
    }

    /**
     * Return unused raw materials from a production order back to factory stock.
     */
    public function returnMaterials(IssueProductionMaterialRequest $request, ProductionOrder $productionOrder): RedirectResponse
    {
        $user = $request->user();

        try {
            $returnRecord = $this->productionService->returnMaterials(
                $productionOrder,
                $request->validated('items'),
                $user,
                $request->validated('notes')
            );

            return redirect()->route('production.orders.show', $productionOrder)
                ->with('success', "Unused materials returned to stock (Return #{$returnRecord->issue_number}).");
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withInput()->withErrors(['return' => $e->getMessage()]);
        }
    }

    /**
     * Record finished footwear output / completion.
     */
    public function complete(CompleteProductionOrderRequest $request, ProductionOrder $productionOrder): RedirectResponse
    {
        $user = $request->user();

        try {
            $completion = $this->productionService->recordCompletion(
                $productionOrder,
                $request->validated(),
                $user
            );

            return redirect()->route('production.orders.show', $productionOrder)
                ->with('success', "Production output recorded (Completion #{$completion->completion_number}). Finished footwear added to stock.");
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withInput()->withErrors(['complete' => $e->getMessage()]);
        }
    }
}
