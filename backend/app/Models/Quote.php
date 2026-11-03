<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quote extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference', 'client_id', 'warehouse_id', 'status', 'quote_date',
        'valid_until', 'subtotal', 'discount_total', 'tax_total', 'total',
        'notes', 'sales_order_id', 'converted_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'quote_date' => 'date',
            'valid_until' => 'date',
            'converted_at' => 'datetime',
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
        return $this->hasMany(QuoteLine::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
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

    /**
     * Quotes that have been sent but not decided yet.
     */
    public function scopeAwaitingDecision(Builder $query): Builder
    {
        return $query->whereIn('status', [QuoteStatus::Sent, QuoteStatus::Expired]);
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null
            && $this->valid_until->isBefore(today())
            && in_array($this->status, [QuoteStatus::Draft, QuoteStatus::Sent], true);
    }

    public function hasBeenConverted(): bool
    {
        return $this->sales_order_id !== null || $this->status === QuoteStatus::Converted;
    }
}
