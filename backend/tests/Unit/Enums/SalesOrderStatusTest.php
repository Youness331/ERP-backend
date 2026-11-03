<?php

namespace Tests\Unit\Enums;

use App\Enums\SalesOrderStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SalesOrderStatusTest extends TestCase
{
    public function test_every_case_has_a_label(): void
    {
        foreach (SalesOrderStatus::cases() as $status) {
            $this->assertNotSame('', $status->label());
        }
    }

    public function test_values_match_the_cases(): void
    {
        $this->assertSame(
            array_column(SalesOrderStatus::cases(), 'value'),
            SalesOrderStatus::values(),
        );
    }

    #[DataProvider('editability')]
    public function test_only_drafts_are_editable(SalesOrderStatus $status, bool $expected): void
    {
        $this->assertSame($expected, $status->canBeEdited());
    }

    /**
     * @return array<string, array{0: SalesOrderStatus, 1: bool}>
     */
    public static function editability(): array
    {
        return [
            'draft' => [SalesOrderStatus::Draft, true],
            'confirmed' => [SalesOrderStatus::Confirmed, false],
            'partially delivered' => [SalesOrderStatus::PartiallyDelivered, false],
            'delivered' => [SalesOrderStatus::Delivered, false],
            'invoiced' => [SalesOrderStatus::Invoiced, false],
            'cancelled' => [SalesOrderStatus::Cancelled, false],
        ];
    }

    #[DataProvider('cancellability')]
    public function test_only_draft_and_confirmed_orders_can_be_cancelled(SalesOrderStatus $status, bool $expected): void
    {
        $this->assertSame($expected, $status->isCancellable());
    }

    /**
     * @return array<string, array{0: SalesOrderStatus, 1: bool}>
     */
    public static function cancellability(): array
    {
        return [
            'draft' => [SalesOrderStatus::Draft, true],
            'confirmed' => [SalesOrderStatus::Confirmed, true],
            'partially delivered' => [SalesOrderStatus::PartiallyDelivered, false],
            'delivered' => [SalesOrderStatus::Delivered, false],
            'invoiced' => [SalesOrderStatus::Invoiced, false],
            'cancelled' => [SalesOrderStatus::Cancelled, false],
        ];
    }

    #[DataProvider('openness')]
    public function test_open_statuses_are_the_ones_still_in_flight(SalesOrderStatus $status, bool $expected): void
    {
        $this->assertSame($expected, $status->isOpen());
    }

    /**
     * @return array<string, array{0: SalesOrderStatus, 1: bool}>
     */
    public static function openness(): array
    {
        return [
            'draft' => [SalesOrderStatus::Draft, true],
            'confirmed' => [SalesOrderStatus::Confirmed, true],
            'partially delivered' => [SalesOrderStatus::PartiallyDelivered, true],
            'delivered' => [SalesOrderStatus::Delivered, false],
            'invoiced' => [SalesOrderStatus::Invoiced, false],
            'cancelled' => [SalesOrderStatus::Cancelled, false],
        ];
    }
}
