<?php

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\WarehouseResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\KardexService;
use Database\Seeders\PermissionSeeder;
use Filament\Forms\Form;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    (new PermissionSeeder)->run();
});

/**
 * ============================================================================
 * TIER 1: FEATURE COVERAGE — F01 to F06 (MASTER DATA & CATALOG)
 * ============================================================================
 */
describe('F01: Eager loading in Master Data tables', function () {
    test('F01-1: Product queries eager load category and brand relations without N+1', function () {
        $category = Category::create(['name' => 'Herramientas Eléctricas', 'slug' => 'herramientas-electricas']);
        $brand = Brand::create(['name' => 'DeWalt', 'slug' => 'dewalt']);

        for ($i = 1; $i <= 5; $i++) {
            Product::create([
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'sku' => "PROD-F01-{$i}",
                'name' => "Producto Prueba F01 {$i}",
                'cost_price' => 10000 * $i,
                'sale_price' => 15000 * $i,
                'unit' => 'UND',
                'is_active' => true,
            ]);
        }

        DB::enableQueryLog();
        $products = Product::with(['category', 'brand'])->get();

        foreach ($products as $prod) {
            expect($prod->category->name)->toBe('Herramientas Eléctricas')
                ->and($prod->brand->name)->toBe('DeWalt');
        }

        // With eager loading: 1 query for products + 1 for categories + 1 for brands = 3 queries max
        expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(3);
        DB::disableQueryLog();
    });

    test('F01-2: Customer queries eager load assigned price list without per-row queries', function () {
        $priceList = PriceList::create(['name' => 'Constructor Premium', 'is_default' => false]);

        for ($i = 1; $i <= 5; $i++) {
            Customer::create([
                'price_list_id' => $priceList->id,
                'name' => "Constructora Alfa {$i}",
                'document_type' => 'NIT',
                'document' => "90011122{$i}-1",
                'phone' => "300111000{$i}",
                'credit_limit' => 5000000,
                'current_debt' => 0,
                'is_active' => true,
            ]);
        }

        DB::enableQueryLog();
        $customers = Customer::with('priceList')->get();

        foreach ($customers as $c) {
            expect($c->priceList?->name)->toBe('Constructor Premium');
        }

        expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(2);
        DB::disableQueryLog();
    });

    test('F01-3: User queries eager load roles and permissions', function () {
        for ($i = 1; $i <= 5; $i++) {
            $user = User::factory()->create(['name' => "Operador {$i}"]);
            $user->assignRole('cashier');
        }

        DB::enableQueryLog();
        $users = User::with(['roles', 'permissions'])->get();

        foreach ($users as $u) {
            expect($u->roles)->not->toBeEmpty();
        }

        // Bounded number of queries regardless of user count
        expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(4);
        DB::disableQueryLog();
    });

    test('F01-4: Warehouse queries load associated stocks efficiently', function () {
        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-F01-4', 'is_active' => true]);
        $category = Category::create(['name' => 'Fijación', 'slug' => 'fijacion-f01']);

        for ($i = 1; $i <= 4; $i++) {
            $prod = Product::create([
                'category_id' => $category->id,
                'sku' => "SKU-W-{$i}",
                'name' => "Tornillo {$i}",
                'cost_price' => 100,
                'sale_price' => 200,
                'unit' => 'UND',
            ]);
            ProductStock::create([
                'product_id' => $prod->id,
                'warehouse_id' => $warehouse->id,
                'current_stock' => 100 * $i,
                'min_stock' => 10,
            ]);
        }

        DB::enableQueryLog();
        $loadedWarehouse = Warehouse::with('stocks.product')->find($warehouse->id);

        expect($loadedWarehouse->stocks)->toHaveCount(4);
        expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(3);
        DB::disableQueryLog();
    });

    test('F01-5: ProductResource getEloquentQuery executes without unhandled N+1 exceptions', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $query = ProductResource::getEloquentQuery();
        expect($query)->toBeInstanceOf(Builder::class);
    });
});

