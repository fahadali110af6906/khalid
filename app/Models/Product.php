<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use InvalidArgumentException;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'brand_id',
        'base_unit_id',
        'article_code',
        'name',
        'description',
        'carton_pack_qty',
        'default_wholesale_rate',
        'default_retail_rate',
        'reorder_threshold_cartons',
        'image_path',
        'is_active',
    ];

    protected $casts = [
        'carton_pack_qty' => 'integer',
        'default_wholesale_rate' => 'decimal:2',
        'default_retail_rate' => 'decimal:2',
        'reorder_threshold_cartons' => 'integer',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_unit_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id');
    }

    public function stockBalances(): HasManyThrough
    {
        return $this->hasManyThrough(StockBalance::class, ProductVariant::class);
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class, 'product_id');
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class, 'product_id');
    }

    /**
     * Explicit packaging conversion: converts cartons into base pair quantities.
     * Rejects negative and non-numeric values.
     */
    public function convertCartonsToPairs(float|int $cartons): float
    {
        if ($cartons < 0) {
            throw new InvalidArgumentException("Carton quantity must be non-negative. Received: {$cartons}");
        }

        $multiplier = $this->carton_pack_qty > 0 ? $this->carton_pack_qty : 12;

        return (float) bcmul((string) $cartons, (string) $multiplier, 4);
    }

    /**
     * Converts base pair quantities into carton representation (cartons + loose pairs).
     */
    public function convertPairsToCartons(float|int $pairs): array
    {
        if ($pairs < 0) {
            throw new InvalidArgumentException("Pairs quantity must be non-negative. Received: {$pairs}");
        }

        $multiplier = $this->carton_pack_qty > 0 ? $this->carton_pack_qty : 12;

        $wholeCartons = (int) floor($pairs / $multiplier);
        $loosePairs = (float) fmod($pairs, $multiplier);

        return [
            'cartons' => $wholeCartons,
            'loose_pairs' => $loosePairs,
            'total_pairs' => (float) $pairs,
            'pack_ratio' => $multiplier,
        ];
    }
}
