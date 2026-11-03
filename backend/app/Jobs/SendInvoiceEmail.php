<?php

namespace App\Jobs;

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Renders the invoice PDF and emails it to the client.
 *
 * The job only carries the invoice id so a retry always works from fresh data.
 */
class SendInvoiceEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $invoiceId,
    ) {}

    public function handle(): void
    {
        $invoice = Invoice::query()
            ->with(['client', 'lines.product'])
            ->find($this->invoiceId);

        if ($invoice === null || blank($invoice->client?->email)) {
            return;
        }

        $pdf = Pdf::loadView('documents.invoice', [
            'invoice' => $invoice,
            'company' => config('erp.company'),
        ])->setPaper('a4')->output();

        Mail::to($invoice->client->email)->send(new InvoiceMail($invoice, $pdf));
    }
}
