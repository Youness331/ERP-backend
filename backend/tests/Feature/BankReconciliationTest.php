<?php

namespace Tests\Feature;

use App\Models\BankStatementLine;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithErp;
use Tests\TestCase;

class BankReconciliationTest extends TestCase
{
    use InteractsWithErp, RefreshDatabase;

    private const PERMISSIONS = ['bank.view', 'bank.create', 'bank.reconcile'];

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $client = Client::factory()->create();

        $invoice = Invoice::query()->create([
            'reference' => 'FAC-2026-0001',
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'sent',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => 200,
            'discount_total' => 0,
            'tax_total' => 40,
            'total' => 240,
            'amount_paid' => 0,
            'balance_due' => 240,
        ]);

        $this->payment = Payment::query()->create([
            'reference' => 'PAY-2026-0001',
            'payable_type' => $invoice->getMorphClass(),
            'payable_id' => $invoice->id,
            'direction' => 'in',
            'method' => 'bank_transfer',
            'amount' => 240,
            'payment_date' => now()->toDateString(),
        ]);

        $this->actingWithPermissions(self::PERMISSIONS);
    }

    private function line(array $attributes = []): BankStatementLine
    {
        $response = $this->postJson('/api/v1/bank-lines', [
            'label' => 'VIR CLIENT ATLAS',
            'amount' => 240,
            'direction' => 'in',
            'value_date' => now()->toDateString(),
            ...$attributes,
        ])->assertCreated();

        return BankStatementLine::query()->findOrFail($response->json('data.id'));
    }

    public function test_a_statement_line_starts_unmatched(): void
    {
        $line = $this->line(['reference' => 'VIR-8891']);

        $this->assertSame('unmatched', $line->status->value);
        $this->assertSame('VIR-8891', $line->reference);
    }

    public function test_a_line_can_be_matched_with_its_payment(): void
    {
        $line = $this->line();

        $this->postJson("/api/v1/bank-lines/{$line->id}/match", ['payment_id' => $this->payment->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'matched')
            ->assertJsonPath('data.payment_id', $this->payment->id)
            ->assertJsonPath('data.payment.reference', 'PAY-2026-0001');

        $this->assertNotNull($line->fresh()->matched_at);
    }

    public function test_suggestions_list_the_unmatched_payment(): void
    {
        $line = $this->line();

        $this->getJson("/api/v1/bank-lines/{$line->id}/suggestions")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->payment->id);
    }

    public function test_a_different_amount_cannot_be_matched(): void
    {
        $line = $this->line(['amount' => 120]);

        $this->postJson("/api/v1/bank-lines/{$line->id}/match", ['payment_id' => $this->payment->id])
            ->assertStatus(422);
    }

    public function test_a_different_direction_cannot_be_matched(): void
    {
        $line = $this->line(['direction' => 'out']);

        $this->postJson("/api/v1/bank-lines/{$line->id}/match", ['payment_id' => $this->payment->id])
            ->assertStatus(422);
    }

    public function test_a_payment_cannot_be_reconciled_twice(): void
    {
        $first = $this->line();
        $second = $this->line(['label' => 'VIR DOUBLON']);

        $this->postJson("/api/v1/bank-lines/{$first->id}/match", ['payment_id' => $this->payment->id])->assertOk();

        $this->postJson("/api/v1/bank-lines/{$second->id}/match", ['payment_id' => $this->payment->id])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ce paiement est déjà rapproché avec une autre ligne.');
    }

    public function test_unmatching_restores_the_line(): void
    {
        $line = $this->line();
        $this->postJson("/api/v1/bank-lines/{$line->id}/match", ['payment_id' => $this->payment->id])->assertOk();

        $this->postJson("/api/v1/bank-lines/{$line->id}/unmatch")
            ->assertOk()
            ->assertJsonPath('data.status', 'unmatched')
            ->assertJsonPath('data.payment_id', null);
    }

    public function test_the_summary_counts_each_status(): void
    {
        $this->line();
        $matched = $this->line(['label' => 'AUTRE']);
        $this->postJson("/api/v1/bank-lines/{$matched->id}/match", ['payment_id' => $this->payment->id])->assertOk();

        $this->getJson('/api/v1/bank-lines/summary')
            ->assertOk()
            ->assertJsonPath('data.unmatched', 1)
            ->assertJsonPath('data.matched', 1);
    }

    public function test_reconciliation_requires_a_permission(): void
    {
        $line = $this->line();

        $this->actingWithPermissions(['clients.view']);

        $this->getJson('/api/v1/bank-lines')->assertForbidden();
        $this->postJson("/api/v1/bank-lines/{$line->id}/match", ['payment_id' => $this->payment->id])->assertForbidden();
    }
}
