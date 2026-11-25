<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class SupplierLedgerEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
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

    protected static function booted(): void
    {
        // Enforce ledger immutability
        static::updating(function () {
            throw new RuntimeException("Financial Integrity Violation: Supplier ledger entries are immutable and cannot be updated once recorded.");
        });

        static::deleting(function () {
            throw new RuntimeException("Financial Integrity Violation: Supplier ledger entries are immutable and cannot be deleted once recorded.");
        });
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
}
