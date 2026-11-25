<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_code',
        'name',
        'account_type',
        'location_id',
        'bank_name',
        'account_number',
        'iban',
        'branch_code',
        'currency',
        'opening_balance',
        'current_balance',
        'is_active',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(AccountTransaction::class, 'financial_account_id')->orderBy('transaction_date')->orderBy('id');
    }

    public function transfersOut(): HasMany
    {
        return $this->hasMany(AccountTransfer::class, 'source_account_id');
    }

    public function transfersIn(): HasMany
    {
        return $this->hasMany(AccountTransfer::class, 'destination_account_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'financial_account_id');
    }

    public function isCash(): bool
    {
        return $this->account_type === 'cash';
    }

    public function getTypeAttribute(): string
    {
        return $this->account_type ?? 'cash';
    }

    public function setTypeAttribute($value): void
    {
        $this->attributes['account_type'] = $value;
    }

    public function isBank(): bool
    {
        return $this->account_type === 'bank';
    }

    /**
     * Mask sensitive bank account number (showing only last 4 digits).
     */
    public function getMaskedAccountNumberAttribute(): ?string
    {
        if (empty($this->account_number)) {
            return null;
        }

        $length = strlen($this->account_number);
        if ($length <= 4) {
            return $this->account_number;
        }

        return str_repeat('*', max(4, $length - 4)) . substr($this->account_number, -4);
    }

    /**
     * Scope accounts to locations authorized for the given user.
     */
    public function scopeAuthorizedFor(Builder $query, User $user): Builder
    {
        if ($user->isOwner()) {
            return $query;
        }

        $authorizedIds = $user->getAuthorizedLocationIds();

        // Users can access accounts tied to their authorized locations, or central accounts with no location
        return $query->where(function ($q) use ($authorizedIds) {
            $q->whereIn('location_id', $authorizedIds)
              ->orWhereNull('location_id');
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeCash(Builder $query): Builder
    {
        return $query->where('account_type', 'cash');
    }

    public function scopeBank(Builder $query): Builder
    {
        return $query->where('account_type', 'bank');
    }
}
