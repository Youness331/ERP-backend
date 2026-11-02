<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attributes = []): User
    {
        $role = Role::create(['name' => 'admin', 'label' => 'Administrateur']);

        return User::create([
            'name' => 'Admin',
            'email' => 'admin@erp.test',
            'password' => 'Secret123!',
            'role_id' => $role->id,
            'is_active' => true,
            ...$attributes,
        ]);
    }

    public function test_user_can_login_and_receives_a_token(): void
    {
        $this->makeUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@erp.test',
            'password' => 'Secret123!',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'token_type',
                'user' => ['id', 'name', 'email', 'role', 'permissions', 'is_admin'],
            ]);

        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'phpunit']);
        $this->assertNotEmpty($response->json('user.permissions'));
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->makeUser();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@erp.test',
            'password' => 'wrong-password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->makeUser(['is_active' => false]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@erp.test',
            'password' => 'Secret123!',
        ])->assertForbidden();
    }

    public function test_guest_cannot_reach_protected_endpoints(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->getJson('/api/v1/clients')->assertUnauthorized();
    }

    public function test_authenticated_user_can_fetch_their_profile(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'admin@erp.test')
            ->assertJsonPath('data.is_admin', true);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
