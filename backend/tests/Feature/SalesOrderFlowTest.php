<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Stock;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithErp;
use Tests\TestCase;

class SalesOrderFlowTest extends TestCase
{
    use InteractsWithErp, RefreshDatabase;

    private const PERMISSIONS = [
        'sales_orders.view', 'sales_orders.create', 'sales_orders.update',
        'sales_orders.confirm', 'sales_orders.deliver', 'sales_orders.delete',
        'invoices.view', 'invoices.create', 'invoices.send',
        'payments.view', 'payments.create',
        'clients.view', 'products.view', 'stock.view',
    ];

    private Client $client;

    private Warehouse $warehouse;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Client::factory()->create();
        $this->warehouse = Warehouse::factory()->create();
        $this->product = Product::factory()->create([
            'sale_price' => 100,
            'purchase_price' => 60,
            'tax_rate' => 20,
        ]);

        app(StockService::class)->increase($this->product, $this->warehouse, 10);

        $this->actingWithPermissions(self::PERMISSIONS);
    }

    private function createOrder(float $quantity = 2): SalesOrder
    {
        $response = $this->postJson('/api/v1/sales-orders', [
            'client_id' => $this->client->id,
            'warehouse_id' => $this->warehouse->id,
            'lines' => [
                ['product_id' => $this->product->id, 'quantity' => $quantity, 'unit_price' => 100],
            ],
        ])->assertCreated();

        return SalesOrder::query()->findOrFail($response->json('data.id'));
    }

    public function test_creating_an_order_computes_totals(): void
    {
        $response = $this->postJson('/api/v1/sales-orders', [
            'client_id' => $this->client->id,
            'warehouse_id' => $this->warehouse->id,
            'lines' => [
                ['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 100, 'discount' => 10],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.subtotal', 200)
            ->assertJsonPath('data.discount_total', 10)
            ->assertJsonPath('data.tax_total', 38)
            ->assertJsonPath('data.total', 228);

        $this->assertStringStartsWith('SO-', $response->json('data.reference'));
    }

    public function test_confirming_reserves_stock(): void
    {
        $order = $this->createOrder(2);

        $this->postJson("/api/v1/sales-orders/{$order->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $stock = Stock::query()->where('product_id', $this->product->id)->firstOrFail();
        $this->assertSame(2.0, (float) $stock->reserved_quantity);
        $this->assertSame(10.0, (float) $stock->quantity);
    }

    public function test_confirming_is_rejected_when_stock_is_insufficient(): void
    {
        $order = $this->createOrder(50);

        $this->postJson("/api/v1/sales-orders/{$order->id}/confirm")
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'Stock insuffisant'));
    }

    public function test_delivering_moves_stock_and_releases_the_reservation(): void
    {
        $order = $this->createOrder(2);
        $this->postJson("/api/v1/sales-orders/{$order->id}/confirm")->assertOk();

        $this->postJson("/api/v1/sales-orders/{$order->id}/deliver")
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered');

        $stock = Stock::query()->where('product_id', $this->product->id)->firstOrFail();
        $this->assertSame(8.0, (float) $stock->quantity);
        $this->assertSame(0.0, (float) $stock->reserved_quantity);
    }

    public function test_invoicing_a_delivered_order_creates_an_invoice(): void
    {
        $order = $this->createOrder(2);
        $this->postJson("/api/v1/sales-orders/{$order->id}/confirm");
        $this->postJson("/api/v1/sales-orders/{$order->id}/deliver");

        $response = $this->postJson("/api/v1/sales-orders/{$order->id}/invoice")
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.total', 240);

        $this->assertStringStartsWith('FAC-', $response->json('data.reference'));
        $this->assertSame('invoiced', $order->fresh()->status->value);

        // A second invoice on the same order is rejected.
        $this->postJson("/api/v1/sales-orders/{$order->id}/invoice")->assertStatus(422);
    }

    public function test_full_payment_marks_the_invoice_as_paid(): void
    {
        $order = $this->createOrder(2);
        $this->postJson("/api/v1/sales-orders/{$order->id}/confirm");
        $this->postJson("/api/v1/sales-orders/{$order->id}/deliver");
        $invoiceId = $this->postJson("/api/v1/sales-orders/{$order->id}/invoice")->json('data.id');

        $this->postJson("/api/v1/invoices/{$invoiceId}/send")->assertOk();

        $this->postJson("/api/v1/invoices/{$invoiceId}/payments", [
            'amount' => 240,
            'method' => 'bank_transfer',
        ])->assertCreated();

        $this->getJson("/api/v1/invoices/{$invoiceId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.balance_due', 0)
            ->assertJsonPath('data.amount_paid', 240);
    }

    public function test_partial_payment_keeps_a_balance(): void
    {
        $order = $this->createOrder(2);
        $this->postJson("/api/v1/sales-orders/{$order->id}/confirm");
        $this->postJson("/api/v1/sales-orders/{$order->id}/deliver");
        $invoiceId = $this->postJson("/api/v1/sales-orders/{$order->id}/invoice")->json('data.id');
        $this->postJson("/api/v1/invoices/{$invoiceId}/send");

        $this->postJson("/api/v1/invoices/{$invoiceId}/payments", ['amount' => 100])->assertCreated();

        $this->getJson("/api/v1/invoices/{$invoiceId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'partially_paid')
            ->assertJsonPath('data.balance_due', 140);
    }

    public function test_overpayment_is_rejected(): void
    {
        $order = $this->createOrder(2);
        $this->postJson("/api/v1/sales-orders/{$order->id}/confirm");
        $this->postJson("/api/v1/sales-orders/{$order->id}/deliver");
        $invoiceId = $this->postJson("/api/v1/sales-orders/{$order->id}/invoice")->json('data.id');
        $this->postJson("/api/v1/invoices/{$invoiceId}/send");

        $this->postJson("/api/v1/invoices/{$invoiceId}/payments", ['amount' => 999])
            ->assertStatus(422);
    }

    public function test_cancelling_a_confirmed_order_releases_reserved_stock(): void
    {
        $order = $this->createOrder(3);
        $this->postJson("/api/v1/sales-orders/{$order->id}/confirm")->assertOk();

        $this->postJson("/api/v1/sales-orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $stock = Stock::query()->where('product_id', $this->product->id)->firstOrFail();
        $this->assertSame(0.0, (float) $stock->reserved_quantity);
    }

    public function test_a_draft_order_can_be_updated(): void
    {
        $order = $this->createOrder(2);

        $this->patchJson("/api/v1/sales-orders/{$order->id}", [
            'lines' => [
                ['product_id' => $this->product->id, 'quantity' => 5, 'unit_price' => 100],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.total', 600)
            ->assertJsonPath('data.lines.0.quantity', 5);
    }
}
