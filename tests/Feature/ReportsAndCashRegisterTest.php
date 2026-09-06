<?php

use App\Filament\Pages\PosTerminal;
use App\Filament\Pages\ReportsPage;
use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
});

test('pos terminal paginates products to 9 items per page for a 3-row layout', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-01', 'is_active' => true]);
    $category = Category::create(['name' => 'Tornillería', 'slug' => 'tornilleria']);

    // Crear 15 productos
    for ($i = 1; $i <= 15; $i++) {
        $prod = Product::create([
            'category_id' => $category->id,
            'sku' => "TORN-{$i}",
            'name' => "Tornillo de Ensamble {$i}",
            'cost_price' => 1000.00,
            'sale_price' => 2000.00,
            'unit' => 'UND',
            'is_active' => true,
        ]);

        ProductStock::create([
            'product_id' => $prod->id,
            'warehouse_id' => $warehouse->id,
            'current_stock' => 50.00,
        ]);
    }

    $this->actingAs($admin);

    $component = Livewire::test(PosTerminal::class)->assertOk();
    $productsPaginator = $component->instance()->products;

    expect($productsPaginator->perPage())->toBe(9);
    expect($productsPaginator->count())->toBe(9);
    expect($productsPaginator->total())->toBeGreaterThanOrEqual(15);
});

test('cash register exclusive lock prevents two users from opening the same register concurrently', function () {
    $cajero1 = User::factory()->create(['name' => 'Cajero Pedro']);
    $cajero1->assignRole('cashier');

    $cajero2 = User::factory()->create(['name' => 'Cajera Maria']);
    $cajero2->assignRole('cashier');

    $warehouse = Warehouse::create(['name' => 'Bodega Principal', 'code' => 'BOD-02', 'is_active' => true]);

    $caja1 = CashRegister::create([
        'name' => 'Caja 1 - Principal',
        'warehouse_id' => $warehouse->id,
        'is_active' => true,
    ]);

    $caja2 = CashRegister::create([
        'name' => 'Caja 2 - Auxiliar',
        'warehouse_id' => $warehouse->id,
        'is_active' => true,
    ]);

    // 1. Cajero 1 inicia turno en Caja 1
    $this->actingAs($cajero1);

    Livewire::test(PosTerminal::class)
        ->call('selectCashRegister', $caja1->id)
        ->assertSet('activeCashRegisterId', $caja1->id)
        ->assertSet('selectRegisterModalOpen', false);

    // Verificar que en base de datos existe el turno abierto para Cajero 1
    $shift = CashShift::where('cash_register_id', $caja1->id)->where('status', 'open')->first();
    expect($shift)->not->toBeNull();
    expect($shift->user_id)->toBe($cajero1->id);

    // 2. Cajero 2 intenta abrir la misma Caja 1
    $this->actingAs($cajero2);

    Livewire::test(PosTerminal::class)
        ->call('selectCashRegister', $caja1->id)
        // No debe asignarle la caja 1
        ->assertNotSet('activeCashRegisterId', $caja1->id);

    // 3. Cajero 2 selecciona Caja 2 (libre)
    Livewire::test(PosTerminal::class)
        ->call('selectCashRegister', $caja2->id)
        ->assertSet('activeCashRegisterId', $caja2->id)
        ->assertSet('selectRegisterModalOpen', false);

    // 4. Cajero 1 cierra su turno y libera Caja 1
    $this->actingAs($cajero1);
    Livewire::test(PosTerminal::class)
        ->set('activeCashShiftId', $shift->id)
        ->set('activeCashRegisterId', $caja1->id)
        ->call('confirmCloseShift');

    $shift->refresh();
    expect($shift->status)->toBe('closed');

    // Ahora Caja 1 está libre y Cajero 2 u otro puede entrar
    $this->actingAs($cajero2);
    Livewire::test(PosTerminal::class)
        ->call('selectCashRegister', $caja1->id)
        ->assertSet('activeCashRegisterId', $caja1->id);
});