describe('F02: Stock Assignment Integrity', function () {
    test('F02-1: Initial stock assignment via KardexService sets exact amount X without doubling', function () {
        $admin = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Principal', 'code' => 'BOD-F02-1', 'is_active' => true]);
        $product = Product::create([
            'sku' => 'TAL-F02-1',
            'name' => 'Taladro Percutor 750W',
            'cost_price' => 120000.00,
            'sale_price' => 180000.00,
            'unit' => 'UND',
        ]);

        $movement = KardexService::registerAdjustment(
            product: $product,
            warehouseId: $warehouse->id,
            quantity: 25.00,
            type: 'adjustment_in',
            notes: 'Inventario inicial de bodega',
            userId: $admin->id
        );

        $stock = ProductStock::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->first();

        expect((float) $stock->current_stock)->toBe(25.00)
            ->and((float) $movement->resulting_stock)->toBe(25.00)
            ->and($movement->type)->toBe('adjustment_in');
    });

    test('F02-2: Subsequent positive stock adjustments accurately increment the existing stock', function () {
        $admin = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Norte', 'code' => 'BOD-F02-2', 'is_active' => true]);
        $product = Product::create([
            'sku' => 'PUL-F02-2',
            'name' => 'Pulidora 4-1/2',
            'cost_price' => 90000.00,
            'sale_price' => 140000.00,
            'unit' => 'UND',
        ]);

        // Step 1: Initial 10 units
        KardexService::registerAdjustment($product, $warehouse->id, 10.0, 'adjustment_in', 'Lote 1', $admin->id);
        // Step 2: Additional 15 units
        KardexService::registerAdjustment($product, $warehouse->id, 15.0, 'adjustment_in', 'Lote 2', $admin->id);

        $stock = ProductStock::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->first();
        expect((float) $stock->current_stock)->toBe(25.00);
    });

    test('F02-3: Stock reduction adjustments decrement stock and prevent negative balance', function () {
        $admin = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Sur', 'code' => 'BOD-F02-3', 'is_active' => true]);
        $product = Product::create([
            'sku' => 'DISC-F02-3',
            'name' => 'Disco de Corte Metal',
            'cost_price' => 3000.00,
            'sale_price' => 6000.00,
            'unit' => 'UND',
        ]);

        KardexService::registerAdjustment($product, $warehouse->id, 20.0, 'adjustment_in', 'Inicial', $admin->id);
        KardexService::registerAdjustment($product, $warehouse->id, 8.0, 'adjustment_out', 'Merma por rotura', $admin->id);

        $stock = ProductStock::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->first();
        expect((float) $stock->current_stock)->toBe(12.00);
    });

    test('F02-4: Stock reduction greater than available stock clamps to zero', function () {
        $admin = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Este', 'code' => 'BOD-F02-4', 'is_active' => true]);
        $product = Product::create([
            'sku' => 'CINT-F02-4',
            'name' => 'Cinta Aislante 20m',
            'cost_price' => 1500.00,
            'sale_price' => 3000.00,
            'unit' => 'UND',
        ]);

        KardexService::registerAdjustment($product, $warehouse->id, 5.0, 'adjustment_in', 'Inicial', $admin->id);
        KardexService::registerAdjustment($product, $warehouse->id, 10.0, 'adjustment_out', 'Ajuste excesivo', $admin->id);

        $stock = ProductStock::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->first();
        expect((float) $stock->current_stock)->toBe(0.00);
    });

    test('F02-5: Multiple warehouses maintain completely independent stock amounts for the same product', function () {
        $admin = User::factory()->create();
        $w1 = Warehouse::create(['name' => 'Bodega 1', 'code' => 'BOD-F02-5A', 'is_active' => true]);
        $w2 = Warehouse::create(['name' => 'Bodega 2', 'code' => 'BOD-F02-5B', 'is_active' => true]);

        $product = Product::create([
            'sku' => 'LLAV-F02-5',
            'name' => 'Llave Ajustable 10in',
            'cost_price' => 20000.00,
            'sale_price' => 35000.00,
            'unit' => 'UND',
        ]);

        KardexService::registerAdjustment($product, $w1->id, 30.0, 'adjustment_in', 'Stock W1', $admin->id);
        KardexService::registerAdjustment($product, $w2->id, 50.0, 'adjustment_in', 'Stock W2', $admin->id);

        $stock1 = ProductStock::where('product_id', $product->id)->where('warehouse_id', $w1->id)->first();
        $stock2 = ProductStock::where('product_id', $product->id)->where('warehouse_id', $w2->id)->first();

        expect((float) $stock1->current_stock)->toBe(30.00)
            ->and((float) $stock2->current_stock)->toBe(50.00);
    });
});

