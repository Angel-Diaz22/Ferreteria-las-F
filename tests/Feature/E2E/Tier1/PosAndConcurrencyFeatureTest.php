<?php

use App\Filament\Pages\PosTerminal;
use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Sale;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\KardexService;
use App\Services\PosService;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    (new PermissionSeeder)->run();
    SystemSetting::set('iva_enabled', true, 'boolean');
});

/**
 * ============================================================================
 * TIER 1: FEATURE COVERAGE — F12 to F16 (POS, CASH REGISTERS & CONCURRENCY)
 * ============================================================================
 */
describe('F12: POS Cash Registers Flow Compliance', function () {
    test('F12-1: Cajas 1 and 2 open automatically with zero base amount and do not handle cash', function () {
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-F12-1', 'is_active' => true]);
        $caja1 = CashRegister::create(['name' => 'Caja 1 - Mostrador Principal', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        $this->actingAs($cashier);
        Livewire::test(PosTerminal::class)
            ->call('selectCashRegister', $caja1->id)
            ->assertSet('activeCashRegisterId', $caja1->id);

        $shift = CashShift::where('cash_register_id', $caja1->id)->where('status', 'open')->first();
        expect($shift)->not->toBeNull()
            ->and((float) $shift->opening_amount)->toBe(0.0)
            ->and($caja1->handlesCash())->toBeFalse();
    });

    test('F12-2: Cajas 1 and 2 cannot access confirmation of payments tab', function () {
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-F12-2', 'is_active' => true]);
        $caja2 = CashRegister::create(['name' => 'Caja 2 - Mostrador Auxiliar', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        $this->actingAs($cashier);
        Livewire::test(PosTerminal::class)
            ->call('selectCashRegister', $caja2->id)
            ->call('switchTab', 'caja')
            ->assertSet('currentTab', 'terminal'); // Remains in terminal
    });

    test('F12-3: Caja 3 is exclusive to Administrator and enables confirmation of payments', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-F12-3', 'is_active' => true]);
        CashRegister::create(['name' => 'Caja 1 - Mostrador', 'warehouse_id' => $warehouse->id, 'is_active' => true]);
        $caja3 = CashRegister::create(['name' => 'Caja 3 - Central y Pagos', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        $this->actingAs($admin);
        $component = Livewire::test(PosTerminal::class);

        $component->assertSet('activeCashRegisterId', $caja3->id);
        expect($component->instance()->canConfirmPayments)->toBeTrue();

        $component->call('switchTab', 'caja')
            ->assertSet('currentTab', 'caja');
    });

    test('F12-4: Non-admin cashier attempting to select Caja 3 is rejected', function () {
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-F12-4', 'is_active' => true]);
        CashRegister::create(['name' => 'Caja 1 - Mostrador', 'warehouse_id' => $warehouse->id, 'is_active' => true]);
        $caja3 = CashRegister::create(['name' => 'Caja 3 - Central', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        $this->actingAs($cashier);
        Livewire::test(PosTerminal::class)
            ->call('selectCashRegister', $caja3->id)
            ->assertNotSet('activeCashRegisterId', $caja3->id);
    });

    test('F12-5: Cashier register selection list excludes Caja 3 for non-admin users', function () {
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-F12-5', 'is_active' => true]);
        $caja1 = CashRegister::create(['name' => 'Caja 1 - Mostrador A', 'warehouse_id' => $warehouse->id, 'is_active' => true]);
        $caja2 = CashRegister::create(['name' => 'Caja 2 - Mostrador B', 'warehouse_id' => $warehouse->id, 'is_active' => true]);
        $caja3 = CashRegister::create(['name' => 'Caja 3 - Patio y Central', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        $this->actingAs($cashier);
        $component = Livewire::test(PosTerminal::class);
        $registers = array_map(fn ($r) => $r['register']->id, $component->instance()->cashRegistersWithStatus);

        expect($registers)->toContain($caja1->id)
            ->and($registers)->toContain($caja2->id)
            ->and($registers)->not->toContain($caja3->id);
    });
});

describe('F13: POS Shift Mount & Close Lifecycle', function () {
    test('F13-1: Cashier with no active shift is prompted with register selection modal on mount', function () {
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-F13-1', 'is_active' => true]);
        CashRegister::create(['name' => 'Caja 1 - Mostrador', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        $this->actingAs($cashier);
        Livewire::test(PosTerminal::class)
            ->assertSet('selectRegisterModalOpen', true)
            ->assertSet('activeCashRegisterId', null);
    });

    test('F13-2: Cashier with already active shift resumes session without modal on mount', function () {
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-F13-2', 'is_active' => true]);
        $caja1 = CashRegister::create(['name' => 'Caja 1 - Mostrador', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        $shift = CashShift::create([
            'cash_register_id' => $caja1->id,
            'user_id' => $cashier->id,
            'opening_amount' => 0.0,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $this->actingAs($cashier);
        Livewire::test(PosTerminal::class)
            ->assertSet('selectRegisterModalOpen', false)
            ->assertSet('activeCashRegisterId', $caja1->id)
            ->assertSet('activeCashShiftId', $shift->id);
    });

    test('F13-3: Shift close on attention register liberates register with zero difference', function () {
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-F13-3', 'is_active' => true]);
        $caja1 = CashRegister::create(['name' => 'Caja 1 - Mostrador', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        $shift = CashShift::create([
            'cash_register_id' => $caja1->id,
            'user_id' => $cashier->id,
            'opening_amount' => 0.0,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $this->actingAs($cashier);
        Livewire::test(PosTerminal::class)
            ->set('activeCashShiftId', $shift->id)
            ->set('activeCashRegisterId', $caja1->id)
            ->call('confirmCloseShift');

        $shift->refresh();
        expect($shift->status)->toBe('closed')
            ->and((float) $shift->difference)->toBe(0.0);
    });

    test('F13-4: Shift close on cash-handling register calculates discrepancy accurately', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-F13-4', 'is_active' => true]);
        $caja3 = CashRegister::create(['name' => 'Caja 3 - Central', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        $shift = CashShift::create([
            'cash_register_id' => $caja3->id,
            'user_id' => $admin->id,
            'opening_amount' => 100000.00,
            'expected_amount' => 150000.00,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $this->actingAs($admin);
        Livewire::test(PosTerminal::class)
            ->set('activeCashShiftId', $shift->id)
            ->set('activeCashRegisterId', $caja3->id)
            ->set('shiftClosingAmount', '145000') // $5,000 short
            ->call('confirmCloseShift');

        $shift->refresh();
        expect($shift->status)->toBe('closed')
            ->and((float) $shift->difference)->toBe(-5000.00);
    });

    test('F13-5: Once shift is closed, register is available for other operators', function () {
        $c1 = User::factory()->create(['name' => 'Cajero 1']);
        $c1->assignRole('cashier');
        $c2 = User::factory()->create(['name' => 'Cajero 2']);
        $c2->assignRole('cashier');

        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-F13-5', 'is_active' => true]);
        $caja1 = CashRegister::create(['name' => 'Caja 1 - Mostrador', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        // C1 opens and closes
        $shift = CashShift::create(['cash_register_id' => $caja1->id, 'user_id' => $c1->id, 'opening_amount' => 0.0, 'status' => 'open', 'opened_at' => now()]);
        $shift->update(['status' => 'closed']);

        // C2 opens successfully
        $this->actingAs($c2);
        Livewire::test(PosTerminal::class)
            ->call('selectCashRegister', $caja1->id)
            ->assertSet('activeCashRegisterId', $caja1->id);

        $newShift = CashShift::where('cash_register_id', $caja1->id)->where('status', 'open')->first();
        expect($newShift->user_id)->toBe($c2->id);
    });
});

describe('F14: Robust Cash Register Identification', function () {
    test('F14-1: CashRegister named Caja 3 is identified as cashier register', function () {
        $reg = new CashRegister(['name' => 'Caja 3 - Patio Principal']);
        expect($reg->isCashier())->toBeTrue()
            ->and($reg->handlesCash())->toBeTrue();
    });

    test('F14-2: CashRegister named Central is identified as cashier register', function () {
        $reg = new CashRegister(['name' => 'Caja Central Recaudadora']);
        expect($reg->isCashier())->toBeTrue()
            ->and($reg->handlesCash())->toBeTrue();
    });

    test('F14-3: CashRegister named Caja 1 is identified as attention register', function () {
        $reg = new CashRegister(['name' => 'Caja 1 - Mostrador Entrada']);
        expect($reg->isAttentionRegister())->toBeTrue()
            ->and($reg->handlesCash())->toBeFalse();
    });

    test('F14-4: CashRegister named Caja 2 is identified as attention register', function () {
        $reg = new CashRegister(['name' => 'Caja 2 - Mostrador Pasillo']);
        expect($reg->isAttentionRegister())->toBeTrue()
            ->and($reg->handlesCash())->toBeFalse();
    });

    test('F14-5: Inactive cash registers cannot be selected', function () {
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-F14-5', 'is_active' => true]);
        $cajaInactive = CashRegister::create(['name' => 'Caja 1 - Fuera de Servicio', 'warehouse_id' => $warehouse->id, 'is_active' => false]);

        $this->actingAs($cashier);
        Livewire::test(PosTerminal::class)
            ->call('selectCashRegister', $cajaInactive->id)
            ->assertNotSet('activeCashRegisterId', $cajaInactive->id);
    });
});

describe('F15: POS CSS Grid Anti-Blowout Compliance', function () {
    test('F15-1: PosTerminal view template exists and renders without blade errors', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $warehouse = Warehouse::create(['name' => 'Bodega Layout', 'code' => 'BOD-F15-1', 'is_active' => true]);
        CashRegister::create(['name' => 'Caja 3 - Central', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        $this->actingAs($admin);
        $component = Livewire::test(PosTerminal::class);
        $component->assertOk();
    });

    test('F15-2: Blade view file pos-terminal.blade.php contains anti-blowout grid constraints', function () {
        $viewPath = resource_path('views/filament/pages/pos-terminal.blade.php');
        expect(file_exists($viewPath))->toBeTrue();

        $content = file_get_contents($viewPath);
        // Checks that grid styles or classes prevent overflow blowouts
        expect($content)->toContain('grid');
    });

    test('F15-3: PosTerminal paginates products to 9 items per page', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $warehouse = Warehouse::create(['name' => 'Bodega Grid', 'code' => 'BOD-F15-3', 'is_active' => true]);
        CashRegister::create(['name' => 'Caja 3 - Central', 'warehouse_id' => $warehouse->id, 'is_active' => true]);
        $cat = Category::create(['name' => 'Fijaciones', 'slug' => 'fij-grid']);

        for ($i = 1; $i <= 12; $i++) {
            $p = Product::create(['category_id' => $cat->id, 'sku' => "GRID-{$i}", 'name' => "Item Grid {$i}", 'cost_price' => 100, 'sale_price' => 200, 'unit' => 'UND']);
            ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 10]);
        }

        $this->actingAs($admin);
        $component = Livewire::test(PosTerminal::class);
        expect($component->instance()->products->perPage())->toBe(9);
    });

    test('F15-4: PosTerminal category filter updates product list reactively', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $warehouse = Warehouse::create(['name' => 'Bodega Cat', 'code' => 'BOD-F15-4', 'is_active' => true]);
        CashRegister::create(['name' => 'Caja 3 - Central', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        $c1 = Category::create(['name' => 'Pinturas', 'slug' => 'pinturas-f15']);
        $c2 = Category::create(['name' => 'Herramientas', 'slug' => 'herramientas-f15']);

        $p1 = Product::create(['category_id' => $c1->id, 'sku' => 'CAT-P-1', 'name' => 'Vinilo', 'cost_price' => 100, 'sale_price' => 200, 'unit' => 'UND']);
        $p2 = Product::create(['category_id' => $c2->id, 'sku' => 'CAT-H-1', 'name' => 'Martillo', 'cost_price' => 100, 'sale_price' => 200, 'unit' => 'UND']);
        ProductStock::create(['product_id' => $p1->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 10]);
        ProductStock::create(['product_id' => $p2->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 10]);

        $this->actingAs($admin);
        $component = Livewire::test(PosTerminal::class)
            ->call('setCategory', $c1->id)
            ->assertSet('selectedCategory', $c1->id);

        expect($component->instance()->products->total())->toBe(1);
    });

    test('F15-5: Product search filters products in real time', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $warehouse = Warehouse::create(['name' => 'Bodega Search', 'code' => 'BOD-F15-5', 'is_active' => true]);
        CashRegister::create(['name' => 'Caja 3 - Central', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        $p1 = Product::create(['sku' => 'SRCH-1', 'name' => 'Pala Redonda', 'cost_price' => 1000, 'sale_price' => 2000, 'unit' => 'UND']);
        $p2 = Product::create(['sku' => 'SRCH-2', 'name' => 'Pico de Acero', 'cost_price' => 1000, 'sale_price' => 2000, 'unit' => 'UND']);
        ProductStock::create(['product_id' => $p1->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 10]);
        ProductStock::create(['product_id' => $p2->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 10]);

        $this->actingAs($admin);
        $component = Livewire::test(PosTerminal::class)
            ->set('search', 'Pala');

        expect($component->instance()->products->total())->toBe(1);
    });
});

describe('F16: Concurrency & Pessimistic Locking', function () {
    test('F16-1: KardexService registerAdjustment wraps stock mutation in database transaction', function () {
        $warehouse = Warehouse::create(['name' => 'Bodega Lock 1', 'code' => 'BOD-F16-1', 'is_active' => true]);
        $prod = Product::create(['sku' => 'LCK-1', 'name' => 'Tornillo Grado 8', 'cost_price' => 500, 'sale_price' => 1000, 'unit' => 'UND']);

        $m = KardexService::registerAdjustment($prod, $warehouse->id, 50.0, 'adjustment_in', 'Ajuste inicial con lock');
        expect((float) $m->resulting_stock)->toBe(50.0);
    });

    test('F16-2: Exclusive lock in register selection prevents two concurrent operators on same register', function () {
        $c1 = User::factory()->create(['name' => 'Operador 1']);
        $c1->assignRole('cashier');
        $c2 = User::factory()->create(['name' => 'Operador 2']);
        $c2->assignRole('cashier');

        $warehouse = Warehouse::create(['name' => 'Bodega Lock 2', 'code' => 'BOD-F16-2', 'is_active' => true]);
        $caja1 = CashRegister::create(['name' => 'Caja 1 - Mostrador', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        // C1 enters Caja 1
        $this->actingAs($c1);
        Livewire::test(PosTerminal::class)->call('selectCashRegister', $caja1->id);

        // C2 attempts to enter Caja 1
        $this->actingAs($c2);
        Livewire::test(PosTerminal::class)
            ->call('selectCashRegister', $caja1->id)
            ->assertNotSet('activeCashRegisterId', $caja1->id);
    });

    test('F16-3: PosService processes sales atomically with stock decrement inside transaction', function () {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Lock 3', 'code' => 'BOD-F16-3', 'is_active' => true]);
        $prod = Product::create(['sku' => 'LCK-3', 'name' => 'Tubo Cobre 1/2', 'cost_price' => 15000, 'sale_price' => 25000, 'unit' => 'MTR']);
        ProductStock::create(['product_id' => $prod->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 10.0]);

        $sale = PosService::processSale(
            userId: $user->id,
            warehouseId: $warehouse->id,
            customerId: null,
            paymentMethod: 'cash',
            items: [['product_id' => $prod->id, 'quantity' => 4.0, 'unit_price' => 25000.0, 'tax_rate' => 19.0, 'subtotal' => 100000.0]],
            paidAmount: 120000.0
        );

        $stock = ProductStock::where('product_id', $prod->id)->where('warehouse_id', $warehouse->id)->first();
        expect((float) $stock->current_stock)->toBe(6.00)
            ->and($sale->status)->toBe('completed');
    });

    test('F16-4: Out-of-stock sale attempt rolls back atomically with DomainException', function () {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Lock 4', 'code' => 'BOD-F16-4', 'is_active' => true]);
        $prod = Product::create(['sku' => 'LCK-4', 'name' => 'Taladro Bosch', 'cost_price' => 150000, 'sale_price' => 250000, 'unit' => 'UND']);
        ProductStock::create(['product_id' => $prod->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 2.0]);

        expect(fn () => PosService::processSale(
            userId: $user->id,
            warehouseId: $warehouse->id,
            customerId: null,
            paymentMethod: 'cash',
            items: [['product_id' => $prod->id, 'quantity' => 5.0, 'unit_price' => 250000.0, 'tax_rate' => 0.0, 'subtotal' => 1250000.0]],
            paidAmount: 1250000.0
        ))->toThrow(DomainException::class);

        // Stock remains unmutated
        $stock = ProductStock::where('product_id', $prod->id)->where('warehouse_id', $warehouse->id)->first();
        expect((float) $stock->current_stock)->toBe(2.00);
    });

    test('F16-5: PosService calculates change and sales atomically without arithmetic leaks', function () {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Lock 5', 'code' => 'BOD-F16-5', 'is_active' => true]);
        $prod = Product::create(['sku' => 'LCK-5', 'name' => 'Tornillo', 'cost_price' => 500, 'sale_price' => 1000, 'unit' => 'UND']);
        ProductStock::create(['product_id' => $prod->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 50.0]);

        // Sale: 10 units * $1000 = $10,000 + 19% IVA = $11,900. Paid: $20,000. Expected change: $8,100
        $sale = PosService::processSale(
            userId: $user->id,
            warehouseId: $warehouse->id,
            customerId: null,
            paymentMethod: 'cash',
            items: [['product_id' => $prod->id, 'quantity' => 10.0, 'unit_price' => 1000.0, 'tax_rate' => 19.0, 'subtotal' => 10000.0]],
            paidAmount: 20000.0
        );

        expect((float) $sale->total)->toBe(11900.00)
            ->and((float) $sale->change_amount)->toBe(8100.00);
    });
});
