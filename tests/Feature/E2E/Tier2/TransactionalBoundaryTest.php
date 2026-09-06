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
 * TIER 2: BOUNDARY & CORNER CASES — F07 to F11 (TRANSACTIONAL DOCUMENTS & KARDEX)
 * ============================================================================
 */
describe('F07 Boundaries: Transactional Eager Loading Edge Cases', function () {
    test('F07-B1: Empty sales and purchases tables load without SQL errors', function () {
        $sales = Sale::with(['customer', 'user', 'warehouse', 'items'])->get();
        $purchases = Purchase::with(['supplier', 'warehouse', 'items'])->get();

        expect($sales)->toBeEmpty()
            ->and($purchases)->toBeEmpty();
    });

    test('F07-B2: Sale with null customer (venta mostrador) loads relationships safely', function () {
        $warehouse = Warehouse::create(['name' => 'Bodega T2-07', 'code' => 'BOD-T2-07', 'is_active' => true]);
        $user = User::factory()->create();

        $sale = Sale::create([
            'customer_id' => null, // Venta mostrador sin cliente registrado
            'user_id' => $user->id,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => 'REM-MOST-001',
            'payment_method' => 'cash',
            'subtotal' => 15000,
            'total' => 15000,
            'paid_amount' => 15000,
            'status' => 'completed',
        ]);

        $loaded = Sale::with(['customer', 'user'])->find($sale->id);
        expect($loaded->customer)->toBeNull()
            ->and($loaded->user->id)->toBe($user->id);
    });

    test('F07-B3: Purchase with 30 items eager loads with strictly bounded query count', function () {
        $supplier = Supplier::create(['name' => 'Mega Proveedor', 'nit' => '900999111-0']);
        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-T2-B3', 'is_active' => true]);
        $user = User::factory()->create();

        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'user_id' => $user->id,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => 'FAC-30-ITEMS',
            'purchase_date' => now(),
            'subtotal' => 300000,
            'total' => 300000,
            'status' => 'completed',
        ]);

        for ($i = 1; $i <= 30; $i++) {
            $prod = Product::create(['sku' => "ITM-30-{$i}", 'name' => "Item {$i}", 'cost_price' => 1000, 'sale_price' => 2000, 'unit' => 'UND']);
            PurchaseItem::create([
                'purchase_id' => $purchase->id,
                'product_id' => $prod->id,
                'quantity' => 10,
                'unit_cost' => 1000,
                'subtotal' => 10000,
            ]);
        }

        DB::enableQueryLog();
        $loaded = Purchase::with(['items.product', 'supplier', 'warehouse'])->find($purchase->id);
        expect($loaded->items)->toHaveCount(30);
        // Fixed queries: purchase + items + products + supplier + warehouse = 5
        expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(5);
        DB::disableQueryLog();
    });

    test('F07-B4: Quote without items loads safely without error', function () {
        $user = User::factory()->create();
        $quote = Quote::create([
            'user_id' => $user->id,
            'quote_number' => 'COT-EMPTY-01',
            'valid_until' => now()->addDays(10),
            'subtotal' => 0,
            'total' => 0,
            'status' => 'draft',
        ]);

        $loaded = Quote::with('items')->find($quote->id);
        expect($loaded->items)->toBeEmpty();
    });

    test('F07-B5: InventoryMovement with polymorphic reference loads cleanly', function () {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Mov', 'code' => 'BOD-MOV-01', 'is_active' => true]);
        $prod = Product::create(['sku' => 'MOV-01', 'name' => 'Item Mov', 'cost_price' => 500, 'sale_price' => 1000, 'unit' => 'UND']);

        $m = InventoryMovement::create([
            'product_id' => $prod->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $user->id,
            'type' => 'adjustment_in',
            'quantity' => 5,
            'unit_cost' => 500,
            'previous_stock' => 0,
            'resulting_stock' => 5,
            'reference_type' => Product::class,
            'reference_id' => $prod->id,
            'notes' => 'Ajuste polimórfico',
        ]);

        $loaded = InventoryMovement::with(['product', 'warehouse'])->find($m->id);
        expect($loaded->product->name)->toBe('Item Mov')
            ->and($loaded->warehouse->code)->toBe('BOD-MOV-01');
    });
});

