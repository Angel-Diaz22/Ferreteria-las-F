<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'document_type' => fake()->randomElement(['CC', 'NIT', 'CE']),
            'document' => (string) fake()->unique()->numerify('##########'),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'credit_limit' => 5000000.00,
            'current_debt' => 0.00,
            'is_active' => true,
        ];
    }
}
