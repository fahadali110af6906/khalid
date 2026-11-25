<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_number',
        'source_account_id',
        'destination_account_id',
        'transfer_date',
        'amount',
        'reason',
        'reference_number',
        'notes',
        'status',
        'idempotency_key',
        'request_hash',
        'created_by',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'source_account_id');
    }

    public function fromAccount(): BelongsTo
    {
        return $this->sourceAccount();
    }

    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'destination_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->destinationAccount();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(AccountTransaction::class, 'transfer_id');
    }

    public function scopeAuthorizedFor(Builder $query, User $user): Builder
    {
        if ($user->isOwner()) {
            return $query;
        }

        $authorizedIds = $user->getAuthorizedLocationIds();

        return $query->whereHas('sourceAccount', function ($q) use ($authorizedIds) {
            $q->whereIn('location_id', $authorizedIds)->orWhereNull('location_id');
        })->orWhereHas('destinationAccount', function ($q) use ($authorizedIds) {
            $q->whereIn('location_id', $authorizedIds)->orWhereNull('location_id');
        });
    }
}
