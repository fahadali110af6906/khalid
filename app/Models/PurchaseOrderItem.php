<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'product_variant_id',
        'unit_type',
        'quantity_cartons',
        'carton_pack_qty',
        'ordered_pairs',
        'received_pairs',
        'unit_cost',
        'unit_cost_per_pair',
        'line_subtotal',
        'line_discount',
        'line_total',
        'notes',
    ];

    protected $casts = [
        'quantity_cartons' => 'decimal:4',
        'carton_pack_qty' => 'integer',
        'ordered_pairs' => 'decimal:4',
        'received_pairs' => 'decimal:4',
        'unit_cost' => 'decimal:2',
        'unit_cost_per_pair' => 'decimal:4',
        'line_subtotal' => 'decimal:2',
        'line_discount' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function receiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class, 'purchase_order_item_id');
    }

    public function remainingPairs(): float
    {
        $rem = bcsub((string) $this->ordered_pairs, (string) $this->received_pairs, 4);
        return max(0.0, (float) $rem);
    }
}
