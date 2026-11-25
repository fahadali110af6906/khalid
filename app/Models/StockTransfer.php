<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_number',
        'source_location_id',
        'destination_location_id',
        'status',
        'transfer_date',
        'total_cartons',
        'total_pairs',
        'total_cost',
        'dispatch_notes',
        'receive_notes',
        'cancellation_reason',
        'created_by',
        'dispatched_by',
        'dispatched_at',
        'received_by',
        'received_at',
        'cancelled_by',
        'cancelled_at',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'total_cartons' => 'integer',
        'total_pairs' => 'decimal:4',
        'total_cost' => 'decimal:2',
        'dispatched_at' => 'datetime',
        'received_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'source_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class, 'stock_transfer_id');
    }

    /**
     * Scope query safely to transfers accessible by the user (source or destination authorized).
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

        $locationIds = $user->locations->pluck('id')->toArray();

        return $query->where(function ($q) use ($locationIds) {
            $q->whereIn('source_location_id', $locationIds)
              ->orWhereIn('destination_location_id', $locationIds);
        });
    }
}
