<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference', 'supplier_id', 'warehouse_id', 'status', 'order_date',
        'expected_date', 'received_at', 'subtotal', 'discount_total',
        'tax_total', 'total', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'order_date' => 'date',
            'expected_date' => 'date',
            'received_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return blank($term) ? $query : $query->where('reference', 'like', '%'.$term.'%');
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return blank($status) ? $query : $query->where('status', $status);
    }

    public function isFullyReceived(): bool
    {
        return $this->lines->every(
            fn (PurchaseOrderLine $line) => (float) $line->received_quantity >= (float) $line->quantity
        );
    }
}
