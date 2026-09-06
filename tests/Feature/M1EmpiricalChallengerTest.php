<?php

use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Filament\Resources\ProductResource\RelationManagers\StocksRelationManager;
use App\Models\Brand;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    (new PermissionSeeder)->run();
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('empirical challenge 1: stocks relation manager creates stock X without duplicating to 2X and exactly 1 kardex movement', function () {
    $warehouse = Warehouse::create([
        'name' => 'Bodega Alpha',
        'code' => 'BOD-ALP-01',
        'is_active' => true,
    ]);

    $category = Category::create(['name' => 'Herramientas', 'slug' => 'herramientas']);
    $brand = Brand::create(['name' => 'DeWalt', 'slug' => 'dewalt']);

    $product = Product::create([
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'sku' => 'TALADRO-20V',
        'name' => 'Taladro Percutor 20V Max',
        'cost_price' => 350000.00,
        'sale_price' => 480000.00,
        'unit' => 'UND',
    ]);

    $initialStock = 17.00;

    Livewire::actingAs($this->admin)
        ->test(StocksRelationManager::class, [
            'ownerRecord' => $product,
            'pageClass' => EditProduct::class,
        ])
        ->callTableAction('create', data: [
            'warehouse_id' => $warehouse->id,
            'current_stock' => $initialStock,
            'min_stock' => 3.00,
            'max_stock' => 50.00,
        ])
        ->assertHasNoTableActionErrors();

    // Verification 1: Exactly 1 ProductStock record
    $stocks = ProductStock::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->get();
    expect($stocks)->toHaveCount(1);

    $stock = $stocks->first();
    // Verification 2: current_stock is strictly X (17.00), NOT 2X (34.00)
    expect((float) $stock->current_stock)->toBe(17.00);

    // Verification 3: Exactly 1 InventoryMovement record generated
    $movements = InventoryMovement::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->get();
    expect($movements)->toHaveCount(1);

    $movement = $movements->first();
    expect($movement->type)->toBe('adjustment_in')
        ->and((float) $movement->quantity)->toBe(17.00)
        ->and((float) $movement->previous_stock)->toBe(0.00)
        ->and((float) $movement->resulting_stock)->toBe(17.00)
        ->and($movement->user_id)->toBe($this->admin->id)
        ->and($movement->reference_type)->toBe(ProductStock::class)
        ->and($movement->reference_id)->toBe($stock->id);
});

test('empirical challenge 2: product query performance on 10 products with category, brand and total_stock has zero N+1', function () {
    $categoryA = Category::create(['name' => 'Cat Performance A', 'slug' => 'cat-perf-a']);
    $categoryB = Category::create(['name' => 'Cat Performance B', 'slug' => 'cat-perf-b']);
    $brandA = Brand::create(['name' => 'Brand Perf A', 'slug' => 'brand-perf-a']);
    $brandB = Brand::create(['name' => 'Brand Perf B', 'slug' => 'brand-perf-b']);

    $warehouses = collect([
        Warehouse::create(['name' => 'WH 1', 'code' => 'WH-P1', 'is_active' => true]),
        Warehouse::create(['name' => 'WH 2', 'code' => 'WH-P2', 'is_active' => true]),
        Warehouse::create(['name' => 'WH 3', 'code' => 'WH-P3', 'is_active' => true]),
    ]);

    // Create 10 distinct products with stock distributed across 3 warehouses
    for ($i = 1; $i <= 10; $i++) {
        $product = Product::create([
            'category_id' => $i % 2 === 0 ? $categoryA->id : $categoryB->id,
            'brand_id' => $i % 2 === 0 ? $brandA->id : $brandB->id,
            'sku' => "SKU-PERF-{$i}",
            'name' => "Producto Rendimiento {$i}",
            'cost_price' => 10000.00 * $i,
            'sale_price' => 15000.00 * $i,
            'unit' => 'UND',
        ]);

        foreach ($warehouses as $index => $wh) {
            ProductStock::create([
                'product_id' => $product->id,
                'warehouse_id' => $wh->id,
                'current_stock' => 10.00 * ($index + 1),
                'min_stock' => 5.00,
            ]);
        }
    }

    // Now execute query using ProductResource::getEloquentQuery() exactly as Filament does
    DB::flushQueryLog();
    DB::enableQueryLog();

    $products = ProductResource::getEloquentQuery()
        ->where('sku', 'like', 'SKU-PERF-%')
        ->take(10)
        ->get();

    // Iterate through all 10 products and access category, brand, total_stock, profit_margin
    $accessedData = [];
    foreach ($products as $prod) {
        $accessedData[] = [
            'sku' => $prod->sku,
            'category' => $prod->category?->name,
            'brand' => $prod->brand?->name,
            'total_stock' => $prod->total_stock,
            'profit_margin' => $prod->profit_margin,
        ];
    }

    $loggedQueries = DB::getQueryLog();
    DB::disableQueryLog();

    // Verify data integrity: each product has 3 warehouses with 10 + 20 + 30 = 60 stock
    expect($accessedData)->toHaveCount(10);
    foreach ($accessedData as $item) {
        expect($item['total_stock'])->toBe(60.0)
            ->and($item['category'])->not->toBeNull()
            ->and($item['brand'])->not->toBeNull()
            ->and($item['profit_margin'])->toBe(50.0);
    }

    // Crucial Assertion: Zero N+1 queries.
    // Query 1: select products.*, (select sum(current_stock) ...) as total_stock from products
    // Query 2: select * from categories where id in (...)
    // Query 3: select * from brands where id in (...)
    // Total query count must be EXACTLY 3, NOT 1 + 10 + 10 + 10 = 31 queries!
    expect(count($loggedQueries))->toBe(3);
});