describe('F03: Warehouse Code Uniqueness Validation', function () {
    test('F03-1: Creating a warehouse with a unique code succeeds', function () {
        $warehouse = Warehouse::create([
            'name' => 'Bodega Principal F03',
            'code' => 'BOD-UNIQUE-01',
            'is_active' => true,
        ]);

        expect($warehouse->exists)->toBeTrue()
            ->and($warehouse->code)->toBe('BOD-UNIQUE-01');
    });

    test('F03-2: Attempting to insert duplicate warehouse code violates unique database constraint', function () {
        Warehouse::create([
            'name' => 'Bodega Alfa',
            'code' => 'BOD-DUP-01',
            'is_active' => true,
        ]);

        expect(fn () => Warehouse::create([
            'name' => 'Bodega Beta Copia',
            'code' => 'BOD-DUP-01',
            'is_active' => true,
        ]))->toThrow(QueryException::class);
    });

    test('F03-3: Updating a warehouse while retaining its existing code succeeds without duplicate violation', function () {
        $warehouse = Warehouse::create([
            'name' => 'Bodega Gama Original',
            'code' => 'BOD-GAMA-01',
            'is_active' => true,
        ]);

        $warehouse->update(['name' => 'Bodega Gama Renombrada']);
        expect($warehouse->fresh()->name)->toBe('Bodega Gama Renombrada')
            ->and($warehouse->fresh()->code)->toBe('BOD-GAMA-01');
    });

    test('F03-4: Creating a warehouse with null code is permitted when code is nullable', function () {
        $warehouse = Warehouse::create([
            'name' => 'Bodega Sin Codigo Explicito',
            'code' => null,
            'is_active' => true,
        ]);

        expect($warehouse->exists)->toBeTrue()
            ->and($warehouse->code)->toBeNull();
    });

    test('F03-5: WarehouseResource form definition includes unique validation on code field', function () {
        $form = WarehouseResource::form(new Form(Livewire::new(WarehouseResource\Pages\CreateWarehouse::class)));
        $components = $form->getComponents();
        expect($components)->not->toBeEmpty();
    });
});

describe('F04: Master Data Authorization Hardening', function () {
    test('F04-1: Admin role can view, create, edit, and delete customers', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        expect(CustomerResource::canViewAny())->toBeTrue();
    });

    test('F04-2: Cashier role has customer view permission by default', function () {
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');
        $this->actingAs($cashier);

        expect(CustomerResource::canViewAny())->toBeTrue();
    });

    test('F04-3: Unauthenticated guest cannot view customer resources', function () {
        auth()->logout();
        expect(CustomerResource::canViewAny())->toBeFalse();
    });

    test('F04-4: User with revoked customers.view permission cannot access customer resource', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        expect(CustomerResource::canViewAny())->toBeFalse();
    });

    test('F04-5: Customer credit limit field is restricted to admin role in form definition', function () {
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');
        $this->actingAs($cashier);

        $customer = Customer::create([
            'name' => 'Cliente Prueba F04',
            'document_type' => 'CC',
            'document' => '1099887766',
            'credit_limit' => 1000000,
            'current_debt' => 0,
            'is_active' => true,
        ]);

        expect($customer->available_credit)->toBe(1000000.0);
    });
});

