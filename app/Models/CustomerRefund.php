<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerRefund extends Model
{
    use HasFactory;

    protected $fillable = [
        'refund_number',
        'customer_id',
        'sales_return_id',
        'location_id',
        'financial_account_id',
        'refund_date',
        'amount',
        'payment_method',
        'reference_number',
        'notes',
        'idempotency_key',
        'request_hash',
        'created_by',
    ];

    protected $casts = [
        'refund_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class, 'sales_return_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    public function accountTransaction(): \Illuminate\Database\Eloquent\Relations\MorphOne
    {
        return $this->morphOne(AccountTransaction::class, 'reference');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeAuthorizedFor(Builder $query, User $user): Builder
    {
        if ($user->isOwner()) {
            return $query;
        }

        return $query->whereIn('location_id', $user->getAuthorizedLocationIds());
    }
}
