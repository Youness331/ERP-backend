<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Stock;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithErp;
use Tests\TestCase;

class StockTest extends TestCase
{
    use InteractsWithErp, RefreshDatabase;

    private const PERMISSIONS = ['stock.view', 'stock.manage'];

    public function test_manual_receipt_updates_stock_and_writes_a_movement(): void
    {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $this->actingWithPermissions(self::PERMISSIONS);

        $this->postJson('/api/v1/stock/movements', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'in',
            'quantity' => 25,
            'reason' => 'Réception manuelle',
        ])
            ->assertCreated()
            ->assertJsonPath('data.quantity_after', 25)
            ->assertJsonPath('data.type', 'in');

        $stock = Stock::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame(25.0, (float) $stock->quantity);

        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_issue_cannot_exceed_available_quantity(): void
    {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        app(StockService::class)->increase($product, $warehouse, 5);

        $this->actingWithPermissions(self::PERMISSIONS);

        $this->postJson('/api/v1/stock/movements', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'out',
            'quantity' => 10,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'Stock insuffisant'));

        $stock = Stock::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame(5.0, (float) $stock->quantity);
    }

    public function test_adjustment_sets_an_absolute_quantity(): void
    {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        app(StockService::class)->increase($product, $warehouse, 10);

        $this->actingWithPermissions(self::PERMISSIONS);

        $this->postJson('/api/v1/stock/movements', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'adjustment',
            'quantity' => 7,
            'reason' => 'Inventaire',
        ])->assertCreated();

        $stock = Stock::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame(7.0, (float) $stock->quantity);
    }

    public function test_creating_a_product_with_opening_stock(): void
    {
        $warehouse = Warehouse::factory()->create();

        $this->actingWithPermissions(['products.view', 'products.create', 'stock.view']);

        $this->postJson('/api/v1/products', [
            'sku' => 'REF-0001',
            'name' => 'Produit avec stock',
            'sale_price' => 120,
            'opening_stock' => [
                ['warehouse_id' => $warehouse->id, 'quantity' => 40],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('stocks', [
            'warehouse_id' => $warehouse->id,
            'quantity' => 40,
        ]);
    }

    public function test_reserved_quantity_reduces_available_stock(): void
    {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $service = app(StockService::class);

        $service->increase($product, $warehouse, 10);
        $service->reserve($product, $warehouse, 4);

        $this->assertSame(6.0, $service->available($product, $warehouse));

        $stock = Stock::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame(4.0, (float) $stock->reserved_quantity);
    }
}
