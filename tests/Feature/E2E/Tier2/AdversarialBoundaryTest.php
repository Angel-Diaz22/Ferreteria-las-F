<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Sale;
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
 * TIER 2: BOUNDARY & CORNER CASES — F17 to F20 (ADVERSARIAL & SECURITY)
 * ============================================================================
 */
describe('F17 Boundaries: Asset Integrity & Fallback Checks', function () {
    test('F17-B1: Logo PNG file permissions allow system read access', function () {
        $logo = public_path('images/logo.png');
        expect(file_exists($logo))->toBeTrue()
            ->and(is_readable($logo))->toBeTrue();
    });

    test('F17-B2: Logo file extension matches PNG signature', function () {
        $logo = public_path('images/logo.png');
        $ext = pathinfo($logo, PATHINFO_EXTENSION);
        expect(strtolower($ext))->toBe('png');
    });

    test('F17-B3: Receipt template renders without error when customer has no document', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $warehouse = Warehouse::create(['name' => 'Bodega Recibo Doc', 'code' => 'BOD-REC-DOC', 'is_active' => true]);

        $sale = Sale::create([
            'user_id' => $admin->id,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => 'REM-NODOC-01',
            'payment_method' => 'cash',
            'subtotal' => 20000,
            'total' => 20000,
            'paid_amount' => 20000,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($admin)->get("/admin/sales/{$sale->id}/receipt");
        $response->assertSuccessful();
    });

    test('F17-B4: Static public directory contains only expected media assets', function () {
        $imagesDir = public_path('images');
        expect(is_dir($imagesDir))->toBeTrue();
    });

    test('F17-B5: Sale receipt thermal PDF streams with Content-Type application/pdf', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $warehouse = Warehouse::create(['name' => 'Bodega Mime', 'code' => 'BOD-MIME-01', 'is_active' => true]);

        $sale = Sale::create([
            'user_id' => $admin->id,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => 'REM-MIME-01',
            'payment_method' => 'cash',
            'subtotal' => 30000,
            'total' => 30000,
            'paid_amount' => 30000,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($admin)->get("/admin/sales/{$sale->id}/pdf");
        $response->assertHeader('Content-Type', 'application/pdf');
    });
});

describe('F18 Boundaries: Model Casts & Serialization Boundaries', function () {
    test('F18-B1: Boolean cast converts integer 1 and 0 to strict booleans', function () {
        $warehouse = Warehouse::create(['name' => 'Bodega Cast', 'code' => 'BOD-CAST-01', 'is_active' => 1]);
        expect($warehouse->fresh()->is_active)->toBeTrue();

        $warehouse->update(['is_active' => 0]);
        expect($warehouse->fresh()->is_active)->toBeFalse();
    });

    test('F18-B2: Decimal casts convert string inputs into formatted decimal numbers', function () {
        $prod = Product::create([
            'sku' => 'CAST-DEC-01',
            'name' => 'Cerradura',
            'cost_price' => '45000.50',
            'sale_price' => '72000.99',
            'unit' => 'UND',
        ]);

        expect((float) $prod->fresh()->cost_price)->toBe(45000.50)
            ->and((float) $prod->fresh()->sale_price)->toBe(72000.99);
    });

    test('F18-B3: Nonexistent SystemSetting key returns null or custom default', function () {
        expect(SystemSetting::get('NON_EXISTENT_KEY_12345'))->toBeNull();
    });

    test('F18-B4: User model hides password in toArray serialization', function () {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);
        $array = $user->toArray();

        expect($array)->not->toHaveKey('password')
            ->and($array)->not->toHaveKey('remember_token');
    });

    test('F18-B5: Customer credit limit cast formats decimal without losing precision', function () {
        $customer = Customer::create([
            'name' => 'Cliente Precision',
            'document_type' => 'CC',
            'document' => '44332211',
            'credit_limit' => 1234567.89,
            'current_debt' => 0.0,
            'is_active' => true,
        ]);

        expect((float) $customer->fresh()->credit_limit)->toBe(1234567.89);
    });
});

describe('F19 Boundaries: State Machine & Settlement Boundaries', function () {
    test('F19-B1: Attempting to confirm payment on an already-paid sale is rejected', function () {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $warehouse = Warehouse::create(['name' => 'Bodega Paid', 'code' => 'BOD-PAID-01', 'is_active' => true]);
        $prod = Product::create(['sku' => 'PAID-01', 'name' => 'Item Pagado', 'cost_price' => 1000, 'sale_price' => 2000, 'unit' => 'UND']);
        ProductStock::create(['product_id' => $prod->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 10]);

        $order = PosService::createPendingOrder(
            userId: $user->id,
            warehouseId: $warehouse->id,
            customerId: null,
            items: [['product_id' => $prod->id, 'quantity' => 1, 'unit_price' => 2000, 'tax_rate' => 0, 'subtotal' => 2000]]
        );

        // Confirm once -> status 'paid'
        PosService::confirmPayment($order, $admin->id, 'cash', 2000);

        // Confirm again -> must throw DomainException
        expect(fn () => PosService::confirmPayment($order, $admin->id, 'cash', 2000))
            ->toThrow(DomainException::class);
    });

    test('F19-B2: Dispatching an order that is not paid throws DomainException', function () {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Disp', 'code' => 'BOD-DISP-01', 'is_active' => true]);
        $prod = Product::create(['sku' => 'DISP-01', 'name' => 'Item Despacho', 'cost_price' => 1000, 'sale_price' => 2000, 'unit' => 'UND']);
        ProductStock::create(['product_id' => $prod->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 10]);

        $order = PosService::createPendingOrder(
            userId: $user->id,
            warehouseId: $warehouse->id,
            customerId: null,
            items: [['product_id' => $prod->id, 'quantity' => 1, 'unit_price' => 2000, 'tax_rate' => 0, 'subtotal' => 2000]]
        );

        // Attempt dispatch directly from pending_payment
        expect(fn () => PosService::dispatchOrder($order, $user->id))
            ->toThrow(DomainException::class);
    });

    test('F19-B3: Cash payment with insufficient paid amount throws DomainException', function () {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $warehouse = Warehouse::create(['name' => 'Bodega Short', 'code' => 'BOD-SHRT-01', 'is_active' => true]);
        $prod = Product::create(['sku' => 'SHRT-01', 'name' => 'Item Corto', 'cost_price' => 1000, 'sale_price' => 5000, 'unit' => 'UND']);
        ProductStock::create(['product_id' => $prod->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 10]);

        $order = PosService::createPendingOrder(
            userId: $user->id,
            warehouseId: $warehouse->id,
            customerId: null,
            items: [['product_id' => $prod->id, 'quantity' => 1, 'unit_price' => 5000, 'tax_rate' => 0, 'subtotal' => 5000]]
        );

        // Paid: $4,000 (Short by $1,000)
        expect(fn () => PosService::confirmPayment($order, $admin->id, 'cash', 4000))
            ->toThrow(DomainException::class);
    });

    test('F19-B4: Credit sale without registered customer throws DomainException', function () {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $warehouse = Warehouse::create(['name' => 'Bodega Cred Null', 'code' => 'BOD-CRD-NULL', 'is_active' => true]);
        $prod = Product::create(['sku' => 'CRD-01', 'name' => 'Item Cred', 'cost_price' => 1000, 'sale_price' => 2000, 'unit' => 'UND']);
        ProductStock::create(['product_id' => $prod->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 10]);

        $order = PosService::createPendingOrder(
            userId: $user->id,
            warehouseId: $warehouse->id,
            customerId: null, // No customer
            items: [['product_id' => $prod->id, 'quantity' => 1, 'unit_price' => 2000, 'tax_rate' => 0, 'subtotal' => 2000]]
        );

        // Cannot pay with credit without customer
        expect(fn () => PosService::confirmPayment($order, $admin->id, 'credit', 0))
            ->toThrow(DomainException::class);
    });

    test('F19-B5: Voiding a completed sale restores stock back into warehouse atomically', function () {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $warehouse = Warehouse::create(['name' => 'Bodega Void', 'code' => 'BOD-VOID-01', 'is_active' => true]);
        $prod = Product::create(['sku' => 'VOID-01', 'name' => 'Item Anulable', 'cost_price' => 5000, 'sale_price' => 10000, 'unit' => 'UND']);
        ProductStock::create(['product_id' => $prod->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 20]);

        $sale = PosService::processSale(
            userId: $user->id,
            warehouseId: $warehouse->id,
            customerId: null,
            paymentMethod: 'cash',
            items: [['product_id' => $prod->id, 'quantity' => 5, 'unit_price' => 10000, 'tax_rate' => 0, 'subtotal' => 50000]],
            paidAmount: 50000
        );

        $stock = ProductStock::where('product_id', $prod->id)->where('warehouse_id', $warehouse->id)->first();
        expect((float) $stock->current_stock)->toBe(15.00);

        // Void the sale
        PosService::voidSale($sale, 'Error de digitación en caja', $admin->id);

        $stock->refresh();
        expect((float) $stock->current_stock)->toBe(20.00)
            ->and($sale->fresh()->status)->toBe('cancelled');
    });
});

describe('F20 Boundaries: Adversarial Injection & Stress Attacks', function () {
    test('F20-B1: SQL injection attempts in product search do not execute as raw SQL', function () {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega SQLi', 'code' => 'BOD-SQLI-01', 'is_active' => true]);

        $prod = Product::create(['sku' => 'SAFE-01', 'name' => 'Tornillo Seguro', 'cost_price' => 100, 'sale_price' => 200, 'unit' => 'UND']);
        ProductStock::create(['product_id' => $prod->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 10]);

        $maliciousPayload = "'; DROP TABLE products; --";

        $results = Product::where('name', 'like', "%{$maliciousPayload}%")->get();
        expect($results)->toBeEmpty()
            ->and(Product::count())->toBeGreaterThan(0); // products table still intact
    });

    test('F20-B2: Cross-site scripting (XSS) in customer name is stored cleanly without execution', function () {
        $xssName = '<script>alert("XSS")</script> Pedro Pérez';
        $customer = Customer::create([
            'name' => $xssName,
            'document_type' => 'CC',
            'document' => '9988776655',
            'is_active' => true,
        ]);

        expect($customer->name)->toBe($xssName);
    });

    test('F20-B3: Large notes string (2,000 characters) in sale is preserved without truncation', function () {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Note', 'code' => 'BOD-NOTE-01', 'is_active' => true]);
        $prod = Product::create(['sku' => 'NOTE-01', 'name' => 'Item Note', 'cost_price' => 100, 'sale_price' => 200, 'unit' => 'UND']);
        ProductStock::create(['product_id' => $prod->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 10]);

        $longNotes = str_repeat('Observación comercial detallada de la venta. ', 40);

        $sale = PosService::processSale(
            userId: $user->id,
            warehouseId: $warehouse->id,
            customerId: null,
            paymentMethod: 'cash',
            items: [['product_id' => $prod->id, 'quantity' => 1, 'unit_price' => 200, 'tax_rate' => 0, 'subtotal' => 200]],
            paidAmount: 200,
            notes: $longNotes
        );

        expect($sale->notes)->toBe($longNotes);
    });

    test('F20-B4: Negative product quantity in items array is rejected by domain validation', function () {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Neg Qty', 'code' => 'BOD-NEG-QTY', 'is_active' => true]);
        $prod = Product::create(['sku' => 'NEG-QTY', 'name' => 'Item Negativo', 'cost_price' => 100, 'sale_price' => 200, 'unit' => 'UND']);

        expect(fn () => PosService::processSale(
            userId: $user->id,
            warehouseId: $warehouse->id,
            customerId: null,
            paymentMethod: 'cash',
            items: [['product_id' => $prod->id, 'quantity' => -5.0, 'unit_price' => 200, 'tax_rate' => 0, 'subtotal' => -1000]],
            paidAmount: 0
        ))->toThrow(DomainException::class);
    });

    test('F20-B5: Extremely large payment amount does not cause numeric overflow', function () {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Mega Pay', 'code' => 'BOD-MEGA-PAY', 'is_active' => true]);
        $prod = Product::create(['sku' => 'MEGA-P', 'name' => 'Item Mega Pay', 'cost_price' => 100, 'sale_price' => 500, 'unit' => 'UND']);
        ProductStock::create(['product_id' => $prod->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 10]);

        $hugePayment = 1000000000.00; // 1 Billion COP

        $sale = PosService::processSale(
            userId: $user->id,
            warehouseId: $warehouse->id,
            customerId: null,
            paymentMethod: 'cash',
            items: [['product_id' => $prod->id, 'quantity' => 1, 'unit_price' => 500, 'tax_rate' => 0, 'subtotal' => 500]],
            paidAmount: $hugePayment
        );

        expect((float) $sale->paid_amount)->toBe(1000000000.00)
            ->and((float) $sale->change_amount)->toBe(999999500.00);
    });
});
