<?php

namespace Tests\Concerns;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

trait InteractsWithErp
{
    /**
     * @param  list<string>  $permissions
     */
    protected function userWithPermissions(array $permissions = [], string $roleName = 'tester'): User
    {
        $role = Role::firstOrCreate(
            ['name' => $roleName],
            ['label' => ucfirst($roleName)],
        );

        $role->permissions()->sync(
            collect($permissions)->map(fn (string $name) => Permission::firstOrCreate(
                ['name' => $name],
                ['label' => $name, 'module' => explode('.', $name)[0]],
            )->id)->all()
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    protected function admin(): User
    {
        $role = Role::firstOrCreate(
            ['name' => 'admin'],
            ['label' => 'Administrateur'],
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function actingWithPermissions(array $permissions, string $roleName = 'tester'): User
    {
        $user = $this->userWithPermissions($permissions, $roleName);
        Sanctum::actingAs($user);

        return $user;
    }

    protected function actingAsAdmin(): User
    {
        $user = $this->admin();
        Sanctum::actingAs($user);

        return $user;
    }
}
