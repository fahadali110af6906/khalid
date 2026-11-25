<?php

namespace App\Models;

use App\Services\Accounting\SupplierKhataService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_code',
        'business_name',
        'contact_person',
        'phone',
        'alternate_phone',
        'email',
        'address',
        'city',
        'tax_number',
        'primary_location_id',
        'credit_limit',
        'opening_balance',
        'notes',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'opening_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Supplier $supplier) {
            if ($supplier->ledgerEntries()->exists() || $supplier->purchaseOrders()->exists() || $supplier->payments()->exists() || $supplier->purchaseReturns()->exists() || $supplier->debitNotes()->exists()) {
                throw new RuntimeException("Cannot delete supplier '{$supplier->business_name}' because purchase orders, payments, returns, or ledger history exist. Deactivate the account instead.");
            }
        });
    }

    public function primaryLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'primary_location_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(SupplierLedgerEntry::class, 'supplier_id')->orderBy('entry_date')->orderBy('id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'supplier_id');
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class, 'supplier_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class, 'supplier_id');
    }

    public function purchaseReturns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class, 'supplier_id');
    }

    public function debitNotes(): HasMany
    {
        return $this->hasMany(SupplierDebitNote::class, 'supplier_id');
    }

    /**
     * Scope suppliers strictly to locations the given user is authorized to access.
     */
    public function scopeAuthorizedFor(Builder $query, User $user): Builder
    {
        if ($user->isOwner()) {
            return $query;
        }

        $authorizedLocationIds = $user->getAuthorizedLocationIds();

        return $query->whereIn('primary_location_id', $authorizedLocationIds);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (!$term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('supplier_code', 'like', "%{$term}%")
              ->orWhere('business_name', 'like', "%{$term}%")
              ->orWhere('contact_person', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%")
              ->orWhere('city', 'like', "%{$term}%");
        });
    }

    public function getKhataSummaryAttribute(): array
    {
        return app(SupplierKhataService::class)->calculateBalance($this);
    }

    /**
     * Get closing balance amount.
     */
    public function getClosingBalanceAttribute(): float
    {
        return (float) ($this->khata_summary['closing_balance'] ?? 0.00);
    }

    /**
     * Get current balance amount (alias for closing balance).
     */
    public function getCurrentBalanceAttribute(): float
    {
        return (float) ($this->khata_summary['closing_balance'] ?? 0.00);
    }
}
