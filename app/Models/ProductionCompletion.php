<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionCompletion extends Model
{
    use HasFactory;

    protected $fillable = [
        'completion_number',
        'production_order_id',
        'location_id',
        'completion_date',
        'good_pairs',
        'wastage_pairs',
        'labor_cost',
        'other_costs',
        'allocated_material_cost',
        'total_cost',
        'unit_cost_per_pair',
        'stock_movement_id',
        'idempotency_key',
        'request_hash',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'completion_date' => 'date',
        'good_pairs' => 'decimal:4',
        'wastage_pairs' => 'decimal:4',
        'labor_cost' => 'decimal:2',
        'other_costs' => 'decimal:2',
        'allocated_material_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'unit_cost_per_pair' => 'decimal:4',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function getTotalBatchCostAttribute(): float
    {
        return (float) $this->total_cost;
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeAuthorizedFor(Builder $query, ?User $user = null): Builder
    {
        $user = $user ?? auth()->user();

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isOwner()) {
            return $query;
        }

        return $query->whereIn('location_id', $user->getAuthorizedLocationIds());
    }
}
