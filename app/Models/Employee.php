<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_code',
        'location_id',
        'name',
        'phone',
        'cnic',
        'address',
        'city',
        'designation',
        'employment_type',
        'monthly_salary',
        'daily_rate',
        'joining_date',
        'emergency_contact_name',
        'emergency_contact_phone',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'monthly_salary' => 'decimal:2',
        'daily_rate' => 'decimal:2',
        'joining_date' => 'date',
    ];

    /**
     * Location relationship (Shop, Factory, Warehouse).
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * All transactions (Salaries, Advances, Deductions, Bonuses).
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(EmployeeTransaction::class)->orderBy('transaction_date', 'desc')->orderBy('id', 'desc');
    }

    /**
     * Creator user.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope for active employees.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for strict location filtering.
     */
    public function scopeForLocation($query, $locationId)
    {
        if ($locationId && $locationId !== 'all') {
            return $query->where('location_id', $locationId);
        }
        return $query;
    }

    /**
     * Total salary payments received.
     */
    public function getTotalSalaryPaidAttribute(): float
    {
        return (float) $this->transactions()->where('type', 'salary_payment')->sum('amount');
    }

    /**
     * Total advances / kharcha taken.
     */
    public function getTotalAdvancesAttribute(): float
    {
        return (float) $this->transactions()->where('type', 'advance_kharcha')->sum('amount');
    }

    /**
     * Total bonuses.
     */
    public function getTotalBonusesAttribute(): float
    {
        return (float) $this->transactions()->where('type', 'bonus')->sum('amount');
    }

    /**
     * Total deductions.
     */
    public function getTotalDeductionsAttribute(): float
    {
        return (float) $this->transactions()->where('type', 'deduction')->sum('amount');
    }

    /**
     * Current month advance balance (Advances taken in current month minus deductions).
     */
    public function getCurrentMonthAdvancesAttribute(): float
    {
        $currentMonth = now()->format('Y-m');
        return (float) $this->transactions()
            ->where('type', 'advance_kharcha')
            ->where('transaction_date', 'like', "$currentMonth%")
            ->sum('amount');
    }

    /**
     * Net outstanding advance balance: (Advances + Deductions) - (Salary Deductions settled).
     * Represents how much advance/kharcha is currently pending against the employee.
     */
    public function getPendingAdvanceBalanceAttribute(): float
    {
        return (float) ($this->total_advances - $this->total_deductions);
    }

    /**
     * Generate next employee code for a location.
     */
    public static function generateCode(?int $locationId = null): string
    {
        $prefix = 'EMP';
        if ($locationId) {
            $loc = Location::find($locationId);
            if ($loc) {
                $codePart = str_replace(['LOC-', 'LOC_'], '', $loc->code ?? '');
                $prefix .= '-' . ($codePart ?: strtoupper(substr($loc->type, 0, 4)));
            }
        }
        $latestId = self::withTrashed()->max('id') ?? 0;
        return sprintf('%s-%04d', $prefix, $latestId + 1);
    }
}