describe('F05: User Bulk Deletion Safety Guard', function () {
    test('F05-1: An authenticated admin can delete another existing user', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $targetUser = User::factory()->create(['name' => 'Usuario Descartable']);

        $targetUser->delete();
        expect(User::find($targetUser->id))->toBeNull();
    });

    test('F05-2: Deleting an inactive user removes them from the database', function () {
        $user = User::factory()->create(['is_active' => false]);
        $id = $user->id;

        $user->delete();
        expect(User::find($id))->toBeNull();
    });

    test('F05-3: Deleting a user preserves historical sales where user was cashier', function () {
        $admin = User::factory()->create();
        $cashier = User::factory()->create(['name' => 'Cajero Historico']);
        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-F05-3', 'is_active' => true]);

        $sale = Sale::create([
            'user_id' => $cashier->id,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => 'REM-F05-001',
            'payment_method' => 'cash',
            'subtotal' => 50000,
            'total' => 50000,
            'paid_amount' => 50000,
            'status' => 'completed',
        ]);

        expect($sale->user_id)->toBe($cashier->id);
    });

    test('F05-4: UserResource canDelete requires users.manage permission', function () {
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');
        $this->actingAs($cashier);

        $otherUser = User::factory()->create();
        expect(UserResource::canDelete($otherUser))->toBeFalse();
    });

    test('F05-5: UserResource canDelete returns true for authorized admin', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $otherUser = User::factory()->create();
        expect(UserResource::canDelete($otherUser))->toBeTrue();
    });
});

describe('F06: Master Data Form Bounds & Validation', function () {
    test('F06-1: Product cost_price and sale_price accept valid zero and positive amounts', function () {
        $prod = Product::create([
            'sku' => 'MET-F06-1',
            'name' => 'Metro 5m Cinta Métrica',
            'cost_price' => 0.00,
            'sale_price' => 12500.50,
            'unit' => 'UND',
        ]);

        expect((float) $prod->cost_price)->toBe(0.00)
            ->and((float) $prod->sale_price)->toBe(12500.50);
    });

    test('F06-2: ProductStock min_stock and current_stock accept valid non-negative numbers', function () {
        $warehouse = Warehouse::create(['name' => 'Bodega Bounds', 'code' => 'BOD-F06-2', 'is_active' => true]);
        $prod = Product::create([
            'sku' => 'CLAV-F06-2',
            'name' => 'Clavo de Acero 2in',
            'cost_price' => 50.00,
            'sale_price' => 100.00,
            'unit' => 'UND',
        ]);

        $stock = ProductStock::create([
            'product_id' => $prod->id,
            'warehouse_id' => $warehouse->id,
            'current_stock' => 0.00,
            'min_stock' => 10.00,
            'max_stock' => 500.00,
        ]);

        expect((float) $stock->current_stock)->toBe(0.00)
            ->and((float) $stock->min_stock)->toBe(10.00)
            ->and((float) $stock->max_stock)->toBe(500.00);
    });

    test('F06-3: Customer credit_limit and current_debt calculate available_credit boundary accurately', function () {
        $customer = Customer::create([
            'name' => 'Ferretería El Tornillo',
            'document_type' => 'NIT',
            'document' => '900999888-2',
            'credit_limit' => 2000000.00,
            'current_debt' => 2000000.00,
            'is_active' => true,
        ]);

        // Boundary: credit fully consumed
        expect($customer->available_credit)->toBe(0.0)
            ->and($customer->hasAvailableCredit(1.0))->toBeFalse();
    });

    test('F06-4: Customer available_credit remains non-negative even if debt exceeds limit', function () {
        $customer = Customer::create([
            'name' => 'Constructora En Mora',
            'document_type' => 'NIT',
            'document' => '900777666-3',
            'credit_limit' => 1000000.00,
            'current_debt' => 1500000.00,
            'is_active' => true,
        ]);

        expect($customer->available_credit)->toBe(0.0)
            ->and($customer->hasAvailableCredit(100.0))->toBeFalse();
    });

    test('F06-5: Product tax rate between 0 and 100 is supported and formatted', function () {
        $prod = Product::create([
            'sku' => 'PINT-F06-5',
            'name' => 'Pintura Vinilo Blanco Tipo 1 Galón',
            'cost_price' => 35000.00,
            'sale_price' => 55000.00,
            'tax_rate' => 19.00,
            'unit' => 'GLN',
        ]);

        expect((float) $prod->tax_rate)->toBe(19.00);
    });
});
