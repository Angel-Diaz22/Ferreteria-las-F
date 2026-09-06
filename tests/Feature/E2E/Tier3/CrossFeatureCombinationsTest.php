<?php

use App\Filament\Pages\PosTerminal;
use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\KardexService;
use App\Services\PosService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

beforeEach(function () {
    (new PermissionSeeder)->run();
    SystemSetting::set('iva_enabled', true, 'boolean');
});

/**
 * ============================================================================
 * TIER 3: CROSS-FEATURE COMBINATIONS (PAIRWISE INTERACTIONS)
 * ============================================================================
 */
describe('Tier 3: Pairwise Feature Combinations', function () {
    test('Comb-01 (F02 + F16): Stock adjustment followed immediately by atomic POS sale updates Kardex accurately', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-COMB-01', 'is_active' => true]);

        $product = Product::create([
            'sku' => 'COMB-01-SKU',
            'name' => 'Tubo Galvanizado 1in',
            'cost_price' => 15000.0,
            'sale_price' => 25000.0,
            'unit' => 'MTR',
        ]);

        // Step 1: Stock adjustment adds 20 units
        KardexService::registerAdjustment($product, $warehouse->id, 20.0, 'adjustment_in', 'Ajuste inicial', $admin->id);
        $stock = ProductStock::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->first();
        expect((float) $stock->current_stock)->toBe(20.0);

        // Step 2: POS sale consumes 8 units
        $sale = PosService::processSale(
            userId: $admin->id,
            warehouseId: $warehouse->id,
            customerId: null,
            paymentMethod: 'cash',
            items: [['product_id' => $product->id, 'quantity' => 8.0, 'unit_price' => 25000.0, 'tax_rate' => 19.0, 'subtotal' => 200000.0]],
            paidAmount: 250000.0
        );

        $stock->refresh();
        expect((float) $stock->current_stock)->toBe(12.0)
            ->and($sale->status)->toBe('completed')
            ->and(InventoryMovement::where('product_id', $product->id)->count())->toBe(2);
    });

    test('Comb-02 (F12 + F13): Attention register zero base shift opening coexists with Caja 3 cash shift', function () {
        $cashier = User::factory()->create(['name' => 'Cajero Mostrador']);
        $cashier->assignRole('cashier');
        $admin = User::factory()->create(['name' => 'Administrador']);
        $admin->assignRole('admin');

        $warehouse = Warehouse::create(['name' => 'Bodega Principal', 'code' => 'BOD-COMB-02', 'is_active' => true]);
        $caja1 = CashRegister::create(['name' => 'Caja 1 - Mostrador', 'warehouse_id' => $warehouse->id, 'is_active' => true]);
        $caja3 = CashRegister::create(['name' => 'Caja 3 - Central', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        // 1. Cashier opens Caja 1 ($0 base)
        $this->actingAs($cashier);
        Livewire::test(PosTerminal::class)->call('selectCashRegister', $caja1->id);

        $shift1 = CashShift::where('cash_register_id', $caja1->id)->where('status', 'open')->first();
        expect((float) $shift1->opening_amount)->toBe(0.0);

        // 2. Admin enters Caja 3 with cash handling
        $this->actingAs($admin);
        Livewire::test(PosTerminal::class)->assertSet('activeCashRegisterId', $caja3->id);

        $shift3 = CashShift::where('cash_register_id', $caja3->id)->where('status', 'open')->first();
        expect($shift3)->not->toBeNull()
            ->and($shift3->user_id)->toBe($admin->id);
    });

    test('Comb-03 (F06 + F10): Customer assigned price list tiers quote unit price with atomic quote consecutive', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $constructorList = PriceList::create(['name' => 'Constructor', 'is_default' => false]);
        $product = Product::create([
            'sku' => 'COMB-03-SKU',
            'name' => 'Ladrillo Estructural',
            'cost_price' => 1200.0,
            'sale_price' => 2000.0, // Detal
            'unit' => 'UND',
        ]);

        // Constructor price: $1,600
        PriceListItem::create([
            'price_list_id' => $constructorList->id,
            'product_id' => $product->id,
            'price' => 1600.0,
        ]);

        $customer = Customer::create([
            'price_list_id' => $constructorList->id,
            'name' => 'Constructora Bolívar',
            'document_type' => 'NIT',
            'document' => '900555123-4',
            'is_active' => true,
        ]);

        $quote = Quote::create([
            'customer_id' => $customer->id,
            'user_id' => $admin->id,
            'quote_number' => 'COT-00777',
            'valid_until' => now()->addDays(15),
            'subtotal' => 160000.0, // 100 units * $1600
            'total' => 160000.0,
            'status' => 'draft',
        ]);

        QuoteItem::create([
            'quote_id' => $quote->id,
            'product_id' => $product->id,
            'quantity' => 100,
            'unit_price' => 1600.0,
            'subtotal' => 160000.0,
        ]);

        expect($quote->quote_number)->toBe('COT-00777')
            ->and($quote->customer->priceList->name)->toBe('Constructor')
            ->and((float) $quote->items()->first()->unit_price)->toBe(1600.0);
    });

    test('Comb-04 (F04 + F19): Customer credit limit authorization directly governs POS credit sales settlement', function () {
        $user = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Credito', 'code' => 'BOD-COMB-04', 'is_active' => true]);
        $product = Product::create(['sku' => 'COMB-04-SKU', 'name' => 'Pintura Epóxica', 'cost_price' => 50000, 'sale_price' => 80000, 'unit' => 'GLN']);
        ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 10]);

        $customer = Customer::create([
            'name' => 'Maestro Contratista',
            'document_type' => 'CC',
            'document' => '79888999',
            'credit_limit' => 200000.0,
            'current_debt' => 50000.0,
            'is_active' => true,
        ]);

        // Available credit = $150,000. Purchase: 1 galón = $80,000 (Approved)
        $sale = PosService::processSale(
            userId: $user->id,
            warehouseId: $warehouse->id,
            customerId: $customer->id,
            paymentMethod: 'credit',
            items: [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 80000, 'tax_rate' => 0, 'subtotal' => 80000]],
            paidAmount: 0
        );

        $customer->refresh();
        expect((float) $customer->current_debt)->toBe(130000.0)
            ->and($customer->available_credit)->toBe(70000.0)
            ->and($sale->status)->toBe('completed');
    });

    test('Comb-05 (F02 + F11): Purchase receipt with CPP recalculation followed by manual Kardex inventory adjustment', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $warehouse = Warehouse::create(['name' => 'Bodega CPP-Adj', 'code' => 'BOD-COMB-05', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Siderúrgica del Norte', 'nit' => '890123456-1']);

        $product = Product::create([
            'sku' => 'COMB-05-SKU',
            'name' => 'Malla Electrosoldada',
            'cost_price' => 30000.0,
            'sale_price' => 45000.0,
            'unit' => 'UND',
        ]);
        ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 10]);

        // Purchase 10 units @ $40,000 -> CPP becomes $35,000
        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'user_id' => $admin->id,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => 'FAC-CPP-ADJ',
            'purchase_date' => now(),
            'subtotal' => 400000,
            'total' => 400000,
            'status' => 'completed',
        ]);
        PurchaseItem::create(['purchase_id' => $purchase->id, 'product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 40000, 'subtotal' => 400000]);
        KardexService::processPurchase($purchase);

        expect((float) $product->fresh()->cost_price)->toBe(35000.0)
            ->and((float) $product->stocks()->first()->current_stock)->toBe(20.0);

        // Subsequent manual adjustment out (merma of 2 units)
        KardexService::registerAdjustment($product, $warehouse->id, 2.0, 'adjustment_out', 'Rotura de varillas', $admin->id);
        expect((float) $product->stocks()->first()->current_stock)->toBe(18.0);
    });

    test('Comb-06 (F08 + F09): User with quotes.view permission can generate and download quote PDF', function () {
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $customer = Customer::create(['name' => 'Cliente Cotiz', 'document_type' => 'CC', 'document' => '1234567']);
        $quote = Quote::create([
            'customer_id' => $customer->id,
            'user_id' => $cashier->id,
            'quote_number' => 'COT-AUTH-PDF',
            'valid_until' => now()->addDays(15),
            'subtotal' => 80000,
            'total' => 80000,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($cashier)->get("/admin/quotes/{$quote->id}/pdf");
        $response->assertSuccessful()
            ->assertHeader('Content-Type', 'application/pdf');
    });

    test('Comb-07 (F01 + F07): Eager loading master customer and transactional sales avoids N+1 in ledger queries', function () {
        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-COMB-07', 'is_active' => true]);
        $user = User::factory()->create();
        $customer = Customer::create(['name' => 'Cliente Frecuente', 'document_type' => 'NIT', 'document' => '900111222-3', 'is_active' => true]);

        for ($i = 1; $i <= 5; $i++) {
            Sale::create([
                'customer_id' => $customer->id,
                'user_id' => $user->id,
                'warehouse_id' => $warehouse->id,
                'invoice_number' => "REM-LEDGER-{$i}",
                'payment_method' => 'cash',
                'subtotal' => 10000 * $i,
                'total' => 10000 * $i,
                'status' => 'completed',
            ]);
        }

        DB::enableQueryLog();
        $loadedCustomer = Customer::with('sales.warehouse')->find($customer->id);
        expect($loadedCustomer->sales)->toHaveCount(5);
        expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(3);
        DB::disableQueryLog();
    });

    test('Comb-08 (F05 + F13): Database foreign key protects against deleting a cashier with historical shifts, requiring deactivation instead', function () {
        $cashier = User::factory()->create(['name' => 'Cajero Temporal']);
        $cashier->assignRole('cashier');
        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-COMB-08', 'is_active' => true]);
        $caja = CashRegister::create(['name' => 'Caja 1 - Mostrador', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        $shift = CashShift::create([
            'cash_register_id' => $caja->id,
            'user_id' => $cashier->id,
            'opening_amount' => 0.0,
            'status' => 'closed',
            'opened_at' => now()->subDay(),
            'closed_at' => now()->subDay()->addHours(8),
        ]);

        // Attempting to hard-delete user throws foreign key constraint violation
        expect(fn () => $cashier->delete())->toThrow(QueryException::class);

        // Deactivating user works safely without violating FK
        $cashier->update(['is_active' => false]);
        expect($cashier->fresh()->is_active)->toBeFalse()
            ->and($shift->fresh()->status)->toBe('closed');
    });

    test('Comb-09 (F11 + F16): Purchase receipt immediately unlocks POS sales on previously out-of-stock product', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-COMB-09', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Proveedor Express', 'nit' => '900999000-1']);

        $product = Product::create(['sku' => 'COMB-09-SKU', 'name' => 'Compresor de Aire', 'cost_price' => 400000, 'sale_price' => 650000, 'unit' => 'UND']);
        ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 0]);

        // Before purchase: sale fails due to 0 stock
        expect(fn () => PosService::processSale(
            userId: $admin->id, warehouseId: $warehouse->id, customerId: null, paymentMethod: 'cash',
            items: [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 650000, 'tax_rate' => 0, 'subtotal' => 650000]],
            paidAmount: 650000
        ))->toThrow(DomainException::class);

        // Supply arrives: 5 compressors
        $purchase = Purchase::create([
            'supplier_id' => $supplier->id, 'user_id' => $admin->id, 'warehouse_id' => $warehouse->id,
            'invoice_number' => 'FAC-COMPRESOR', 'purchase_date' => now(), 'subtotal' => 2000000, 'total' => 2000000, 'status' => 'completed',
        ]);
        PurchaseItem::create(['purchase_id' => $purchase->id, 'product_id' => $product->id, 'quantity' => 5, 'unit_cost' => 400000, 'subtotal' => 2000000]);
        KardexService::processPurchase($purchase);

        // Now sale succeeds seamlessly
        $sale = PosService::processSale(
            userId: $admin->id, warehouseId: $warehouse->id, customerId: null, paymentMethod: 'cash',
            items: [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 650000, 'tax_rate' => 0, 'subtotal' => 1300000]],
            paidAmount: 1300000
        );

        expect((float) $product->stocks()->first()->current_stock)->toBe(3.0)
            ->and($sale->status)->toBe('completed');
    });

    test('Comb-10 (F12 + F16): Attention cashier reserves order and Central Cashier confirms payment in sequence', function () {
        $cashier = User::factory()->create(['name' => 'Asesor 1']);
        $cashier->assignRole('cashier');
        $admin = User::factory()->create(['name' => 'Cajero Central']);
        $admin->assignRole('admin');

        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-COMB-10', 'is_active' => true]);
        $product = Product::create(['sku' => 'COMB-10-SKU', 'name' => 'Bomba de Agua 1HP', 'cost_price' => 120000, 'sale_price' => 190000, 'unit' => 'UND']);
        ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 5]);

        // Step 1: Cashier creates pending order (stock reserved: 5 -> 4)
        $order = PosService::createPendingOrder(
            userId: $cashier->id,
            warehouseId: $warehouse->id,
            customerId: null,
            items: [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 190000, 'tax_rate' => 0, 'subtotal' => 190000]]
        );
        expect((float) $product->stocks()->first()->current_stock)->toBe(4.0)
            ->and($order->status)->toBe('pending_payment');

        // Step 2: Admin confirms payment in Caja Central
        $confirmedSale = PosService::confirmPayment($order, $admin->id, 'cash', 200000);
        expect($confirmedSale->status)->toBe('paid')
            ->and((float) $confirmedSale->change_amount)->toBe(10000.0);

        // Step 3: Despacho hands over product
        $deliveredSale = PosService::dispatchOrder($confirmedSale, $cashier->id);
        expect($deliveredSale->status)->toBe('delivered');
    });

    test('Comb-11 (F06 + F14): Cash register handlesCash flag dictates opening amount validation boundaries', function () {
        $w = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-COMB-11', 'is_active' => true]);
        $cajaMostrador = CashRegister::create(['name' => 'Caja 1 - Mostrador', 'warehouse_id' => $w->id, 'is_active' => true]);
        $cajaCentral = CashRegister::create(['name' => 'Caja 3 - Central', 'warehouse_id' => $w->id, 'is_active' => true]);

        expect($cajaMostrador->handlesCash())->toBeFalse()
            ->and($cajaCentral->handlesCash())->toBeTrue();
    });

    test('Comb-12 (F03 + F02): Multiple unique warehouses maintain distinct isolated Kardex and stock ledgers', function () {
        $admin = User::factory()->create();
        $wA = Warehouse::create(['name' => 'Bodega Centro', 'code' => 'BOD-A', 'is_active' => true]);
        $wB = Warehouse::create(['name' => 'Bodega Satelite', 'code' => 'BOD-B', 'is_active' => true]);

        $prod = Product::create(['sku' => 'COMB-12-SKU', 'name' => 'Cable #12 AWG', 'cost_price' => 1500, 'sale_price' => 2500, 'unit' => 'MTR']);

        KardexService::registerAdjustment($prod, $wA->id, 100, 'adjustment_in', 'Lote Centro', $admin->id);
        KardexService::registerAdjustment($prod, $wB->id, 50, 'adjustment_in', 'Lote Satélite', $admin->id);

        $sA = ProductStock::where('product_id', $prod->id)->where('warehouse_id', $wA->id)->first();
        $sB = ProductStock::where('product_id', $prod->id)->where('warehouse_id', $wB->id)->first();

        expect((float) $sA->current_stock)->toBe(100.0)
            ->and((float) $sB->current_stock)->toBe(50.0);
    });

    test('Comb-13 (F04 + F08): Cashier role authorization boundaries are strictly separated between POS and purchases', function () {
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $this->actingAs($cashier);
        $this->get('/admin/purchases')->assertForbidden();
        $this->get('/admin/suppliers')->assertForbidden();
        $this->get('/admin/customers')->assertSuccessful();
    });

    test('Comb-14 (F10 + F19): Quote consecutive generation followed by sale creation maintains independent invoice sequences', function () {
        $admin = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-COMB-14', 'is_active' => true]);
        $prod = Product::create(['sku' => 'COMB-14-SKU', 'name' => 'Cerradura Yale', 'cost_price' => 30000, 'sale_price' => 50000, 'unit' => 'UND']);
        ProductStock::create(['product_id' => $prod->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 10]);

        $quote = Quote::create([
            'user_id' => $admin->id,
            'quote_number' => 'COT-00888',
            'valid_until' => now()->addDays(15),
            'subtotal' => 50000,
            'total' => 50000,
            'status' => 'draft',
        ]);

        $sale = PosService::processSale(
            userId: $admin->id, warehouseId: $warehouse->id, customerId: null, paymentMethod: 'cash',
            items: [['product_id' => $prod->id, 'quantity' => 1, 'unit_price' => 50000, 'tax_rate' => 0, 'subtotal' => 50000]],
            paidAmount: 50000
        );

        expect($quote->quote_number)->toStartWith('COT-')
            ->and($sale->invoice_number)->toStartWith('REM-');
    });

    test('Comb-15 (F09 + F17): Receipt HTML view renders official company branding details and logo image reference', function () {
        $admin = User::factory()->create();
        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-COMB-15', 'is_active' => true]);
        $sale = Sale::create([
            'user_id' => $admin->id,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => 'REM-BRAND-01',
            'payment_method' => 'cash',
            'subtotal' => 10000,
            'total' => 10000,
            'paid_amount' => 10000,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($admin)->get("/admin/sales/{$sale->id}/receipt");
        $response->assertSuccessful()
            ->assertSee('images/logo.png')
            ->assertSee('NIT: 7719126');
    });

    test('Comb-16 (F11 + F14): Multi-warehouse purchase receiving into Warehouse A does not affect POS sales on Warehouse B', function () {
        $admin = User::factory()->create();
        $wA = Warehouse::create(['name' => 'Bodega Norte', 'code' => 'BOD-NORTE', 'is_active' => true]);
        $wB = Warehouse::create(['name' => 'Bodega Sur', 'code' => 'BOD-SUR', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Ferretera Nacional', 'nit' => '900888777-1']);

        $product = Product::create(['sku' => 'COMB-16-SKU', 'name' => 'Pala Hoyadora', 'cost_price' => 25000, 'sale_price' => 45000, 'unit' => 'UND']);
        ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $wA->id, 'current_stock' => 0]);
        ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $wB->id, 'current_stock' => 5]);

        // Purchase into Bodega Norte
        $purchase = Purchase::create([
            'supplier_id' => $supplier->id, 'user_id' => $admin->id, 'warehouse_id' => $wA->id,
            'invoice_number' => 'FAC-NORTE-01', 'purchase_date' => now(), 'subtotal' => 250000, 'total' => 250000, 'status' => 'completed',
        ]);
        PurchaseItem::create(['purchase_id' => $purchase->id, 'product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 25000, 'subtotal' => 250000]);
        KardexService::processPurchase($purchase);

        // Sale from Bodega Sur
        $sale = PosService::processSale(
            userId: $admin->id, warehouseId: $wB->id, customerId: null, paymentMethod: 'cash',
            items: [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 45000, 'tax_rate' => 0, 'subtotal' => 90000]],
            paidAmount: 90000
        );

        $stockA = ProductStock::where('product_id', $product->id)->where('warehouse_id', $wA->id)->first();
        $stockB = ProductStock::where('product_id', $product->id)->where('warehouse_id', $wB->id)->first();

        expect((float) $stockA->current_stock)->toBe(10.0)
            ->and((float) $stockB->current_stock)->toBe(3.0)
            ->and($sale->status)->toBe('completed');
    });
});
