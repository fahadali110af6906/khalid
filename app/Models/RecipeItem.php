<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipe_id',
        'raw_material_variant_id',
        'quantity_per_pair',
        'unit_of_measure_id',
        'wastage_percentage',
        'notes',
    ];

    protected $casts = [
        'quantity_per_pair' => 'decimal:4',
        'wastage_percentage' => 'decimal:2',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'recipe_id');
    }

    public function rawMaterialVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'raw_material_variant_id');
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id');
    }

    /**
     * Quantity required per pair including configured wastage allowance.
     */
    public function getGrossQuantityPerPairAttribute(): float
    {
        $base = (string) $this->quantity_per_pair;
        $wastePct = (string) $this->wastage_percentage;
        $allowance = bcdiv(bcmul($base, $wastePct, 4), '100', 4);

        return (float) bcadd($base, $allowance, 4);
    }
}
