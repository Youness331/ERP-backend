<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'code' => 'WH-'.fake()->unique()->numerify('###'),
            'name' => 'Entrepôt '.fake()->city(),
            'address' => fake()->streetAddress(),
            'city' => fake()->randomElement(['Casablanca', 'Rabat', 'Marrakech', 'Tanger']),
            'phone' => '+212 5 '.fake()->numerify('## ## ## ##'),
            'is_default' => false,
            'is_active' => true,
        ];
    }
}
