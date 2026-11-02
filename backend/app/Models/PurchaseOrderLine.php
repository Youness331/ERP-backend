<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id', 'product_id', 'description', 'quantity',
        'received_quantity', 'unit_price', 'discount', 'tax_rate', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'received_quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function remainingQuantity(): Attribute
    {
        return Attribute::get(
            fn () => round((float) $this->quantity - (float) $this->received_quantity, 3)
        );
    }
}
