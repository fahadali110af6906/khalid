<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PurchaseReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_number',
        'goods_receipt_id',
        'purchase_order_id',
        'supplier_id',
        'location_id',
        'return_date',
        'status',
        'total_returned_cartons',
        'total_returned_pairs',
        'total_return_amount',
        'reason',
        'notes',
        'idempotency_key',
        'request_hash',
        'posted_at',
        'posted_by',
        'reversed_at',
        'reversed_by',
        'created_by',
    ];

    protected $casts = [
        'return_date' => 'date',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
        'total_returned_cartons' => 'decimal:4',
        'total_returned_pairs' => 'decimal:4',
        'total_return_amount' => 'decimal:2',
    ];

    public function getTotalReturnPairsAttribute()
    {
        return $this->total_returned_pairs;
    }

    public function getTotalReturnCartonsAttribute()
    {
        return $this->total_returned_cartons;
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class, 'purchase_return_id');
    }

    public function debitNote(): HasOne
    {
        return $this->hasOne(SupplierDebitNote::class, 'purchase_return_id');
    }

    /**
     * Scope query to authorized locations for a user.
     */
    public function scopeAuthorizedFor(Builder $query, User $user): Builder
    {
        if ($user->isOwner()) {
            return $query;
        }

        return $query->whereIn('location_id', $user->getAuthorizedLocationIds());
    }
}
