<?php

namespace App\Enums;

enum SalesOrderStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case PartiallyDelivered = 'partially_delivered';
    case Delivered = 'delivered';
    case Invoiced = 'invoiced';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Confirmed => 'Confirmée',
            self::PartiallyDelivered => 'Partiellement livrée',
            self::Delivered => 'Livrée',
            self::Invoiced => 'Facturée',
            self::Cancelled => 'Annulée',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function canBeEdited(): bool
    {
        return $this === self::Draft;
    }

    public function isCancellable(): bool
    {
        return in_array($this, [self::Draft, self::Confirmed], true);
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::Confirmed, self::PartiallyDelivered], true);
    }
}
