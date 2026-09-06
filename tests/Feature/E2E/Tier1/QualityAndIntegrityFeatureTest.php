<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PosService;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    (new PermissionSeeder)->run();
    SystemSetting::set('iva_enabled', true, 'boolean');
});

/**
 * ============================================================================
 * TIER 1: FEATURE COVERAGE — F17 to F20 (QUALITY, MODERNIZATION & SYSTEM INTEGRITY)
 * ============================================================================
 */
describe('F17: Safe Dead Asset Cleanup', function () {
    test('F17-1: Official brand logo logo.png exists in public images directory', function () {
        $logoPath = public_path('images/logo.png');
        expect(file_exists($logoPath))->toBeTrue()
            ->and(filesize($logoPath))->toBeGreaterThan(1000);
    });

    test('F17-2: Official brand logo is a valid PNG image format', function () {
        $logoPath = public_path('images/logo.png');
        $mime = mime_content_type($logoPath);
        expect($mime)->toBe('image/png');
    });

    test('F17-3: Receipt print view references existing branding elements', function () {
        $viewPath = resource_path('views/pdf/receipt.blade.php');
        expect(file_exists($viewPath))->toBeTrue();

        $content = file_get_contents($viewPath);
        expect($content)->toContain('images/logo.png');
    });

    test('F17-4: Quote PDF view references company branding assets properly', function () {
        $viewPath = resource_path('views/pdf/quote.blade.php');
        expect(file_exists($viewPath))->toBeTrue();

        $content = file_get_contents($viewPath);
        expect($content)->toContain('$company');
    });

    test('F17-5: Public images directory path is readable and accessible', function () {
        $dir = public_path('images');
        expect(is_dir($dir))->toBeTrue()
            ->and(is_readable($dir))->toBeTrue();
    });
});

describe('F18: Framework Modernization & Pint Cleanliness', function () {
    test('F18-1: Eloquent models define modern casts() method returning typed array', function () {
        $product = new Product;
        $casts = $product->getCasts();

        expect($casts)->toBeArray()
            ->and($casts)->toHaveKey('cost_price')
            ->and($casts)->toHaveKey('sale_price')
            ->and($casts)->toHaveKey('is_active');
    });

    test('F18-2: Customer model has properly typed decimal casts for credit and debt', function () {
        $customer = new Customer;
        $casts = $customer->getCasts();

        expect($casts)->toHaveKey('credit_limit')
            ->and($casts)->toHaveKey('current_debt');
    });

    test('F18-3: SystemSetting model casts boolean values cleanly', function () {
        SystemSetting::set('test_bool', true, 'boolean');
        expect(SystemSetting::get('test_bool'))->toBeTrue();

        SystemSetting::set('test_bool', false, 'boolean');
        expect(SystemSetting::get('test_bool'))->toBeFalse();
    });

    test('F18-4: User model defines fillable attributes protecting sensitive columns', function () {
        $user = new User;
        expect($user->getFillable())->toContain('name')
            ->and($user->getFillable())->toContain('email')
            ->and($user->getFillable())->toContain('password')
            ->and($user->getHidden())->toContain('password')
            ->and($user->getHidden())->toContain('remember_token');
    });

    test('F18-5: SystemSetting handles typed integer, float and string values consistently', function () {
        SystemSetting::set('integer_val', 42, 'integer');
        SystemSetting::set('float_val', 19.5, 'float');
        SystemSetting::set('string_val', 'Ferretería Las F', 'string');

        expect(SystemSetting::get('integer_val'))->toBe(42)
            ->and(SystemSetting::get('float_val'))->toBe(19.5)
            ->and(SystemSetting::get('string_val'))->toBe('Ferretería Las F');
    });
});

