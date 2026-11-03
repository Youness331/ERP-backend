<?php

namespace Tests\Feature;

use App\Jobs\SendInvoiceEmail;
use App\Mail\InvoiceMail;
use App\Mail\StockAlertMail;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithErp;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use InteractsWithErp, RefreshDatabase;

    private function invoice(?string $email = 'client@atlas.ma'): Invoice
    {
        $client = Client::factory()->create([
            'name' => 'Atlas Distribution',
            'email' => $email,
        ]);

        return Invoice::query()->create([
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
    }

    public function test_sending_an_invoice_queues_a_job(): void
    {
        $invoice = $this->invoice();

        Queue::fake();
        $this->actingWithPermissions(['invoices.send']);

        $this->postJson("/api/v1/invoices/{$invoice->id}/email")
            ->assertStatus(202)
            ->assertJsonPath('message', 'Facture mise en file d\'envoi.');

        Queue::assertPushed(SendInvoiceEmail::class, fn (SendInvoiceEmail $job) => $job->invoiceId === $invoice->id);
    }

    public function test_the_job_emails_the_invoice_pdf_to_the_client(): void
    {
        $invoice = $this->invoice();

        Mail::fake();

        (new SendInvoiceEmail($invoice->id))->handle();

        Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) use ($invoice) {
            return $mail->hasTo('client@atlas.ma')
                && $mail->invoice->id === $invoice->id
                && str_starts_with($mail->pdf, '%PDF');
        });
    }

    public function test_an_invoice_without_a_client_email_is_refused(): void
    {
        $invoice = $this->invoice(null);

        $this->actingWithPermissions(['invoices.send']);

        $this->postJson("/api/v1/invoices/{$invoice->id}/email")->assertStatus(422);
    }

    public function test_the_stock_alert_command_emails_low_stock_products(): void
    {
        Product::factory()->create(['name' => 'Produit critique', 'reorder_level' => 10]);

        Mail::fake();

        $this->artisan('erp:stock-alerts')->assertSuccessful();

        Mail::assertSent(StockAlertMail::class);
    }

    public function test_the_stock_alert_command_stays_quiet_when_stock_is_sufficient(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['reorder_level' => 10, 'track_stock' => true]);

        app(StockService::class)->increase($product, $warehouse, 500);

        Mail::fake();

        $this->artisan('erp:stock-alerts')->assertSuccessful();

        Mail::assertNothingSent();
    }
}
