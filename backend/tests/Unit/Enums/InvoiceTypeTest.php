<?php

namespace Tests\Unit\Enums;

use App\Enums\InvoiceType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InvoiceTypeTest extends TestCase
{
    public function test_every_case_has_a_label(): void
    {
        foreach (InvoiceType::cases() as $type) {
            $this->assertNotSame('', $type->label());
        }
    }

    #[DataProvider('prefixes')]
    public function test_reference_prefixes(InvoiceType $type, string $expected): void
    {
        $this->assertSame($expected, $type->referencePrefix());
    }

    /**
     * @return array<string, array{0: InvoiceType, 1: string}>
     */
    public static function prefixes(): array
    {
        return [
            'invoice' => [InvoiceType::Invoice, 'FAC'],
            'credit note' => [InvoiceType::CreditNote, 'AVR'],
        ];
    }

    public function test_it_is_backed_by_expected_values(): void
    {
        $this->assertSame(['invoice', 'credit_note'], InvoiceType::values());
    }
}
