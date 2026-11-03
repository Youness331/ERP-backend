<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithErp;
use Tests\TestCase;

class InvoicePdfTest extends TestCase
{
    use InteractsWithErp, RefreshDatabase;

    private function invoice(): Invoice
    {
        $client = Client::factory()->create([
            'name' => 'Atlas Distribution',
            'city' => 'Casablanca',
            'ice' => '002345678000012',
        ]);

        $product = Product::factory()->create(['name' => 'Ramette A4', 'sku' => 'REF-0012']);

        $invoice = Invoice::query()->create([
            'reference' => 'FAC-2026-0001',
            'client_id' => $client->id,
            'type' => 'invoice',
            'status' => 'sent',
            'issue_date' => '2026-11-02',
            'due_date' => '2026-12-02',
            'subtotal' => 480,
            'discount_total' => 0,
            'tax_total' => 96,
            'total' => 576,
            'amount_paid' => 0,
            'balance_due' => 576,
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'description' => 'Ramette A4',
            'quantity' => 2,
            'unit_price' => 240,
            'discount' => 0,
            'tax_rate' => 20,
            'line_total' => 480,
        ]);

        return $invoice;
    }

    public function test_an_invoice_can_be_downloaded_as_pdf(): void
    {
        $invoice = $this->invoice();

        $this->actingWithPermissions(['invoices.view']);

        $response = $this->get("/api/v1/invoices/{$invoice->id}/pdf");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_the_pdf_is_named_after_the_reference(): void
    {
        $invoice = $this->invoice();

        $this->actingWithPermissions(['invoices.view']);

        $response = $this->get("/api/v1/invoices/{$invoice->id}/pdf");

        $this->assertStringContainsString(
            'FAC-2026-0001.pdf',
            (string) $response->headers->get('content-disposition'),
        );
    }

    public function test_downloading_a_pdf_requires_the_invoice_permission(): void
    {
        $invoice = $this->invoice();

        $this->actingWithPermissions(['clients.view']);

        $this->get("/api/v1/invoices/{$invoice->id}/pdf")->assertForbidden();
    }
}
