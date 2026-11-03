<?php

namespace App\Enums;

enum BankLineStatus: string
{
    case Unmatched = 'unmatched';
    case Matched = 'matched';
    case Ignored = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::Unmatched => 'Non rapprochée',
            self::Matched => 'Rapprochée',
            self::Ignored => 'Ignorée',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
