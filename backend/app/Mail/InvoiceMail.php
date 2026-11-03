<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly string $pdf,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('Facture %s', $this->invoice->reference),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.invoice');
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdf, $this->invoice->reference.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
