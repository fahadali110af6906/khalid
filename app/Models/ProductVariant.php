<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'size',
        'color',
        'barcode',
        'wholesale_rate',
        'retail_rate',
        'purchase_cost',
        'is_active',
    ];

    protected $casts = [
        'wholesale_rate' => 'decimal:2',
        'retail_rate' => 'decimal:2',
        'purchase_cost' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function stockBalances(): HasMany
    {
        return $this->hasMany(StockBalance::class, 'product_variant_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'product_variant_id');
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class, 'product_variant_id');
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class, 'product_variant_id');
    }

    public function getEffectiveWholesaleRateAttribute(): float
    {
        return $this->wholesale_rate !== null 
            ? (float) $this->wholesale_rate 
            : (float) $this->product->default_wholesale_rate;
    }

    public function getEffectiveRetailRateAttribute(): float
    {
        return $this->retail_rate !== null 
            ? (float) $this->retail_rate 
            : (float) $this->product->default_retail_rate;
    }
}
