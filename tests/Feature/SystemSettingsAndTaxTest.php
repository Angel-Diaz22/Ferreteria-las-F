<?php

use App\Filament\Pages\PosTerminal;
use App\Filament\Pages\SettingsPage;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PosService;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
});

test('default system setting has iva_enabled set to false for non-responsible tax regime', function () {
    SystemSetting::set('iva_enabled', false, 'boolean');

    expect(SystemSetting::isIvaEnabled())->toBeFalse();
    expect(SystemSetting::getIvaRate())->toEqual(19.00);
});

test('when iva_enabled is false, POS sales calculate zero tax and total equals subtotal', function () {
    SystemSetting::set('iva_enabled', false, 'boolean');

    $cashier = User::factory()->create();
    $warehouse = Warehouse::create(['name' => 'Bodega Principal', 'is_active' => true]);
    $category = Category::create(['name' => 'Herramientas', 'slug' => 'herramientas']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'TAL-001',
        'name' => 'Taladro Percutor 650W',
        'cost_price' => 100000.00,
        'sale_price' => 150000.00,
        'unit' => 'UND',
        'is_active' => true,
    ]);

    ProductStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'current_stock' => 10.00,
    ]);

    $sale = PosService::processSale(
        userId: $cashier->id,
        warehouseId: $warehouse->id,
        customerId: null,
        paymentMethod: 'cash',
        items: [
            [
                'product_id' => $product->id,
                'quantity' => 2.0,
                'unit_price' => 150000.00,
                'subtotal' => 300000.00,
            ],
        ],
        paidAmount: 300000.00
    );

    expect((float) $sale->subtotal)->toEqual(300000.00);
    expect((float) $sale->tax_amount)->toEqual(0.00);
    expect((float) $sale->total)->toEqual(300000.00);

    $item = $sale->items()->first();
    expect((float) $item->tax_rate)->toEqual(0.00);
});

test('when iva_enabled is false, pending orders calculate zero tax and total equals subtotal', function () {
    SystemSetting::set('iva_enabled', false, 'boolean');

    $cashier = User::factory()->create();
    $warehouse = Warehouse::create(['name' => 'Bodega Principal', 'is_active' => true]);
    $category = Category::create(['name' => 'Herramientas', 'slug' => 'herramientas-2']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'PUL-001',
        'name' => 'Pulidora Angular 4-1/2',
        'cost_price' => 80000.00,
        'sale_price' => 120000.00,
        'unit' => 'UND',
        'is_active' => true,
    ]);

    ProductStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'current_stock' => 5.00,
    ]);

    $sale = PosService::createPendingOrder(
        userId: $cashier->id,
        warehouseId: $warehouse->id,
        customerId: null,
        items: [
            [
                'product_id' => $product->id,
                'quantity' => 1.0,
                'unit_price' => 120000.00,
                'subtotal' => 120000.00,
            ],
        ]
    );

    expect((float) $sale->subtotal)->toEqual(120000.00);
    expect((float) $sale->tax_amount)->toEqual(0.00);
    expect((float) $sale->total)->toEqual(120000.00);
    expect((float) $sale->items()->first()->tax_rate)->toEqual(0.00);
});