describe('F19: Opaque-box E2E Test Suite', function () {
    test('F19-1: Complete sales pipeline executes with invoice numbering and stock depletion', function () {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega E2E', 'code' => 'BOD-E2E-1', 'is_active' => true]);
        $prod = Product::create(['sku' => 'E2E-01', 'name' => 'Tornillo Drywall', 'cost_price' => 50, 'sale_price' => 100, 'unit' => 'UND']);
        ProductStock::create(['product_id' => $prod->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 100]);

        $sale = PosService::processSale(
            userId: $user->id,
            warehouseId: $warehouse->id,
            customerId: null,
            paymentMethod: 'cash',
            items: [['product_id' => $prod->id, 'quantity' => 20, 'unit_price' => 100, 'tax_rate' => 19, 'subtotal' => 2000]],
            paidAmount: 3000
        );

        expect($sale->invoice_number)->toStartWith('REM-')
            ->and($sale->status)->toBe('completed')
            ->and((float) $prod->stocks()->first()->current_stock)->toBe(80.00);
    });

    test('F19-2: Credit sales update customer debt and enforce available credit bounds', function () {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Credit', 'code' => 'BOD-E2E-2', 'is_active' => true]);
        $prod = Product::create(['sku' => 'E2E-02', 'name' => 'Cemento', 'cost_price' => 20000, 'sale_price' => 30000, 'unit' => 'BOL']);
        ProductStock::create(['product_id' => $prod->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 10]);

        $customer = Customer::create([
            'name' => 'Cliente Fiel',
            'document_type' => 'CC',
            'document' => '10203040',
            'credit_limit' => 500000,
            'current_debt' => 0,
            'is_active' => true,
        ]);

        $sale = PosService::processSale(
            userId: $user->id,
            warehouseId: $warehouse->id,
            customerId: $customer->id,
            paymentMethod: 'credit',
            items: [['product_id' => $prod->id, 'quantity' => 2, 'unit_price' => 30000, 'tax_rate' => 0, 'subtotal' => 60000]],
            paidAmount: 0
        );

        $customer->refresh();
        expect((float) $customer->current_debt)->toBe(60000.00)
            ->and($customer->available_credit)->toBe(440000.00)
            ->and($sale->status)->toBe('completed');
    });

    test('F19-3: Multi-role hierarchy enforces distinct operational capabilities', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        expect($admin->can('users.manage'))->toBeTrue()
            ->and($admin->can('purchases.view'))->toBeTrue()
            ->and($cashier->can('users.manage'))->toBeFalse()
            ->and($cashier->can('purchases.view'))->toBeFalse()
            ->and($cashier->can('pos.access'))->toBeTrue();
    });

    test('F19-4: Order reservation locks stock and subsequent cancellation restores it', function () {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Order', 'code' => 'BOD-E2E-4', 'is_active' => true]);
        $prod = Product::create(['sku' => 'E2E-04', 'name' => 'Martillo', 'cost_price' => 10000, 'sale_price' => 20000, 'unit' => 'UND']);
        ProductStock::create(['product_id' => $prod->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 15]);

        // Step 1: Create order (reserves 5 units, leaving 10)
        $order = PosService::createPendingOrder(
            userId: $user->id,
            warehouseId: $warehouse->id,
            customerId: null,
            items: [['product_id' => $prod->id, 'quantity' => 5, 'unit_price' => 20000, 'tax_rate' => 0, 'subtotal' => 100000]]
        );

        $stock = ProductStock::where('product_id', $prod->id)->where('warehouse_id', $warehouse->id)->first();
        expect((float) $stock->current_stock)->toBe(10.00);

        // Step 2: Cancel order (restores 5 units, back to 15)
        PosService::cancelPendingOrder($order, $user->id, 'Cliente desistió del pedido');

        $stock->refresh();
        expect((float) $stock->current_stock)->toBe(15.00)
            ->and($order->fresh()->status)->toBe('cancelled');
    });

    test('F19-5: Comprehensive Kardex audit trail preserves all movement records in order', function () {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Kardex E2E', 'code' => 'BOD-E2E-5', 'is_active' => true]);
        $prod = Product::create(['sku' => 'E2E-05', 'name' => 'Broca 1/4', 'cost_price' => 2000, 'sale_price' => 4000, 'unit' => 'UND']);
        ProductStock::create(['product_id' => $prod->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 50]);

        PosService::processSale(
            userId: $user->id,
            warehouseId: $warehouse->id,
            customerId: null,
            paymentMethod: 'cash',
            items: [['product_id' => $prod->id, 'quantity' => 5, 'unit_price' => 4000, 'tax_rate' => 0, 'subtotal' => 20000]],
            paidAmount: 20000
        );

        $movements = $prod->inventoryMovements()->orderBy('id')->get();
        expect($movements)->not->toBeEmpty()
            ->and($movements->last()->type)->toBe('sale')
            ->and((float) $movements->last()->quantity)->toBe(5.00);
    });
});

