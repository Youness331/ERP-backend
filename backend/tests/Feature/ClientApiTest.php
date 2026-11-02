<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithErp;
use Tests\TestCase;

class ClientApiTest extends TestCase
{
    use InteractsWithErp, RefreshDatabase;

    private const PERMISSIONS = [
        'clients.view', 'clients.create', 'clients.update', 'clients.delete',
    ];

    public function test_index_returns_paginated_clients(): void
    {
        Client::factory()->count(20)->create();
        $this->actingWithPermissions(self::PERMISSIONS);

        $this->getJson('/api/v1/clients?per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 20);
    }

    public function test_index_can_search_by_name_or_code(): void
    {
        Client::factory()->create(['name' => 'Atlas Distribution', 'code' => 'CLI-00001']);
        Client::factory()->create(['name' => 'Marrakech Traders', 'code' => 'CLI-00002']);

        $this->actingWithPermissions(self::PERMISSIONS);

        $this->getJson('/api/v1/clients?search=Atlas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Atlas Distribution');
    }

    public function test_store_generates_a_reference_code(): void
    {
        $this->actingWithPermissions(self::PERMISSIONS);

        $response = $this->postJson('/api/v1/clients', [
            'name' => 'Nouveau Client',
            'email' => 'nouveau@client.ma',
            'city' => 'Casablanca',
        ])->assertCreated();

        $this->assertStringStartsWith('CLI-', $response->json('data.code'));
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingWithPermissions(self::PERMISSIONS);

        $this->postJson('/api/v1/clients', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email']);
    }

    public function test_update_changes_fields(): void
    {
        $client = Client::factory()->create(['name' => 'Ancien Nom']);
        $this->actingWithPermissions(self::PERMISSIONS);

        $this->patchJson("/api/v1/clients/{$client->id}", ['name' => 'Nouveau Nom'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nouveau Nom');
    }

    public function test_client_with_open_orders_cannot_be_deleted(): void
    {
        $client = Client::factory()->create();
        SalesOrder::create([
            'reference' => 'SO-2026-0001',
            'client_id' => $client->id,
            'warehouse_id' => Warehouse::factory()->create()->id,
            'status' => 'draft',
            'order_date' => now(),
            'subtotal' => 0,
            'discount_total' => 0,
            'tax_total' => 0,
            'total' => 0,
        ]);

        $this->actingWithPermissions(self::PERMISSIONS);

        $this->deleteJson("/api/v1/clients/{$client->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Impossible de supprimer un client ayant des commandes en cours.');
    }
}
