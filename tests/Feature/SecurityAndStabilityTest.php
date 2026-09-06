<?php

use App\Filament\Resources\BrandResource;
use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\PriceListResource;
use App\Filament\Resources\SupplierResource;
use App\Filament\Resources\WarehouseResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PosService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

test('cashier cannot create, edit, or delete catalog auxiliary resources', function () {
    $cashier = User::factory()->create();
    $cashier->assignRole('cashier');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($cashier);
    $brand = Brand::create(['name' => 'Stanley', 'slug' => 'stanley']);
    $category = Category::create(['name' => 'Tornillería', 'slug' => 'tornilleria']);

    // Cashier cannot create or edit auxiliary resources
    expect(BrandResource::canCreate())->toBeFalse()
        ->and(BrandResource::canEdit($brand))->toBeFalse()
        ->and(BrandResource::canDelete($brand))->toBeFalse()
        ->and(CategoryResource::canCreate())->toBeFalse()
        ->and(CategoryResource::canEdit($category))->toBeFalse()
        ->and(CategoryResource::canDelete($category))->toBeFalse()
        ->and(PriceListResource::canCreate())->toBeFalse()
        ->and(WarehouseResource::canCreate())->toBeFalse()
        ->and(SupplierResource::canCreate())->toBeFalse();

    // Admin can create and manage them
    $this->actingAs($admin);
    expect(BrandResource::canCreate())->toBeTrue()
        ->and(BrandResource::canEdit($brand))->toBeTrue()
        ->and(BrandResource::canDelete($brand))->toBeTrue()
        ->and(CategoryResource::canCreate())->toBeTrue()
        ->and(CategoryResource::canEdit($category))->toBeTrue()
        ->and(CategoryResource::canDelete($category))->toBeTrue()
        ->and(PriceListResource::canCreate())->toBeTrue()
        ->and(WarehouseResource::canCreate())->toBeTrue()
        ->and(SupplierResource::canCreate())->toBeTrue();
});

test('pos service rejects negative pricing, tax rate, and excessive discount anomalies', function () {
    $cashier = User::factory()->create();
    $warehouse = Warehouse::create(['name' => 'Bodega Test', 'is_active' => true]);
    $category = Category::create(['name' => 'Eléctricos', 'slug' => 'electricos']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'CAB-001',
        'name' => 'Cable THHN #12',
        'cost_price' => 2000.00,
        'sale_price' => 3500.00,
        'unit' => 'MTR',
        'is_active' => true,
    ]);

    ProductStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'current_stock' => 100.00,
    ]);

    // Negative unit price
    expect(fn () => PosService::processSale(
        userId: $cashier->id,
        warehouseId: $warehouse->id,
        customerId: null,
        paymentMethod: 'cash',
        items: [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => -100, 'subtotal' => -100],
        ],
        paidAmount: 0
    ))->toThrow(DomainException::class, 'El precio unitario no puede ser negativo.');

    // Negative discount
    expect(fn () => PosService::processSale(
        userId: $cashier->id,
        warehouseId: $warehouse->id,
        customerId: null,
        paymentMethod: 'cash',
        items: [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 3500, 'discount' => -500, 'subtotal' => 3500],
        ],
        paidAmount: 3500
    ))->toThrow(DomainException::class, 'El descuento no puede ser negativo.');

    // Discount greater than total item price
    expect(fn () => PosService::processSale(
        userId: $cashier->id,
        warehouseId: $warehouse->id,
        customerId: null,
        paymentMethod: 'cash',
        items: [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 3500, 'discount' => 4000, 'subtotal' => -500],
        ],
        paidAmount: 0
    ))->toThrow(DomainException::class, 'El descuento no puede superar el valor total del producto.');

    // Negative tax rate
    expect(fn () => PosService::processSale(
        userId: $cashier->id,
        warehouseId: $warehouse->id,
        customerId: null,
        paymentMethod: 'cash',
        items: [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 3500, 'tax_rate' => -5, 'subtotal' => 3500],
        ],
        paidAmount: 3500
    ))->toThrow(DomainException::class, 'La tasa de impuesto no puede ser negativa.');
});

