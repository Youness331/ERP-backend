<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return RoleResource::collection(
            Role::query()->with('permissions')->withCount('users')->orderBy('id')->get()
        );
    }

    public function show(Role $role): RoleResource
    {
        return new RoleResource($role->load('permissions')->loadCount('users'));
    }

    /**
     * Flat permission catalogue grouped by module, for the Angular matrix UI.
     */
    public function permissions(): JsonResponse
    {
        $permissions = Permission::query()->orderBy('module')->orderBy('name')->get();

        return response()->json([
            'data' => $permissions
                ->groupBy('module')
                ->map(fn ($items, $module) => [
                    'module' => $module,
                    'permissions' => $items->map(fn (Permission $permission) => [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'label' => $permission->label,
                    ])->values()->all(),
                ])
                ->values()
                ->all(),
        ]);
    }
}
