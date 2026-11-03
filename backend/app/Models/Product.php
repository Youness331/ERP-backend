<?php

namespace App\Models;

use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, Searchable, SoftDeletes;

    protected $fillable = [
        'sku', 'barcode', 'name', 'description', 'category_id', 'unit',
        'purchase_price', 'sale_price', 'cost_price', 'tax_rate',
        'reorder_level', 'track_stock', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'reorder_level' => 'decimal:3',
            'track_stock' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return list<string>
     */
    protected function searchableColumns(): array
    {
        return ['name', 'sku', 'barcode'];
    }

    /**
     * Products whose on-hand quantity fell to (or below) their reorder level.
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('track_stock', true)
            ->whereRaw(
                'COALESCE((SELECT SUM(stocks.quantity) FROM stocks WHERE stocks.product_id = products.id), 0) <= products.reorder_level'
            );
    }

    public function availableIn(int $warehouseId): float
    {
        $stock = $this->stocks->firstWhere('warehouse_id', $warehouseId);

        return $stock ? (float) $stock->quantity - (float) $stock->reserved_quantity : 0.0;
    }
}
