<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stock extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'quantity',
        'reserved_quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'reserved_quantity' => 'decimal:3',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Quantity that can still be sold (on hand minus reservations).
     */
    protected function availableQuantity(): Attribute
    {
        return Attribute::get(
            fn () => round((float) $this->quantity - (float) $this->reserved_quantity, 3)
        );
    }
}
