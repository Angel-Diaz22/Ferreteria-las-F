<?php

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\PriceListResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\WarehouseResource;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    (new PermissionSeeder)->run();
});

/**
 * ============================================================================
 * EMPIRICAL CHALLENGE 1: WAREHOUSE CODE UNIQUENESS VALIDATION
 * ============================================================================
 */
describe('Empirical Challenge 1: Warehouse Code Uniqueness', function () {
    test('1.1: Attempting to create warehouse with duplicate code is caught by validation and prevents 500 PDOException', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Warehouse::create([
            'name' => 'Bodega Existente',
            'code' => 'BOD-DUP-CHALLENGE',
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(WarehouseResource\Pages\CreateWarehouse::class)
            ->fillForm([
                'name' => 'Segunda Bodega Con Mismo Codigo',
                'code' => 'BOD-DUP-CHALLENGE',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['code' => 'unique']);

        // Verify that only the initial warehouse exists with this code
        expect(Warehouse::where('code', 'BOD-DUP-CHALLENGE')->count())->toBe(1);
    });

    test('1.2: Attempting to update warehouse code to an existing code is caught by validation without 500 PDOException', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $warehouseA = Warehouse::create([
            'name' => 'Bodega A',
            'code' => 'BOD-ALPHA',
            'is_active' => true,
        ]);

        $warehouseB = Warehouse::create([
            'name' => 'Bodega B',
            'code' => 'BOD-BETA',
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(WarehouseResource\Pages\EditWarehouse::class, [
                'record' => $warehouseB->getRouteKey(),
            ])
            ->fillForm([
                'code' => 'BOD-ALPHA', // collision with warehouseA
            ])
            ->call('save')
            ->assertHasFormErrors(['code' => 'unique']);

        expect($warehouseB->fresh()->code)->toBe('BOD-BETA');
    });

    test('1.3: Updating a warehouse while keeping its own code passes validation successfully (ignoreRecord)', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $warehouse = Warehouse::create([
            'name' => 'Bodega Original',
            'code' => 'BOD-KEEP',
            'address' => 'Direccion Original',
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(WarehouseResource\Pages\EditWarehouse::class, [
                'record' => $warehouse->getRouteKey(),
            ])
            ->fillForm([
                'name' => 'Bodega Nombre Actualizado',
                'code' => 'BOD-KEEP',
                'address' => 'Nueva Direccion # 10-20',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($warehouse->fresh()->name)->toBe('Bodega Nombre Actualizado')
            ->and($warehouse->fresh()->code)->toBe('BOD-KEEP')
            ->and($warehouse->fresh()->address)->toBe('Nueva Direccion # 10-20');
    });

    test('1.4: Multiple warehouses with null code can be created without collision or violation', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(WarehouseResource\Pages\CreateWarehouse::class)
            ->fillForm([
                'name' => 'Bodega Sin Codigo 1',
                'code' => null,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::actingAs($admin)
            ->test(WarehouseResource\Pages\CreateWarehouse::class)
            ->fillForm([
                'name' => 'Bodega Sin Codigo 2',
                'code' => null,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Warehouse::whereNull('code')->count())->toBe(2);
    });
});

/**
 * ============================================================================
 * EMPIRICAL CHALLENGE 2: CUSTOMER DEBT GUARD
 * ============================================================================
 */
describe('Empirical Challenge 2: Customer Debt Guard', function () {
    test('2.1: CustomerResource canDelete rejects customers with current_debt > 0', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $customerWithLargeDebt = Customer::create([
            'name' => 'Cliente Deuda Grande',
            'document_type' => 'CC',
            'document' => '1000000001',
            'credit_limit' => 5000000,
            'current_debt' => 2500000,
            'is_active' => true,
        ]);

        $customerWithSmallDebt = Customer::create([
            'name' => 'Cliente Deuda Centavo',
            'document_type' => 'CC',
            'document' => '1000000002',
            'credit_limit' => 5000000,
            'current_debt' => 0.05,
            'is_active' => true,
        ]);

        $customerNoDebt = Customer::create([
            'name' => 'Cliente Cero Deuda',
            'document_type' => 'CC',
            'document' => '1000000003',
            'credit_limit' => 5000000,
            'current_debt' => 0.00,
            'is_active' => true,
        ]);

        $customerCreditBalance = Customer::create([
            'name' => 'Cliente Saldo a Favor',
            'document_type' => 'CC',
            'document' => '1000000004',
            'credit_limit' => 5000000,
            'current_debt' => -50000,
            'is_active' => true,
        ]);

        expect(CustomerResource::canDelete($customerWithLargeDebt))->toBeFalse()
            ->and(CustomerResource::canDelete($customerWithSmallDebt))->toBeFalse()
            ->and(CustomerResource::canDelete($customerNoDebt))->toBeTrue()
            ->and(CustomerResource::canDelete($customerCreditBalance))->toBeTrue();
    });

    test('2.2: Single DeleteAction on ListCustomers halts and notifies if customer has debt', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $customerWithDebt = Customer::create([
            'name' => 'Cliente Indebted',
            'document_type' => 'CC',
            'document' => '1000000005',
            'credit_limit' => 1000000,
            'current_debt' => 450000,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(CustomerResource\Pages\ListCustomers::class)
            ->assertTableActionHidden('delete', $customerWithDebt);

        expect(Customer::find($customerWithDebt->id))->not->toBeNull();
    });

    test('2.3: Bulk DeleteAction deletes debt-free customers but protects customers with current_debt > 0', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $debtor1 = Customer::create([
            'name' => 'Deudor 1',
            'document_type' => 'CC',
            'document' => '1000000006',
            'credit_limit' => 1000000,
            'current_debt' => 120000,
            'is_active' => true,
        ]);

        $cleanCustomer = Customer::create([
            'name' => 'Limpio Sin Deuda',
            'document_type' => 'CC',
            'document' => '1000000007',
            'credit_limit' => 1000000,
            'current_debt' => 0.00,
            'is_active' => true,
        ]);

        $debtor2 = Customer::create([
            'name' => 'Deudor 2',
            'document_type' => 'CC',
            'document' => '1000000008',
            'credit_limit' => 1000000,
            'current_debt' => 500000,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(CustomerResource\Pages\ListCustomers::class)
            ->callTableBulkAction('delete', [$debtor1, $cleanCustomer, $debtor2])
            ->assertNotified('Eliminación parcial de clientes');

        // Debtors must survive, clean customer must be deleted
        expect(Customer::find($debtor1->id))->not->toBeNull()
            ->and(Customer::find($debtor2->id))->not->toBeNull()
            ->and(Customer::find($cleanCustomer->id))->toBeNull();
    });

    test('2.4: Bulk DeleteAction when all selected customers have debt halts without deleting any', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $debtorA = Customer::create([
            'name' => 'Deudor Solo A',
            'document_type' => 'CC',
            'document' => '1000000009',
            'credit_limit' => 500000,
            'current_debt' => 300000,
            'is_active' => true,
        ]);

        $debtorB = Customer::create([
            'name' => 'Deudor Solo B',
            'document_type' => 'CC',
            'document' => '1000000010',
            'credit_limit' => 500000,
            'current_debt' => 150000,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(CustomerResource\Pages\ListCustomers::class)
            ->callTableBulkAction('delete', [$debtorA, $debtorB])
            ->assertNotified('Eliminación parcial de clientes');

        expect(Customer::find($debtorA->id))->not->toBeNull()
            ->and(Customer::find($debtorB->id))->not->toBeNull();
    });
});

/**
 * ============================================================================
 * EMPIRICAL CHALLENGE 3: USER SELF-DELETION GUARD
 * ============================================================================
 */
describe('Empirical Challenge 3: User Self-Deletion Guard', function () {
    test('3.1: Table checkIfRecordIsSelectableUsing rejects selection of authenticated user', function () {
        $admin = User::factory()->create(['name' => 'Admin Autenticado']);
        $admin->assignRole('admin');

        $other = User::factory()->create(['name' => 'Otro Usuario']);
        $other->assignRole('cashier');

        $this->actingAs($admin);

        $livewire = Livewire::test(UserResource\Pages\ListUsers::class);
        $table = $livewire->instance()->getTable();

        expect($table->isRecordSelectable($admin))->toBeFalse()
            ->and($table->isRecordSelectable($other))->toBeTrue();
    });

    test('3.2: Table Bulk Delete excludes authenticated admin even if injected into payload', function () {
        $admin = User::factory()->create(['name' => 'Admin Inmune']);
        $admin->assignRole('admin');

        $victim1 = User::factory()->create(['name' => 'Victima 1']);
        $victim2 = User::factory()->create(['name' => 'Victima 2']);

        Livewire::actingAs($admin)
            ->test(UserResource\Pages\ListUsers::class)
            ->callTableBulkAction('delete', [$admin, $victim1, $victim2])
            ->assertNotified('Aviso de seguridad');

        // Admin must not be deleted!
        expect(User::find($admin->id))->not->toBeNull()
            ->and(User::find($victim1->id))->toBeNull()
            ->and(User::find($victim2->id))->toBeNull();
    });

    test('3.3: Table Single Delete Action on authenticated admin is cancelled and notified', function () {
        $admin = User::factory()->create(['name' => 'Admin Suicida']);
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(UserResource\Pages\ListUsers::class)
            ->callTableAction('delete', $admin)
            ->assertNotified('Operación no permitida');

        expect(User::find($admin->id))->not->toBeNull();
    });

    test('3.4: Cashier cannot delete users via canDelete or page action', function () {
        $cashier = User::factory()->create(['name' => 'Cajero Limitado']);
        $cashier->assignRole('cashier');

        $target = User::factory()->create(['name' => 'Target']);

        $this->actingAs($cashier);
        expect(UserResource::canDelete($target))->toBeFalse();
    });
});

/**
 * ============================================================================
 * EMPIRICAL CHALLENGE 4: PRICELIST SINGLE-DEFAULT INVARIANT
 * ============================================================================
 */
describe('Empirical Challenge 4: PriceList Single-Default Invariant', function () {
    test('4.1: Creating a new default price list unsets existing default lists', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $initialDefault = PriceList::create([
            'name' => 'Lista Default Original',
            'is_default' => true,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(PriceListResource\Pages\CreatePriceList::class)
            ->fillForm([
                'name' => 'Segunda Lista Default',
                'is_default' => true,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect($initialDefault->fresh()->is_default)->toBeFalse()
            ->and(PriceList::where('name', 'Segunda Lista Default')->first()->is_default)->toBeTrue()
            ->and(PriceList::where('is_default', true)->count())->toBe(1);
    });

    test('4.2: Editing a non-default list to default unsets other default lists', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $listA = PriceList::create([
            'name' => 'Lista A Default',
            'is_default' => true,
            'is_active' => true,
        ]);

        $listB = PriceList::create([
            'name' => 'Lista B Secundaria',
            'is_default' => false,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(PriceListResource\Pages\EditPriceList::class, [
                'record' => $listB->getRouteKey(),
            ])
            ->fillForm([
                'is_default' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($listA->fresh()->is_default)->toBeFalse()
            ->and($listB->fresh()->is_default)->toBeTrue()
            ->and(PriceList::where('is_default', true)->count())->toBe(1);
    });

    test('4.3: Editing a default list preserves its default status (does not unset itself)', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $defaultList = PriceList::create([
            'name' => 'Lista Default Inalterada',
            'description' => 'Descripcion vieja',
            'is_default' => true,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(PriceListResource\Pages\EditPriceList::class, [
                'record' => $defaultList->getRouteKey(),
            ])
            ->fillForm([
                'description' => 'Descripcion actualizada',
                'is_default' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($defaultList->fresh()->is_default)->toBeTrue()
            ->and($defaultList->fresh()->description)->toBe('Descripcion actualizada')
            ->and(PriceList::where('is_default', true)->count())->toBe(1);
    });

    test('4.4: Default price list is protected from single and bulk deletion', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $defaultList = PriceList::create([
            'name' => 'Lista Protegida Default',
            'is_default' => true,
            'is_active' => true,
        ]);

        $normalList = PriceList::create([
            'name' => 'Lista Descartable',
            'is_default' => false,
            'is_active' => true,
        ]);

        // canDelete must return false for default list and true for normal list
        expect(PriceListResource::canDelete($defaultList))->toBeFalse()
            ->and(PriceListResource::canDelete($normalList))->toBeTrue();

        // Single delete action on ListPriceLists halts
        Livewire::actingAs($admin)
            ->test(PriceListResource\Pages\ListPriceLists::class)
            ->assertTableActionHidden('delete', $defaultList);

        // Bulk delete deletes normalList but omits defaultList
        Livewire::actingAs($admin)
            ->test(PriceListResource\Pages\ListPriceLists::class)
            ->callTableBulkAction('delete', [$defaultList, $normalList])
            ->assertNotified('Eliminación parcial de listas');

        expect(PriceList::find($defaultList->id))->not->toBeNull()
            ->and(PriceList::find($normalList->id))->toBeNull();
    });

    test('4.5: Default price list delete action is hidden on EditPriceList page', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $defaultList = PriceList::create([
            'name' => 'Default Header Action Test',
            'is_default' => true,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(PriceListResource\Pages\EditPriceList::class, [
                'record' => $defaultList->getRouteKey(),
            ])
            ->assertActionHidden('delete');
    });
});

/**
 * ============================================================================
 * ADDITIONAL ADVERSARIAL STRESS TESTS: EDIT PAGES & HISTORICAL INTEGRITY
 * ============================================================================
 */
describe('Additional Adversarial Stress Tests', function () {
    test('Customer debt guard applies on EditCustomer page header action as well', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $customerWithDebt = Customer::create([
            'name' => 'Cliente Deuda Edit Page',
            'document_type' => 'CC',
            'document' => '2000000001',
            'credit_limit' => 500000,
            'current_debt' => 150000,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(CustomerResource\Pages\EditCustomer::class, [
                'record' => $customerWithDebt->getRouteKey(),
            ])
            ->assertActionHidden('delete');
    });

    test('User self-deletion guard applies on EditUser page header action', function () {
        $admin = User::factory()->create(['name' => 'Admin Self Edit']);
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(UserResource\Pages\EditUser::class, [
                'record' => $admin->getRouteKey(),
            ])
            ->callAction('delete')
            ->assertNotified('Operación no permitida');

        expect(User::find($admin->id))->not->toBeNull();
    });

    test('Warehouse cannot be deleted if it has historical sales even with zero current stock', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $warehouse = Warehouse::create([
            'name' => 'Bodega Con Ventas Historicas',
            'code' => 'BOD-HIST-01',
            'is_active' => true,
        ]);

        Sale::create([
            'user_id' => $admin->id,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => 'FAC-HIST-01',
            'payment_method' => 'cash',
            'subtotal' => 10000,
            'total' => 10000,
            'paid_amount' => 10000,
            'status' => 'completed',
        ]);

        Livewire::actingAs($admin)
            ->test(WarehouseResource\Pages\ListWarehouses::class)
            ->callTableAction('delete', $warehouse)
            ->assertNotified('No se puede eliminar la bodega');

        expect(Warehouse::find($warehouse->id))->not->toBeNull();
    });

    test('Inactive customer with debt is still strictly protected against deletion', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $inactiveDebtor = Customer::create([
            'name' => 'Cliente Inactivo Con Deuda',
            'document_type' => 'CC',
            'document' => '2000000002',
            'credit_limit' => 200000,
            'current_debt' => 180000,
            'is_active' => false,
        ]);

        expect(CustomerResource::canDelete($inactiveDebtor))->toBeFalse();

        Livewire::actingAs($admin)
            ->test(CustomerResource\Pages\ListCustomers::class)
            ->assertTableActionHidden('delete', $inactiveDebtor);

        expect(Customer::find($inactiveDebtor->id))->not->toBeNull();
    });
});
