<?php

namespace App\Support;

/**
 * Money and tax arithmetic shared by orders and invoices.
 *
 * Every amount is rounded to 2 decimals at the line level so that the sum of
 * the lines always matches the document totals.
 */
final class LineTotals
{
    public static function lineTotal(float $quantity, float $unitPrice, float $discount = 0.0): float
    {
        return round(max(0, $quantity * $unitPrice - $discount), 2);
    }

    public static function tax(float $lineTotal, float $taxRate): float
    {
        return round($lineTotal * $taxRate / 100, 2);
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $lines
     * @return array{subtotal: float, discount_total: float, tax_total: float, total: float}
     */
    public static function summarize(iterable $lines): array
    {
        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;

        foreach ($lines as $line) {
            $quantity = (float) $line['quantity'];
            $unitPrice = (float) $line['unit_price'];
            $discount = (float) ($line['discount'] ?? 0);
            $taxRate = (float) ($line['tax_rate'] ?? 0);

            $gross = round($quantity * $unitPrice, 2);
            $net = round($gross - $discount, 2);

            $subtotal += $gross;
            $discountTotal += $discount;
            $taxTotal += round($net * $taxRate / 100, 2);
        }

        $subtotal = round($subtotal, 2);
        $discountTotal = round($discountTotal, 2);
        $taxTotal = round($taxTotal, 2);

        return [
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'total' => round($subtotal - $discountTotal + $taxTotal, 2),
        ];
    }
}
