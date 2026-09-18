<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Roles y Permisos del Sistema
        $this->call(PermissionSeeder::class);

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $cashierRole = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
        $auditorRole = Role::firstOrCreate(['name' => 'auditor', 'guard_name' => 'web']);

        // 2. Initial Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@ferreteria.com'],
            [
                'name' => 'Administrador Principal',
                'password' => Hash::make('password123'),
            ]
        );
        $admin->assignRole($adminRole);

        // 3. Initial Cashier User
        $cashier = User::firstOrCreate(
            ['email' => 'cajero@ferreteria.com'],
            [
                'name' => 'Cajero de Turno',
                'password' => Hash::make('password123'),
            ]
        );
        $cashier->assignRole($cashierRole);

        // 4. Default Warehouse
        $warehouse = Warehouse::firstOrCreate(
            ['name' => 'Bodega Principal'],
            [
                'code' => 'BOD-01',
                'address' => 'Sede Central - Calle Principal',
                'phone' => '3001234567',
                'is_active' => true,
            ]
        );

        // 5. Default Price Lists
        $detalList = PriceList::firstOrCreate(
            ['name' => 'Detal'],
            ['description' => 'Precio al público general', 'is_default' => true, 'is_active' => true]
        );

        PriceList::firstOrCreate(
            ['name' => 'Mayorista'],
            ['description' => 'Descuento para compras al por mayor', 'is_default' => false, 'is_active' => true]
        );

        PriceList::firstOrCreate(
            ['name' => 'Constructor'],
            ['description' => 'Tarifa preferencial para profesionales de construcción', 'is_default' => false, 'is_active' => true]
        );

        // 6. Cash Registers (Cajas de Atención)
        $register1 = CashRegister::firstOrCreate(
            ['name' => 'Caja 1 - Mostrador Principal'],
            [
                'warehouse_id' => $warehouse->id,
                'type' => CashRegister::TYPE_COUNTER,
                'is_main' => false,
                'display_order' => 1,
                'is_active' => true,
            ]
        );

        $register2 = CashRegister::firstOrCreate(
            ['name' => 'Caja 2 - Mostrador Auxiliar'],
            [
                'warehouse_id' => $warehouse->id,
                'type' => CashRegister::TYPE_COUNTER,
                'is_main' => false,
                'display_order' => 2,
                'is_active' => true,
            ]
        );

        $register3 = CashRegister::firstOrCreate(
            ['name' => 'Caja 3 - Patio y Despacho'],
            [
                'warehouse_id' => $warehouse->id,
                'type' => CashRegister::TYPE_CASHIER,
                'is_main' => true,
                'display_order' => 3,
                'is_active' => true,
            ]
        );

        // 7. Initial Category, Brand & Sample Product
        $brand = Brand::firstOrCreate(
            ['name' => 'Stanley'],
            ['slug' => 'stanley', 'is_active' => true]
        );

        $category = Category::firstOrCreate(
            ['name' => 'Herramientas Manuales'],
            ['slug' => 'herramientas-manuales', 'description' => 'Martillos, destornilladores, llaves']
        );

        $product = Product::firstOrCreate(
            ['sku' => 'MART-001'],
            [
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'barcode' => '7701234567890',
                'name' => 'Martillo de Uña 16oz Mango Fibra de Vidrio',
                'description' => 'Martillo forjado en acero con empuñadura ergonómica',
                'unit' => 'UND',
                'cost_price' => 15000.00,
                'sale_price' => 25000.00,
                'tax_rate' => 19.00,
                'is_active' => true,
            ]
        );

        ProductStock::firstOrCreate(
            ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
            ['current_stock' => 50.00, 'min_stock' => 10.00, 'max_stock' => 200.00]
        );

        // Registro en Kardex del inventario inicial del seeder
        InventoryMovement::firstOrCreate(
            [
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'type' => 'adjustment_in',
            ],
            [
                'user_id' => $admin->id,
                'quantity' => 50.00,
                'unit_cost' => $product->cost_price,
                'previous_stock' => 0.00,
                'resulting_stock' => 50.00,
                'reference_type' => ProductStock::class,
                'notes' => 'Carga de inventario inicial del sistema (Seeder)',
            ]
        );

        // 8. Sample Customer with Credit and Habeas Data
        $constructorList = PriceList::where('name', 'Constructor')->first();
        $sampleCustomer = Customer::firstOrCreate(
            ['document' => '900123987-5'],
            [
                'price_list_id' => $constructorList?->id,
                'name' => 'Constructora Bolívar S.A.S.',
                'document_type' => 'NIT',
                'phone' => '3151234567',
                'email' => 'compras@constructorabolivar.com',
                'address' => 'Av. El Dorado # 68-45',
                'city' => 'Bogotá',
                'credit_limit' => 15000000.00,
                'current_debt' => 3200000.00,
                'is_active' => true,
            ]
        );

        if (! $sampleCustomer->has_consented) {
            $sampleCustomer->consentLogs()->create([
                'subject_type' => 'customer',
                'policy_version' => 'v1.0',
                'consented_at' => now(),
                'ip_address' => '127.0.0.1',
                'user_agent' => 'System Seeder',
                'channel' => 'pos_terminal',
                'notes' => 'Autorización inicial en registro comercial.',
            ]);
        }

        // 9. Sample Quote
        $sampleQuote = Quote::firstOrCreate(
            ['quote_number' => 'COT-00001'],
            [
                'customer_id' => $sampleCustomer->id,
                'user_id' => $admin->id,
                'valid_until' => now()->addDays(15),
                'subtotal' => 250000.00,
                'tax_amount' => 47500.00,
                'total' => 297500.00,
                'status' => 'pending',
                'notes' => "Cotización inicial de herramientas para obra en curso.\nVálida por 15 días calendario.",
            ]
        );

        QuoteItem::firstOrCreate(
            ['quote_id' => $sampleQuote->id, 'product_id' => $product->id],
            [
                'quantity' => 10.00,
                'unit_price' => 25000.00,
                'tax_rate' => 19.00,
                'subtotal' => 250000.00,
            ]
        );

        // 10. Catálogo Completo de Productos, Kardex y Precios para Pruebas
        $this->call(ProductCatalogSeeder::class);
        $this->call(SalesReportDataSeeder::class);
    }
}
