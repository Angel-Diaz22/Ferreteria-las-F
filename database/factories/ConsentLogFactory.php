<?php

namespace Database\Factories;

use App\Models\ConsentLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsentLog>
 */
class ConsentLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_type' => 'customer',
            'subject_id' => 1,
            'policy_version' => 'v1.0',
            'consented_at' => now(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'channel' => 'pos_terminal',
            'notes' => 'Autorización Habeas Data Ley 1581 de 2012',
        ];
    }
}
