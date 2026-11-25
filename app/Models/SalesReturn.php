<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SalesReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_number',
        'sales_invoice_id',
        'customer_id',
        'location_id',
        'return_date',
        'status',
        'total_returned_cartons',
        'total_returned_pairs',
        'total_return_amount',
        'refund_status',
        'refunded_amount',
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
        'refunded_amount' => 'decimal:2',
    ];

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
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
        return $this->hasMany(SalesReturnItem::class, 'sales_return_id');
    }

    public function creditNote(): HasOne
    {
        return $this->hasOne(CustomerCreditNote::class, 'sales_return_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(CustomerRefund::class, 'sales_return_id');
    }

    public function scopeAuthorizedFor(Builder $query, User $user): Builder
    {
        if ($user->isOwner()) {
            return $query;
        }

        return $query->whereIn('location_id', $user->getAuthorizedLocationIds());
    }

    public function scopeForLocation(Builder $query, int $locationId): Builder
    {
        return $query->where('location_id', $locationId);
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', 'posted');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }
}
