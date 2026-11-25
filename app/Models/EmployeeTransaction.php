<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_number',
        'employee_id',
        'location_id',
        'type',
        'amount',
        'transaction_date',
        'month_year',
        'payment_method',
        'description',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    /**
     * Associated employee.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Associated location.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * User who recorded this transaction.
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Type badge label in Urdu / English.
     */
    public function getTypeLabelAttribute(): string
    {
        $isUr = app()->getLocale() === 'ur';
        return match($this->type) {
            'salary_payment' => $isUr ? 'تنخواہ کی ادائیگی' : 'Salary Payment',
            'advance_kharcha' => $isUr ? 'پیشگی خرچہ / ادھار' : 'Advance (Kharcha)',
            'bonus' => $isUr ? 'بونس / انعام' : 'Bonus',
            'deduction' => $isUr ? 'کٹوتی' : 'Deduction',
            default => ucfirst(str_replace('_', ' ', $this->type)),
        };
    }

    /**
     * Badge CSS classes.
     */
    public function getBadgeClassAttribute(): string
    {
        return match($this->type) {
            'salary_payment' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            'advance_kharcha' => 'bg-amber-100 text-amber-800 border-amber-200',
            'bonus' => 'bg-purple-100 text-purple-800 border-purple-200',
            'deduction' => 'bg-rose-100 text-rose-800 border-rose-200',
            default => 'bg-slate-100 text-slate-800 border-slate-200',
        };
    }

    /**
     * Generate unique transaction number.
     */
    public static function generateTransactionNumber(string $type = 'advance_kharcha'): string
    {
        $prefix = match($type) {
            'salary_payment' => 'SAL',
            'advance_kharcha' => 'ADV',
            'bonus' => 'BON',
            'deduction' => 'DED',
            default => 'ETX',
        };

        $year = now()->format('Y');
        $latestId = self::max('id') ?? 0;
        return sprintf('%s-%s-%05d', $prefix, $year, $latestId + 1);
    }
}
