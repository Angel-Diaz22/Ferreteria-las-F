<?php

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\PriceListResource;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\RelationManagers\StocksRelationManager;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\WarehouseResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('can create a warehouse and store products with independent stock', function () {
    $warehouseA = Warehouse::create([
        'name' => 'Bodega Central',
        'code' => 'BOD-CENTRAL',
        'address' => 'Calle 10 # 20-30',
        'is_active' => true,
    ]);

    $warehouseB = Warehouse::create([
        'name' => 'Sucursal Norte',
        'code' => 'SUC-NORTE',
        'address' => 'Av Santander # 45-12',
        'is_active' => true,
    ]);

    $category = Category::create([
        'name' => 'Fijaciones',
        'slug' => 'fijaciones',
    ]);

    $brand = Brand::create([
        'name' => 'Stanley',
        'slug' => 'stanley',
    ]);

    $product = Product::create([
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'sku' => 'CHAZO-01',
        'barcode' => '7709998881112',
        'name' => 'Chazo Plástico 5/16 con Tornillo',
        'cost_price' => 200.00,
        'sale_price' => 500.00,
        'tax_rate' => 19.00,
        'unit' => 'UND',
    ]);

    $stockA = ProductStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouseA->id,
        'current_stock' => 1500.00,
        'min_stock' => 200.00,
    ]);

    $stockB = ProductStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouseB->id,
        'current_stock' => 300.00,
        'min_stock' => 50.00,
    ]);

    expect($product->stocks)->toHaveCount(2)
        ->and($stockA->current_stock)->toEqual('1500.00')
        ->and($stockB->current_stock)->toEqual('300.00');
});

test('applies differentiated price lists correctly', function () {
    $detal = PriceList::create([
        'name' => 'Detal',
        'is_default' => true,
    ]);

    $mayorista = PriceList::create([
        'name' => 'Mayorista',
        'is_default' => false,
    ]);

    $product = Product::create([
        'sku' => 'TUBO-PVC-1/2',
        'name' => 'Tubo PVC Presión 1/2 pulgada x 6m',
        'cost_price' => 12000.00,
        'sale_price' => 20000.00, // Precio base al detal
        'unit' => 'UND',
    ]);

    PriceListItem::create([
        'price_list_id' => $mayorista->id,
        'product_id' => $product->id,
        'price' => 16500.00,
    ]);

    $item = PriceListItem::where('price_list_id', $mayorista->id)
        ->where('product_id', $product->id)
        ->first();

    expect($item->price)->toEqual('16500.00')
        ->and($product->sale_price)->toEqual('20000.00');
});

test('product total stock accessor uses withSum attribute or relationLoaded without additional query', function () {
    $warehouse = Warehouse::create(['name' => 'Bodega Test', 'code' => 'BOD-TEST-ACC']);
    $category = Category::create(['name' => 'Cat Acc', 'slug' => 'cat-acc']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'SKU-ACC-01',
        'name' => 'Producto Acc Test',
        'cost_price' => 1000,
        'sale_price' => 2000,
        'unit' => 'UND',
    ]);

    ProductStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'current_stock' => 45.0,
        'min_stock' => 5.0,
    ]);

    // Test 1: withSum
    $pWithSum = Product::withSum('stocks as total_stock', 'current_stock')->find($product->id);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $stock = $pWithSum->total_stock;
    expect(count(DB::getQueryLog()))->toBe(0)
        ->and($stock)->toBe(45.0);
    DB::disableQueryLog();

    // Test 2: with relation loaded
    $pWithRel = Product::with('stocks')->find($product->id);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $stockRel = $pWithRel->total_stock;
    expect(count(DB::getQueryLog()))->toBe(0)
        ->and($stockRel)->toBe(45.0);
    DB::disableQueryLog();
});

test('stocks relation manager creates initial stock X without duplicating to 2X and records kardex', function () {
    (new PermissionSeeder)->run();
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $warehouse = Warehouse::create(['name' => 'Bodega Kardex Test', 'code' => 'BOD-KDX-01', 'is_active' => true]);
    $category = Category::create(['name' => 'Cat Kdx', 'slug' => 'cat-kdx']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'SKU-KDX-01',
        'name' => 'Producto Kardex Test',
        'cost_price' => 10000,
        'sale_price' => 15000,
        'unit' => 'UND',
    ]);

    Livewire::actingAs($admin)
        ->test(StocksRelationManager::class, [
            'ownerRecord' => $product,
            'pageClass' => EditProduct::class,
        ])
        ->callTableAction('create', data: [
            'warehouse_id' => $warehouse->id,
            'current_stock' => 20.00,
            'min_stock' => 5.00,
            'max_stock' => 50.00,
        ])
        ->assertHasNoTableActionErrors();

    $stock = ProductStock::where('product_id', $product->id)
        ->where('warehouse_id', $warehouse->id)
        ->firstOrFail();

    // Stock must be exactly 20, NOT 40
    expect((float) $stock->current_stock)->toBe(20.00);

    // Kardex must show initial adjustment
    $movement = InventoryMovement::where('product_id', $product->id)
        ->where('warehouse_id', $warehouse->id)
        ->firstOrFail();

    expect((float) $movement->previous_stock)->toBe(0.00)
        ->and((float) $movement->quantity)->toBe(20.00)
        ->and((float) $movement->resulting_stock)->toBe(20.00)
        ->and($movement->type)->toBe('adjustment_in');
});

