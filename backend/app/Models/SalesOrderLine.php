<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_order_id', 'product_id', 'description', 'quantity',
        'delivered_quantity', 'unit_price', 'discount', 'tax_rate', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'delivered_quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function remainingQuantity(): Attribute
    {
        return Attribute::get(
            fn () => round((float) $this->quantity - (float) $this->delivered_quantity, 3)
        );
    }
}
