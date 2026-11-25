<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_invoice_id',
        'product_variant_id',
        'unit_type',
        'quantity_cartons',
        'carton_pack_qty',
        'quantity_pairs',
        'unit_price',
        'unit_price_per_pair',
        'line_subtotal',
        'line_discount',
        'line_total',
        'unit_cost',
        'notes',
    ];

    protected $casts = [
        'quantity_cartons' => 'decimal:4',
        'carton_pack_qty' => 'integer',
        'quantity_pairs' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'unit_price_per_pair' => 'decimal:2',
        'line_subtotal' => 'decimal:2',
        'line_discount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'unit_cost' => 'decimal:4',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->variant();
    }

    public function returnItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalesReturnItem::class, 'sales_invoice_item_id');
    }

    /**
     * Compute remaining returnable pairs against posted returns.
     */
    public function getRemainingReturnablePairsAttribute(): float
    {
        $postedReturnedPairs = (string) $this->returnItems()
            ->whereHas('salesReturn', function ($query) {
                $query->where('status', 'posted');
            })
            ->sum('returned_pairs');

        $remaining = bcsub((string) $this->quantity_pairs, $postedReturnedPairs, 4);

        return max(0.0, (float) $remaining);
    }
}
