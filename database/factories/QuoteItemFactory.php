<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteItem>
 */
class QuoteItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quote_id' => Quote::factory(),
            'product_id' => Product::factory(),
            'quantity' => 2.00,
            'unit_price' => 50000.00,
            'tax_rate' => 19.00,
            'subtotal' => 100000.00,
        ];
    }
}
