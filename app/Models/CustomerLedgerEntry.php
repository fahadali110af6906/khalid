<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

class CustomerLedgerEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'location_id',
        'entry_type',
        'reference_type',
        'reference_id',
        'reference_number',
        'entry_date',
        'debit_amount',
        'credit_amount',
        'description',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'debit_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
    ];

    /**
     * Enforce strict immutability: posted ledger entries can never be modified or deleted.
     */
    protected static function booted(): void
    {
        static::updating(function ($entry) {
            throw new RuntimeException("Posted customer ledger entries are strictly immutable. Corrections must be recorded via new adjustment entries.");
        });

        static::deleting(function ($entry) {
            throw new RuntimeException("Posted customer ledger entries cannot be deleted. History must be preserved for accounting audits.");
        });
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

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope ledger entries to user's authorized locations.
     */
    public function scopeAuthorizedFor(Builder $query, User $user): Builder
    {
        if ($user->isOwner()) {
            return $query;
        }

        return $query->whereIn('location_id', $user->getAuthorizedLocationIds());
    }
}
