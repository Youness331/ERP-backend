<?php

namespace App\Models;

use App\Enums\SalesOrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference', 'client_id', 'warehouse_id', 'status', 'order_date',
        'delivery_date', 'delivered_at', 'subtotal', 'discount_total',
        'tax_total', 'total', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => SalesOrderStatus::class,
            'order_date' => 'date',
            'delivery_date' => 'date',
            'delivered_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
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

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            SalesOrderStatus::Draft,
            SalesOrderStatus::Confirmed,
            SalesOrderStatus::PartiallyDelivered,
        ]);
    }

    public function isFullyDelivered(): bool
    {
        return $this->lines->every(
            fn (SalesOrderLine $line) => (float) $line->delivered_quantity >= (float) $line->quantity
        );
    }
}
