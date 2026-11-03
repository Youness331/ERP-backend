<?php

namespace App\Models;

use App\Enums\BankLineStatus;
use App\Enums\PaymentDirection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line imported from the bank statement, matched against an internal payment.
 */
class BankStatementLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'label', 'amount', 'direction', 'value_date',
        'status', 'payment_id', 'matched_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'value_date' => 'date',
            'status' => BankLineStatus::class,
            'direction' => PaymentDirection::class,
            'matched_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return blank($status) ? $query : $query->where('status', $status);
    }

    public function scopeUnmatched(Builder $query): Builder
    {
        return $query->where('status', BankLineStatus::Unmatched);
    }

    public function isMatched(): bool
    {
        return $this->status === BankLineStatus::Matched;
    }
}
