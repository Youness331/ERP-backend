<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithErp;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use InteractsWithErp, RefreshDatabase;

    private function invoice(array $attributes = []): Invoice
    {
        return Invoice::query()->create([
            'reference' => 'FAC-2026-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'client_id' => Client::factory()->create()->id,
            'type' => 'invoice',
            'status' => 'sent',
            'issue_date' => '2026-06-10',
            'due_date' => '2026-07-10',
            'subtotal' => 200,
            'discount_total' => 0,
            'tax_total' => 40,
            'total' => 240,
            'amount_paid' => 0,
            'balance_due' => 240,
            ...$attributes,
        ]);
    }

    public function test_the_sales_report_aggregates_invoices_over_a_period(): void
    {
        $this->invoice();
        $this->invoice(['issue_date' => '2026-07-15', 'total' => 120, 'tax_total' => 20, 'subtotal' => 100, 'balance_due' => 120]);
        $this->invoice(['status' => 'draft', 'issue_date' => '2026-06-12']);

        $this->actingWithPermissions(['reports.view']);

        $this->getJson('/api/v1/reports/sales?from=2026-06-01&to=2026-06-30')
            ->assertOk()
            ->assertJsonPath('data.invoice_count', 1)
            ->assertJsonPath('data.revenue', 240)
            ->assertJsonPath('data.tax_collected', 40)
            ->assertJsonPath('data.average_invoice', 240);
    }

    public function test_the_sales_report_breaks_revenue_down_by_month(): void
    {
        $this->invoice();
        $this->invoice(['issue_date' => '2026-07-15', 'total' => 120]);

        $this->actingWithPermissions(['reports.view']);

        $this->getJson('/api/v1/reports/sales?from=2026-06-01&to=2026-07-31')
            ->assertOk()
            ->assertJsonCount(2, 'data.by_month')
            ->assertJsonPath('data.by_month.0.month', '2026-06')
            ->assertJsonPath('data.by_month.1.month', '2026-07');
    }

    public function test_the_aging_report_buckets_outstanding_invoices(): void
    {
        $this->invoice(['due_date' => today()->addDays(10)->toDateString()]);
        $this->invoice(['due_date' => today()->subDays(5)->toDateString(), 'balance_due' => 100]);
        $this->invoice(['due_date' => today()->subDays(45)->toDateString(), 'balance_due' => 50]);

        $this->actingWithPermissions(['reports.view']);

        $response = $this->getJson('/api/v1/reports/receivables-aging')->assertOk();

        $this->assertSame(390.0, (float) $response->json('data.total'));
        $this->assertSame(3, $response->json('data.invoice_count'));

        $buckets = collect($response->json('data.buckets'))->keyBy('key');
        $this->assertSame(240.0, (float) $buckets['current']['amount']);
        $this->assertSame(100.0, (float) $buckets['days_1_30']['amount']);
        $this->assertSame(50.0, (float) $buckets['days_31_60']['amount']);
    }

    public function test_the_stock_valuation_report_multiplies_quantity_by_cost(): void
    {
        $warehouse = Warehouse::factory()->create(['name' => 'Entrepôt Test']);
        $product = Product::factory()->create(['cost_price' => 10]);
        Stock::query()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 5,
            'reserved_quantity' => 0,
        ]);

        $this->actingWithPermissions(['reports.view']);

        $this->getJson('/api/v1/reports/stock-valuation')
            ->assertOk()
            ->assertJsonPath('data.total_value', 50)
            ->assertJsonPath('data.by_warehouse.0.warehouse', 'Entrepôt Test')
            ->assertJsonPath('data.by_warehouse.0.value', 50);
    }

    public function test_reports_require_a_permission(): void
    {
        $this->actingWithPermissions(['clients.view']);

        $this->getJson('/api/v1/reports/sales')->assertForbidden();
        $this->getJson('/api/v1/reports/stock-valuation')->assertForbidden();
    }

    public function test_the_purchases_report_totals_orders_and_payments(): void
    {
        $this->actingWithPermissions(['reports.view']);

        $this->getJson('/api/v1/reports/purchases?from=2026-01-01&to=2026-12-31')
            ->assertOk()
            ->assertJsonPath('data.order_count', 0)
            ->assertJsonPath('data.spend', 0);
    }
}
