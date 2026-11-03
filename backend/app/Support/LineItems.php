<?php

namespace App\Support;

use App\Models\Product;

/**
 * Normalises raw order lines coming from an HTTP request into the columns
 * stored on purchase order, sales order and invoice lines.
 */
final class LineItems
{
    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  callable(Product): float  $defaultUnitPrice
     * @return array<int, array<string, mixed>>
     */
    public static function prepare(array $lines, callable $defaultUnitPrice): array
    {
        return array_values(array_map(function (array $line) use ($defaultUnitPrice) {
            /** @var Product $product */
            $product = Product::query()->findOrFail($line['product_id']);

            $quantity = (float) $line['quantity'];
            $unitPrice = (float) ($line['unit_price'] ?? $defaultUnitPrice($product));
            $discount = (float) ($line['discount'] ?? 0);
            $taxRate = (float) ($line['tax_rate'] ?? $product->tax_rate);

            return [
                'product_id' => $product->id,
                'description' => $line['description'] ?? $product->name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount' => $discount,
                'tax_rate' => $taxRate,
                'line_total' => LineTotals::lineTotal($quantity, $unitPrice, $discount),
            ];
        }, $lines));
    }
}
