<?php

namespace Tests\Feature;

use App\Services\ReferenceGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private ReferenceGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = $this->app->make(ReferenceGenerator::class);
    }

    public function test_it_generates_sequential_references(): void
    {
        $year = (int) now()->format('Y');

        $this->assertSame("SO-{$year}-0001", $this->generator->next('sales_order', 'SO'));
        $this->assertSame("SO-{$year}-0002", $this->generator->next('sales_order', 'SO'));
        $this->assertSame("SO-{$year}-0003", $this->generator->next('sales_order', 'SO'));
    }

    public function test_counters_are_scoped_per_document_type(): void
    {
        $date = CarbonImmutable::parse('2026-03-15');

        $this->assertSame('PO-2026-0001', $this->generator->next('purchase_order', 'PO', $date));
        $this->assertSame('SO-2026-0001', $this->generator->next('sales_order', 'SO', $date));
        $this->assertSame('PO-2026-0002', $this->generator->next('purchase_order', 'PO', $date));
    }

    public function test_counters_are_scoped_per_year(): void
    {
        $this->assertSame('FAC-2025-0001', $this->generator->next('invoice', 'FAC', CarbonImmutable::parse('2025-12-31')));
        $this->assertSame('FAC-2026-0001', $this->generator->next('invoice', 'FAC', CarbonImmutable::parse('2026-01-01')));
        $this->assertSame('FAC-2026-0002', $this->generator->next('invoice', 'FAC', CarbonImmutable::parse('2026-06-01')));
    }

    public function test_the_counter_is_padded_to_four_digits(): void
    {
        $reference = $this->generator->next('invoice', 'FAC', CarbonImmutable::parse('2026-01-01'));

        $this->assertSame('FAC-2026-0001', $reference);
        $this->assertSame(4, strlen(substr($reference, -4)));
    }

    public function test_the_sequence_is_persisted(): void
    {
        $this->generator->next('payment', 'PAY', CarbonImmutable::parse('2026-01-01'));

        $this->assertDatabaseHas('sequences', [
            'key' => 'payment:2026',
            'value' => 1,
        ]);
    }

    public function test_references_never_collide(): void
    {
        $references = [];

        for ($i = 1; $i <= 25; $i++) {
            $references[] = $this->generator->next('invoice', 'FAC', CarbonImmutable::parse('2026-01-01'));
        }

        $this->assertCount(25, array_unique($references));
        $this->assertSame('FAC-2026-0025', end($references));
    }
}
