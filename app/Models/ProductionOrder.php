<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'factory_location_id',
        'product_id',
        'product_variant_id',
        'recipe_id',
        'planned_quantity_pairs',
        'completed_good_pairs',
        'wastage_pairs',
        'start_date',
        'due_date',
        'status',
        'planned_material_cost',
        'actual_material_cost',
        'labor_cost',
        'other_costs',
        'total_production_cost',
        'unit_cost_per_pair',
        'cancellation_reason',
        'notes',
        'idempotency_key',
        'request_hash',
        'started_at',
        'completed_at',
        'created_by',
        'completed_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'planned_quantity_pairs' => 'decimal:4',
        'completed_good_pairs' => 'decimal:4',
        'wastage_pairs' => 'decimal:4',
        'planned_material_cost' => 'decimal:2',
        'actual_material_cost' => 'decimal:2',
        'labor_cost' => 'decimal:2',
        'other_costs' => 'decimal:2',
        'total_production_cost' => 'decimal:2',
        'unit_cost_per_pair' => 'decimal:4',
    ];

    public function factoryLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'factory_location_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'recipe_id');
    }

    public function materialIssues(): HasMany
    {
        return $this->hasMany(ProductionMaterialIssue::class, 'production_order_id');
    }

    public function completions(): HasMany
    {
        return $this->hasMany(ProductionCompletion::class, 'production_order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
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

        return $query->whereIn('factory_location_id', $user->getAuthorizedLocationIds());
    }

    public function getRemainingPairsToProduceAttribute(): float
    {
        return max(0.0, (float) bcsub((string) $this->planned_quantity_pairs, (string) $this->completed_good_pairs, 4));
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPlanned(): bool
    {
        return $this->status === 'planned';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
