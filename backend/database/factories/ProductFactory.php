<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $purchasePrice = fake()->randomFloat(2, 15, 800);

        return [
            'sku' => strtoupper(fake()->unique()->bothify('REF-####-??')),
            'barcode' => fake()->unique()->ean13(),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'unit' => fake()->randomElement(['pièce', 'carton', 'kg', 'litre', 'mètre']),
            'purchase_price' => $purchasePrice,
            'sale_price' => round($purchasePrice * 1.35, 2),
            'cost_price' => $purchasePrice,
            'tax_rate' => 20,
            'reorder_level' => fake()->numberBetween(5, 25),
            'track_stock' => true,
            'is_active' => true,
        ];
    }
}
