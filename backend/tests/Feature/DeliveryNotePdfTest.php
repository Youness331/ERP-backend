<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithErp;
use Tests\TestCase;

class DeliveryNotePdfTest extends TestCase
{
    use InteractsWithErp, RefreshDatabase;

    private function order(): SalesOrder
    {
        $client = Client::factory()->create(['name' => 'Atlas Distribution']);
        $product = Product::factory()->create(['name' => 'Ramette A4', 'sku' => 'REF-0012', 'unit' => 'carton']);

        $order = SalesOrder::query()->create([
            'reference' => 'SO-2026-0007',
            'client_id' => $client->id,
            'warehouse_id' => Warehouse::factory()->create()->id,
            'status' => 'delivered',
            'order_date' => '2026-11-02',
            'delivered_at' => now(),
            'subtotal' => 480,
            'discount_total' => 0,
            'tax_total' => 96,
            'total' => 576,
        ]);

        SalesOrderLine::create([
            'sales_order_id' => $order->id,
            'product_id' => $product->id,
            'description' => 'Ramette A4',
            'quantity' => 4,
            'delivered_quantity' => 4,
            'unit_price' => 120,
            'discount' => 0,
            'tax_rate' => 20,
            'line_total' => 480,
        ]);

        return $order;
    }

    public function test_a_delivery_note_can_be_downloaded_as_pdf(): void
    {
        $order = $this->order();

        $this->actingWithPermissions(['sales_orders.view']);

        $response = $this->get("/api/v1/sales-orders/{$order->id}/delivery-note");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
        $this->assertStringContainsString(
            'SO-2026-0007.pdf',
            (string) $response->headers->get('content-disposition'),
        );
    }

    public function test_the_delivery_note_requires_a_permission(): void
    {
        $order = $this->order();

        $this->actingWithPermissions(['clients.view']);

        $this->get("/api/v1/sales-orders/{$order->id}/delivery-note")->assertForbidden();
    }
}
