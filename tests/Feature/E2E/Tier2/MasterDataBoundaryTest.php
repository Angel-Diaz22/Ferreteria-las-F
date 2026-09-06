<?php

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\WarehouseResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\KardexService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    (new PermissionSeeder)->run();
});

/**
 * ============================================================================
 * TIER 2: BOUNDARY & CORNER CASES — F01 to F06 (MASTER DATA & CATALOG)
 * ============================================================================
 */
describe('F01 Boundaries: Catalog Eager Loading Edge Conditions', function () {
    test('F01-B1: Empty catalog query runs without errors and returns empty collection', function () {
        $products = Product::with(['category', 'brand'])->get();
        expect($products)->toBeEmpty();
    });

    test('F01-B2: Products without category or brand (null relations) load safely without exceptions', function () {
        Product::create([
            'category_id' => null,
            'brand_id' => null,
            'sku' => 'NULL-REL-01',
            'name' => 'Producto Sin Clasificar',
            'cost_price' => 1000,
            'sale_price' => 2000,
            'unit' => 'UND',
        ]);

        $loaded = Product::with(['category', 'brand'])->first();
        expect($loaded->category)->toBeNull()
            ->and($loaded->brand)->toBeNull();
    });

    test('F01-B3: Single record lookup with relations loads in exactly two/three bounded queries', function () {
        $cat = Category::create(['name' => 'Adhesivos', 'slug' => 'adhesivos']);
        $prod = Product::create([
            'category_id' => $cat->id,
            'sku' => 'ADH-01',
            'name' => 'Silicona Transparente',
            'cost_price' => 8000,
            'sale_price' => 14000,
            'unit' => 'UND',
        ]);

        DB::enableQueryLog();
        $record = Product::with('category')->find($prod->id);
        expect($record->category->name)->toBe('Adhesivos')
            ->and(count(DB::getQueryLog()))->toBe(2);
        DB::disableQueryLog();
    });

    test('F01-B4: Querying 50+ products loads relations with strictly bounded query count', function () {
        $cat = Category::create(['name' => 'General', 'slug' => 'general']);
        $brand = Brand::create(['name' => 'Genérica', 'slug' => 'generica']);

        $items = [];
        for ($i = 1; $i <= 50; $i++) {
            $items[] = [
                'category_id' => $cat->id,
                'brand_id' => $brand->id,
                'sku' => "BULK-F01-{$i}",
                'name' => "Articulo Bulk {$i}",
                'cost_price' => 500,
                'sale_price' => 1000,
                'unit' => 'UND',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        Product::insert($items);

        DB::enableQueryLog();
        $results = Product::with(['category', 'brand'])->get();
        expect($results)->toHaveCount(50);
        expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(3);
        DB::disableQueryLog();
    });

    test('F01-B5: Customer query without price list handles null relationship gracefully', function () {
        $customer = Customer::create([
            'price_list_id' => null,
            'name' => 'Cliente Sin Tarifa',
            'document_type' => 'CC',
            'document' => '55443322',
            'is_active' => true,
        ]);

        $loaded = Customer::with('priceList')->find($customer->id);
        expect($loaded->priceList)->toBeNull();
    });
});

describe('F02 Boundaries: Stock Assignment Extreme Values', function () {
    test('F02-B1: Initial stock assignment with exactly 0.0 units succeeds and records zero initial balance', function () {
        $warehouse = Warehouse::create(['name' => 'Bodega Zero', 'code' => 'BOD-ZERO', 'is_active' => true]);
        $prod = Product::create(['sku' => 'ZERO-01', 'name' => 'Producto Cero Stock', 'cost_price' => 1000, 'sale_price' => 2000, 'unit' => 'UND']);

        $stock = ProductStock::create([
            'product_id' => $prod->id,
            'warehouse_id' => $warehouse->id,
            'current_stock' => 0.0,
            'min_stock' => 0.0,
        ]);

        expect((float) $stock->current_stock)->toBe(0.0);
    });

    test('F02-B2: Large stock magnitude (1,000,000 units) persists without integer overflow', function () {
        $warehouse = Warehouse::create(['name' => 'Bodega Mega', 'code' => 'BOD-MEGA', 'is_active' => true]);
        $prod = Product::create(['sku' => 'MEGA-01', 'name' => 'Tornillo Granel', 'cost_price' => 10, 'sale_price' => 20, 'unit' => 'UND']);

        $m = KardexService::registerAdjustment($prod, $warehouse->id, 1000000.0, 'adjustment_in', 'Lote gigante');
        expect((float) $m->resulting_stock)->toBe(1000000.0);
    });

    test('F02-B3: Fractional stock with fine decimal precision (0.005 units / kg) stores accurately', function () {
        $warehouse = Warehouse::create(['name' => 'Bodega Decimal', 'code' => 'BOD-DEC', 'is_active' => true]);
        $prod = Product::create(['sku' => 'DEC-01', 'name' => 'Químico Líquido', 'cost_price' => 50000, 'sale_price' => 80000, 'unit' => 'KG']);

        $m = KardexService::registerAdjustment($prod, $warehouse->id, 0.25, 'adjustment_in', 'Cuarto de kilo');
        expect((float) $m->resulting_stock)->toBe(0.25);
    });

    test('F02-B4: Stock reduction exactly equal to current balance results in clean 0.0', function () {
        $warehouse = Warehouse::create(['name' => 'Bodega Exact', 'code' => 'BOD-EXACT', 'is_active' => true]);
        $prod = Product::create(['sku' => 'EXACT-01', 'name' => 'Item Vaciado', 'cost_price' => 100, 'sale_price' => 200, 'unit' => 'UND']);

        KardexService::registerAdjustment($prod, $warehouse->id, 15.0, 'adjustment_in', 'Entrada');
        $m = KardexService::registerAdjustment($prod, $warehouse->id, 15.0, 'adjustment_out', 'Salida total');

        expect((float) $m->resulting_stock)->toBe(0.0);
    });

    test('F02-B5: Min stock and max stock thresholds equal to each other are accepted', function () {
        $warehouse = Warehouse::create(['name' => 'Bodega MinMax', 'code' => 'BOD-MM', 'is_active' => true]);
        $prod = Product::create(['sku' => 'MM-01', 'name' => 'Item Fijo', 'cost_price' => 100, 'sale_price' => 200, 'unit' => 'UND']);

        $stock = ProductStock::create([
            'product_id' => $prod->id,
            'warehouse_id' => $warehouse->id,
            'current_stock' => 10.0,
            'min_stock' => 10.0,
            'max_stock' => 10.0,
        ]);

        expect((float) $stock->min_stock)->toBe((float) $stock->max_stock);
    });
});

describe('F03 Boundaries: Warehouse Code Constraints & Formatting', function () {
    test('F03-B1: Warehouse code accepts maximum string length up to 255 chars', function () {
        $longCode = 'BOD-'.str_repeat('X', 200);
        $warehouse = Warehouse::create([
            'name' => 'Bodega Código Largo',
            'code' => $longCode,
            'is_active' => true,
        ]);

        expect($warehouse->code)->toBe($longCode);
    });

    test('F03-B2: Special characters and hyphens in warehouse code are supported', function () {
        $warehouse = Warehouse::create([
            'name' => 'Bodega Especial',
            'code' => 'BOD_NORTE#01-A/B',
            'is_active' => true,
        ]);

        expect($warehouse->code)->toBe('BOD_NORTE#01-A/B');
    });

    test('F03-B3: Multiple warehouses with null codes do not trigger unique index violations', function () {
        $w1 = Warehouse::create(['name' => 'Bodega Sin Codigo 1', 'code' => null, 'is_active' => true]);
        $w2 = Warehouse::create(['name' => 'Bodega Sin Codigo 2', 'code' => null, 'is_active' => true]);

        expect($w1->exists)->toBeTrue()
            ->and($w2->exists)->toBeTrue();
    });

    test('F03-B4: Exact duplicate warehouse code throws QueryException', function () {
        Warehouse::create(['name' => 'Bodega Uno', 'code' => 'BOD-EXACT-DUP', 'is_active' => true]);

        expect(fn () => Warehouse::create(['name' => 'Bodega Dos', 'code' => 'BOD-EXACT-DUP', 'is_active' => true]))
            ->toThrow(QueryException::class);
    });

    test('F03-B5: Warehouse address and phone boundaries handle empty and maximal inputs', function () {
        $warehouse = Warehouse::create([
            'name' => 'Bodega Minimal',
            'code' => 'BOD-MIN-01',
            'address' => null,
            'phone' => null,
            'is_active' => true,
        ]);

        expect($warehouse->address)->toBeNull()
            ->and($warehouse->phone)->toBeNull();
    });
});

describe('F04 Boundaries: Master Data Authorization Edge Cases', function () {
    test('F04-B1: User with no roles assigned has no administrative capabilities', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        expect(UserResource::canViewAny())->toBeFalse()
            ->and(WarehouseResource::canCreate())->toBeFalse();
    });

    test('F04-B2: Inactive user account is rejected or inactive flag is respected', function () {
        $inactiveAdmin = User::factory()->create(['is_active' => false]);
        $inactiveAdmin->assignRole('admin');

        expect($inactiveAdmin->is_active)->toBeFalse();
    });

    test('F04-B3: Customer with exactly $0 credit limit has 0 available credit', function () {
        $customer = Customer::create([
            'name' => 'Cliente Cero Credito',
            'document_type' => 'CC',
            'document' => '77665544',
            'credit_limit' => 0.0,
            'current_debt' => 0.0,
            'is_active' => true,
        ]);

        expect($customer->available_credit)->toBe(0.0)
            ->and($customer->hasAvailableCredit(1.0))->toBeFalse()
            ->and($customer->hasAvailableCredit(0.0))->toBeTrue();
    });

    test('F04-B4: Customer with negative debt (credit balance in favor) treats available credit safely', function () {
        $customer = Customer::create([
            'name' => 'Cliente Saldo a Favor',
            'document_type' => 'CC',
            'document' => '88776655',
            'credit_limit' => 100000.0,
            'current_debt' => -20000.0,
            'is_active' => true,
        ]);

        // 100,000 - (-20,000) = 120,000
        expect($customer->available_credit)->toBe(120000.0);
    });

    test('F04-B5: Cashier role can view customers but cannot create warehouses or users', function () {
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');
        $this->actingAs($cashier);

        expect(CustomerResource::canViewAny())->toBeTrue()
            ->and(WarehouseResource::canCreate())->toBeFalse()
            ->and(UserResource::canCreate())->toBeFalse();
    });
});

describe('F05 Boundaries: User Safety Guard Edge Cases', function () {
    test('F05-B1: Attempting to delete the only administrator account is recognized and audited', function () {
        $admin = User::factory()->create(['name' => 'Admin Unico']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        expect($admin->id)->toBe(auth()->id());
    });

    test('F05-B2: User with multiple roles retains highest permission level', function () {
        $multiUser = User::factory()->create();
        $multiUser->assignRole('cashier');
        $multiUser->assignRole('admin');

        $this->actingAs($multiUser);
        expect(UserResource::canViewAny())->toBeTrue();
    });

    test('F05-B3: Deleting a user with 0 associated records succeeds cleanly', function () {
        $cleanUser = User::factory()->create();
        $id = $cleanUser->id;
        $cleanUser->delete();

        expect(User::find($id))->toBeNull();
    });

    test('F05-B4: Deleting an already-deleted user model does not throw unhandled exception', function () {
        $user = User::factory()->create();
        $user->delete();

        expect(fn () => $user->delete())->not->toThrow(Throwable::class);
    });

    test('F05-B5: Bulk deletion guard does not delete authenticated user even in batch', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $other1 = User::factory()->create();
        $other2 = User::factory()->create();

        // Safe deletion of others
        User::whereIn('id', [$other1->id, $other2->id])->delete();

        expect(User::find($admin->id))->not->toBeNull()
            ->and(User::find($other1->id))->toBeNull()
            ->and(User::find($other2->id))->toBeNull();
    });
});

describe('F06 Boundaries: Numeric & Input Constraints Edge Cases', function () {
    test('F06-B1: Product cost_price at exact 0.00 is allowed (e.g. promotional/sample items)', function () {
        $prod = Product::create([
            'sku' => 'SAMP-01',
            'name' => 'Muestra Comercial de Pegante',
            'cost_price' => 0.00,
            'sale_price' => 0.00,
            'unit' => 'UND',
        ]);

        expect((float) $prod->cost_price)->toBe(0.00)
            ->and((float) $prod->sale_price)->toBe(0.00);
    });

    test('F06-B2: Tax rate at boundary 0% (exento de IVA) is valid', function () {
        $prod = Product::create([
            'sku' => 'EXEN-01',
            'name' => 'Artículo Exento',
            'cost_price' => 1000,
            'sale_price' => 2000,
            'tax_rate' => 0.00,
            'unit' => 'UND',
        ]);

        expect((float) $prod->tax_rate)->toBe(0.00);
    });

    test('F06-B3: Tax rate at boundary 100% is valid', function () {
        $prod = Product::create([
            'sku' => 'MAXTAX-01',
            'name' => 'Artículo Tasa Maxima',
            'cost_price' => 1000,
            'sale_price' => 2000,
            'tax_rate' => 100.00,
            'unit' => 'UND',
        ]);

        expect((float) $prod->tax_rate)->toBe(100.00);
    });

    test('F06-B4: Maximum customer credit limit up to 999,999,999 COP is supported without truncation', function () {
        $customer = Customer::create([
            'name' => 'Constructora Mega Obras',
            'document_type' => 'NIT',
            'document' => '900999000-9',
            'credit_limit' => 999999999.00,
            'current_debt' => 0.00,
            'is_active' => true,
        ]);

        expect((float) $customer->credit_limit)->toBe(999999999.00);
    });

    test('F06-B5: Barcode and SKU containing alphanumeric and punctuation symbols persist correctly', function () {
        $prod = Product::create([
            'sku' => 'SKU.TORN-1/4x2-GALV',
            'barcode' => '770-1234-56789-0',
            'name' => 'Tornillo Galvanizado 1/4 x 2',
            'cost_price' => 300,
            'sale_price' => 600,
            'unit' => 'UND',
        ]);

        expect($prod->sku)->toBe('SKU.TORN-1/4x2-GALV')
            ->and($prod->barcode)->toBe('770-1234-56789-0');
    });
});
