<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'code' => 'SUP-'.fake()->unique()->numerify('#####'),
            'name' => fake()->company(),
            'company_name' => fake()->company(),
            'contact_name' => fake()->name(),
            'email' => fake()->unique()->companyEmail(),
            'phone' => '+212 5 '.fake()->numerify('## ## ## ##'),
            'address' => fake()->streetAddress(),
            'city' => fake()->randomElement(['Casablanca', 'Rabat', 'Marrakech', 'Tanger', 'Fès']),
            'postal_code' => fake()->numerify('#####'),
            'country' => 'Maroc',
            'ice' => fake()->numerify('#############'),
            'tax_id' => fake()->numerify('########'),
            'is_active' => true,
        ];
    }
}
