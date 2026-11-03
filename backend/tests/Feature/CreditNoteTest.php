<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithErp;
use Tests\TestCase;

class CreditNoteTest extends TestCase
{
    use InteractsWithErp, RefreshDatabase;

    private const PERMISSIONS = ['invoices.view', 'invoices.create', 'invoices.update', 'invoices.send', 'payments.create'];

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $client = Client::factory()->create();
        $product = Product::factory()->create(['sale_price' => 100, 'tax_rate' => 20]);

        $this->invoice = Invoice::query()->create([
            'reference' => 'FAC-2026-0001',
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'sent',
            'issue_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->addDays(20)->toDateString(),
            'subtotal' => 200,
            'discount_total' => 0,
            'tax_total' => 40,
            'total' => 240,
            'amount_paid' => 0,
            'balance_due' => 240,
        ]);

        InvoiceLine::create([
            'invoice_id' => $this->invoice->id,
            'product_id' => $product->id,
            'description' => 'Prestation',
            'quantity' => 2,
            'unit_price' => 100,
            'discount' => 0,
            'tax_rate' => 20,
            'line_total' => 200,
        ]);

        $this->actingWithPermissions(self::PERMISSIONS);
    }

    public function test_a_full_credit_note_copies_the_invoice_lines(): void
    {
        $response = $this->postJson("/api/v1/invoices/{$this->invoice->id}/credit-note")
            ->assertCreated()
            ->assertJsonPath('data.type', 'credit_note')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.total', 240)
            ->assertJsonPath('data.parent_invoice_id', $this->invoice->id);

        $this->assertStringStartsWith('AVR-', $response->json('data.reference'));
        $this->assertCount(1, $response->json('data.lines'));
    }

    public function test_sending_a_credit_note_reduces_the_invoice_balance(): void
    {
        $creditId = $this->postJson("/api/v1/invoices/{$this->invoice->id}/credit-note")
            ->json('data.id');

        $this->postJson("/api/v1/invoices/{$creditId}/send")->assertOk();

        $invoice = $this->invoice->fresh();

        $this->assertSame(240.0, (float) $invoice->credited_amount);
        $this->assertSame(0.0, (float) $invoice->balance_due);
        $this->assertSame('paid', $invoice->status->value);
    }

    public function test_a_partial_credit_note_keeps_a_balance(): void
    {
        $creditId = $this->postJson("/api/v1/invoices/{$this->invoice->id}/credit-note", [
            'lines' => [
                ['product_id' => null, 'description' => 'Geste commercial', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 20],
            ],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/invoices/{$creditId}/send")->assertOk();

        $invoice = $this->invoice->fresh();

        $this->assertSame(120.0, (float) $invoice->credited_amount);
        $this->assertSame(120.0, (float) $invoice->balance_due);
        $this->assertSame('partially_paid', $invoice->status->value);
    }

    public function test_a_credit_note_cannot_exceed_the_remaining_balance(): void
    {
        $this->postJson("/api/v1/invoices/{$this->invoice->id}/credit-note", [
            'lines' => [
                ['description' => 'Trop', 'quantity' => 10, 'unit_price' => 100, 'tax_rate' => 20],
            ],
        ])->assertStatus(422);
    }

    public function test_a_draft_invoice_cannot_be_credited(): void
    {
        $this->invoice->update(['status' => 'draft']);

        $this->postJson("/api/v1/invoices/{$this->invoice->id}/credit-note")->assertStatus(422);
    }

    public function test_cancelling_a_sent_credit_note_reverts_the_credit(): void
    {
        $creditId = $this->postJson("/api/v1/invoices/{$this->invoice->id}/credit-note")->json('data.id');
        $this->postJson("/api/v1/invoices/{$creditId}/send")->assertOk();

        $this->postJson("/api/v1/invoices/{$creditId}/cancel")->assertOk();

        $invoice = $this->invoice->fresh();

        $this->assertSame(0.0, (float) $invoice->credited_amount);
        $this->assertSame(240.0, (float) $invoice->balance_due);
    }

    public function test_credit_notes_are_not_counted_as_receivables(): void
    {
        $creditId = $this->postJson("/api/v1/invoices/{$this->invoice->id}/credit-note")->json('data.id');
        $this->postJson("/api/v1/invoices/{$creditId}/send")->assertOk();

        $outstanding = Invoice::query()->outstanding()->count();

        $this->assertSame(0, $outstanding);
    }

    public function test_a_credit_note_is_attached_to_its_invoice(): void
    {
        $creditId = $this->postJson("/api/v1/invoices/{$this->invoice->id}/credit-note")->json('data.id');

        $invoice = Invoice::query()->with('creditNotes')->findOrFail($this->invoice->id);

        $this->assertCount(1, $invoice->creditNotes);
        $this->assertSame($creditId, $invoice->creditNotes->first()->id);
    }
}
