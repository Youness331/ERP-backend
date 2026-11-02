<?php

namespace App\Models;

use App\Support\Permissions;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'phone',
        'job_title',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function isAdmin(): bool
    {
        return $this->role?->name === Role::ADMIN;
    }

    public function hasRole(string ...$names): bool
    {
        return $this->role !== null && in_array($this->role->name, $names, true);
    }

    /**
     * Administrators implicitly hold every permission.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $this->loadMissing('role.permissions');

        return (bool) $this->role?->permissions->contains('name', $permission);
    }

    /**
     * @return list<string>
     */
    public function permissionNames(): array
    {
        if ($this->isAdmin()) {
            return Permissions::names();
        }

        $this->loadMissing('role.permissions');

        return $this->role?->permissions->pluck('name')->values()->all() ?? [];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
