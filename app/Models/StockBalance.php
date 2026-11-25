<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'location_id',
        'product_variant_id',
        'pairs_quantity',
        'allocated_quantity',
        'avg_unit_cost',
    ];

    protected $casts = [
        'pairs_quantity' => 'decimal:4',
        'allocated_quantity' => 'decimal:4',
        'avg_unit_cost' => 'decimal:4',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Net available pairs not reserved for pending shipments.
     */
    public function getAvailablePairsAttribute(): float
    {
        return max(0.0, (float) bcsub((string) $this->pairs_quantity, (string) $this->allocated_quantity, 4));
    }

    /**
     * Scope query safely to authorized locations for a given user.
     * Avoids global scope breakages in console commands or background jobs.
     */
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
