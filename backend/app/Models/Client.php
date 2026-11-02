<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'company_name', 'email', 'phone', 'mobile',
        'address', 'city', 'postal_code', 'country', 'ice', 'tax_id',
        'rc', 'patente', 'credit_limit', 'notes', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('name', 'like', $like)
                ->orWhere('code', 'like', $like)
                ->orWhere('company_name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like);
        });
    }
}