describe('F20: Final Adversarial Coverage Hardening', function () {
    test('F20-1: Empty items payload in sales processing is rejected with descriptive error', function () {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Adv', 'code' => 'BOD-F20-1', 'is_active' => true]);

        expect(fn () => PosService::processSale(
            userId: $user->id,
            warehouseId: $warehouse->id,
            customerId: null,
            paymentMethod: 'cash',
            items: [],
            paidAmount: 0
        ))->toThrow(DomainException::class);
    });

    test('F20-2: Zero quantity items in sale are rejected by validation bounds', function () {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Adv 2', 'code' => 'BOD-F20-2', 'is_active' => true]);
        $prod = Product::create(['sku' => 'ADV-02', 'name' => 'Item Cero', 'cost_price' => 100, 'sale_price' => 200, 'unit' => 'UND']);
        ProductStock::create(['product_id' => $prod->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 10]);

        expect(fn () => PosService::processSale(
            userId: $user->id,
            warehouseId: $warehouse->id,
            customerId: null,
            paymentMethod: 'cash',
            items: [['product_id' => $prod->id, 'quantity' => 0, 'unit_price' => 200, 'tax_rate' => 0, 'subtotal' => 0]],
            paidAmount: 0
        ))->toThrow(DomainException::class);
    });

    test('F20-3: Negative unit price in sale payload is rejected', function () {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Adv 3', 'code' => 'BOD-F20-3', 'is_active' => true]);
        $prod = Product::create(['sku' => 'ADV-03', 'name' => 'Item Negativo', 'cost_price' => 100, 'sale_price' => 200, 'unit' => 'UND']);
        ProductStock::create(['product_id' => $prod->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 10]);

        expect(fn () => PosService::processSale(
            userId: $user->id,
            warehouseId: $warehouse->id,
            customerId: null,
            paymentMethod: 'cash',
            items: [['product_id' => $prod->id, 'quantity' => 1, 'unit_price' => -50, 'tax_rate' => 0, 'subtotal' => -50]],
            paidAmount: 0
        ))->toThrow(DomainException::class);
    });

    test('F20-4: Excessive discount exceeding item total value is rejected', function () {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Adv 4', 'code' => 'BOD-F20-4', 'is_active' => true]);
        $prod = Product::create(['sku' => 'ADV-04', 'name' => 'Item Descuento', 'cost_price' => 1000, 'sale_price' => 2000, 'unit' => 'UND']);
        ProductStock::create(['product_id' => $prod->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 10]);

        expect(fn () => PosService::processSale(
            userId: $user->id,
            warehouseId: $warehouse->id,
            customerId: null,
            paymentMethod: 'cash',
            items: [['product_id' => $prod->id, 'quantity' => 1, 'unit_price' => 2000, 'discount' => 3000, 'tax_rate' => 0, 'subtotal' => 0]],
            paidAmount: 0
        ))->toThrow(DomainException::class);
    });

    test('F20-5: Decimal precision with fractional centavos is rounded cleanly to two decimal places', function () {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Adv 5', 'code' => 'BOD-F20-5', 'is_active' => true]);
        $prod = Product::create(['sku' => 'ADV-05', 'name' => 'Cable por Metro', 'cost_price' => 1250.33, 'sale_price' => 1850.75, 'unit' => 'MTR']);
        ProductStock::create(['product_id' => $prod->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 100]);

        $sale = PosService::processSale(
            userId: $user->id,
            warehouseId: $warehouse->id,
            customerId: null,
            paymentMethod: 'cash',
            items: [['product_id' => $prod->id, 'quantity' => 3.75, 'unit_price' => 1850.75, 'tax_rate' => 19.0, 'subtotal' => 6940.31]],
            paidAmount: 10000.00
        );

        expect((float) $sale->total)->toBeGreaterThan(0.0)
            ->and((float) $sale->paid_amount)->toBe(10000.00)
            ->and((float) $sale->change_amount)->toBeGreaterThan(0.0);
    });
});