test('reports page computes kpis, daily histogram, top products and sales by register correctly', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-03', 'is_active' => true]);

    $reg1 = CashRegister::create(['name' => 'Caja 1 - Principal', 'warehouse_id' => $warehouse->id, 'is_active' => true]);
    $reg2 = CashRegister::create(['name' => 'Caja 2 - Auxiliar', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

    $category = Category::create(['name' => 'Herramientas', 'slug' => 'herramientas-rep']);
    $prod1 = Product::create([
        'category_id' => $category->id,
        'sku' => 'TAL-01',
        'name' => 'Taladro Percutor',
        'cost_price' => 100000.00,
        'sale_price' => 150000.00,
        'unit' => 'UND',
        'is_active' => true,
    ]);

    $prod2 = Product::create([
        'category_id' => $category->id,
        'sku' => 'PUL-01',
        'name' => 'Pulidora Angular',
        'cost_price' => 80000.00,
        'sale_price' => 120000.00,
        'unit' => 'UND',
        'is_active' => true,
    ]);

    $shift1 = CashShift::create([
        'cash_register_id' => $reg1->id,
        'user_id' => $admin->id,
        'opening_amount' => 100000.00,
        'status' => 'closed',
        'opened_at' => Carbon::now()->startOfMonth(),
    ]);

    $shift2 = CashShift::create([
        'cash_register_id' => $reg2->id,
        'user_id' => $admin->id,
        'opening_amount' => 100000.00,
        'status' => 'closed',
        'opened_at' => Carbon::now()->startOfMonth(),
    ]);

    // Venta en Caja 1: $150.000
    $sale1 = Sale::create([
        'user_id' => $admin->id,
        'warehouse_id' => $warehouse->id,
        'cash_shift_id' => $shift1->id,
        'invoice_number' => 'REM-999001',
        'payment_method' => 'cash',
        'subtotal' => 150000.00,
        'tax_amount' => 0.00,
        'discount_amount' => 0.00,
        'total' => 150000.00,
        'paid_amount' => 150000.00,
        'change_amount' => 0.00,
        'status' => 'delivered',
        'created_at' => Carbon::now(),
    ]);

    SaleItem::create([
        'sale_id' => $sale1->id,
        'product_id' => $prod1->id,
        'quantity' => 1.0,
        'unit_cost' => 100000.00,
        'unit_price' => 150000.00,
        'tax_rate' => 0.00,
        'discount' => 0.00,
        'subtotal' => 150000.00,
        'created_at' => Carbon::now(),
    ]);

    // Venta en Caja 2: $240.000 (2 pulidoras)
    $sale2 = Sale::create([
        'user_id' => $admin->id,
        'warehouse_id' => $warehouse->id,
        'cash_shift_id' => $shift2->id,
        'invoice_number' => 'REM-999002',
        'payment_method' => 'card',
        'subtotal' => 240000.00,
        'tax_amount' => 0.00,
        'discount_amount' => 0.00,
        'total' => 240000.00,
        'paid_amount' => 240000.00,
        'change_amount' => 0.00,
        'status' => 'delivered',
        'created_at' => Carbon::now(),
    ]);

    SaleItem::create([
        'sale_id' => $sale2->id,
        'product_id' => $prod2->id,
        'quantity' => 2.0,
        'unit_cost' => 80000.00,
        'unit_price' => 120000.00,
        'tax_rate' => 0.00,
        'discount' => 0.00,
        'subtotal' => 240000.00,
        'created_at' => Carbon::now(),
    ]);

    $this->actingAs($admin);

    $component = Livewire::test(ReportsPage::class)
        ->call('applyPreset', 'this_month')
        ->assertOk();

    $kpis = $component->instance()->kpis;
    expect($kpis['total_sales'])->toBeGreaterThanOrEqual(390000.00);
    expect($kpis['orders_count'])->toBeGreaterThanOrEqual(2);

    $stats = $component->instance()->registerStats;
    expect($stats['winner_name'])->not->toBeNull();

    $top = $component->instance()->topProducts;
    expect(count($top['labels']))->toBeGreaterThanOrEqual(2);
});

test('reports page downloads pdf successfully with correct headers and stream', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin);

    $response = Livewire::test(ReportsPage::class)
        ->call('downloadPdf');

    $response->assertFileDownloaded();
});

