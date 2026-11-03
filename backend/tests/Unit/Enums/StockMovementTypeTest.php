<?php

namespace Tests\Unit\Enums;

use App\Enums\StockMovementType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StockMovementTypeTest extends TestCase
{
    public function test_every_case_has_a_label(): void
    {
        $labels = [];

        foreach (StockMovementType::cases() as $type) {
            $this->assertNotSame('', $type->label());
            $labels[] = $type->label();
        }

        $this->assertSame($labels, array_values(array_unique($labels)));
    }

    #[DataProvider('signs')]
    public function test_the_sign_tells_the_direction_of_the_movement(StockMovementType $type, int $expected): void
    {
        $this->assertSame($expected, $type->sign());
    }

    /**
     * @return array<string, array{0: StockMovementType, 1: int}>
     */
    public static function signs(): array
    {
        return [
            'receipt' => [StockMovementType::In, 1],
            'issue' => [StockMovementType::Out, -1],
            'physical count' => [StockMovementType::Adjustment, 0],
            'transfer in' => [StockMovementType::TransferIn, 1],
            'transfer out' => [StockMovementType::TransferOut, -1],
        ];
    }
}
