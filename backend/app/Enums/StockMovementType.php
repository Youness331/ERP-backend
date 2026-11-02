<?php

namespace App\Enums;

enum StockMovementType: string
{
    case In = 'in';
    case Out = 'out';
    case Adjustment = 'adjustment';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Entrée',
            self::Out => 'Sortie',
            self::Adjustment => 'Ajustement',
            self::TransferIn => 'Transfert entrant',
            self::TransferOut => 'Transfert sortant',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** +1 when the movement feeds the warehouse, -1 when it drains it, 0 for signed adjustments. */
    public function sign(): int
    {
        return match ($this) {
            self::In, self::TransferIn => 1,
            self::Out, self::TransferOut => -1,
            self::Adjustment => 0,
        };
    }
}