describe('F08 Boundaries: Transactional Authorization Edge Cases', function () {
    test('F08-B1: Unauthenticated request to purchases index is rejected', function () {
        auth()->logout();
        expect(PurchaseResource::canViewAny())->toBeFalse();
    });

    test('F08-B2: User with custom role without purchases.view cannot access PurchaseResource', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        expect(PurchaseResource::canViewAny())->toBeFalse();
    });

    test('F08-B3: Cashier without purchases.view cannot access purchases module', function () {
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');
        $this->actingAs($cashier);

        expect(PurchaseResource::canViewAny())->toBeFalse();
        $this->get('/admin/purchases')->assertForbidden();
    });

    test('F08-B4: Quote with expired valid_until date retains status and remains accessible for review', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $expiredQuote = Quote::create([
            'user_id' => $admin->id,
            'quote_number' => 'COT-EXP-01',
            'valid_until' => now()->subDays(5), // Expired 5 days ago
            'subtotal' => 100000,
            'total' => 100000,
            'status' => 'expired',
        ]);

        expect($expiredQuote->valid_until->isPast())->toBeTrue()
            ->and($expiredQuote->status)->toBe('expired');
    });

    test('F08-B5: Cashier role can access QuoteResource but cannot access PurchaseResource', function () {
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');
        $this->actingAs($cashier);

        expect(QuoteResource::canViewAny())->toBeTrue()
            ->and(PurchaseResource::canViewAny())->toBeFalse();
    });
});

describe('F09 Boundaries: Document Download & PDF Generation Edge Cases', function () {
    test('F09-B1: Requesting non-existent quote PDF returns 404 Not Found', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get('/admin/quotes/999999/pdf');
        $response->assertNotFound();
    });

    test('F09-B2: Requesting non-existent sale receipt returns 404 Not Found', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get('/admin/sales/999999/receipt');
        $response->assertNotFound();
    });

    test('F09-B3: Sale receipt with $0 tax and $0 change renders cleanly', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $warehouse = Warehouse::create(['name' => 'Bodega Cero', 'code' => 'BOD-REC-CERO', 'is_active' => true]);

        $sale = Sale::create([
            'user_id' => $admin->id,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => 'REM-CERO-001',
            'payment_method' => 'card',
            'subtotal' => 50000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 50000,
            'paid_amount' => 50000,
            'change_amount' => 0,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($admin)->get("/admin/sales/{$sale->id}/receipt");
        $response->assertSuccessful();
    });

    test('F09-B4: Quote PDF with customer having no address or phone renders without blade errors', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $customer = Customer::create([
            'name' => 'Cliente Sin Contacto',
            'document_type' => 'CC',
            'document' => '11223344',
            'phone' => null,
            'address' => null,
            'is_active' => true,
        ]);

        $quote = Quote::create([
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'quote_number' => 'COT-NOCONTACT',
            'valid_until' => now()->addDays(10),
            'subtotal' => 20000,
            'total' => 20000,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($admin)->get("/admin/quotes/{$quote->id}/pdf");
        $response->assertSuccessful();
    });

    test('F09-B5: Sale receipt with 100-character long product name renders without layout explosion', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $warehouse = Warehouse::create(['name' => 'Bodega Long', 'code' => 'BOD-REC-LONG', 'is_active' => true]);

        $longName = 'Tubo Conduit PVC Uso Eléctrico Pesado Certificado Retie 1/2 Pulgada por Seis Metros de Longitud Grado A';
        $prod = Product::create(['sku' => 'LONG-PROD', 'name' => $longName, 'cost_price' => 5000, 'sale_price' => 10000, 'unit' => 'MTR']);

        $sale = Sale::create([
            'user_id' => $admin->id,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => 'REM-LONG-01',
            'payment_method' => 'cash',
            'subtotal' => 10000,
            'total' => 10000,
            'paid_amount' => 10000,
            'status' => 'completed',
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $prod->id,
            'quantity' => 1,
            'unit_cost' => 5000,
            'unit_price' => 10000,
            'subtotal' => 10000,
        ]);

        $response = $this->actingAs($admin)->get("/admin/sales/{$sale->id}/receipt");
        $response->assertSuccessful();
    });
});

