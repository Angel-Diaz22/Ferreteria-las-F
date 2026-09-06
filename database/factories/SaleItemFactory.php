<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * ============================================================================
 * FACTORY: SaleItemFactory (Líneas de Detalle de Venta)
 * ============================================================================
 * Genera renglones de venta vinculados a un producto, con cantidad, precio unitario,
 * costo de adquisición al momento de la venta y subtotal calculado.
 *
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
{
    protected $model = SaleItem::class;

    /**
     * Define el estado por defecto del modelo SaleItem.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 10);
        $unitPrice = $this->faker->randomFloat(2, 5000, 80000);
        $subtotal = round($quantity * $unitPrice, 2);

        return [
            'sale_id' => Sale::factory(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'unit_cost' => round($unitPrice * 0.7, 2),
            'unit_price' => $unitPrice,
            'tax_rate' => 19.00,
            'discount' => 0.00,
            'subtotal' => $subtotal,
        ];
    }
}
