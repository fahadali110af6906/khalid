<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_number',
        'supplier_id',
        'location_id',
        'order_date',
        'expected_delivery_date',
        'status',
        'total_cartons',
        'total_pairs',
        'subtotal_amount',
        'discount_amount',
        'additional_charges',
        'total_amount',
        'notes',
        'ordered_at',
        'ordered_by',
        'created_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'ordered_at' => 'datetime',
        'total_cartons' => 'integer',
        'total_pairs' => 'decimal:4',
        'subtotal_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'additional_charges' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class, 'purchase_order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class, 'purchase_order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function orderer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isOrdered(): bool
    {
        return $this->status === 'ordered';
    }

    public function isPartiallyReceived(): bool
    {
        return $this->status === 'partially_received';
    }

    public function isReceived(): bool
    {
        return $this->status === 'received';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function canReceive(): bool
    {
        return in_array($this->status, ['ordered', 'partially_received']);
    }

    /**
     * Scope purchase orders to authorized locations for the given user.
     */
    public function scopeAuthorizedFor(Builder $query, User $user): Builder
    {
        if ($user->isOwner()) {
            return $query;
        }

        return $query->whereIn('location_id', $user->getAuthorizedLocationIds());
    }
}
