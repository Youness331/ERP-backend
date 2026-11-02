<?php

namespace App\Enums;

enum PaymentDirection: string
{
    case Incoming = 'in';
    case Outgoing = 'out';

    public function label(): string
    {
        return match ($this) {
            self::Incoming => 'Encaissement',
            self::Outgoing => 'Décaissement',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
