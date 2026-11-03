<?php

namespace Tests\Unit\Support;

use App\Support\LineTotals;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LineTotalsTest extends TestCase
{
    #[DataProvider('lineTotals')]
    public function test_it_computes_a_rounded_line_total(
        float $quantity,
        float $unitPrice,
        float $discount,
        float $expected,
    ): void {
        $this->assertSame($expected, LineTotals::lineTotal($quantity, $unitPrice, $discount));
    }

    /**
     * @return array<string, array{0: float, 1: float, 2: float, 3: float}>
     */
    public static function lineTotals(): array
    {
        return [
            'quantity times price' => [2.0, 100.0, 0.0, 200.0],
            'discount is subtracted' => [2.0, 100.0, 10.0, 190.0],
            'fractional quantity' => [1.5, 19.99, 0.0, 29.99],
            'half cent rounds up' => [3.0, 19.995, 0.0, 59.99],
            'discount above gross floors at zero' => [1.0, 100.0, 150.0, 0.0],
            'zero quantity' => [0.0, 250.0, 0.0, 0.0],
        ];
    }

    #[DataProvider('taxes')]
    public function test_it_computes_tax_on_the_net_amount(float $net, float $rate, float $expected): void
    {
        $this->assertSame($expected, LineTotals::tax($net, $rate));
    }

    /**
     * @return array<string, array{0: float, 1: float, 2: float}>
     */
    public static function taxes(): array
    {
        return [
            'twenty percent' => [200.0, 20.0, 40.0],
            'zero rate' => [200.0, 0.0, 0.0],
            'exempt line' => [200.0, 0.0, 0.0],
            'rounds to cents' => [99.99, 20.0, 20.0],
            'reduced rate' => [150.0, 10.0, 15.0],
        ];
    }

    public function test_summarize_aggregates_every_line(): void
    {
        $totals = LineTotals::summarize([
            ['quantity' => 2, 'unit_price' => 100, 'discount' => 10, 'tax_rate' => 20],
            ['quantity' => 1, 'unit_price' => 50, 'discount' => 0, 'tax_rate' => 10],
        ]);

        $this->assertSame([
            'subtotal' => 250.0,
            'discount_total' => 10.0,
            'tax_total' => 43.0,
            'total' => 283.0,
        ], $totals);
    }

    public function test_summarize_defaults_missing_discount_and_tax(): void
    {
        $totals = LineTotals::summarize([
            ['quantity' => 1, 'unit_price' => 100],
        ]);

        $this->assertSame([
            'subtotal' => 100.0,
            'discount_total' => 0.0,
            'tax_total' => 0.0,
            'total' => 100.0,
        ], $totals);
    }

    public function test_summarize_of_an_empty_document_is_zeroed(): void
    {
        $this->assertSame([
            'subtotal' => 0.0,
            'discount_total' => 0.0,
            'tax_total' => 0.0,
            'total' => 0.0,
        ], LineTotals::summarize([]));
    }
}
