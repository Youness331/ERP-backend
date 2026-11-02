<?php

namespace App\Exceptions;

use App\Models\Product;
use App\Models\Warehouse;

class InsufficientStockException extends BusinessException
{
    public static function for(Product $product, Warehouse $warehouse, float $requested, float $available): self
    {
        return new self(sprintf(
            'Stock insuffisant pour « %s » dans l\'entrepôt « %s » : disponible %s, demandé %s.',
            $product->name,
            $warehouse->name,
            self::format($available),
            self::format($requested),
        ));
    }

    private static function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, ',', ' '), '0'), ',');
    }
}
