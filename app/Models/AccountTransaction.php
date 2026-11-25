<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AccountTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_number',
        'financial_account_id',
        'transaction_type',
        'transaction_date',
        'debit_amount',
        'credit_amount',
        'balance_after',
        'reference_type',
        'reference_id',
        'reference_number',
        'description',
        'transfer_id',
        'is_reversal',
        'reversed_transaction_id',
        'idempotency_key',
        'request_hash',
        'created_by',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'debit_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'is_reversal' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(AccountTransfer::class, 'transfer_id');
    }

    public function reversedTransaction(): BelongsTo
    {
        return $this->belongsTo(AccountTransaction::class, 'reversed_transaction_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function isInflow(): bool
    {
        return bccomp((string) $this->debit_amount, '0.00', 2) > 0;
    }

    public function isOutflow(): bool
    {
        return bccomp((string) $this->credit_amount, '0.00', 2) > 0;
    }
}
