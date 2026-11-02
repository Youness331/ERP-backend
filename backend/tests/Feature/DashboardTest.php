<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\DashboardService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithErp;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use InteractsWithErp, RefreshDatabase;

    public function test_dashboard_returns_the_kpi_payload(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'company' => ['name', 'city', 'ice'],
                    'currency' => ['code', 'symbol'],
                    'period' => ['from', 'to'],
                    'revenue' => ['current_month', 'previous_month', 'growth_percent', 'collected_this_month'],
                    'receivables' => ['outstanding', 'overdue_amount', 'overdue_count'],
                    'sales' => ['open_orders', 'to_deliver', 'orders_this_month'],
                    'purchasing' => ['pending_orders', 'orders_this_month'],
                    'inventory' => ['low_stock_count', 'stock_value', 'product_count'],
                    'partners' => ['clients', 'suppliers'],
                    'charts' => ['sales_last_12_months'],
                    'top_products',
                    'low_stock_products',
                    'recent_invoices',
                ],
            ])
            ->assertJsonCount(12, 'data.charts.sales_last_12_months');
    }

    public function test_overdue_command_flags_late_invoices(): void
    {
        $invoice = Invoice::query()->create([
            'reference' => 'FAC-2026-0001',
            'client_id' => Client::factory()->create()->id,
            'type' => 'invoice',
            'status' => 'sent',
            'issue_date' => now()->subDays(40)->toDateString(),
            'due_date' => now()->subDays(10)->toDateString(),
            'subtotal' => 100,
            'discount_total' => 0,
            'tax_total' => 20,
            'total' => 120,
            'amount_paid' => 0,
            'balance_due' => 120,
        ]);

        $this->artisan('erp:refresh-overdue-invoices')->assertSuccessful();

        $this->assertSame('overdue', $invoice->fresh()->status->value);
    }

    public function test_dashboard_service_reports_stock_value(): void
    {
        $product = Product::factory()->create(['cost_price' => 10]);
        $warehouse = Warehouse::factory()->create();

        app(StockService::class)->increase($product, $warehouse, 5);

        $summary = app(DashboardService::class)->summary();

        $this->assertSame(50.0, $summary['inventory']['stock_value']);
    }
}
