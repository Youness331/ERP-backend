<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Current user with the flat permission list consumed by the Angular guards.
 *
 * @mixin User
 */
class ProfileResource extends UserResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'permissions' => $this->permissionNames(),
            'is_admin' => $this->isAdmin(),
        ];
    }
}
