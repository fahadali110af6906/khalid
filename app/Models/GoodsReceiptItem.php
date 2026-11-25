<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'goods_receipt_id',
        'purchase_order_item_id',
        'product_variant_id',
        'received_cartons',
        'received_pairs',
        'unit_cost_per_pair',
        'total_cost',
        'stock_movement_id',
        'notes',
    ];

    protected $casts = [
        'received_cartons' => 'decimal:4',
        'received_pairs' => 'decimal:4',
        'unit_cost_per_pair' => 'decimal:4',
        'total_cost' => 'decimal:2',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }

    public function returnItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class, 'goods_receipt_item_id');
    }

    /**
     * Calculate previously posted returned pairs for this receipt item.
     */
    public function getPreviouslyReturnedPairsAttribute(): float
    {
        return (float) $this->returnItems()
            ->whereHas('purchaseReturn', fn ($q) => $q->where('status', 'posted'))
            ->sum('returned_pairs');
    }

    /**
     * Calculate remaining returnable pairs.
     */
    public function getRemainingReturnablePairsAttribute(): float
    {
        $recv = (float) $this->received_pairs;
        $ret = $this->previously_returned_pairs;
        return max(0.0, (float) bcsub((string) $recv, (string) $ret, 4));
    }
}
