<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecipeRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\UnitOfMeasure;
use App\Services\Production\ProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class ProductionRecipeController extends Controller
{
    public function __construct(
        protected ProductionService $productionService
    ) {}

    /**
     * Display a listing of recipes / BOMs.
     */
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $productId = $request->query('product_id');

        $query = Recipe::with(['product', 'productVariant', 'items.rawMaterialVariant', 'creator'])
            ->when($search, function ($q) use ($search) {
                $q->where('recipe_number', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%")->orWhere('article_code', 'like', "%{$search}%"));
            })
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->orderByDesc('id');

        $recipes = $query->paginate(20)->withQueryString();
        $products = Product::where('is_active', true)->orderBy('name')->get();

        return view('production.recipes.index', compact('recipes', 'products', 'search', 'productId'));
    }

    /**
     * Show the form to create a new recipe / BOM.
     */
    public function create(): View
    {
        // Finished footwear products
        $finishedProducts = Product::whereHas('category', fn ($c) => $c->where('type', 'finished_good'))
            ->orWhereDoesntHave('category')
            ->where('is_active', true)
            ->with('variants')
            ->orderBy('name')
            ->get();

        // Raw materials and components
        $rawMaterials = ProductVariant::whereHas('product.category', fn ($c) => $c->whereIn('type', ['raw_material', 'packaging']))
            ->orWhereHas('product', fn ($p) => $p->where('is_active', true))
            ->with(['product.baseUnit'])
            ->where('is_active', true)
            ->orderBy('sku')
            ->get();

        $units = UnitOfMeasure::where('is_active', true)->orderBy('name')->get();

        return view('production.recipes.create', compact('finishedProducts', 'rawMaterials', 'units'));
    }

    /**
     * Store a new recipe / BOM.
     */
    public function store(StoreRecipeRequest $request): RedirectResponse
    {
        try {
            $recipe = $this->productionService->createRecipe($request->validated(), $request->user());

            return redirect()->route('production.recipes.show', $recipe)
                ->with('success', "Recipe #{$recipe->recipe_number} created successfully.");
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['items' => $e->getMessage()]);
        }
    }

    /**
     * Display a specific recipe / BOM with component costing breakdown.
     */
    public function show(Recipe $recipe): View
    {
        $recipe->load([
            'product.category',
            'productVariant',
            'items.rawMaterialVariant.product.baseUnit',
            'items.unitOfMeasure',
            'creator',
            'updater',
            'productionOrders' => fn ($q) => $q->latest('id')->take(10),
        ]);

        return view('production.recipes.show', compact('recipe'));
    }
}
