<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'entry_number',
        'entry_date',
        'location_id',
        'source_type',
        'source_id',
        'reference_number',
        'description',
        'status',
        'total_debit',
        'total_credit',
        'reversed_by_entry_id',
        'reversal_of_entry_id',
        'idempotency_key',
        'posted_at',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'posted_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'journal_entry_id')->orderBy('line_number');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_entry_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_entry_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', 'posted');
    }

    public function scopeAuthorizedFor(Builder $query, ?User $user = null): Builder
    {
        if (!$user) {
            return $query;
        }

        if ($user->isOwner()) {
            return $query;
        }

        $locationIds = $user->locations->pluck('id')->toArray();
        return $query->where(function ($q) use ($locationIds) {
            $q->whereIn('location_id', $locationIds)
              ->orWhereNull('location_id'); // Global/company-level entries visible if authorized
        });
    }

    /**
     * Check if total debits equal total credits.
     */
    public function isBalanced(): bool
    {
        return bccomp((string) $this->total_debit, (string) $this->total_credit, 2) === 0;
    }

    public function isReversed(): bool
    {
        return $this->status === 'reversed' || $this->reversed_by_entry_id !== null;
    }
}
