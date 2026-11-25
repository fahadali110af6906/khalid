<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionMaterialIssueItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_material_issue_id',
        'raw_material_variant_id',
        'quantity',
        'unit_cost',
        'total_cost',
        'stock_movement_id',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:2',
    ];

    public function materialIssue(): BelongsTo
    {
        return $this->belongsTo(ProductionMaterialIssue::class, 'production_material_issue_id');
    }

    public function rawMaterialVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'raw_material_variant_id');
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }
}
