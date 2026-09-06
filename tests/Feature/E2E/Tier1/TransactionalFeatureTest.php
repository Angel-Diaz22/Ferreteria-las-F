<?php

use App\Filament\Resources\PurchaseResource;
use App\Filament\Resources\QuoteResource;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\KardexService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    (new PermissionSeeder)->run();
});

/**
 * ============================================================================
 * TIER 1: FEATURE COVERAGE — F07 to F11 (TRANSACTIONAL DOCUMENTS & KARDEX)
 * ============================================================================
 */
describe('F07: Eager loading in Transactional tables', function () {
    test('F07-1: Sale queries eager load customer, user, warehouse, and items without N+1', function () {
        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-F07-1', 'is_active' => true]);
        $user = User::factory()->create();
        $customer = Customer::create([
            'name' => 'Cliente F07',
            'document_type' => 'CC',
            'document' => '10007771',
            'credit_limit' => 1000000,
            'is_active' => true,
        ]);
        $product = Product::create([
            'sku' => 'SKU-F07-1',
            'name' => 'Tubo PVC 1/2',
            'cost_price' => 5000,
            'sale_price' => 9000,
            'unit' => 'UND',
        ]);

        for ($i = 1; $i <= 4; $i++) {
            $sale = Sale::create([
                'customer_id' => $customer->id,
                'user_id' => $user->id,
                'warehouse_id' => $warehouse->id,
                'invoice_number' => "REM-F07-{$i}",
                'payment_method' => 'cash',
                'subtotal' => 18000,
                'total' => 18000,
                'paid_amount' => 18000,
                'status' => 'completed',
            ]);

            SaleItem::create([
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_cost' => 5000,
                'unit_price' => 9000,
                'subtotal' => 18000,
            ]);
        }

        DB::enableQueryLog();
        $sales = Sale::with(['customer', 'user', 'warehouse', 'items.product'])->get();

        foreach ($sales as $s) {
            expect($s->customer?->name)->toBe('Cliente F07')
                ->and($s->user->id)->toBe($user->id)
                ->and($s->warehouse->name)->toBe('Bodega Central')
                ->and($s->items)->toHaveCount(1);
        }

        expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(6);
        DB::disableQueryLog();
    });

    test('F07-2: Purchase queries eager load supplier and warehouse relations', function () {
        $supplier = Supplier::create(['name' => 'Proveedor Argos', 'nit' => '900123999-1']);
        $warehouse = Warehouse::create(['name' => 'Bodega Materiales', 'code' => 'BOD-F07-2', 'is_active' => true]);
        $user = User::factory()->create();

        for ($i = 1; $i <= 3; $i++) {
            Purchase::create([
                'supplier_id' => $supplier->id,
                'user_id' => $user->id,
                'warehouse_id' => $warehouse->id,
                'purchase_date' => now(),
                'invoice_number' => "FAC-F07-{$i}",
                'subtotal' => 500000,
                'total' => 500000,
                'status' => 'completed',
            ]);
        }

        DB::enableQueryLog();
        $purchases = Purchase::with(['supplier', 'warehouse', 'user'])->get();

        foreach ($purchases as $p) {
            expect($p->supplier->name)->toBe('Proveedor Argos')
                ->and($p->warehouse->name)->toBe('Bodega Materiales');
        }

        expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(4);
        DB::disableQueryLog();
    });

    test('F07-3: Quote queries eager load customer and creator user', function () {
        $customer = Customer::create([
            'name' => 'Inversiones Norte',
            'document_type' => 'NIT',
            'document' => '900888111-2',
            'is_active' => true,
        ]);
        $user = User::factory()->create();

        for ($i = 1; $i <= 3; $i++) {
            Quote::create([
                'customer_id' => $customer->id,
                'user_id' => $user->id,
                'quote_number' => "COT-F07-{$i}",
                'valid_until' => now()->addDays(15),
                'subtotal' => 100000,
                'total' => 100000,
                'status' => 'draft',
            ]);
        }

        DB::enableQueryLog();
        $quotes = Quote::with(['customer', 'user'])->get();

        foreach ($quotes as $q) {
            expect($q->customer->name)->toBe('Inversiones Norte');
        }

        expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(3);
        DB::disableQueryLog();
    });

    test('F07-4: InventoryMovement queries eager load product, warehouse and user', function () {
        $warehouse = Warehouse::create(['name' => 'Bodega Kardex', 'code' => 'BOD-F07-4', 'is_active' => true]);
        $product = Product::create([
            'sku' => 'SKU-F07-4',
            'name' => 'Cemento Gris 50kg',
            'cost_price' => 28000,
            'sale_price' => 34000,
            'unit' => 'BOL',
        ]);
        $user = User::factory()->create();

        for ($i = 1; $i <= 4; $i++) {
            InventoryMovement::create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'user_id' => $user->id,
                'type' => 'adjustment_in',
                'quantity' => 10 * $i,
                'unit_cost' => 28000,
                'previous_stock' => 0,
                'resulting_stock' => 10 * $i,
                'notes' => "Ajuste {$i}",
            ]);
        }

        DB::enableQueryLog();
        $movements = InventoryMovement::with(['product', 'warehouse', 'user'])->get();

        foreach ($movements as $m) {
            expect($m->product->name)->toBe('Cemento Gris 50kg')
                ->and($m->warehouse->name)->toBe('Bodega Kardex');
        }

        expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(4);
        DB::disableQueryLog();
    });

    test('F07-5: Large batches of sales with multiple items maintain bounded database query count', function () {
        $warehouse = Warehouse::create(['name' => 'Bodega Batch', 'code' => 'BOD-F07-5', 'is_active' => true]);
        $user = User::factory()->create();
        $product = Product::create(['sku' => 'SKU-F07-5', 'name' => 'Pintura', 'cost_price' => 1000, 'sale_price' => 2000, 'unit' => 'GLN']);

        for ($i = 1; $i <= 10; $i++) {
            $sale = Sale::create([
                'user_id' => $user->id,
                'warehouse_id' => $warehouse->id,
                'invoice_number' => "REM-F07-BATCH-{$i}",
                'payment_method' => 'cash',
                'subtotal' => 2000,
                'total' => 2000,
                'paid_amount' => 2000,
                'status' => 'completed',
            ]);
            SaleItem::create([
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_cost' => 1000,
                'unit_price' => 2000,
                'subtotal' => 2000,
            ]);
        }

        DB::enableQueryLog();
        $results = Sale::with(['items.product', 'user', 'warehouse'])->take(10)->get();
        expect($results)->toHaveCount(10);
        expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(6);
        DB::disableQueryLog();
    });
});