test('cancelPendingOrder restores stock and creates audited kardex adjustment_in movement', function () {
    $cashier = User::factory()->create();
    $warehouse = Warehouse::create(['name' => 'Bodega Central', 'is_active' => true]);
    $category = Category::create(['name' => 'Pinturas', 'slug' => 'pinturas']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'VIN-001',
        'name' => 'Vinilo Blanco Galón',
        'cost_price' => 35000.00,
        'sale_price' => 55000.00,
        'unit' => 'GAL',
        'is_active' => true,
    ]);

    $stock = ProductStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'current_stock' => 20.00,
    ]);

    // Create pending order reserving 5 units
    $sale = PosService::createPendingOrder(
        userId: $cashier->id,
        warehouseId: $warehouse->id,
        customerId: null,
        items: [
            ['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 55000, 'tax_rate' => 0, 'subtotal' => 275000],
        ]
    );

    // Stock was reduced to 15 while pending
    expect((float) $stock->fresh()->current_stock)->toBe(15.0);

    // Customer desists and order is cancelled
    PosService::cancelPendingOrder($sale, $cashier->id, 'El cliente no contaba con efectivo');

    // Stock is restored back to 20
    expect((float) $stock->fresh()->current_stock)->toBe(20.0);
    expect($sale->fresh()->status)->toBe('cancelled');

    // Verify Kardex adjustment_in movement exists with reason
    $movement = InventoryMovement::where('product_id', $product->id)
        ->where('type', 'adjustment_in')
        ->where('reference_id', $sale->id)
        ->first();

    expect($movement)->not->toBeNull()
        ->and((float) $movement->quantity)->toBe(5.0)
        ->and((float) $movement->previous_stock)->toBe(15.0)
        ->and((float) $movement->resulting_stock)->toBe(20.0)
        ->and($movement->notes)->toContain('Liberación de reserva por anulación de pedido');
});

test('confirmPayment prevents duplicate payment and double spend', function () {
    $cashier = User::factory()->create();
    $warehouse = Warehouse::create(['name' => 'Bodega Central', 'is_active' => true]);
    $category = Category::create(['name' => 'Fijaciones', 'slug' => 'fijaciones']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'CHAZ-001',
        'name' => 'Chazo Plástico 5/16',
        'cost_price' => 50.00,
        'sale_price' => 150.00,
        'unit' => 'UND',
        'is_active' => true,
    ]);

    ProductStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'current_stock' => 1000.00,
    ]);

    $sale = PosService::createPendingOrder(
        userId: $cashier->id,
        warehouseId: $warehouse->id,
        customerId: null,
        items: [
            ['product_id' => $product->id, 'quantity' => 10, 'unit_price' => 150, 'tax_rate' => 0, 'subtotal' => 1500],
        ]
    );

    // Confirm first payment
    PosService::confirmPayment($sale, $cashier->id, 'cash', 2000);
    expect($sale->fresh()->status)->toBe('paid');

    // Attempting to confirm payment a second time must fail
    expect(fn () => PosService::confirmPayment($sale, $cashier->id, 'cash', 2000))
        ->toThrow(DomainException::class, "no se puede cobrar porque su estado actual es 'paid'");
});

test('dispatchOrder rejects non-paid order and prevents fraud', function () {
    $cashier = User::factory()->create();
    $warehouse = Warehouse::create(['name' => 'Bodega Central', 'is_active' => true]);
    $category = Category::create(['name' => 'Herramientas', 'slug' => 'herramientas']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'DEST-001',
        'name' => 'Destornillador Pala 6 pulg',
        'cost_price' => 8000.00,
        'sale_price' => 15000.00,
        'unit' => 'UND',
        'is_active' => true,
    ]);

    ProductStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'current_stock' => 50.00,
    ]);

    $sale = PosService::createPendingOrder(
        userId: $cashier->id,
        warehouseId: $warehouse->id,
        customerId: null,
        items: [
            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 15000, 'tax_rate' => 0, 'subtotal' => 15000],
        ]
    );

    // Cannot dispatch pending order
    expect(fn () => PosService::dispatchOrder($sale, $cashier->id))
        ->toThrow(DomainException::class, '¡ALERTA ANTI-FRAUDE!');

    // Pay and then dispatch succeeds
    PosService::confirmPayment($sale, $cashier->id, 'cash', 15000);
    PosService::dispatchOrder($sale, $cashier->id);
    expect($sale->fresh()->status)->toBe('delivered');
});

test('pdf and receipt routes have rate limiting throttle middleware applied', function () {
    $routes = ['quotes.pdf', 'sales.receipt', 'sales.pdf'];

    foreach ($routes as $routeName) {
        $route = Route::getRoutes()->getByName($routeName);
        expect($route)->not->toBeNull();
        expect($route->gatherMiddleware())->toContain('throttle:60,1');
    }
});