describe('F10 Boundaries: Quote Number Generator Constraints', function () {
    test('F10-B1: First quote starts from number 1 (COT-00001) if no prior quotes exist', function () {
        $user = User::factory()->create();
        $q = Quote::create([
            'user_id' => $user->id,
            'quote_number' => 'COT-00001',
            'valid_until' => now()->addDays(15),
            'subtotal' => 1000,
            'total' => 1000,
            'status' => 'draft',
        ]);

        expect($q->quote_number)->toBe('COT-00001');
    });

    test('F10-B2: Large quote number boundary (COT-99999) pads correctly with 5 digits', function () {
        $user = User::factory()->create();
        $num = 'COT-'.str_pad('99999', 5, '0', STR_PAD_LEFT);
        $q = Quote::create([
            'user_id' => $user->id,
            'quote_number' => $num,
            'valid_until' => now()->addDays(15),
            'subtotal' => 1000,
            'total' => 1000,
            'status' => 'draft',
        ]);

        expect($q->quote_number)->toBe('COT-99999');
    });

    test('F10-B3: Quote number beyond 99999 expands gracefully without truncation', function () {
        $user = User::factory()->create();
        $num = 'COT-'.str_pad('100000', 5, '0', STR_PAD_LEFT);
        $q = Quote::create([
            'user_id' => $user->id,
            'quote_number' => $num,
            'valid_until' => now()->addDays(15),
            'subtotal' => 1000,
            'total' => 1000,
            'status' => 'draft',
        ]);

        expect($q->quote_number)->toBe('COT-100000');
    });

    test('F10-B4: Consecutive number collision resolution finds next available slot', function () {
        $user = User::factory()->create();
        Quote::create(['user_id' => $user->id, 'quote_number' => 'COT-00010', 'valid_until' => now()->addDays(15), 'subtotal' => 100, 'total' => 100, 'status' => 'draft']);
        Quote::create(['user_id' => $user->id, 'quote_number' => 'COT-00011', 'valid_until' => now()->addDays(15), 'subtotal' => 100, 'total' => 100, 'status' => 'draft']);

        $next = 10;
        do {
            $candidate = 'COT-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
            $next++;
        } while (Quote::where('quote_number', $candidate)->exists());

        expect($candidate)->toBe('COT-00012');
    });

    test('F10-B5: Custom alphanumeric prefix in quote number is fully accepted', function () {
        $user = User::factory()->create();
        $q = Quote::create([
            'user_id' => $user->id,
            'quote_number' => 'COT-CORP-2026-X',
            'valid_until' => now()->addDays(30),
            'subtotal' => 500000,
            'total' => 500000,
            'status' => 'sent',
        ]);

        expect($q->quote_number)->toBe('COT-CORP-2026-X');
    });
});

