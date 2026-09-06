<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\KardexService;
use Spatie\Permission\Models\Role;

test('calculates weighted average cost and updates warehouse stock accurately upon purchase', function () {
    $warehouse = Warehouse::create([
        'name' => 'Bodega Central',
        'code' => 'BOD-CENTRAL',
        'is_active' => true,
    ]);

    $category = Category::create(['name' => 'Fijaciones', 'slug' => 'fijaciones']);

    // 1. Producto inicial con 10 unidades a costo de $10.000 ($100.000 invertidos)
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'MART-STANLEY-16',
        'name' => 'Martillo Stanley 16oz',
        'cost_price' => 10000.00,
        'sale_price' => 18000.00,
        'unit' => 'UND',
    ]);

    ProductStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'current_stock' => 10.00,
        'min_stock' => 2.00,
    ]);

    $supplier = Supplier::create([
        'name' => 'Distribuidora Central S.A.S.',
        'nit' => '900.555.444-1',
    ]);

    $admin = User::factory()->create();

    // 2. Nueva compra de 10 unidades pero a costo unitario de $14.000 ($140.000 invertidos)
    $purchase = Purchase::create([
        'supplier_id' => $supplier->id,
        'user_id' => $admin->id,
        'warehouse_id' => $warehouse->id,
        'purchase_date' => now()->toDateString(),
        'invoice_number' => 'FAC-9988',
        'subtotal' => 140000.00,
        'total' => 140000.00,
        'status' => 'completed',
    ]);

    $item = PurchaseItem::create([
        'purchase_id' => $purchase->id,
        'product_id' => $product->id,
        'quantity' => 10.00,
        'unit_cost' => 14000.00,
        'subtotal' => 140000.00,
    ]);

    // 3. Procesar en el Kardex
    KardexService::processPurchase($purchase);

    // 4. Verificaciones
    // Costo promedio esperado: (10 * 10.000 + 10 * 14.000) / 20 = 240.000 / 20 = $12.000
    $product->refresh();
    expect((float) $product->cost_price)->toEqual(12000.00);

    // Stock esperado en la bodega: 10 + 10 = 20
    $stock = ProductStock::where('product_id', $product->id)
        ->where('warehouse_id', $warehouse->id)
        ->first();
    expect((float) $stock->current_stock)->toEqual(20.00);

    // Movimiento registrado en el Kardex
    $this->assertDatabaseHas('inventory_movements', [
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'type' => 'purchase',
        'quantity' => 10.00,
        'unit_cost' => 14000.00,
        'previous_stock' => 10.00,
        'resulting_stock' => 20.00,
    ]);
});

test('cashier role is forbidden from accessing purchases, suppliers and kardex', function () {
    $cashierRole = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
    $cashier = User::factory()->create();
    $cashier->assignRole($cashierRole);

    // Intentos de acceso por el cajero
    $this->actingAs($cashier)->get('/admin/purchases')->assertForbidden();
    $this->actingAs($cashier)->get('/admin/suppliers')->assertForbidden();
    $this->actingAs($cashier)->get('/admin/inventory-movements')->assertForbidden();
});

test('admin role can access purchases, suppliers and kardex', function () {
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole($adminRole);

    $this->actingAs($admin)->get('/admin/purchases')->assertSuccessful();
    $this->actingAs($admin)->get('/admin/suppliers')->assertSuccessful();
    $this->actingAs($admin)->get('/admin/inventory-movements')->assertSuccessful();
});

test('registers manual inventory adjustments correctly in Kardex for entries and exits', function () {
    $warehouse = Warehouse::create([
        'name' => 'Bodega Sur',
        'code' => 'BOD-SUR',
        'is_active' => true,
    ]);

    $product = Product::create([
        'sku' => 'BROCHA-3IN',
        'name' => 'Brocha Monasterio 3 pulgadas',
        'cost_price' => 5000.00,
        'sale_price' => 9000.00,
        'unit' => 'UND',
    ]);

    $admin = User::factory()->create();

    // 1. Ajuste de entrada manual (+15 unidades)
    $inMovement = KardexService::registerAdjustment(
        product: $product,
        warehouseId: $warehouse->id,
        quantity: 15.00,
        type: 'adjustment_in',
        notes: 'Conteo físico inicial en bodega',
        userId: $admin->id
    );

    expect($inMovement->quantity)->toEqual('15.00')
        ->and($inMovement->resulting_stock)->toEqual('15.00')
        ->and($inMovement->type)->toBe('adjustment_in');

    $stock = ProductStock::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->first();
    expect((float) $stock->current_stock)->toEqual(15.00);

    // 2. Ajuste de salida manual (-3 unidades por merma)
    $outMovement = KardexService::registerAdjustment(
        product: $product,
        warehouseId: $warehouse->id,
        quantity: 3.00,
        type: 'adjustment_out',
        notes: 'Merma por cerdas dañadas',
        userId: $admin->id
    );

    expect($outMovement->quantity)->toEqual('3.00')
        ->and($outMovement->previous_stock)->toEqual('15.00')
        ->and($outMovement->resulting_stock)->toEqual('12.00')
        ->and($outMovement->type)->toBe('adjustment_out');

    expect((float) $stock->fresh()->current_stock)->toEqual(12.00);
});
