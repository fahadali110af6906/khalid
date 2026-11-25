<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_return_id',
        'sales_invoice_item_id',
        'product_variant_id',
        'returned_cartons',
        'returned_pairs',
        'unit_price_per_pair',
        'total_amount',
        'unit_cost',
        'stock_movement_id',
        'is_restocked',
        'condition',
        'reason',
        'notes',
    ];

    protected $casts = [
        'returned_cartons' => 'decimal:4',
        'returned_pairs' => 'decimal:4',
        'unit_price_per_pair' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'unit_cost' => 'decimal:4',
        'is_restocked' => 'boolean',
    ];

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class, 'sales_return_id');
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(SalesInvoiceItem::class, 'sales_invoice_item_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }
}
