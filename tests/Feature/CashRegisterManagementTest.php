<?php

use App\Filament\Pages\PosTerminal;
use App\Filament\Resources\CashRegisterResource;
use App\Filament\Resources\CashRegisterResource\Pages\ListCashRegisters;
use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
});

test('system enforces a strict maximum limit of 10 cash registers', function () {
    // Crear 10 cajas debe ser exitoso
    for ($i = 1; $i <= 10; $i++) {
        CashRegister::create([
            'name' => "Caja Test {$i}",
            'type' => CashRegister::TYPE_COUNTER,
            'is_active' => true,
        ]);
    }

    expect(CashRegister::count())->toBe(10);

    // Intentar crear la caja número 11 debe arrojar DomainException
    expect(fn () => CashRegister::create([
        'name' => 'Caja Test 11',
        'type' => CashRegister::TYPE_COUNTER,
        'is_active' => true,
    ]))->toThrow(DomainException::class, 'Se ha alcanzado el límite máximo de 10 cajas registradoras en el sistema.');
});

test('only one cash register can be marked as is_main at any time', function () {
    $caja1 = CashRegister::create([
        'name' => 'Caja 1',
        'type' => CashRegister::TYPE_COUNTER,
        'is_main' => true,
    ]);

    expect($caja1->fresh()->is_main)->toBeTrue();

    // Crear segunda caja marcada como principal
    $caja2 = CashRegister::create([
        'name' => 'Caja 2',
        'type' => CashRegister::TYPE_CASHIER,
        'is_main' => true,
    ]);

    expect($caja2->fresh()->is_main)->toBeTrue()
        ->and($caja1->fresh()->is_main)->toBeFalse();

    // Actualizar caja 1 para volver a ser principal
    $caja1->refresh()->update(['is_main' => true]);

    expect($caja1->fresh()->is_main)->toBeTrue()
        ->and($caja2->fresh()->is_main)->toBeFalse();
});

test('cash register type methods behave correctly for counter, cashier, and hybrid', function () {
    $counter = CashRegister::create(['name' => 'Mostrador', 'type' => CashRegister::TYPE_COUNTER]);
    $cashier = CashRegister::create(['name' => 'Recaudadora', 'type' => CashRegister::TYPE_CASHIER]);
    $hybrid = CashRegister::create(['name' => 'Híbrida', 'type' => CashRegister::TYPE_HYBRID]);

    // Mostrador: no cobra, no maneja efectivo, sí es de atención
    expect($counter->isCashier())->toBeFalse()
        ->and($counter->handlesCash())->toBeFalse()
        ->and($counter->isAttentionRegister())->toBeTrue()
        ->and($counter->isHybrid())->toBeFalse();

    // Recaudadora: sí cobra, sí maneja efectivo, no es de atención general
    expect($cashier->isCashier())->toBeTrue()
        ->and($cashier->handlesCash())->toBeTrue()
        ->and($cashier->isAttentionRegister())->toBeFalse()
        ->and($cashier->isHybrid())->toBeFalse();

    // Híbrida: cotiza y cobra directo, sí maneja efectivo, sí es de atención
    expect($hybrid->isCashier())->toBeFalse()
        ->and($hybrid->handlesCash())->toBeTrue()
        ->and($hybrid->isAttentionRegister())->toBeTrue()
        ->and($hybrid->isHybrid())->toBeTrue();
});

test('only administrator can access CashRegisterResource', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $cashier = User::factory()->create();
    $cashier->assignRole('cashier');

    // Admin tiene acceso a listar, crear, editar y eliminar
    $this->actingAs($admin);
    expect(CashRegisterResource::canViewAny())->toBeTrue()
        ->and(CashRegisterResource::canCreate())->toBeTrue();

    Livewire::test(ListCashRegisters::class)
        ->assertSuccessful();

    // Cajero no tiene acceso
    $this->actingAs($cashier);
    expect(CashRegisterResource::canViewAny())->toBeFalse()
        ->and(CashRegisterResource::canCreate())->toBeFalse();
});

test('cannot delete a cash register that has shift history', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $caja = CashRegister::create(['name' => 'Caja Con Historial', 'type' => CashRegister::TYPE_COUNTER]);

    CashShift::create([
        'cash_register_id' => $caja->id,
        'user_id' => $admin->id,
        'opening_amount' => 0,
        'status' => 'closed',
        'opened_at' => now()->subHour(),
        'closed_at' => now(),
    ]);

    expect($caja->shifts()->exists())->toBeTrue();
});

test('pos terminal respects dynamic is_main and register types', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $cashier = User::factory()->create();
    $cashier->assignRole('cashier');

    $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-DYN', 'is_active' => true]);

    $cajaMostrador = CashRegister::create([
        'name' => 'Puesto 1 Mostrador',
        'type' => CashRegister::TYPE_COUNTER,
        'display_order' => 1,
        'is_active' => true,
    ]);

    $cajaHibrida = CashRegister::create([
        'name' => 'Puesto 2 Híbrido',
        'type' => CashRegister::TYPE_HYBRID,
        'display_order' => 2,
        'is_active' => true,
    ]);

    $cajaPrincipal = CashRegister::create([
        'name' => 'Caja Central Principal',
        'type' => CashRegister::TYPE_CASHIER,
        'is_main' => true,
        'display_order' => 3,
        'is_active' => true,
    ]);

    $cajaInactiva = CashRegister::create([
        'name' => 'Caja Fuera de Servicio',
        'type' => CashRegister::TYPE_COUNTER,
        'is_active' => false,
        'display_order' => 4,
    ]);

    // 1. Administrador entra automáticamente a la caja marcada con is_main
    $this->actingAs($admin);
    $adminTerminal = Livewire::test(PosTerminal::class);
    $adminTerminal->assertSet('activeCashRegisterId', $cajaPrincipal->id)
        ->assertSet('selectRegisterModalOpen', false);

    // 2. Cajero ve solo cajas activas de atención (Mostrador e Híbrida), NO la recaudadora ni la inactiva
    $this->actingAs($cashier);
    $cashierTerminal = Livewire::test(PosTerminal::class);
    $cashierTerminal->assertSet('selectRegisterModalOpen', true);

    $registersWithStatus = $cashierTerminal->instance()->cashRegistersWithStatus;
    $availableIds = array_map(fn ($item) => $item['register']->id, $registersWithStatus);

    expect($availableIds)->toContain($cajaMostrador->id)
        ->and($availableIds)->toContain($cajaHibrida->id)
        ->and($availableIds)->not->toContain($cajaPrincipal->id)
        ->and($availableIds)->not->toContain($cajaInactiva->id);

    // 3. Si cajero intenta entrar a caja recaudadora, es bloqueado
    $cashierTerminal->call('selectCashRegister', $cajaPrincipal->id)
        ->assertNotSet('activeCashRegisterId', $cajaPrincipal->id);

    // 4. Cajero puede entrar a caja híbrida e inicia turno
    $cashierTerminal->set('shiftOpeningAmount', '50000')
        ->call('selectCashRegister', $cajaHibrida->id)
        ->assertSet('activeCashRegisterId', $cajaHibrida->id)
        ->assertSet('selectRegisterModalOpen', false);

    $shift = CashShift::where('cash_register_id', $cajaHibrida->id)->where('user_id', $cashier->id)->first();
    expect($shift)->not->toBeNull()
        ->and((float) $shift->opening_amount)->toBe(50000.0);
});
