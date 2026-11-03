<?php

namespace App\Enums;

enum QuoteStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Converted = 'converted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Sent => 'Envoyé',
            self::Accepted => 'Accepté',
            self::Rejected => 'Refusé',
            self::Expired => 'Expiré',
            self::Converted => 'Converti en commande',
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

    public function isDecidable(): bool
    {
        return in_array($this, [self::Sent, self::Expired], true);
    }

    public function isConvertible(): bool
    {
        return $this === self::Accepted;
    }
}
