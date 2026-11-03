<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\StockMovement;
use App\Observers\AuditObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithErp;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use InteractsWithErp, RefreshDatabase;

    public function test_creating_a_client_is_journalled(): void
    {
        $this->actingWithPermissions(['clients.create', 'audit.view']);

        $this->postJson('/api/v1/clients', ['name' => 'Atlas Distribution'])->assertCreated();

        $log = ActivityLog::query()->where('event', 'created')->latest('id')->firstOrFail();

        $this->assertSame('Client Atlas Distribution créé', $log->description);
        $this->assertSame(Client::class, $log->subject_type);
        $this->assertNotNull($log->user_id);
    }

    public function test_updating_a_client_records_only_the_changed_fields(): void
    {
        $client = Client::factory()->create(['name' => 'Ancien Nom']);
        $this->actingWithPermissions(['clients.update', 'audit.view']);

        $this->patchJson("/api/v1/clients/{$client->id}", ['name' => 'Nouveau Nom'])->assertOk();

        $log = ActivityLog::query()->where('event', 'updated')->latest('id')->firstOrFail();

        $this->assertSame('Nouveau Nom', $log->changes['name']);
        $this->assertArrayNotHasKey('updated_at', $log->changes);
        $this->assertSame($client->id, $log->subject_id);
    }

    public function test_deleting_a_client_is_journalled(): void
    {
        $client = Client::factory()->create(['name' => 'Client Éphémère']);
        $this->actingWithPermissions(['clients.delete', 'audit.view']);

        $this->deleteJson("/api/v1/clients/{$client->id}")->assertNoContent();

        $log = ActivityLog::query()->where('event', 'deleted')->latest('id')->firstOrFail();

        $this->assertSame('Client Client Éphémère supprimé', $log->description);
    }

    public function test_the_journal_is_protected_by_a_permission(): void
    {
        $this->actingWithPermissions(['clients.view']);

        $this->getJson('/api/v1/activity-logs')->assertForbidden();
    }

    public function test_the_journal_can_be_filtered_by_subject(): void
    {
        Client::factory()->count(2)->create();

        $this->actingWithPermissions(['audit.view'], 'auditor');

        $this->getJson('/api/v1/activity-logs?subject_type='.urlencode(Client::class).'&event=created')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.event', 'created');
    }

    public function test_a_stock_movement_is_not_journalled(): void
    {
        // The stock ledger already provides its own audit trail.
        $this->assertNotContains(
            StockMovement::class,
            AuditObserver::MODELS,
        );
    }
}
