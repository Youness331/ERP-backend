<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    public const ADMIN = 'admin';

    public const MANAGER = 'manager';

    public const ACCOUNTANT = 'accountant';

    public const STOCK_MANAGER = 'stock_manager';

    public const SALES = 'sales';

    protected $fillable = ['name', 'label', 'description'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
