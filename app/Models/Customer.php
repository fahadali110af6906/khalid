<?php

namespace App\Models;

use App\Services\Accounting\CustomerKhataService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_code',
        'business_name',
        'contact_person',
        'phone',
        'alternate_phone',
        'email',
        'address',
        'city',
        'tax_number',
        'credit_limit',
        'opening_balance',
        'notes',
        'primary_location_id',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'opening_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * The primary shop/branch managing this customer.
     */
    public function primaryLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'primary_location_id');
    }

    public function location(): BelongsTo
    {
        return $this->primaryLocation();
    }

    /**
     * User who registered the customer.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Customer Khata ledger entries.
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(CustomerLedgerEntry::class, 'customer_id')->orderBy('entry_date')->orderBy('id');
    }

    /**
     * Sales orders associated with this customer.
     */
    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class, 'customer_id');
    }

    /**
     * Sales invoices associated with this customer.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class, 'customer_id');
    }

    /**
     * Payments recorded for this customer.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'customer_id');
    }

    /**
     * Sales returns recorded for this customer.
     */
    public function salesReturns(): HasMany
    {
        return $this->hasMany(SalesReturn::class, 'customer_id');
    }

    /**
     * Credit notes issued to this customer.
     */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(CustomerCreditNote::class, 'customer_id');
    }

    /**
     * Refunds paid to this customer.
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(CustomerRefund::class, 'customer_id');
    }

    /**
     * Scope customers strictly to locations the given user is authorized to access.
     */
    public function scopeAuthorizedFor(Builder $query, User $user): Builder
    {
        if ($user->isOwner()) {
            return $query;
        }

        $authorizedLocationIds = $user->getAuthorizedLocationIds();

        return $query->whereIn('primary_location_id', $authorizedLocationIds);
    }

    /**
     * Scope to active customers only.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Flexible multi-field search scope.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (!$term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('customer_code', 'like', "%{$term}%")
              ->orWhere('business_name', 'like', "%{$term}%")
              ->orWhere('contact_person', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%")
              ->orWhere('city', 'like', "%{$term}%");
        });
    }

    /**
     * Get real-time Khata summary via CustomerKhataService.
     */
    public function getKhataSummaryAttribute(): array
    {
        return app(CustomerKhataService::class)->calculateBalance($this);
    }

    /**
     * Get closing balance amount.
     */
    public function getClosingBalanceAttribute(): float
    {
        return (float) $this->khata_summary['closing_balance'];
    }

    /**
     * Get current balance amount (alias for closing balance).
     */
    public function getCurrentBalanceAttribute(): float
    {
        return (float) $this->khata_summary['closing_balance'];
    }

    /**
     * Get balance status label: receivable | payable | settled.
     */
    public function getBalanceStatusAttribute(): string
    {
        return $this->khata_summary['balance_status'];
    }
}
