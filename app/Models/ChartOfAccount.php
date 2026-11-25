<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class ChartOfAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'parent_id',
        'is_group',
        'is_active',
        'is_system',
        'system_identifier',
        'normal_balance',
        'description',
    ];

    protected $casts = [
        'is_group' => 'boolean',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    /**
     * Parent category / group account.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Sub-accounts or child accounts.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }

    /**
     * General ledger journal lines posted to this account.
     */
    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'chart_of_account_id');
    }

    /**
     * Scope postable leaf accounts.
     */
    public function scopePostable(Builder $query): Builder
    {
        return $query->where('is_group', false)->where('is_active', true);
    }

    /**
     * Scope group/folder accounts.
     */
    public function scopeGroup(Builder $query): Builder
    {
        return $query->where('is_group', true);
    }

    /**
     * Scope active accounts.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Find postable system account by its well-known identifier.
     */
    public static function findBySystemIdentifier(string $identifier): ?self
    {
        return static::where('system_identifier', $identifier)->first();
    }

    /**
     * Require postable system account by identifier or fail with clean exception.
     */
    public static function requireSystemAccount(string $identifier): self
    {
        $account = static::findBySystemIdentifier($identifier);
        if (!$account) {
            throw new InvalidArgumentException("Required system account '{$identifier}' is not configured in Chart of Accounts.");
        }
        return $account;
    }

    /**
     * Determine if account is a postable leaf.
     */
    public function isPostable(): bool
    {
        return !$this->is_group && $this->is_active;
    }
}
