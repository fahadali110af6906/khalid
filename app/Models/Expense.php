<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_number',
        'location_id',
        'expense_category_id',
        'financial_account_id',
        'expense_date',
        'amount',
        'payee_name',
        'payment_method',
        'description',
        'reference_number',
        'notes',
        'status',
        'reversal_reason',
        'posted_at',
        'posted_by',
        'reversed_at',
        'reversed_by',
        'idempotency_key',
        'request_hash',
        'created_by',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    public function financialAccount(): BelongsTo
    {
        return $this->account();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function poster(): BelongsTo
    {
        return $this->postedBy();
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function reverser(): BelongsTo
    {
        return $this->reversedBy();
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(AccountTransaction::class, 'reference_id')
            ->where(function ($q) {
                $q->where('reference_type', 'Expense')
                  ->orWhere('reference_type', self::class);
            });
    }

    public function accountTransaction(): HasOne
    {
        return $this->transaction();
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function isReversed(): bool
    {
        return $this->status === 'reversed';
    }

    public function scopeAuthorizedFor(Builder $query, User $user): Builder
    {
        if ($user->isOwner()) {
            return $query;
        }

        return $query->whereIn('location_id', $user->getAuthorizedLocationIds());
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', 'posted');
    }
}
