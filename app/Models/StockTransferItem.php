<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_transfer_id',
        'product_variant_id',
        'quantity_cartons',
        'carton_pack_qty',
        'quantity_pairs',
        'unit_cost',
        'total_cost',
        'received_quantity_pairs',
        'notes',
    ];

    protected $casts = [
        'quantity_cartons' => 'integer',
        'carton_pack_qty' => 'integer',
        'quantity_pairs' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:2',
        'received_quantity_pairs' => 'decimal:4',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
