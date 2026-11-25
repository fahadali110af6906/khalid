<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\UnitOfMeasure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Display a listing of footwear products.
     */
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $categoryId = $request->query('category_id');

        $products = Product::with(['category', 'brand', 'variants'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('article_code', 'like', "%{$search}%");
                });
            })
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->orderBy('article_code')
            ->paginate(15)
            ->withQueryString();

        $categories = ProductCategory::where('is_active', true)->get();

        return view('products.index', compact('products', 'categories', 'search', 'categoryId'));
    }

    /**
     * Show form to create a new product.
     */
    public function create(): View
    {
        $categories = ProductCategory::where('is_active', true)->get();
        $brands = Brand::where('is_active', true)->get();
        $units = UnitOfMeasure::where('is_active', true)->get();

        return view('products.create', compact('categories', 'brands', 'units'));
    }

    /**
     * Store a newly created product with its variant matrix.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'article_code' => ['required', 'string', 'max:50', 'unique:products,article_code'],
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'exists:product_categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'base_unit_id' => ['required', 'exists:units_of_measure,id'],
            'carton_pack_qty' => ['required', 'integer', 'min:1', 'max:100'],
            'default_wholesale_rate' => ['required', 'numeric', 'min:0'],
            'default_retail_rate' => ['nullable', 'numeric', 'min:0'],
            'reorder_threshold_cartons' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.size' => ['required', 'string', 'max:20'],
            'variants.*.color' => ['required', 'string', 'max:30'],
            'variants.*.barcode' => ['nullable', 'string', 'max:60', 'unique:product_variants,barcode'],
            'variants.*.purchase_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($validated) {
            $product = Product::create([
                'article_code' => strtoupper(trim($validated['article_code'])),
                'name' => trim($validated['name']),
                'category_id' => $validated['category_id'],
                'brand_id' => $validated['brand_id'] ?? null,
                'base_unit_id' => $validated['base_unit_id'],
                'carton_pack_qty' => $validated['carton_pack_qty'],
                'default_wholesale_rate' => $validated['default_wholesale_rate'],
                'default_retail_rate' => $validated['default_retail_rate'] ?? 0.00,
                'reorder_threshold_cartons' => $validated['reorder_threshold_cartons'],
                'description' => $validated['description'] ?? null,
                'is_active' => true,
            ]);

            foreach ($validated['variants'] as $variantData) {
                $sku = trim($product->article_code) . '-' . substr(trim($variantData['color']), 0, 3) . '-' . trim($variantData['size']);
                $sku = strtoupper(Str::slug($sku, '-'));

                ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $sku,
                    'size' => trim($variantData['size']),
                    'color' => trim($variantData['color']),
                    'barcode' => !empty($variantData['barcode']) ? trim($variantData['barcode']) : null,
                    'purchase_cost' => $variantData['purchase_cost'] ?? 0.0000,
                    'is_active' => true,
                ]);
            }
        });

        return redirect()->route('products.index')->with('success', 'Product and variant matrix created successfully.');
    }

    /**
     * Display product detail and variant-level stock.
     */
    public function show(Product $product): View
    {
        $product->load(['category', 'brand', 'baseUnit', 'variants.stockBalances.location']);

        $user = auth()->user();
        $authorizedLocations = $user->isOwner() 
            ? Location::where('is_active', true)->get() 
            : $user->locations;

        return view('products.show', compact('product', 'authorizedLocations'));
    }

    /**
     * Show form to edit an existing product article.
     */
    public function edit(Product $product): View
    {
        $categories = ProductCategory::where('is_active', true)->get();
        $brands = Brand::where('is_active', true)->get();
        $units = UnitOfMeasure::where('is_active', true)->get();
        $product->load(['variants']);

        return view('products.edit', compact('product', 'categories', 'brands', 'units'));
    }

    /**
     * Update an existing product article.
     */
    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'exists:product_categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'carton_pack_qty' => ['required', 'integer', 'min:1', 'max:100'],
            'default_wholesale_rate' => ['required', 'numeric', 'min:0'],
            'default_retail_rate' => ['nullable', 'numeric', 'min:0'],
            'reorder_threshold_cartons' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);

        $product->update([
            'name' => trim($validated['name']),
            'category_id' => $validated['category_id'],
            'brand_id' => $validated['brand_id'] ?? null,
            'carton_pack_qty' => $validated['carton_pack_qty'],
            'default_wholesale_rate' => $validated['default_wholesale_rate'],
            'default_retail_rate' => $validated['default_retail_rate'] ?? 0.00,
            'reorder_threshold_cartons' => $validated['reorder_threshold_cartons'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('products.show', $product)->with('success', 'Product article updated successfully.');
    }
}
