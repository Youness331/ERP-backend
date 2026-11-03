<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;

    private Product $product;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stock = $this->app->make(StockService::class);
        $this->product = Product::factory()->create();
        $this->warehouse = Warehouse::factory()->create();
    }

    public function test_a_receipt_creates_the_stock_row_and_a_movement(): void
    {
        $movement = $this->stock->increase($this->product, $this->warehouse, 25);

        $this->assertSame(25.0, (float) $movement->quantity);
        $this->assertSame(0.0, (float) $movement->quantity_before);
        $this->assertSame(25.0, (float) $movement->quantity_after);
        $this->assertSame(25.0, $this->onHand());
    }

    public function test_receipts_accumulate(): void
    {
        $this->stock->increase($this->product, $this->warehouse, 10);
        $movement = $this->stock->increase($this->product, $this->warehouse, 5);

        $this->assertSame(15.0, (float) $movement->quantity_after);
        $this->assertSame(15.0, $this->onHand());
    }

    public function test_an_issue_reduces_the_on_hand_quantity(): void
    {
        $this->stock->increase($this->product, $this->warehouse, 10);

        $this->stock->decrease($this->product, $this->warehouse, 4);

        $this->assertSame(6.0, $this->onHand());
        $this->assertSame(2, StockMovement::query()->count());
    }

    public function test_an_issue_above_availability_is_refused_and_leaves_stock_untouched(): void
    {
        $this->stock->increase($this->product, $this->warehouse, 5);

        try {
            $this->stock->decrease($this->product, $this->warehouse, 6);
            $this->fail('An over-issue should have been refused.');
        } catch (InsufficientStockException $exception) {
            $this->assertStringContainsString($this->product->name, $exception->getMessage());
            $this->assertStringContainsString('disponible', $exception->getMessage());
        }

        $this->assertSame(5.0, $this->onHand());
        $this->assertSame(1, StockMovement::query()->count());
    }

    public function test_reserving_holds_the_quantity_without_moving_it(): void
    {
        $this->stock->increase($this->product, $this->warehouse, 10);

        $this->stock->reserve($this->product, $this->warehouse, 4);

        $this->assertSame(10.0, $this->onHand());
        $this->assertSame(4.0, (float) Stock::query()->firstOrFail()->reserved_quantity);
        $this->assertSame(6.0, $this->stock->available($this->product, $this->warehouse));
    }

    public function test_reserving_more_than_available_is_refused(): void
    {
        $this->stock->increase($this->product, $this->warehouse, 3);

        $this->expectException(InsufficientStockException::class);

        $this->stock->reserve($this->product, $this->warehouse, 4);
    }

    public function test_releasing_a_reservation_frees_the_quantity(): void
    {
        $this->stock->increase($this->product, $this->warehouse, 10);
        $this->stock->reserve($this->product, $this->warehouse, 4);

        $this->stock->release($this->product, $this->warehouse, 4);

        $this->assertSame(0.0, (float) Stock::query()->firstOrFail()->reserved_quantity);
        $this->assertSame(10.0, $this->stock->available($this->product, $this->warehouse));
    }

    public function test_delivering_consumes_the_reservation_and_the_stock(): void
    {
        $this->stock->increase($this->product, $this->warehouse, 10);
        $this->stock->reserve($this->product, $this->warehouse, 4);

        $this->stock->decrease($this->product, $this->warehouse, 4, ['consume_reservation' => true]);

        $stock = Stock::query()->firstOrFail();

        $this->assertSame(6.0, (float) $stock->quantity);
        $this->assertSame(0.0, (float) $stock->reserved_quantity);
    }

    public function test_a_physical_count_sets_an_absolute_quantity(): void
    {
        $this->stock->increase($this->product, $this->warehouse, 10);

        $movement = $this->stock->adjust($this->product, $this->warehouse, 7);

        $this->assertNotNull($movement);
        $this->assertSame(7.0, $this->onHand());
        $this->assertSame(3.0, (float) $movement->quantity);
    }

    public function test_counting_the_same_quantity_is_a_no_op(): void
    {
        $this->stock->increase($this->product, $this->warehouse, 10);
        $movements = StockMovement::query()->count();

        $this->assertNull($this->stock->adjust($this->product, $this->warehouse, 10));
        $this->assertSame($movements, StockMovement::query()->count());
    }

    public function test_a_zero_quantity_is_rejected(): void
    {
        $this->expectException(\App\Exceptions\BusinessException::class);

        $this->stock->increase($this->product, $this->warehouse, 0);
    }

    public function test_movements_are_immutable(): void
    {
        $movement = $this->stock->increase($this->product, $this->warehouse, 5);

        $this->assertNull($movement->getUpdatedAtColumn());
    }

    private function onHand(): float
    {
        return (float) Stock::query()->firstOrFail()->quantity;
    }
}
