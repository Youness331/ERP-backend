<?php

namespace Tests\Feature;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithErp;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use InteractsWithErp, RefreshDatabase;

    public function test_user_without_permission_is_forbidden(): void
    {
        $this->actingWithPermissions(['clients.view']);

        $this->getJson('/api/v1/suppliers')->assertForbidden();
    }

    public function test_user_with_permission_can_access_the_module(): void
    {
        Client::factory()->count(3)->create();

        $this->actingWithPermissions(['clients.view']);

        $this->getJson('/api/v1/clients')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_bypasses_permission_checks(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/users')->assertOk();
    }

    public function test_write_permission_is_required_to_create(): void
    {
        $payload = ['name' => 'Client Test', 'email' => 'client@test.ma'];

        $this->actingWithPermissions(['clients.view']);

        $this->postJson('/api/v1/clients', $payload)->assertForbidden();

        $this->actingWithPermissions(['clients.create'], 'creator');

        $this->postJson('/api/v1/clients', $payload)->assertCreated();
    }

    public function test_disabled_account_is_rejected_even_with_token(): void
    {
        $user = $this->actingWithPermissions(['clients.view']);
        $user->update(['is_active' => false]);

        $this->getJson('/api/v1/clients')->assertForbidden();
    }
}