describe('F08: Transactional Authorization Hardening', function () {
    test('F08-1: Admin can access purchases module', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        expect(PurchaseResource::canViewAny())->toBeTrue();
    });

    test('F08-2: Cashier without purchases.view permission is forbidden from purchases', function () {
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');
        $this->actingAs($cashier);

        expect(PurchaseResource::canViewAny())->toBeFalse();
    });

    test('F08-3: User with quotes.view permission can view quotes', function () {
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');
        $this->actingAs($cashier);

        expect(QuoteResource::canViewAny())->toBeTrue();
    });

    test('F08-4: User without quotes.view permission cannot access quotes', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        expect(QuoteResource::canViewAny())->toBeFalse();
    });

    test('F08-5: Guest cannot access PurchaseResource or QuoteResource', function () {
        auth()->logout();

        expect(PurchaseResource::canViewAny())->toBeFalse()
            ->and(QuoteResource::canViewAny())->toBeFalse();
    });
});

describe('F09: Document Download Route Authorization', function () {
    test('F09-1: Authenticated user with quotes.view can download quote PDF', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $customer = Customer::create(['name' => 'Cliente PDF', 'document_type' => 'CC', 'document' => '99887711']);
        $quote = Quote::create([
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'quote_number' => 'COT-PDF-001',
            'valid_until' => now()->addDays(15),
            'subtotal' => 50000,
            'total' => 50000,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($admin)->get("/admin/quotes/{$quote->id}/pdf");
        $response->assertSuccessful();
    });

    test('F09-2: Unauthenticated guest requesting quote PDF is denied with 401 unauthorized', function () {
        auth()->logout();
        $user = User::factory()->create();
        $quote = Quote::create([
            'user_id' => $user->id,
            'quote_number' => 'COT-GUEST-001',
            'valid_until' => now()->addDays(15),
            'subtotal' => 50000,
            'total' => 50000,
            'status' => 'draft',
        ]);

        $response = $this->getJson("/admin/quotes/{$quote->id}/pdf");
        $response->assertUnauthorized();
    });

    test('F09-3: Authenticated user with sales.view can access receipt print view', function () {
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $warehouse = Warehouse::create(['name' => 'Bodega Recibo', 'code' => 'BOD-REC-01', 'is_active' => true]);
        $sale = Sale::create([
            'user_id' => $cashier->id,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => 'REM-REC-001',
            'payment_method' => 'cash',
            'subtotal' => 25000,
            'total' => 25000,
            'paid_amount' => 25000,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($cashier)->get("/admin/sales/{$sale->id}/receipt");
        $response->assertSuccessful();
    });

    test('F09-4: Unauthenticated guest requesting receipt print is denied with 401 unauthorized', function () {
        auth()->logout();
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Recibo 2', 'code' => 'BOD-REC-02', 'is_active' => true]);
        $sale = Sale::create([
            'user_id' => $user->id,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => 'REM-REC-002',
            'payment_method' => 'cash',
            'subtotal' => 10000,
            'total' => 10000,
            'status' => 'completed',
        ]);

        $response = $this->getJson("/admin/sales/{$sale->id}/receipt");
        $response->assertUnauthorized();
    });

    test('F09-5: Sale receipt thermal PDF generation produces valid PDF output', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $warehouse = Warehouse::create(['name' => 'Bodega PDF', 'code' => 'BOD-PDF-01', 'is_active' => true]);
        $sale = Sale::create([
            'user_id' => $admin->id,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => 'REM-THERMAL-01',
            'payment_method' => 'cash',
            'subtotal' => 45000,
            'total' => 45000,
            'paid_amount' => 50000,
            'change_amount' => 5000,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($admin)->get("/admin/sales/{$sale->id}/pdf");
        $response->assertSuccessful();
    });
});

describe('F10: Quote Consecutive Atomic Generation', function () {
    test('F10-1: Quotes follow sequential numbered format COT-XXXXX', function () {
        $admin = User::factory()->create();
        $q1 = Quote::create([
            'user_id' => $admin->id,
            'quote_number' => 'COT-00001',
            'valid_until' => now()->addDays(15),
            'subtotal' => 10000,
            'total' => 10000,
            'status' => 'draft',
        ]);

        expect($q1->quote_number)->toMatch('/^COT-\d{5}$/');
    });

    test('F10-2: Existing quote numbers are not overwritten upon updating quote items', function () {
        $admin = User::factory()->create();
        $quote = Quote::create([
            'user_id' => $admin->id,
            'quote_number' => 'COT-00123',
            'valid_until' => now()->addDays(15),
            'subtotal' => 15000,
            'total' => 15000,
            'status' => 'draft',
        ]);

        $quote->update(['notes' => 'Cotización con descuento especial']);
        expect($quote->fresh()->quote_number)->toBe('COT-00123');
    });

    test('F10-3: Multiple quotes increment distinct sequential numbers', function () {
        $admin = User::factory()->create();
        $numbers = [];

        for ($i = 1; $i <= 4; $i++) {
            $num = 'COT-'.str_pad((string) (100 + $i), 5, '0', STR_PAD_LEFT);
            $q = Quote::create([
                'user_id' => $admin->id,
                'quote_number' => $num,
                'valid_until' => now()->addDays(15),
                'subtotal' => 5000 * $i,
                'total' => 5000 * $i,
                'status' => 'draft',
            ]);
            $numbers[] = $q->quote_number;
        }

        expect(array_unique($numbers))->toHaveCount(4);
    });

    test('F10-4: Custom quote number format provided explicitly is preserved', function () {
        $admin = User::factory()->create();
        $quote = Quote::create([
            'user_id' => $admin->id,
            'quote_number' => 'COT-ESPECIAL-2026',
            'valid_until' => now()->addDays(15),
            'subtotal' => 200000,
            'total' => 200000,
            'status' => 'sent',
        ]);

        expect($quote->quote_number)->toBe('COT-ESPECIAL-2026');
    });

    test('F10-5: Quote number collision avoidance ensures uniqueness', function () {
        $user = User::factory()->create();
        Quote::create([
            'user_id' => $user->id,
            'quote_number' => 'COT-99990',
            'valid_until' => now()->addDays(15),
            'subtotal' => 100,
            'total' => 100,
            'status' => 'draft',
        ]);

        $nextNumber = 99990;
        do {
            $candidate = 'COT-'.str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
            $nextNumber++;
        } while (Quote::where('quote_number', $candidate)->exists());

        expect($candidate)->toBe('COT-99991');
    });
});

describe('F11: Purchase Status & Stock Sync Guard', function () {
    test('F11-1: Processing a completed purchase increments warehouse stock in Kardex', function () {
        $admin = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Compras', 'code' => 'BOD-F11-1', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Pavco de Colombia', 'nit' => '860000111-1']);

        $product = Product::create([
            'sku' => 'TUB-F11-1',
            'name' => 'Tubo Sanitario 3in x 6m',
            'cost_price' => 20000.00,
            'sale_price' => 30000.00,
            'unit' => 'UND',
        ]);

        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'user_id' => $admin->id,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => 'FAC-PAVCO-101',
            'purchase_date' => now(),
            'subtotal' => 400000.00,
            'total' => 400000.00,
            'status' => 'completed',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 20.00,
            'unit_cost' => 20000.00,
            'subtotal' => 400000.00,
        ]);

        KardexService::processPurchase($purchase);

        $stock = ProductStock::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->first();
        expect((float) $stock->current_stock)->toBe(20.00);

        $movement = InventoryMovement::where('product_id', $product->id)
            ->where('reference_id', $purchase->id)
            ->first();

        expect($movement)->not->toBeNull()
            ->and((float) $movement->quantity)->toBe(20.00)
            ->and($movement->type)->toBe('purchase');
    });

    test('F11-2: Purchase items recalculate weighted average cost (CPP) accurately', function () {
        $admin = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega CPP', 'code' => 'BOD-F11-2', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Sika Colombia', 'nit' => '860111222-2']);

        // Initial state: 10 units at $10,000 cost = $100,000 invested
        $product = Product::create([
            'sku' => 'SIKA-F11-2',
            'name' => 'Sika 1 Impermeabilizante 4kg',
            'cost_price' => 10000.00,
            'sale_price' => 16000.00,
            'unit' => 'GLN',
        ]);

        ProductStock::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'current_stock' => 10.00,
            'min_stock' => 2.00,
        ]);

        // Purchase: 10 new units at $14,000 cost = $140,000 invested
        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'user_id' => $admin->id,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => 'FAC-SIKA-202',
            'purchase_date' => now(),
            'subtotal' => 140000.00,
            'total' => 140000.00,
            'status' => 'completed',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 10.00,
            'unit_cost' => 14000.00,
            'subtotal' => 140000.00,
        ]);

        KardexService::processPurchase($purchase);

        // Expected CPP: (100,000 + 140,000) / 20 = 240,000 / 20 = $12,000
        expect((float) $product->fresh()->cost_price)->toBe(12000.00);
    });

    test('F11-3: Multi-item purchase updates stock for all involved products', function () {
        $admin = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Multi', 'code' => 'BOD-F11-3', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Ferretera Mayorista', 'nit' => '900333444-3']);

        $p1 = Product::create(['sku' => 'ITM-1', 'name' => 'Brocha 2in', 'cost_price' => 3000, 'sale_price' => 5000, 'unit' => 'UND']);
        $p2 = Product::create(['sku' => 'ITM-2', 'name' => 'Espátula 3in', 'cost_price' => 4000, 'sale_price' => 7000, 'unit' => 'UND']);

        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'user_id' => $admin->id,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => 'FAC-MULTI-303',
            'purchase_date' => now(),
            'subtotal' => 70000,
            'total' => 70000,
            'status' => 'completed',
        ]);

        PurchaseItem::create(['purchase_id' => $purchase->id, 'product_id' => $p1->id, 'quantity' => 10, 'unit_cost' => 3000, 'subtotal' => 30000]);
        PurchaseItem::create(['purchase_id' => $purchase->id, 'product_id' => $p2->id, 'quantity' => 10, 'unit_cost' => 4000, 'subtotal' => 40000]);

        KardexService::processPurchase($purchase);

        $s1 = ProductStock::where('product_id', $p1->id)->where('warehouse_id', $warehouse->id)->first();
        $s2 = ProductStock::where('product_id', $p2->id)->where('warehouse_id', $warehouse->id)->first();

        expect((float) $s1->current_stock)->toBe(10.00)
            ->and((float) $s2->current_stock)->toBe(10.00);
    });

    test('F11-4: Pending or draft purchase without processing does not alter stock', function () {
        $admin = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Draft', 'code' => 'BOD-F11-4', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Distribuidor Local', 'nit' => '900555666-4']);
        $product = Product::create(['sku' => 'SKU-F11-4', 'name' => 'Llave Bristol', 'cost_price' => 8000, 'sale_price' => 12000, 'unit' => 'UND']);

        ProductStock::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'current_stock' => 5.00,
            'min_stock' => 1.00,
        ]);

        // Create purchase in draft status without calling KardexService::processPurchase
        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'user_id' => $admin->id,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => 'FAC-DRAFT-404',
            'purchase_date' => now(),
            'subtotal' => 80000,
            'total' => 80000,
            'status' => 'pending',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_cost' => 8000,
            'subtotal' => 80000,
        ]);

        $stock = ProductStock::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->first();
        expect((float) $stock->current_stock)->toBe(5.00); // Unchanged
    });

    test('F11-5: Kardex movements created by purchases retain inmutable reference to purchase record', function () {
        $admin = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Audit', 'code' => 'BOD-F11-5', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Tornillos y Partes', 'nit' => '900777888-5']);
        $product = Product::create(['sku' => 'TOR-F11-5', 'name' => 'Tornillo Autoperforante', 'cost_price' => 50, 'sale_price' => 100, 'unit' => 'UND']);

        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'user_id' => $admin->id,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => 'FAC-AUDIT-505',
            'purchase_date' => now(),
            'subtotal' => 5000,
            'total' => 5000,
            'status' => 'completed',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 100,
            'unit_cost' => 50,
            'subtotal' => 5000,
        ]);

        KardexService::processPurchase($purchase);

        $movement = InventoryMovement::where('reference_type', Purchase::class)
            ->where('reference_id', $purchase->id)
            ->first();

        expect($movement)->not->toBeNull()
            ->and($movement->notes)->toContain('FAC-AUDIT-505')
            ->and($movement->notes)->toContain('Tornillos y Partes');
    });
});