test('warehouse delete action halts if warehouse has active stock', function () {
    (new PermissionSeeder)->run();
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $warehouse = Warehouse::create(['name' => 'Bodega Con Stock', 'code' => 'BOD-STOCK-01', 'is_active' => true]);
    $category = Category::create(['name' => 'Cat W', 'slug' => 'cat-w']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'SKU-W-01',
        'name' => 'Producto W',
        'cost_price' => 1000,
        'sale_price' => 2000,
        'unit' => 'UND',
    ]);

    ProductStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'current_stock' => 15.00,
        'min_stock' => 5.00,
    ]);

    Livewire::actingAs($admin)
        ->test(WarehouseResource\Pages\ListWarehouses::class)
        ->callTableAction('delete', $warehouse)
        ->assertNotified('No se puede eliminar la bodega');

    expect(Warehouse::find($warehouse->id))->not->toBeNull();
});

test('customer has_consented attribute uses preloaded exists column', function () {
    $customer = Customer::create([
        'name' => 'Cliente Consent Test',
        'document_type' => 'CC',
        'document' => '123456789',
        'credit_limit' => 500000,
        'current_debt' => 0,
        'is_active' => true,
    ]);

    $loaded = CustomerResource::getEloquentQuery()->find($customer->id);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $consented = $loaded->has_consented;
    expect(count(DB::getQueryLog()))->toBe(0)
        ->and($consented)->toBeFalse();
    DB::disableQueryLog();
});

test('customer cannot be deleted if current debt is greater than zero', function () {
    (new PermissionSeeder)->run();
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $customer = Customer::create([
        'name' => 'Cliente Con Deuda',
        'document_type' => 'CC',
        'document' => '987654321',
        'credit_limit' => 1000000,
        'current_debt' => 250000,
        'is_active' => true,
    ]);

    expect(CustomerResource::canDelete($customer))->toBeFalse();

    Livewire::actingAs($admin)
        ->test(CustomerResource\Pages\ListCustomers::class)
        ->assertTableActionHidden('delete', $customer);

    expect(Customer::find($customer->id))->not->toBeNull();
});

test('customer without debt can be deleted by authorized admin', function () {
    (new PermissionSeeder)->run();
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    $customer = Customer::create([
        'name' => 'Cliente Sin Deuda',
        'document_type' => 'CC',
        'document' => '555666777',
        'credit_limit' => 1000000,
        'current_debt' => 0,
        'is_active' => true,
    ]);

    expect(CustomerResource::canDelete($customer))->toBeTrue();

    Livewire::actingAs($admin)
        ->test(CustomerResource\Pages\ListCustomers::class)
        ->callTableAction('delete', $customer);

    expect(Customer::find($customer->id))->toBeNull();
});

test('user resource excludes authenticated user from bulk deletion', function () {
    (new PermissionSeeder)->run();
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $otherUser = User::factory()->create(['name' => 'Otro Usuario']);
    $otherUser->assignRole('cashier');

    Livewire::actingAs($admin)
        ->test(UserResource\Pages\ListUsers::class)
        ->callTableBulkAction('delete', [$admin, $otherUser]);

    // Admin must still exist, otherUser must be deleted
    expect(User::find($admin->id))->not->toBeNull()
        ->and(User::find($otherUser->id))->toBeNull();
});

test('price list single default invariant unsets other default lists on save', function () {
    (new PermissionSeeder)->run();
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $detal = PriceList::create(['name' => 'Detal Test', 'is_default' => true]);

    Livewire::actingAs($admin)
        ->test(PriceListResource\Pages\CreatePriceList::class)
        ->fillForm([
            'name' => 'Nueva Lista Default',
            'is_default' => true,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect($detal->fresh()->is_default)->toBeFalse()
        ->and(PriceList::where('name', 'Nueva Lista Default')->first()->is_default)->toBeTrue();
});