test('when iva_enabled is true, POS calculates 19% tax by default', function () {
    SystemSetting::set('iva_enabled', true, 'boolean');
    SystemSetting::set('iva_percentage', 19.00, 'float');

    $cashier = User::factory()->create();
    $warehouse = Warehouse::create(['name' => 'Bodega Principal', 'is_active' => true]);
    $category = Category::create(['name' => 'Herramientas', 'slug' => 'herramientas-3']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'SIE-001',
        'name' => 'Sierra Circular 7-1/4',
        'cost_price' => 200000.00,
        'sale_price' => 300000.00,
        'unit' => 'UND',
        'is_active' => true,
    ]);

    ProductStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'current_stock' => 5.00,
    ]);

    $sale = PosService::processSale(
        userId: $cashier->id,
        warehouseId: $warehouse->id,
        customerId: null,
        paymentMethod: 'cash',
        items: [
            [
                'product_id' => $product->id,
                'quantity' => 1.0,
                'unit_price' => 300000.00,
                'subtotal' => 300000.00,
            ],
        ],
        paidAmount: 357000.00
    );

    expect((float) $sale->subtotal)->toEqual(300000.00);
    expect((float) $sale->tax_amount)->toEqual(57000.00); // 19% of 300,000
    expect((float) $sale->total)->toEqual(357000.00);
    expect((float) $sale->items()->first()->tax_rate)->toEqual(19.00);
});

test('POS terminal Livewire component reflects iva_enabled toggle correctly', function () {
    SystemSetting::set('iva_enabled', false, 'boolean');

    $cashier = User::factory()->create();
    $cashier->assignRole('cashier');

    $warehouse = Warehouse::create(['name' => 'Bodega Central', 'is_active' => true]);
    $category = Category::create(['name' => 'Tuberías', 'slug' => 'tuberias']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'TUB-001',
        'name' => 'Tubo PVC 1/2 pulgada',
        'cost_price' => 5000.00,
        'sale_price' => 10000.00,
        'unit' => 'UND',
        'is_active' => true,
    ]);

    ProductStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'current_stock' => 20.00,
    ]);

    // Test with IVA disabled
    $component = Livewire::actingAs($cashier)
        ->test(PosTerminal::class)
        ->set('warehouseId', $warehouse->id)
        ->call('addToCart', $product->id);

    expect($component->get('isIvaEnabled'))->toBeFalse();
    expect($component->get('taxAmount'))->toEqual(0.0);
    expect($component->get('subtotal'))->toEqual(10000.0);
    expect($component->get('total'))->toEqual(10000.0);

    // Now enable IVA and verify reactivity
    SystemSetting::set('iva_enabled', true, 'boolean');

    $componentEnabled = Livewire::actingAs($cashier)
        ->test(PosTerminal::class)
        ->set('warehouseId', $warehouse->id)
        ->call('addToCart', $product->id);

    expect($componentEnabled->get('isIvaEnabled'))->toBeTrue();
    expect($componentEnabled->get('taxAmount'))->toEqual(1900.0);
    expect($componentEnabled->get('subtotal'))->toEqual(10000.0);
    expect($componentEnabled->get('total'))->toEqual(11900.0);
});

test('only administrator can access settings page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $cashier = User::factory()->create();
    $cashier->assignRole('cashier');

    expect(SettingsPage::canAccess())->toBeFalse(); // guest

    $this->actingAs($cashier);
    expect(SettingsPage::canAccess())->toBeFalse();

    $this->actingAs($admin);
    expect(SettingsPage::canAccess())->toBeTrue();
});

test('administrator can save settings page form to update IVA and company info', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Livewire::actingAs($admin)
        ->test(SettingsPage::class)
        ->fillForm([
            'iva_enabled' => true,
            'iva_percentage' => 16.00,
            'company_name' => 'FERRETERIA LAS F ACTUALIZADA',
            'company_nit' => '900987654-1',
            'company_regime' => 'RESPONSABLE DE IVA',
            'company_address' => 'Carrera 15 # 45-20',
            'company_phone' => '3200000000',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(SystemSetting::isIvaEnabled())->toBeTrue();
    expect(SystemSetting::getIvaRate())->toEqual(16.00);
    expect(SystemSetting::get('company_name'))->toBe('FERRETERIA LAS F ACTUALIZADA');
    expect(SystemSetting::get('company_nit'))->toBe('900987654-1');
    expect(SystemSetting::get('company_regime'))->toBe('RESPONSABLE DE IVA');

    // Restore to false
    SystemSetting::set('iva_enabled', false, 'boolean');
});
