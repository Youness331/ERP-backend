<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'code' => 'CLI-'.fake()->unique()->numerify('#####'),
            'name' => fake()->company(),
            'company_name' => fake()->company(),
            'email' => fake()->unique()->companyEmail(),
            'phone' => '+212 5 '.fake()->numerify('## ## ## ##'),
            'mobile' => '+212 6 '.fake()->numerify('## ## ## ##'),
            'address' => fake()->streetAddress(),
            'city' => fake()->randomElement(['Casablanca', 'Rabat', 'Marrakech', 'Tanger', 'Fès', 'Agadir']),
            'postal_code' => fake()->numerify('#####'),
            'country' => 'Maroc',
            'ice' => fake()->numerify('#############'),
            'tax_id' => fake()->numerify('########'),
            'rc' => 'RC '.fake()->numerify('######'),
            'credit_limit' => fake()->randomElement([0, 5000, 10000, 25000]),
            'is_active' => true,
        ];
    }
}
