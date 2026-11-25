<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionMaterialIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'issue_number',
        'production_order_id',
        'location_id',
        'issue_date',
        'type',
        'total_material_cost',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'total_material_cost' => 'decimal:2',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionMaterialIssueItem::class, 'production_material_issue_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTotalCostAttribute(): float
    {
        return (float) $this->total_material_cost;
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

    public function isIssue(): bool
    {
        return $this->type === 'issue';
    }

    public function isReturn(): bool
    {
        return $this->type === 'return';
    }
}
