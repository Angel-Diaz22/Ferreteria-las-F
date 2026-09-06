<?php

namespace Database\Seeders;

use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SalesReportDataSeeder extends Seeder
{
    /**
     * Genera datos de ventas y turnos de caja distribuidos en el mes actual
     * para alimentar los gráficos del módulo de Informes y Estadísticas.
     */
    public function run(): void
    {
        $warehouse = Warehouse::where('is_active', true)->first() ?? Warehouse::first();
        $adminUser = User::where('email', 'admin@ferreteria.com')->first() ?? User::first();
        $cashierUser = User::where('email', 'cajero@ferreteria.com')->first() ?? $adminUser;

        $reg1 = CashRegister::firstOrCreate(
            ['name' => 'Caja 1 - Mostrador Principal'],
            ['warehouse_id' => $warehouse->id, 'is_active' => true]
        );

        $reg2 = CashRegister::firstOrCreate(
            ['name' => 'Caja 2 - Mostrador Auxiliar'],
            ['warehouse_id' => $warehouse->id, 'is_active' => true]
        );

        $products = Product::where('is_active', true)->get();
        if ($products->isEmpty()) {
            return;
        }

        $customers = Customer::where('is_active', true)->get();

        // Crear turnos cerrados de días anteriores en Caja 1 y Caja 2
        $shift1 = CashShift::create([
            'cash_register_id' => $reg1->id,
            'user_id' => $cashierUser->id,
            'opening_amount' => 200000.00,
            'closing_amount' => 200000.00,
            'expected_amount' => 200000.00,
            'difference' => 0.00,
            'status' => 'closed',
            'opened_at' => Carbon::now()->startOfMonth()->addHours(8),
            'closed_at' => Carbon::now()->startOfMonth()->addHours(18),
            'notes' => 'Turno inicial de mes Caja 1',
        ]);

        $shift2 = CashShift::create([
            'cash_register_id' => $reg2->id,
            'user_id' => $adminUser->id,
            'opening_amount' => 150000.00,
            'closing_amount' => 150000.00,
            'expected_amount' => 150000.00,
            'difference' => 0.00,
            'status' => 'closed',
            'opened_at' => Carbon::now()->startOfMonth()->addHours(8),
            'closed_at' => Carbon::now()->startOfMonth()->addHours(18),
            'notes' => 'Turno inicial de mes Caja 2',
        ]);

        // Generar ventas a lo largo de los últimos 20 días
        $paymentMethods = ['cash', 'cash', 'card', 'transfer', 'credit'];
        $daysBack = 20;

        for ($d = $daysBack; $d >= 0; $d--) {
            $date = Carbon::now()->subDays($d);
            // 2 a 5 ventas por día
            $salesCount = rand(2, 5);

            for ($s = 0; $s < $salesCount; $s++) {
                $saleDate = $date->clone()->setTime(rand(8, 17), rand(0, 59));
                $useReg1 = rand(1, 10) <= 6; // 60% Caja 1, 40% Caja 2
                $chosenShift = $useReg1 ? $shift1 : $shift2;
                $chosenUser = $useReg1 ? $cashierUser : $adminUser;
                $chosenCustomer = rand(0, 1) && $customers->isNotEmpty() ? $customers->random() : null;
                $chosenMethod = $paymentMethods[array_rand($paymentMethods)];

                // 1 a 3 productos por venta
                $selectedProducts = $products->random(min(rand(1, 3), $products->count()));
                $subtotal = 0.0;
                $taxAmount = 0.0;
                $itemsData = [];

                foreach ($selectedProducts as $prod) {
                    $qty = rand(1, 6);
                    $price = (float) $prod->sale_price;
                    $lineSubtotal = round($qty * $price, 2);
                    $lineTax = round($lineSubtotal * ((float) $prod->tax_rate / 100), 2);

                    $subtotal += $lineSubtotal;
                    $taxAmount += $lineTax;

                    $itemsData[] = [
                        'product_id' => $prod->id,
                        'quantity' => $qty,
                        'unit_cost' => (float) $prod->cost_price,
                        'unit_price' => $price,
                        'tax_rate' => (float) $prod->tax_rate,
                        'discount' => 0.0,
                        'subtotal' => $lineSubtotal,
                    ];
                }

                $total = round($subtotal + $taxAmount, 2);
                $paid = $chosenMethod === 'cash' ? ceil($total / 50000) * 50000 : $total;
                $change = round($paid - $total, 2);

                $lastId = Sale::max('id') ?? 0;
                $invoiceNumber = 'REM-'.str_pad($lastId + 1, 6, '0', STR_PAD_LEFT);

                $sale = Sale::create([
                    'customer_id' => $chosenCustomer?->id,
                    'user_id' => $chosenUser->id,
                    'warehouse_id' => $warehouse->id,
                    'cash_shift_id' => $chosenShift->id,
                    'invoice_number' => $invoiceNumber,
                    'payment_method' => $chosenMethod,
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'discount_amount' => 0.0,
                    'total' => $total,
                    'paid_amount' => $paid,
                    'change_amount' => $change,
                    'status' => 'delivered',
                    'notes' => 'Venta registrada para estadísticas comerciales',
                    'created_at' => $saleDate,
                    'updated_at' => $saleDate,
                ]);

                foreach ($itemsData as $item) {
                    $item['sale_id'] = $sale->id;
                    $item['created_at'] = $saleDate;
                    $item['updated_at'] = $saleDate;
                    SaleItem::create($item);
                }
            }
        }
    }
}
