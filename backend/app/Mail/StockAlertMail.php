<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StockAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, \App\Models\Product>  $products
     */
    public function __construct(
        public readonly Collection $products,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('Alerte stock — %d produit(s) sous le seuil', $this->products->count()),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.stock-alert');
    }
}
