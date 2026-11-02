<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Client;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InvoiceService;
use App\Services\PurchaseOrderService;
use App\Services\SalesOrderService;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Builds a realistic dataset by driving the domain services, so the seeded
 * ledger and documents are consistent with the application rules.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('Demo data skipped in production.');

            return;
        }

        /** @var User|null $admin */
        $admin = User::query()->where('email', 'admin@erp.test')->first();

        if ($admin === null) {
            return;
        }

        $warehouses = $this->seedWarehouses();
        $categories = Category::factory()->count(6)->create();

        /** @var Collection<int, Product> $products */
        $products = Product::factory()->count(24)->create()->each(
            fn (Product $product) => $product->update(['category_id' => $categories->random()->id])
        );

        $this->seedOpeningStock($products, $warehouses, $admin);

        /** @var Collection<int, Client> $clients */
        $clients = Client::factory()->count(14)->create();

        /** @var Collection<int, Supplier> $suppliers */
        $suppliers = Supplier::factory()->count(8)->create();

        $this->seedPurchases($suppliers, $warehouses, $products, $admin);
        $this->seedSales($clients, $warehouses, $products, $admin);
    }

    /**
     * @return Collection<int, Warehouse>
     */
    private function seedWarehouses(): Collection
    {
        $defaults = [
            ['code' => 'WH-001', 'name' => 'Entrepôt Principal', 'city' => 'Casablanca', 'address' => 'Zone industrielle Sidi Bernoussi', 'is_default' => true],
            ['code' => 'WH-002', 'name' => 'Entrepôt Anfa', 'city' => 'Casablanca', 'address' => 'Boulevard d\'Anfa', 'is_default' => false],
            ['code' => 'WH-003', 'name' => 'Dépôt Marrakech', 'city' => 'Marrakech', 'address' => 'Route de l\'Ourika', 'is_default' => false],
        ];

        return collect($defaults)->map(
            fn (array $warehouse) => Warehouse::query()->firstOrCreate(
                ['code' => $warehouse['code']],
                $warehouse + ['is_active' => true],
            )
        );
    }

    /**
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, Warehouse>  $warehouses
     */
    private function seedOpeningStock(Collection $products, Collection $warehouses, User $admin): void
    {
        $stock = app(StockService::class);

        foreach ($products as $product) {
            foreach ($warehouses as $warehouse) {
                $stock->increase($product, $warehouse, fake()->numberBetween(80, 400), [
                    'reason' => 'Stock initial',
                    'unit_cost' => (float) $product->purchase_price,
                    'user_id' => $admin->id,
                ]);
            }
        }
    }

    /**
     * @param  Collection<int, Supplier>  $suppliers
     * @param  Collection<int, Warehouse>  $warehouses
     * @param  Collection<int, Product>  $products
     */
    private function seedPurchases(Collection $suppliers, Collection $warehouses, Collection $products, User $admin): void
    {
        $service = app(PurchaseOrderService::class);

        for ($i = 0; $i < 6; $i++) {
            $lines = $products->random(rand(2, 4))->map(fn (Product $product) => [
                'product_id' => $product->id,
                'quantity' => rand(10, 40),
                'unit_price' => (float) $product->purchase_price,
            ])->all();

            $order = $service->create([
                'supplier_id' => $suppliers->random()->id,
                'warehouse_id' => $warehouses->random()->id,
                'order_date' => now()->subDays(rand(10, 120))->toDateString(),
                'expected_date' => now()->subDays(rand(0, 9))->toDateString(),
                'lines' => $lines,
            ], $admin);

            $service->confirm($order);

            // Leave the last two orders open so the dashboard shows pending receipts.
            if ($i < 4) {
                $service->receive($order);
            }
        }
    }

    /**
     * @param  Collection<int, Client>  $clients
     * @param  Collection<int, Warehouse>  $warehouses
     * @param  Collection<int, Product>  $products
     */
    private function seedSales(Collection $clients, Collection $warehouses, Collection $products, User $admin): void
    {
        $salesService = app(SalesOrderService::class);
        $invoiceService = app(InvoiceService::class);

        for ($i = 0; $i < 10; $i++) {
            $lines = $products->random(rand(1, 4))->map(fn (Product $product) => [
                'product_id' => $product->id,
                'quantity' => rand(1, 8),
                'unit_price' => (float) $product->sale_price,
            ])->all();

            $order = $salesService->create([
                'client_id' => $clients->random()->id,
                'warehouse_id' => $warehouses->random()->id,
                'order_date' => now()->subDays(rand(0, 100))->toDateString(),
                'lines' => $lines,
            ], $admin);

            $salesService->confirm($order);

            // Three orders stay to be delivered.
            if ($i >= 7) {
                continue;
            }

            $delivered = $salesService->deliver($order);

            // Two delivered orders stay to be invoiced.
            if ($i >= 5) {
                continue;
            }

            $invoice = $invoiceService->fromSalesOrder($delivered, $admin);
            $invoiceService->send($invoice);

            if ($i % 5 === 0) {
                $invoice->due_date = now()->subDays(7);
                $invoice->save();
                $invoiceService->refreshStatus($invoice);
            }

            $paidRatio = [1.0, 0.5, 0.0][$i % 3];

            if ($paidRatio > 0) {
                $invoiceService->recordPayment($invoice->fresh(), [
                    'amount' => round((float) $invoice->total * $paidRatio, 2),
                    'method' => fake()->randomElement(['cash', 'bank_transfer', 'check']),
                    'payment_date' => now()->subDays(rand(0, 20))->toDateString(),
                ], $admin);
            }
        }
    }
}
