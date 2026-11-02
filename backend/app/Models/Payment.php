<?php

namespace App\Models;

use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'payable_type', 'payable_id', 'direction', 'method',
        'amount', 'payment_date', 'reference_number', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'direction' => PaymentDirection::class,
            'method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
