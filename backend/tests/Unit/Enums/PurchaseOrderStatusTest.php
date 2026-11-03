<?php

namespace Tests\Unit\Enums;

use App\Enums\PurchaseOrderStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PurchaseOrderStatusTest extends TestCase
{
    public function test_every_case_has_a_label(): void
    {
        foreach (PurchaseOrderStatus::cases() as $status) {
            $this->assertNotSame('', $status->label());
        }
    }

    #[DataProvider('editability')]
    public function test_only_drafts_are_editable(PurchaseOrderStatus $status, bool $expected): void
    {
        $this->assertSame($expected, $status->canBeEdited());
    }

    /**
     * @return array<string, array{0: PurchaseOrderStatus, 1: bool}>
     */
    public static function editability(): array
    {
        return [
            'draft' => [PurchaseOrderStatus::Draft, true],
            'confirmed' => [PurchaseOrderStatus::Confirmed, false],
            'partially received' => [PurchaseOrderStatus::PartiallyReceived, false],
            'received' => [PurchaseOrderStatus::Received, false],
            'cancelled' => [PurchaseOrderStatus::Cancelled, false],
        ];
    }

    #[DataProvider('receivability')]
    public function test_only_confirmed_orders_expect_goods(PurchaseOrderStatus $status, bool $expected): void
    {
        $this->assertSame($expected, $status->isReceivable());
    }

    /**
     * @return array<string, array{0: PurchaseOrderStatus, 1: bool}>
     */
    public static function receivability(): array
    {
        return [
            'draft' => [PurchaseOrderStatus::Draft, false],
            'confirmed' => [PurchaseOrderStatus::Confirmed, true],
            'partially received' => [PurchaseOrderStatus::PartiallyReceived, true],
            'received' => [PurchaseOrderStatus::Received, false],
            'cancelled' => [PurchaseOrderStatus::Cancelled, false],
        ];
    }

    #[DataProvider('cancellability')]
    public function test_only_unreceived_orders_can_be_cancelled(PurchaseOrderStatus $status, bool $expected): void
    {
        $this->assertSame($expected, $status->isCancellable());
    }

    /**
     * @return array<string, array{0: PurchaseOrderStatus, 1: bool}>
     */
    public static function cancellability(): array
    {
        return [
            'draft' => [PurchaseOrderStatus::Draft, true],
            'confirmed' => [PurchaseOrderStatus::Confirmed, true],
            'partially received' => [PurchaseOrderStatus::PartiallyReceived, false],
            'received' => [PurchaseOrderStatus::Received, false],
            'cancelled' => [PurchaseOrderStatus::Cancelled, false],
        ];
    }
}
