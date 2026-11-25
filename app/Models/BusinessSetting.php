<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class BusinessSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'group',
        'type',
        'location_id',
        'description',
    ];

    /**
     * Cache key prefix for fast settings retrieval.
     */
    protected const CACHE_PREFIX = 'erp_setting_';

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    /**
     * Get a setting by key, with default fallback and location-specific override support.
     */
    public static function get(string $key, mixed $default = null, ?int $locationId = null): mixed
    {
        $cacheKey = self::CACHE_PREFIX . $key . '_' . ($locationId ?? 'global');

        return Cache::remember($cacheKey, 3600, function () use ($key, $default, $locationId) {
            // Check location-specific override first if locationId is given
            if ($locationId !== null) {
                $setting = self::where('key', $key)->where('location_id', $locationId)->first();
                if ($setting) {
                    return self::castValue($setting->value, $setting->type);
                }
            }

            // Fallback to global setting
            $global = self::where('key', $key)->whereNull('location_id')->first();
            if ($global) {
                return self::castValue($global->value, $global->type);
            }

            // Fallback to defined system defaults
            return self::systemDefault($key, $default);
        });
    }

    /**
     * Set a setting value and clear its cache.
     */
    public static function set(string $key, mixed $value, string $group = 'general', ?int $locationId = null, ?string $description = null): self
    {
        $type = match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_array($value) => 'json',
            default => 'string',
        };

        $serialized = match ($type) {
            'boolean' => $value ? '1' : '0',
            'json' => json_encode($value),
            default => (string) $value,
        };

        $setting = self::updateOrCreate(
            ['key' => $key, 'location_id' => $locationId],
            [
                'value' => $serialized,
                'group' => $group,
                'type' => $type,
                'description' => $description,
            ]
        );

        Cache::forget(self::CACHE_PREFIX . $key . '_' . ($locationId ?? 'global'));

        return $setting;
    }

    /**
     * Cast string representation to proper PHP type.
     */
    protected static function castValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Defined system default fallbacks for Khalid Traders ERP.
     */
    public static function systemDefault(string $key, mixed $fallback = null): mixed
    {
        $defaults = [
            // Business Details
            'business_name' => 'Khalid Traders',
            'legal_name' => 'Khalid Traders Footwear Wholesale & Manufacturing',
            'business_phone' => '+92 42 37350001',
            'business_email' => 'info@khalidtraders.pk',
            'business_address' => 'Circular Road, Shah Alam Market, Lahore, Punjab, Pakistan',
            'tax_number' => 'NTN-7359124-8',

            // Branding & Invoices
            'invoice_header_text' => 'Wholesale Footwear Manufacturers & Distributors',
            'invoice_footer_terms' => 'Goods once sold will only be returned within 7 days in original cartons with approval. All disputes subject to Lahore jurisdiction.',
            'logo_url' => null,

            // Localization
            'default_locale' => 'en',
            'timezone' => 'Asia/Karachi',
            'date_format' => 'Y-m-d',
            'time_format' => 'h:i A',
            'currency_code' => 'PKR',
            'currency_symbol' => 'Rs.',
            'currency_position' => 'before', // before or after
            'decimal_places' => 2,

            // Numbering Prefixes
            'sales_invoice_prefix' => 'INV-',
            'purchase_order_prefix' => 'PO-',
            'goods_receipt_prefix' => 'GRN-',
            'sales_return_prefix' => 'SR-',
            'purchase_return_prefix' => 'PR-',
            'payment_voucher_prefix' => 'PMT-',
            'journal_entry_prefix' => 'JV-',
            'production_order_prefix' => 'MFG-',

            // Operational Defaults
            'default_report_range' => 'this_month',
            'auto_backup_enabled' => true,
            'backup_retention_days' => 30,
            'backup_retention_count' => 10,
        ];

        return $defaults[$key] ?? $fallback;
    }

    /**
     * Retrieve all settings grouped for the settings dashboard.
     */
    public static function getAllGrouped(?int $locationId = null): array
    {
        $settings = self::where(function ($q) use ($locationId) {
            $q->whereNull('location_id');
            if ($locationId !== null) {
                $q->orWhere('location_id', $locationId);
            }
        })->get()->keyBy('key');

        $groups = [
            'general' => [
                'business_name', 'legal_name', 'business_phone', 'business_email',
                'business_address', 'tax_number',
            ],
            'branding' => [
                'invoice_header_text', 'invoice_footer_terms', 'logo_url',
            ],
            'localization' => [
                'default_locale', 'timezone', 'date_format', 'time_format',
                'currency_code', 'currency_symbol', 'currency_position', 'decimal_places',
            ],
            'numbering' => [
                'sales_invoice_prefix', 'purchase_order_prefix', 'goods_receipt_prefix',
                'sales_return_prefix', 'purchase_return_prefix', 'payment_voucher_prefix',
                'journal_entry_prefix', 'production_order_prefix',
            ],
            'operational' => [
                'default_report_range', 'auto_backup_enabled',
                'backup_retention_days', 'backup_retention_count',
            ],
        ];

        $result = [];
        foreach ($groups as $groupKey => $keys) {
            $result[$groupKey] = [];
            foreach ($keys as $k) {
                $record = $settings->get($k);
                $result[$groupKey][$k] = $record
                    ? self::castValue($record->value, $record->type)
                    : self::systemDefault($k);
            }
        }

        return $result;
    }
}