test('empirical challenge 2b: product resource table rendering in Livewire maintains zero N+1 queries', function () {
    $category = Category::create(['name' => 'Cat Table Perf', 'slug' => 'cat-table-perf']);
    $brand = Brand::create(['name' => 'Brand Table Perf', 'slug' => 'brand-table-perf']);
    $warehouse = Warehouse::create(['name' => 'WH Table', 'code' => 'WH-TBL', 'is_active' => true]);

    for ($i = 1; $i <= 10; $i++) {
        $p = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => "SKU-TBL-{$i}",
            'name' => "Producto Tabla {$i}",
            'cost_price' => 20000.00,
            'sale_price' => 30000.00,
            'unit' => 'UND',
        ]);
        ProductStock::create([
            'product_id' => $p->id,
            'warehouse_id' => $warehouse->id,
            'current_stock' => 50.00,
            'min_stock' => 10.00,
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::actingAs($this->admin)
        ->test(ListProducts::class)
        ->assertSuccessful();

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Verify eager loading in single queries
    $categoryQueries = array_filter($queries, fn ($q) => str_contains($q['query'], 'categories'));
    $brandQueries = array_filter($queries, fn ($q) => str_contains($q['query'], 'brands'));

    expect(count($categoryQueries))->toBeLessThanOrEqual(2)
        ->and(count($brandQueries))->toBeLessThanOrEqual(2);
});

test('empirical challenge 3a: edge case 0 initial stock creates stock record with 0 and zero kardex movements', function () {
    $warehouse = Warehouse::create([
        'name' => 'Bodega Cero Stock',
        'code' => 'BOD-ZERO-01',
        'is_active' => true,
    ]);

    $category = Category::create(['name' => 'Electricidad', 'slug' => 'electricidad']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'CABLE-CERO',
        'name' => 'Cable Coaxial RG6 Bobina',
        'cost_price' => 80000.00,
        'sale_price' => 120000.00,
        'unit' => 'MTR',
    ]);

    Livewire::actingAs($this->admin)
        ->test(StocksRelationManager::class, [
            'ownerRecord' => $product,
            'pageClass' => EditProduct::class,
        ])
        ->callTableAction('create', data: [
            'warehouse_id' => $warehouse->id,
            'current_stock' => 0.00,
            'min_stock' => 10.00,
            'max_stock' => 100.00,
        ])
        ->assertHasNoTableActionErrors();

    $stock = ProductStock::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->firstOrFail();
    expect((float) $stock->current_stock)->toBe(0.00);

    // Initial stock was 0, so NO Kardex adjustment should be generated
    $movements = InventoryMovement::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->get();
    expect($movements)->toHaveCount(0);
});

