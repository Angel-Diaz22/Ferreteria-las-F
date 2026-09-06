<?php

use App\Filament\Pages\PosTerminal;
use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Sale;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PosService;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    (new PermissionSeeder)->run();
    SystemSetting::set('iva_enabled', true, 'boolean');
});

/**
 * ============================================================================
 * TIER 2: BOUNDARY & CORNER CASES — F12 to F16 (POS, CASH REGISTERS & CONCURRENCY)
 * ============================================================================
 */
describe('F12 Boundaries: POS Cash Registers Flow Edge Cases', function () {
    test('F12-B1: Cashier providing non-zero opening amount on attention register is clamped to 0.0', function () {
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-B12-1', 'is_active' => true]);
        $caja1 = CashRegister::create(['name' => 'Caja 1 - Mostrador', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        $this->actingAs($cashier);
        Livewire::test(PosTerminal::class)
            ->set('shiftOpeningAmount', '50000') // Cashier attempts to enter $50k base
            ->call('selectCashRegister', $caja1->id);

        $shift = CashShift::where('cash_register_id', $caja1->id)->where('status', 'open')->first();
        expect((float) $shift->opening_amount)->toBe(0.0);
    });

    test('F12-B2: Cashier calling openPaymentModal directly is blocked with modal staying closed', function () {
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-B12-2', 'is_active' => true]);
        $caja1 = CashRegister::create(['name' => 'Caja 1 - Mostrador', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        $this->actingAs($cashier);
        Livewire::test(PosTerminal::class)
            ->call('selectCashRegister', $caja1->id)
            ->call('openPaymentModal')
            ->assertSet('paymentModalOpen', false);
    });

    test('F12-B3: Cashier attempting switchTab to caja is reverted to terminal', function () {
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-B12-3', 'is_active' => true]);
        $caja2 = CashRegister::create(['name' => 'Caja 2 - Auxiliar', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        $this->actingAs($cashier);
        Livewire::test(PosTerminal::class)
            ->call('selectCashRegister', $caja2->id)
            ->call('switchTab', 'caja')
            ->assertSet('currentTab', 'terminal');
    });

    test('F12-B4: Admin enters Caja 3 directly without selection modal even with multiple registers', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-B12-4', 'is_active' => true]);
        CashRegister::create(['name' => 'Caja 1 - Mostrador Principal', 'warehouse_id' => $warehouse->id, 'is_active' => true]);
        CashRegister::create(['name' => 'Caja 2 - Mostrador Auxiliar', 'warehouse_id' => $warehouse->id, 'is_active' => true]);
        $caja3 = CashRegister::create(['name' => 'Caja 3 - Central de Pagos', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        $this->actingAs($admin);
        Livewire::test(PosTerminal::class)
            ->assertSet('selectRegisterModalOpen', false)
            ->assertSet('activeCashRegisterId', $caja3->id);
    });

    test('F12-B5: Switching to valid terminal tab keeps currentTab in terminal', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-B12-5', 'is_active' => true]);
        CashRegister::create(['name' => 'Caja 3 - Central', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        $this->actingAs($admin);
        Livewire::test(PosTerminal::class)
            ->call('switchTab', 'terminal')
            ->assertSet('currentTab', 'terminal');
    });
});

describe('F13 Boundaries: Shift Close Discrepancies & Edge Cases', function () {
    test('F13-B1: Closing shift with negative cash discrepancy (cash shortage) records negative difference', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-B13-1', 'is_active' => true]);
        $caja3 = CashRegister::create(['name' => 'Caja 3 - Central', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        $shift = CashShift::create([
            'cash_register_id' => $caja3->id,
            'user_id' => $admin->id,
            'opening_amount' => 100000.00,
            'expected_amount' => 200000.00,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $this->actingAs($admin);
        Livewire::test(PosTerminal::class)
            ->set('activeCashShiftId', $shift->id)
            ->set('activeCashRegisterId', $caja3->id)
            ->set('shiftClosingAmount', '190000') // $10k shortage
            ->call('confirmCloseShift');

        expect((float) $shift->fresh()->difference)->toBe(-10000.00);
    });

    test('F13-B2: Closing shift with positive cash discrepancy (cash surplus) records positive difference', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-B13-2', 'is_active' => true]);
        $caja3 = CashRegister::create(['name' => 'Caja 3 - Central', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        $shift = CashShift::create([
            'cash_register_id' => $caja3->id,
            'user_id' => $admin->id,
            'opening_amount' => 50000.00,
            'expected_amount' => 100000.00,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $this->actingAs($admin);
        Livewire::test(PosTerminal::class)
            ->set('activeCashShiftId', $shift->id)
            ->set('activeCashRegisterId', $caja3->id)
            ->set('shiftClosingAmount', '105000') // $5k surplus
            ->call('confirmCloseShift');

        expect((float) $shift->fresh()->difference)->toBe(5000.00);
    });

    test('F13-B3: Calling confirmCloseShift without active shift returns safely without exception', function () {
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $this->actingAs($cashier);
        Livewire::test(PosTerminal::class)
            ->set('activeCashShiftId', null)
            ->call('confirmCloseShift')
            ->assertOk();
    });

    test('F13-B4: Difference calculation rounds to two decimal places', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-B13-4', 'is_active' => true]);
        $caja3 = CashRegister::create(['name' => 'Caja 3 - Central', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        $shift = CashShift::create([
            'cash_register_id' => $caja3->id,
            'user_id' => $admin->id,
            'opening_amount' => 0.00,
            'expected_amount' => 100.55,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $this->actingAs($admin);
        Livewire::test(PosTerminal::class)
            ->set('activeCashShiftId', $shift->id)
            ->set('activeCashRegisterId', $caja3->id)
            ->set('shiftClosingAmount', '100.50')
            ->call('confirmCloseShift');

        expect((float) $shift->fresh()->difference)->toBe(-0.05);
    });

    test('F13-B5: Shift close timestamp closed_at is recorded', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-B13-5', 'is_active' => true]);
        $caja3 = CashRegister::create(['name' => 'Caja 3 - Central', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        $shift = CashShift::create([
            'cash_register_id' => $caja3->id,
            'user_id' => $admin->id,
            'opening_amount' => 0.0,
            'status' => 'open',
            'opened_at' => now()->subHours(2),
        ]);

        $this->actingAs($admin);
        Livewire::test(PosTerminal::class)
            ->set('activeCashShiftId', $shift->id)
            ->set('activeCashRegisterId', $caja3->id)
            ->set('shiftClosingAmount', '0')
            ->call('confirmCloseShift');

        expect($shift->fresh()->closed_at)->not->toBeNull();
    });
});

describe('F14 Boundaries: Cash Register Identification Variations', function () {
    test('F14-B1: Case-insensitive match on CAJA 3 identifies cashier register', function () {
        $reg = new CashRegister(['name' => 'CAJA 3 PRINCIPAL']);
        expect($reg->isCashier())->toBeTrue();
    });

    test('F14-B2: Name containing Patio identifies cashier register', function () {
        $reg = new CashRegister(['name' => 'Caja Patio y Materiales Pesados']);
        expect($reg->isCashier())->toBeTrue();
    });

    test('F14-B3: Attention register name variations do not trigger cashier flag', function () {
        $r1 = new CashRegister(['name' => 'Caja 1']);
        $r2 = new CashRegister(['name' => 'Caja 2']);
        $r3 = new CashRegister(['name' => 'Puesto Asesor 1']);

        expect($r1->isCashier())->toBeFalse()
            ->and($r2->isCashier())->toBeFalse()
            ->and($r3->isCashier())->toBeFalse();
    });

    test('F14-B4: Register handlesCash is strictly synonymous with isCashier', function () {
        $cajaCentral = new CashRegister(['name' => 'Caja Central']);
        $cajaMostrador = new CashRegister(['name' => 'Caja 1 Mostrador']);

        expect($cajaCentral->handlesCash())->toBe($cajaCentral->isCashier())
            ->and($cajaMostrador->handlesCash())->toBe($cajaMostrador->isCashier());
    });

    test('F14-B5: Register isAttentionRegister is strictly inverse of isCashier', function () {
        $reg1 = new CashRegister(['name' => 'Caja 1']);
        $reg3 = new CashRegister(['name' => 'Caja 3']);

        expect($reg1->isAttentionRegister())->toBe(! $reg1->isCashier())
            ->and($reg3->isAttentionRegister())->toBe(! $reg3->isCashier());
    });
});

describe('F15 Boundaries: POS Grid & Layout Extremes', function () {
    test('F15-B1: Cart holding 30 items calculates totals without numerical errors', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $warehouse = Warehouse::create(['name' => 'Bodega Cart 30', 'code' => 'BOD-CART-30', 'is_active' => true]);
        CashRegister::create(['name' => 'Caja 3 - Central', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        $cart = [];
        for ($i = 1; $i <= 30; $i++) {
            $cart[] = [
                'product_id' => $i,
                'name' => "Articulo {$i}",
                'sku' => "SKU-{$i}",
                'unit' => 'UND',
                'unit_price' => 1000.0,
                'original_price' => 1000.0,
                'quantity' => 1.0,
                'tax_rate' => 0.0,
                'discount' => 0.0,
                'subtotal' => 1000.0,
                'stock' => 100.0,
            ];
        }

        $this->actingAs($admin);
        $component = Livewire::test(PosTerminal::class)->set('cart', $cart);

        expect($component->instance()->subtotal)->toBe(30000.0);
    });

    test('F15-B2: Product search with nonexistent string returns empty page without error', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $warehouse = Warehouse::create(['name' => 'Bodega Search Null', 'code' => 'BOD-SRCH-0', 'is_active' => true]);
        CashRegister::create(['name' => 'Caja 3 - Central', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        $this->actingAs($admin);
        $component = Livewire::test(PosTerminal::class)->set('search', 'NONEXISTENT_ITEM_XYZ');

        expect($component->instance()->products->total())->toBe(0);
    });

    test('F15-B3: Product search handles leading and trailing spaces gracefully', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $warehouse = Warehouse::create(['name' => 'Bodega Search Trim', 'code' => 'BOD-SRCH-TRIM', 'is_active' => true]);
        CashRegister::create(['name' => 'Caja 3 - Central', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        $prod = Product::create(['sku' => 'TRIM-01', 'name' => 'Serrucho Carpintero', 'cost_price' => 10000, 'sale_price' => 18000, 'unit' => 'UND']);
        ProductStock::create(['product_id' => $prod->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 5]);

        $this->actingAs($admin);
        $component = Livewire::test(PosTerminal::class)->set('search', '  Serrucho  ');

        expect($component->instance()->products->total())->toBe(1);
    });

    test('F15-B4: Setting category to null resets category filter', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $warehouse = Warehouse::create(['name' => 'Bodega Reset Cat', 'code' => 'BOD-RST-CAT', 'is_active' => true]);
        CashRegister::create(['name' => 'Caja 3 - Central', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        $this->actingAs($admin);
        $component = Livewire::test(PosTerminal::class)
            ->call('setCategory', 5)
            ->assertSet('selectedCategory', 5)
            ->call('setCategory', null)
            ->assertSet('selectedCategory', null);
    });

    test('F15-B5: Cart items with discount equal to subtotal result in zero line total', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $warehouse = Warehouse::create(['name' => 'Bodega Disc Zero', 'code' => 'BOD-DISC-0', 'is_active' => true]);
        CashRegister::create(['name' => 'Caja 3 - Central', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        $cart = [
            [
                'product_id' => 1,
                'name' => 'Item 100% Descuento',
                'sku' => 'DISC-100',
                'unit' => 'UND',
                'unit_price' => 5000.0,
                'original_price' => 5000.0,
                'quantity' => 1.0,
                'tax_rate' => 0.0,
                'discount' => 5000.0, // 100% discount
                'subtotal' => 0.0,
                'stock' => 10.0,
            ],
        ];

        $this->actingAs($admin);
        $component = Livewire::test(PosTerminal::class)->set('cart', $cart);

        expect($component->instance()->subtotal)->toBe(0.0);
    });
});

describe('F16 Boundaries: Pessimistic Locking & Precision Edge Cases', function () {
    test('F16-B1: Selling exact entire inventory leaves exactly 0.0 current stock', function () {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Exact Sale', 'code' => 'BOD-EX-SALE', 'is_active' => true]);
        $prod = Product::create(['sku' => 'ALL-01', 'name' => 'Manguera 1/2', 'cost_price' => 1000, 'sale_price' => 2000, 'unit' => 'MTR']);
        ProductStock::create(['product_id' => $prod->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 7.0]);

        PosService::processSale(
            userId: $user->id,
            warehouseId: $warehouse->id,
            customerId: null,
            paymentMethod: 'cash',
            items: [['product_id' => $prod->id, 'quantity' => 7.0, 'unit_price' => 2000.0, 'tax_rate' => 0.0, 'subtotal' => 14000.0]],
            paidAmount: 14000.0
        );

        $stock = ProductStock::where('product_id', $prod->id)->where('warehouse_id', $warehouse->id)->first();
        expect((float) $stock->current_stock)->toBe(0.0);
    });

    test('F16-B2: Selling 0.001 units more than available stock is rejected by out-of-stock guard', function () {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Tiny Over', 'code' => 'BOD-TINY-OVR', 'is_active' => true]);
        $prod = Product::create(['sku' => 'TINY-01', 'name' => 'Arena Fina', 'cost_price' => 100, 'sale_price' => 200, 'unit' => 'KG']);
        ProductStock::create(['product_id' => $prod->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 5.0]);

        expect(fn () => PosService::processSale(
            userId: $user->id,
            warehouseId: $warehouse->id,
            customerId: null,
            paymentMethod: 'cash',
            items: [['product_id' => $prod->id, 'quantity' => 5.001, 'unit_price' => 200.0, 'tax_rate' => 0.0, 'subtotal' => 1000.2]],
            paidAmount: 2000.0
        ))->toThrow(DomainException::class);
    });

    test('F16-B3: Consecutive sequential sales on same product decrement stock reliably', function () {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Seq', 'code' => 'BOD-SEQ', 'is_active' => true]);
        $prod = Product::create(['sku' => 'SEQ-01', 'name' => 'Broca Concreto 3/8', 'cost_price' => 3000, 'sale_price' => 6000, 'unit' => 'UND']);
        ProductStock::create(['product_id' => $prod->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 10.0]);

        // Sale 1: 3 units
        PosService::processSale(
            userId: $user->id, warehouseId: $warehouse->id, customerId: null, paymentMethod: 'cash',
            items: [['product_id' => $prod->id, 'quantity' => 3.0, 'unit_price' => 6000.0, 'tax_rate' => 0.0, 'subtotal' => 18000.0]],
            paidAmount: 18000.0
        );

        // Sale 2: 4 units
        PosService::processSale(
            userId: $user->id, warehouseId: $warehouse->id, customerId: null, paymentMethod: 'cash',
            items: [['product_id' => $prod->id, 'quantity' => 4.0, 'unit_price' => 6000.0, 'tax_rate' => 0.0, 'subtotal' => 24000.0]],
            paidAmount: 24000.0
        );

        $stock = ProductStock::where('product_id', $prod->id)->where('warehouse_id', $warehouse->id)->first();
        expect((float) $stock->current_stock)->toBe(3.0);
    });

    test('F16-B4: Payment with exact amount produces exactly $0 change', function () {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Exact Pay', 'code' => 'BOD-EX-PAY', 'is_active' => true]);
        $prod = Product::create(['sku' => 'PAY-01', 'name' => 'Candado 40mm', 'cost_price' => 10000, 'sale_price' => 15000, 'unit' => 'UND']);
        ProductStock::create(['product_id' => $prod->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 5.0]);

        $sale = PosService::processSale(
            userId: $user->id, warehouseId: $warehouse->id, customerId: null, paymentMethod: 'cash',
            items: [['product_id' => $prod->id, 'quantity' => 1.0, 'unit_price' => 15000.0, 'tax_rate' => 0.0, 'subtotal' => 15000.0]],
            paidAmount: 15000.0
        );

        expect((float) $sale->change_amount)->toBe(0.0);
    });

    test('F16-B5: Payment with large denomination ($500,000 COP) calculates precise change', function () {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Large Pay', 'code' => 'BOD-LRG-PAY', 'is_active' => true]);
        $prod = Product::create(['sku' => 'PAY-02', 'name' => 'Tornillo', 'cost_price' => 100, 'sale_price' => 200, 'unit' => 'UND']);
        ProductStock::create(['product_id' => $prod->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 10.0]);

        // Sale: $200. Paid: $500,000. Change: $499,800.
        $sale = PosService::processSale(
            userId: $user->id, warehouseId: $warehouse->id, customerId: null, paymentMethod: 'cash',
            items: [['product_id' => $prod->id, 'quantity' => 1.0, 'unit_price' => 200.0, 'tax_rate' => 0.0, 'subtotal' => 200.0]],
            paidAmount: 500000.0
        );

        expect((float) $sale->change_amount)->toBe(499800.0);
    });
});