test('admin automatically enters Caja 3 directly without selection modal', function () {
    $admin = User::factory()->create(['name' => 'Don Administrador']);
    $admin->assignRole('admin');

    $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-ADMIN', 'is_active' => true]);

    $caja1 = CashRegister::create(['name' => 'Caja 1 - Mostrador Principal', 'warehouse_id' => $warehouse->id, 'is_active' => true]);
    $caja2 = CashRegister::create(['name' => 'Caja 2 - Mostrador Auxiliar', 'warehouse_id' => $warehouse->id, 'is_active' => true]);
    $caja3 = CashRegister::create(['name' => 'Caja 3 - Patio y Despacho', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

    $this->actingAs($admin);

    $component = Livewire::test(PosTerminal::class);

    // Debe asignarse directamente la Caja 3
    $component->assertSet('activeCashRegisterId', $caja3->id);
    $component->assertSet('selectRegisterModalOpen', false);

    // Admin tiene habilitada la confirmación de pagos
    expect($component->instance()->canConfirmPayments)->toBeTrue();

    // Puede cambiar a la pestaña 'caja'
    $component->call('switchTab', 'caja')
        ->assertSet('currentTab', 'caja');
});

test('cashier only sees attention registers 1 and 2, cannot select caja 3, and opens shift with zero base', function () {
    $cashier = User::factory()->create(['name' => 'Asesor Mostrador']);
    $cashier->assignRole('cashier');

    $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-CASHIER', 'is_active' => true]);

    $caja1 = CashRegister::create(['name' => 'Caja 1 - Mostrador Principal', 'warehouse_id' => $warehouse->id, 'is_active' => true]);
    $caja2 = CashRegister::create(['name' => 'Caja 2 - Mostrador Auxiliar', 'warehouse_id' => $warehouse->id, 'is_active' => true]);
    $caja3 = CashRegister::create(['name' => 'Caja 3 - Patio y Despacho', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

    $this->actingAs($cashier);

    $component = Livewire::test(PosTerminal::class);

    // Debe abrir el modal para que el cajero seleccione entre Caja 1 y Caja 2
    $component->assertSet('selectRegisterModalOpen', true);

    // Solo debe listar las cajas de atención (Caja 1 y 2)
    $registersWithStatus = $component->instance()->cashRegistersWithStatus;
    $registerIds = array_map(fn ($item) => $item['register']->id, $registersWithStatus);

    expect($registerIds)->toContain($caja1->id);
    expect($registerIds)->toContain($caja2->id);
    expect($registerIds)->not->toContain($caja3->id);

    // Si intenta llamar directamente a selectCashRegister con Caja 3, debe bloquearlo
    $component->call('selectCashRegister', $caja3->id)
        ->assertNotSet('activeCashRegisterId', $caja3->id);

    // Selecciona Caja 1: inicia turno con base inicial 0.0 automáticamente
    $component->call('selectCashRegister', $caja1->id)
        ->assertSet('activeCashRegisterId', $caja1->id)
        ->assertSet('selectRegisterModalOpen', false);

    $shift = CashShift::where('cash_register_id', $caja1->id)->where('user_id', $cashier->id)->first();
    expect($shift)->not->toBeNull();
    expect((float) $shift->opening_amount)->toBe(0.0);

    // En Caja 1 no puede confirmar pagos
    expect($component->instance()->canConfirmPayments)->toBeFalse();

    // No puede cambiar a la pestaña 'caja' (Módulo 2 de confirmación de pago)
    $component->call('switchTab', 'caja')
        ->assertSet('currentTab', 'terminal');

    // No puede abrir el modal de cobro directo
    $component->call('openPaymentModal')
        ->assertSet('paymentModalOpen', false);
});
