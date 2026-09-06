<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Lista completa de permisos organizados por módulo del negocio
        $permissions = [
            // 1. Ventas y Clientes
            'pos.access',
            'sales.view',
            'sales.cancel',
            'customers.view',
            'quotes.view',

            // 2. Catálogo e Inventario
            'products.view',
            'products.manage',
            'products.view_cost',
            'categories.view',
            'brands.view',
            'price_lists.view',
            'inventory.view',
            'warehouses.view',

            // 3. Compras y Proveedores
            'suppliers.view',
            'purchases.view',

            // 4. Informes y Estadísticas
            'reports.view',

            // 5. Configuración del Sistema
            'users.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Roles del sistema
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $cashierRole = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
        $auditorRole = Role::firstOrCreate(['name' => 'auditor', 'guard_name' => 'web']);

        // El rol Administrador recibe todos los permisos
        $adminRole->syncPermissions(Permission::all());

        // El rol Cajero recibe permisos operativos de venta y consulta básica de catálogo
        $cashierRole->syncPermissions([
            'pos.access',
            'sales.view',
            'customers.view',
            'quotes.view',
            'products.view',
        ]);

        // El rol Auditor recibe permisos de consulta e informes
        $auditorRole->syncPermissions([
            'sales.view',
            'products.view',
            'inventory.view',
            'reports.view',
        ]);
    }
}
