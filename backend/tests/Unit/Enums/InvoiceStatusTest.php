<?php

namespace Tests\Unit\Enums;

use App\Enums\InvoiceStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InvoiceStatusTest extends TestCase
{
    public function test_every_case_has_a_label(): void
    {
        foreach (InvoiceStatus::cases() as $status) {
            $this->assertNotSame('', $status->label());
        }
    }

    #[DataProvider('outstanding')]
    public function test_outstanding_statuses_still_expect_money(InvoiceStatus $status, bool $expected): void
    {
        $this->assertSame($expected, $status->isOutstanding());
    }

    /**
     * @return array<string, array{0: InvoiceStatus, 1: bool}>
     */
    public static function outstanding(): array
    {
        return [
            'draft' => [InvoiceStatus::Draft, false],
            'sent' => [InvoiceStatus::Sent, true],
            'partially paid' => [InvoiceStatus::PartiallyPaid, true],
            'paid' => [InvoiceStatus::Paid, false],
            'overdue' => [InvoiceStatus::Overdue, true],
            'cancelled' => [InvoiceStatus::Cancelled, false],
        ];
    }

    public function test_only_drafts_are_editable(): void
    {
        foreach (InvoiceStatus::cases() as $status) {
            $this->assertSame($status === InvoiceStatus::Draft, $status->isEditable());
        }
    }

    #[DataProvider('payable')]
    public function test_only_outstanding_invoices_accept_payments(InvoiceStatus $status, bool $expected): void
    {
        $this->assertSame($expected, $status->isPayable());
    }

    /**
     * @return array<string, array{0: InvoiceStatus, 1: bool}>
     */
    public static function payable(): array
    {
        return [
            'draft' => [InvoiceStatus::Draft, false],
            'sent' => [InvoiceStatus::Sent, true],
            'partially paid' => [InvoiceStatus::PartiallyPaid, true],
            'paid' => [InvoiceStatus::Paid, false],
            'overdue' => [InvoiceStatus::Overdue, true],
            'cancelled' => [InvoiceStatus::Cancelled, false],
        ];
    }
}