test('empirical challenge 3b: edge case float quantities with decimals are accurately calculated and audited', function () {
    $warehouse = Warehouse::create([
        'name' => 'Bodega Granel',
        'code' => 'BOD-GRANEL-01',
        'is_active' => true,
    ]);

    $category = Category::create(['name' => 'Materiales Granel', 'slug' => 'materiales-granel']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'PUNTILLA-2PULG',
        'name' => 'Puntilla con Cabeza 2 Pulgadas Granel',
        'cost_price' => 4500.00,
        'sale_price' => 7000.00,
        'unit' => 'KG',
    ]);

    $initialStock = 12.75; // 12.75 Kilograms

    $component = Livewire::actingAs($this->admin)
        ->test(StocksRelationManager::class, [
            'ownerRecord' => $product,
            'pageClass' => EditProduct::class,
        ])
        ->callTableAction('create', data: [
            'warehouse_id' => $warehouse->id,
            'current_stock' => $initialStock,
            'min_stock' => 2.50,
            'max_stock' => 100.00,
        ])
        ->assertHasNoTableActionErrors();

    $stock = ProductStock::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->firstOrFail();
    expect((float) $stock->current_stock)->toBe(12.75);

    // Verify Kardex for float initial stock
    $kardex1 = InventoryMovement::where('product_id', $product->id)->firstOrFail();
    expect((float) $kardex1->quantity)->toBe(12.75)
        ->and((float) $kardex1->previous_stock)->toBe(0.00)
        ->and((float) $kardex1->resulting_stock)->toBe(12.75);

    // Now test adjust_stock action with float addition (+0.25 kg)
    $component->callTableAction('adjust_stock', $stock, data: [
        'type' => 'adjustment_in',
        'quantity' => 0.25,
        'notes' => 'Ajuste de pesado decimal',
    ])->assertHasNoTableActionErrors();

    $stock->refresh();
    expect((float) $stock->current_stock)->toBe(13.00);

    // Now test adjust_stock action with float subtraction (-3.45 kg)
    $component->callTableAction('adjust_stock', $stock, data: [
        'type' => 'adjustment_out',
        'quantity' => 3.45,
        'notes' => 'Merma por fraccionamiento',
    ])->assertHasNoTableActionErrors();

    $stock->refresh();
    expect((float) $stock->current_stock)->toBe(9.55);

    $allMovements = InventoryMovement::where('product_id', $product->id)->orderBy('id')->get();
    expect($allMovements)->toHaveCount(3);

    expect((float) $allMovements[1]->quantity)->toBe(0.25)
        ->and((float) $allMovements[1]->previous_stock)->toBe(12.75)
        ->and((float) $allMovements[1]->resulting_stock)->toBe(13.00)
        ->and((float) $allMovements[2]->quantity)->toBe(3.45)
        ->and((float) $allMovements[2]->previous_stock)->toBe(13.00)
        ->and((float) $allMovements[2]->resulting_stock)->toBe(9.55);
});

test('empirical challenge 3c: edge case multiple warehouses maintain strict isolation, correct totals, and dropdown exclusion', function () {
    $warehouses = collect([
        Warehouse::create(['name' => 'Bodega Centro', 'code' => 'WH-CTR', 'is_active' => true]),
        Warehouse::create(['name' => 'Bodega Norte', 'code' => 'WH-NTE', 'is_active' => true]),
        Warehouse::create(['name' => 'Bodega Sur', 'code' => 'WH-SUR', 'is_active' => true]),
    ]);

    $category = Category::create(['name' => 'Plomería', 'slug' => 'plomeria']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'VALVULA-PASO-1/2',
        'name' => 'Válvula de Paso 1/2 Pulgada',
        'cost_price' => 15000.00,
        'sale_price' => 24000.00,
        'unit' => 'UND',
    ]);

    $component = Livewire::actingAs($this->admin)
        ->test(StocksRelationManager::class, [
            'ownerRecord' => $product,
            'pageClass' => EditProduct::class,
        ]);

    // Assign to Warehouse 1 (Stock: 10)
    $component->callTableAction('create', data: [
        'warehouse_id' => $warehouses[0]->id,
        'current_stock' => 10.00,
        'min_stock' => 2.00,
    ])->assertHasNoTableActionErrors();

    // Assign to Warehouse 2 (Stock: 25.5)
    $component->callTableAction('create', data: [
        'warehouse_id' => $warehouses[1]->id,
        'current_stock' => 25.50,
        'min_stock' => 5.00,
    ])->assertHasNoTableActionErrors();

    // Assign to Warehouse 3 (Stock: 5.25)
    $component->callTableAction('create', data: [
        'warehouse_id' => $warehouses[2]->id,
        'current_stock' => 5.25,
        'min_stock' => 1.00,
    ])->assertHasNoTableActionErrors();

    // Verify all 3 warehouses have independent stocks
    $stock1 = ProductStock::where('product_id', $product->id)->where('warehouse_id', $warehouses[0]->id)->first();
    $stock2 = ProductStock::where('product_id', $product->id)->where('warehouse_id', $warehouses[1]->id)->first();
    $stock3 = ProductStock::where('product_id', $product->id)->where('warehouse_id', $warehouses[2]->id)->first();

    expect((float) $stock1->current_stock)->toBe(10.00)
        ->and((float) $stock2->current_stock)->toBe(25.50)
        ->and((float) $stock3->current_stock)->toBe(5.25);

    // Verify total_stock via Eloquent accessor without withSum
    $freshProduct = Product::find($product->id);
    expect($freshProduct->total_stock)->toBe(40.75);

    // Verify total_stock via withSum
    $withSumProduct = Product::withSum('stocks as total_stock', 'current_stock')->find($product->id);
    expect($withSumProduct->total_stock)->toBe(40.75);

    // Verify create action visibility: now that all 3 warehouses are assigned, the CreateAction header button must be hidden
    $component->assertTableActionHidden('create');
});

