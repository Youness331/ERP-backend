<?php

namespace App\Enums;

enum InvoiceType: string
{
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Facture',
            self::CreditNote => 'Avoir',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Prefix used when generating the document reference. */
    public function referencePrefix(): string
    {
        return match ($this) {
            self::Invoice => 'FAC',
            self::CreditNote => 'AVR',
        };
    }
}
