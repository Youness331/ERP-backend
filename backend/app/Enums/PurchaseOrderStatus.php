<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Confirmed => 'Confirmée',
            self::PartiallyReceived => 'Partiellement réceptionnée',
            self::Received => 'Réceptionnée',
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

    public function isReceivable(): bool
    {
        return in_array($this, [self::Confirmed, self::PartiallyReceived], true);
    }

    public function isCancellable(): bool
    {
        return in_array($this, [self::Draft, self::Confirmed], true);
    }
}
