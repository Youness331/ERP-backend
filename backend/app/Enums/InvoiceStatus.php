<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Sent => 'Envoyée',
            self::PartiallyPaid => 'Partiellement payée',
            self::Paid => 'Payée',
            self::Overdue => 'En retard',
            self::Cancelled => 'Annulée',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Statuses that still expect money from the client. */
    public function isOutstanding(): bool
    {
        return in_array($this, [self::Sent, self::PartiallyPaid, self::Overdue], true);
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function isPayable(): bool
    {
        return in_array($this, [self::Sent, self::PartiallyPaid, self::Overdue], true);
    }
}
