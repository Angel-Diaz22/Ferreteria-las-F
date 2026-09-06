<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'user_id' => User::factory(),
            'quote_number' => 'COT-'.fake()->unique()->numerify('#####'),
            'valid_until' => now()->addDays(15),
            'subtotal' => 100000.00,
            'tax_amount' => 19000.00,
            'total' => 119000.00,
            'status' => 'pending',
            'notes' => fake()->sentence(),
        ];
    }
}
