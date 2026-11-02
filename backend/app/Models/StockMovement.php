<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    use HasFactory;

    /**
     * Movements are immutable: they are written once and never updated.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'type',
        'quantity',
        'quantity_before',
        'quantity_after',
        'unit_cost',
        'reference_type',
        'reference_id',
        'reason',
        'user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'decimal:3',
            'quantity_before' => 'decimal:3',
            'quantity_after' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'created_at' => 'datetime',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