test('empirical challenge 3d: adjustment_out exceeding current stock clamps safely to zero without negative stock', function () {
    $warehouse = Warehouse::create([
        'name' => 'Bodega Clamping',
        'code' => 'BOD-CLMP-01',
        'is_active' => true,
    ]);

    $category = Category::create(['name' => 'General', 'slug' => 'general']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'PROD-CLAMP',
        'name' => 'Producto Prueba Clamping',
        'cost_price' => 1000.00,
        'sale_price' => 2000.00,
        'unit' => 'UND',
    ]);

    $stock = ProductStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'current_stock' => 5.00,
        'min_stock' => 1.00,
    ]);

    Livewire::actingAs($this->admin)
        ->test(StocksRelationManager::class, [
            'ownerRecord' => $product,
            'pageClass' => EditProduct::class,
        ])
        ->callTableAction('adjust_stock', $stock, data: [
            'type' => 'adjustment_out',
            'quantity' => 10.00, // Greater than available 5.00
            'notes' => 'Ajuste excesivo por pérdida total',
        ])
        ->assertHasNoTableActionErrors();

    $stock->refresh();
    expect((float) $stock->current_stock)->toBe(0.00);

    $movement = InventoryMovement::where('product_id', $product->id)->latest('id')->first();
    expect((float) $movement->previous_stock)->toBe(5.00)
        ->and((float) $movement->resulting_stock)->toBe(0.00)
        ->and((float) $movement->quantity)->toBe(10.00);
});

test('empirical challenge 3e: direct edit of product stock record does not mutate current_stock (audit bypass guard)', function () {
    $warehouse = Warehouse::create([
        'name' => 'Bodega Edit Guard',
        'code' => 'BOD-EDT-01',
        'is_active' => true,
    ]);

    $category = Category::create(['name' => 'Cerrajería', 'slug' => 'cerrajeria']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'CANDADO-50',
        'name' => 'Candado de Seguridad 50mm',
        'cost_price' => 25000.00,
        'sale_price' => 38000.00,
        'unit' => 'UND',
    ]);

    $stock = ProductStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'current_stock' => 15.00,
        'min_stock' => 3.00,
    ]);

    Livewire::actingAs($this->admin)
        ->test(StocksRelationManager::class, [
            'ownerRecord' => $product,
            'pageClass' => EditProduct::class,
        ])
        ->callTableAction('edit', $stock, data: [
            'min_stock' => 8.00,
            'max_stock' => 60.00,
        ])
        ->assertHasNoTableActionErrors();

    $stock->refresh();
    expect((float) $stock->current_stock)->toBe(15.00)
        ->and((float) $stock->min_stock)->toBe(8.00)
        ->and((float) $stock->max_stock)->toBe(60.00);
});

test('empirical challenge 4: form validation prevents negative numbers on stock thresholds and adjustments', function () {
    $warehouse = Warehouse::create([
        'name' => 'Bodega Val Test',
        'code' => 'BOD-VAL-01',
        'is_active' => true,
    ]);

    $category = Category::create(['name' => 'Pinturas', 'slug' => 'pinturas']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'PIN-ESMALTE-GL',
        'name' => 'Esmalte Sintético Blanco Galón',
        'cost_price' => 45000.00,
        'sale_price' => 68000.00,
        'unit' => 'GLN',
    ]);

    // Attempt negative initial stock: must trigger validation error
    Livewire::actingAs($this->admin)
        ->test(StocksRelationManager::class, [
            'ownerRecord' => $product,
            'pageClass' => EditProduct::class,
        ])
        ->callTableAction('create', data: [
            'warehouse_id' => $warehouse->id,
            'current_stock' => -10.00,
            'min_stock' => 5.00,
        ])
        ->assertHasTableActionErrors(['current_stock']);
});
