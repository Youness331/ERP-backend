<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Product;
use App\Models\Quote;
use App\Models\SalesOrder;
use App\Models\Stock;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithErp;
use Tests\TestCase;

class QuoteFlowTest extends TestCase
{
    use InteractsWithErp, RefreshDatabase;

    private const PERMISSIONS = [
        'quotes.view', 'quotes.create', 'quotes.update', 'quotes.delete',
        'quotes.send', 'quotes.convert',
        'clients.view', 'products.view', 'sales_orders.view',
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

        app(StockService::class)->increase($this->product, $this->warehouse, 50);

        $this->actingWithPermissions(self::PERMISSIONS);
    }

    private function createQuote(float $quantity = 2): Quote
    {
        $response = $this->postJson('/api/v1/quotes', [
            'client_id' => $this->client->id,
            'warehouse_id' => $this->warehouse->id,
            'lines' => [
                ['product_id' => $this->product->id, 'quantity' => $quantity, 'unit_price' => 100],
            ],
        ])->assertCreated();

        return Quote::query()->findOrFail($response->json('data.id'));
    }

    public function test_creating_a_quote_computes_totals_and_a_reference(): void
    {
        $response = $this->postJson('/api/v1/quotes', [
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

        $this->assertStringStartsWith('DEV-', $response->json('data.reference'));
    }

    public function test_a_quote_gets_a_default_validity_window(): void
    {
        $quote = $this->createQuote();

        $this->assertSame(
            today()->addDays((int) config('erp.quote.valid_days'))->toDateString(),
            $quote->valid_until->toDateString(),
        );
    }

    public function test_a_quote_can_be_sent_and_accepted(): void
    {
        $quote = $this->createQuote();

        $this->postJson("/api/v1/quotes/{$quote->id}/send")
            ->assertOk()
            ->assertJsonPath('data.status', 'sent');

        $this->postJson("/api/v1/quotes/{$quote->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.can_be_converted', true);
    }

    public function test_a_quote_can_be_rejected(): void
    {
        $quote = $this->createQuote();
        $this->postJson("/api/v1/quotes/{$quote->id}/send");

        $this->postJson("/api/v1/quotes/{$quote->id}/reject")
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');
    }

    public function test_an_accepted_quote_converts_into_a_sales_order(): void
    {
        $quote = $this->createQuote(3);
        $this->postJson("/api/v1/quotes/{$quote->id}/send");
        $this->postJson("/api/v1/quotes/{$quote->id}/accept");

        $response = $this->postJson("/api/v1/quotes/{$quote->id}/convert")->assertCreated();

        $this->assertStringStartsWith('SO-', $response->json('data.reference'));
        $this->assertSame(3.0, (float) $response->json('data.lines.0.quantity'));

        $order = SalesOrder::query()->findOrFail($response->json('data.id'));
        $this->assertSame($this->client->id, $order->client_id);
        $this->assertSame(360.0, (float) $order->total);

        $quote->refresh();
        $this->assertSame('converted', $quote->status->value);
        $this->assertSame($order->id, $quote->sales_order_id);
        $this->assertNotNull($quote->converted_at);

        // Converting twice is refused.
        $this->postJson("/api/v1/quotes/{$quote->id}/convert")->assertStatus(422);
    }

    public function test_a_draft_quote_cannot_be_converted(): void
    {
        $quote = $this->createQuote();

        $this->postJson("/api/v1/quotes/{$quote->id}/convert")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Seul un devis accepté peut être converti en commande.');
    }

    public function test_a_sent_quote_can_no_longer_be_edited(): void
    {
        $quote = $this->createQuote();
        $this->postJson("/api/v1/quotes/{$quote->id}/send");

        $this->patchJson("/api/v1/quotes/{$quote->id}", ['notes' => 'trop tard'])
            ->assertStatus(422);
    }

    public function test_a_draft_quote_can_be_updated_and_deleted(): void
    {
        $quote = $this->createQuote();

        $this->patchJson("/api/v1/quotes/{$quote->id}", [
            'lines' => [
                ['product_id' => $this->product->id, 'quantity' => 5, 'unit_price' => 100],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.total', 600);

        $this->deleteJson("/api/v1/quotes/{$quote->id}")->assertNoContent();
        $this->assertSoftDeleted('quotes', ['id' => $quote->id]);
    }

    public function test_converting_does_not_move_stock(): void
    {
        $quote = $this->createQuote(2);
        $this->postJson("/api/v1/quotes/{$quote->id}/send");
        $this->postJson("/api/v1/quotes/{$quote->id}/accept");
        $this->postJson("/api/v1/quotes/{$quote->id}/convert")->assertCreated();

        $stock = Stock::query()->where('product_id', $this->product->id)->firstOrFail();

        $this->assertSame(50.0, (float) $stock->quantity);
        $this->assertSame(0.0, (float) $stock->reserved_quantity);
    }

    public function test_expired_quotes_are_flagged_by_the_command(): void
    {
        $quote = $this->createQuote();
        $quote->update(['valid_until' => now()->subDays(2)]);
        $this->postJson("/api/v1/quotes/{$quote->id}/send");

        $this->artisan('erp:expire-quotes')->assertSuccessful();

        $this->assertSame('expired', $quote->fresh()->status->value);
    }
}
