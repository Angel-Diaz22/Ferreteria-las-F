<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * ============================================================================
 * FACTORY: SaleFactory (Generador de Ventas de Prueba para Testing)
 * ============================================================================
 * ¿CÓMO FUNCIONA ESTE FACTORY?
 * Permite a las pruebas automatizadas (Pest/PHPUnit) instanciar comprobantes de
 * venta consistentes sin tener que escribir manualmente 15 campos en cada test.
 *
 * Utiliza números de remisión correlativos simulados (REM-XXXXXX), genera
 * valores decimales acordes a la moneda colombiana (COP) y enlaza automáticamente
 * llaves foráneas a usuarios (cajero), bodegas y clientes.
 *
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    /**
     * Define el estado por defecto del modelo Sale.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(2, 50000, 500000);
        $tax = round($subtotal * 0.19, 2);
        $total = $subtotal + $tax;

        return [
            'customer_id' => Customer::factory(),
            'user_id' => User::factory(),
            'warehouse_id' => Warehouse::factory(),
            'cash_shift_id' => null,
            'invoice_number' => 'REM-'.str_pad((string) $this->faker->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'payment_method' => 'cash',
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'discount_amount' => 0.00,
            'total' => $total,
            'paid_amount' => $total,
            'change_amount' => 0.00,
            'status' => 'completed',
            'notes' => 'Venta de mostrador generada por Factory',
        ];
    }

    /**
     * Estado de venta a crédito
     */
    public function credit(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'credit',
            'paid_amount' => 0.00,
            'change_amount' => 0.00,
        ]);
    }

    /**
     * Estado de venta anulada
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'notes' => 'Venta anulada por solicitud administrativa',
        ]);
    }
}
