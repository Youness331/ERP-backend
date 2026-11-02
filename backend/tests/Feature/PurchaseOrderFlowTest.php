<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithErp;
use Tests\TestCase;

class PurchaseOrderFlowTest extends TestCase
{
    use InteractsWithErp, RefreshDatabase;

    private const PERMISSIONS = [
        'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.update',
        'purchase_orders.confirm', 'purchase_orders.receive', 'purchase_orders.delete',
        'suppliers.view', 'products.view', 'stock.view',
    ];

    private Supplier $supplier;

    private Warehouse $warehouse;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supplier = Supplier::factory()->create();
        $this->warehouse = Warehouse::factory()->create();
        $this->product = Product::factory()->create([
            'purchase_price' => 50,
            'cost_price' => 0,
            'tax_rate' => 20,
        ]);

        $this->actingWithPermissions(self::PERMISSIONS);
    }

    private function createOrder(float $quantity = 10): PurchaseOrder
    {
        $response = $this->postJson('/api/v1/purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'lines' => [
                ['product_id' => $this->product->id, 'quantity' => $quantity, 'unit_price' => 50],
            ],
        ])->assertCreated();

        return PurchaseOrder::query()->findOrFail($response->json('data.id'));
    }

    public function test_creating_an_order_computes_totals(): void
    {
        $response = $this->postJson('/api/v1/purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'lines' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit_price' => 50],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.subtotal', 500)
            ->assertJsonPath('data.tax_total', 100)
            ->assertJsonPath('data.total', 600);

        $this->assertStringStartsWith('PO-', $response->json('data.reference'));
    }

    public function test_receiving_the_whole_order_feeds_the_warehouse(): void
    {
        $order = $this->createOrder(10);
        $this->postJson("/api/v1/purchase-orders/{$order->id}/confirm")->assertOk();

        $this->postJson("/api/v1/purchase-orders/{$order->id}/receive")
            ->assertOk()
            ->assertJsonPath('data.status', 'received');

        $stock = Stock::query()->where('product_id', $this->product->id)->firstOrFail();
        $this->assertSame(10.0, (float) $stock->quantity);

        $this->assertSame(50.0, (float) $this->product->fresh()->cost_price);
    }

    public function test_partial_receipt_keeps_the_order_open(): void
    {
        $order = $this->createOrder(10);
        $this->postJson("/api/v1/purchase-orders/{$order->id}/confirm")->assertOk();

        $lineId = $order->lines()->firstOrFail()->id;

        $this->postJson("/api/v1/purchase-orders/{$order->id}/receive", [
            'lines' => [$lineId => ['quantity' => 4]],
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'partially_received')
            ->assertJsonPath('data.lines.0.received_quantity', 4);

        $stock = Stock::query()->where('product_id', $this->product->id)->firstOrFail();
        $this->assertSame(4.0, (float) $stock->quantity);
    }

    public function test_a_confirmed_order_cannot_be_modified(): void
    {
        $order = $this->createOrder(10);
        $this->postJson("/api/v1/purchase-orders/{$order->id}/confirm")->assertOk();

        $this->patchJson("/api/v1/purchase-orders/{$order->id}", ['notes' => 'test'])
            ->assertStatus(422);
    }

    public function test_receiving_more_than_ordered_is_capped_at_the_ordered_quantity(): void
    {
        $order = $this->createOrder(5);
        $this->postJson("/api/v1/purchase-orders/{$order->id}/confirm")->assertOk();

        $lineId = $order->lines()->firstOrFail()->id;

        $this->postJson("/api/v1/purchase-orders/{$order->id}/receive", [
            'lines' => [$lineId => ['quantity' => 99]],
        ])->assertOk();

        $stock = Stock::query()->where('product_id', $this->product->id)->firstOrFail();
        $this->assertSame(5.0, (float) $stock->quantity);
    }

    public function test_a_draft_order_can_be_deleted(): void
    {
        $order = $this->createOrder(3);

        $this->deleteJson("/api/v1/purchase-orders/{$order->id}")->assertNoContent();

        $this->assertSoftDeleted('purchase_orders', ['id' => $order->id]);
    }
}