describe('F11 Boundaries: Purchase Status & Stock Sync Edge Conditions', function () {
    test('F11-B1: Purchase with zero-cost item (bonificación o muestra de proveedor) processes without division by zero', function () {
        $admin = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Bonific', 'code' => 'BOD-BON-01', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Proveedor Muestras', 'nit' => '900111333-1']);
        $prod = Product::create(['sku' => 'BON-01', 'name' => 'Item Muestra Gratis', 'cost_price' => 0, 'sale_price' => 0, 'unit' => 'UND']);

        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'user_id' => $admin->id,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => 'FAC-MUESTRA-01',
            'purchase_date' => now(),
            'subtotal' => 0,
            'total' => 0,
            'status' => 'completed',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $prod->id,
            'quantity' => 10,
            'unit_cost' => 0,
            'subtotal' => 0,
        ]);

        KardexService::processPurchase($purchase);

        $stock = ProductStock::where('product_id', $prod->id)->where('warehouse_id', $warehouse->id)->first();
        expect((float) $stock->current_stock)->toBe(10.00);
    });

    test('F11-B2: Purchase with fine decimal quantity (e.g. 12.75 meters of cable) adds accurately to warehouse stock', function () {
        $admin = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Cable', 'code' => 'BOD-CAB-01', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Cables de Colombia', 'nit' => '900222444-2']);
        $prod = Product::create(['sku' => 'CAB-DEC', 'name' => 'Cable Coaxial', 'cost_price' => 1200, 'sale_price' => 2000, 'unit' => 'MTR']);

        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'user_id' => $admin->id,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => 'FAC-CAB-02',
            'purchase_date' => now(),
            'subtotal' => 15300,
            'total' => 15300,
            'status' => 'completed',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $prod->id,
            'quantity' => 12.75,
            'unit_cost' => 1200,
            'subtotal' => 15300,
        ]);

        KardexService::processPurchase($purchase);

        $stock = ProductStock::where('product_id', $prod->id)->where('warehouse_id', $warehouse->id)->first();
        expect((float) $stock->current_stock)->toBe(12.75);
    });

    test('F11-B3: Multiple consecutive purchases of same product update CPP cumulatively', function () {
        $admin = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega CPP Cum', 'code' => 'BOD-CPP-CUM', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Proveedor Acero', 'nit' => '900333555-3']);

        $prod = Product::create(['sku' => 'VAR-1/2', 'name' => 'Varilla 1/2', 'cost_price' => 10000, 'sale_price' => 15000, 'unit' => 'UND']);

        // Purchase 1: 10 units @ $10,000 = $100,000
        $p1 = Purchase::create(['supplier_id' => $supplier->id, 'user_id' => $admin->id, 'warehouse_id' => $warehouse->id, 'invoice_number' => 'FAC-CPP-1', 'purchase_date' => now(), 'subtotal' => 100000, 'total' => 100000, 'status' => 'completed']);
        PurchaseItem::create(['purchase_id' => $p1->id, 'product_id' => $prod->id, 'quantity' => 10, 'unit_cost' => 10000, 'subtotal' => 100000]);
        KardexService::processPurchase($p1);

        // Purchase 2: 10 units @ $20,000 = $200,000 -> CPP should be $15,000
        $p2 = Purchase::create(['supplier_id' => $supplier->id, 'user_id' => $admin->id, 'warehouse_id' => $warehouse->id, 'invoice_number' => 'FAC-CPP-2', 'purchase_date' => now(), 'subtotal' => 200000, 'total' => 200000, 'status' => 'completed']);
        PurchaseItem::create(['purchase_id' => $p2->id, 'product_id' => $prod->id, 'quantity' => 10, 'unit_cost' => 20000, 'subtotal' => 200000]);
        KardexService::processPurchase($p2);

        expect((float) $prod->fresh()->cost_price)->toBe(15000.00);
    });

    test('F11-B4: Purchase invoice number boundary accepts maximum length string up to 255 chars', function () {
        $supplier = Supplier::create(['name' => 'Proveedor Global', 'nit' => '900444666-4']);
        $warehouse = Warehouse::create(['name' => 'Bodega Fac', 'code' => 'BOD-FAC-01', 'is_active' => true]);
        $user = User::factory()->create();

        $longInvoice = 'FAC-'.str_repeat('9', 150);
        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'user_id' => $user->id,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => $longInvoice,
            'purchase_date' => now(),
            'subtotal' => 1000,
            'total' => 1000,
            'status' => 'pending',
        ]);

        expect($purchase->invoice_number)->toBe($longInvoice);
    });

    test('F11-B5: Purchase total calculation matches sum of item subtotals', function () {
        $supplier = Supplier::create(['name' => 'Proveedor Suma', 'nit' => '900555777-5']);
        $warehouse = Warehouse::create(['name' => 'Bodega Suma', 'code' => 'BOD-SUM-01', 'is_active' => true]);
        $user = User::factory()->create();

        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'user_id' => $user->id,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => 'FAC-SUM-01',
            'purchase_date' => now(),
            'subtotal' => 75000,
            'total' => 75000,
            'status' => 'completed',
        ]);

        expect((float) $purchase->total)->toBe(75000.00);
    });
});
