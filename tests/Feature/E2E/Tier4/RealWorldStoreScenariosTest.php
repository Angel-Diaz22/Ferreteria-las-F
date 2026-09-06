<?php

use App\Filament\Pages\PosTerminal;
use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\Category;
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
use App\Models\Supplier;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\KardexService;
use App\Services\PosService;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    (new PermissionSeeder)->run();
    SystemSetting::set('iva_enabled', true, 'boolean');
});

/**
 * ============================================================================
 * TIER 4: REAL-WORLD APPLICATION SCENARIOS
 * ============================================================================
 */
describe('Tier 4: Realistic Store Workflows', function () {
    test('Scenario 1: Complete 3-Step Store POS Workflow (Mostrador -> Caja 3 -> Despacho)', function () {
        // SETUP: Roles, Warehouse, Registers, Catalog
        $cashier = User::factory()->create(['name' => 'Pedro Asesor']);
        $cashier->assignRole('cashier');
        $admin = User::factory()->create(['name' => 'Don Fernando Gerente']);
        $admin->assignRole('admin');

        $warehouse = Warehouse::create(['name' => 'Ferretería Las F - Principal', 'code' => 'BOD-PRINCIPAL', 'is_active' => true]);
        $caja1 = CashRegister::create(['name' => 'Caja 1 - Mostrador Entrada', 'warehouse_id' => $warehouse->id, 'is_active' => true]);
        $caja3 = CashRegister::create(['name' => 'Caja 3 - Central de Cobro', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        $cat = Category::create(['name' => 'Herramientas y Tornillos', 'slug' => 'herramientas-tornillos']);
        $prod1 = Product::create(['category_id' => $cat->id, 'sku' => 'MART-16', 'name' => 'Martillo Stanley 16oz', 'cost_price' => 15000, 'sale_price' => 25000, 'unit' => 'UND']);
        $prod2 = Product::create(['category_id' => $cat->id, 'sku' => 'TORN-HEX', 'name' => 'Tornillo Grado 5 Caja x100', 'cost_price' => 8000, 'sale_price' => 15000, 'unit' => 'CJA']);

        ProductStock::create(['product_id' => $prod1->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 20]);
        ProductStock::create(['product_id' => $prod2->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 15]);

        // =====================================================================
        // PASO 1: MOSTRADOR (ASESOR EN CAJA 1)
        // El asesor abre su puesto con $0 COP y genera pedido de mostrador
        // =====================================================================
        $this->actingAs($cashier);
        Livewire::test(PosTerminal::class)->call('selectCashRegister', $caja1->id);

        $shiftCaja1 = CashShift::where('cash_register_id', $caja1->id)->where('user_id', $cashier->id)->where('status', 'open')->first();
        expect((float) $shiftCaja1->opening_amount)->toBe(0.0);

        // Cliente pide 2 martillos ($50,000) y 1 caja tornillos ($15,000) = $65,000 + 19% IVA ($12,350) = $77,350
        $items = [
            ['product_id' => $prod1->id, 'quantity' => 2.0, 'unit_price' => 25000.0, 'tax_rate' => 19.0, 'subtotal' => 50000.0],
            ['product_id' => $prod2->id, 'quantity' => 1.0, 'unit_price' => 15000.0, 'tax_rate' => 19.0, 'subtotal' => 15000.0],
        ];

        $order = PosService::createPendingOrder(
            userId: $cashier->id,
            warehouseId: $warehouse->id,
            customerId: null,
            items: $items,
            notes: 'Atendido por Pedro en Caja 1',
            cashShiftId: $shiftCaja1->id
        );

        // Validaciones Paso 1:
        expect($order->status)->toBe('pending_payment')
            ->and($order->invoice_number)->toStartWith('REM-')
            ->and((float) $order->total)->toBe(77350.0);

        // El stock fue preventivamente apartado en bodega
        expect((float) $prod1->stocks()->first()->current_stock)->toBe(18.0)
            ->and((float) $prod2->stocks()->first()->current_stock)->toBe(14.0);

        // Aún NO hay movimiento de salida formal en Kardex (solo reserva)
        expect(InventoryMovement::where('reference_id', $order->id)->count())->toBe(0);

        // =====================================================================
        // PASO 2: CAJA CENTRAL (GERENCIA EN CAJA 3)
        // El cliente presenta su tirilla impresa en Caja 3 para pagar en efectivo
        // =====================================================================
        $this->actingAs($admin);
        $adminPos = Livewire::test(PosTerminal::class);
        $adminPos->assertSet('activeCashRegisterId', $caja3->id);

        $shiftCaja3 = CashShift::where('cash_register_id', $caja3->id)->where('status', 'open')->first();

        // Cliente paga con billete de $100,000 COP
        // Total: $77,350. Vueltas / Cambio: $22,650
        $paidSale = PosService::confirmPayment(
            sale: $order,
            cashierUserId: $admin->id,
            paymentMethod: 'cash',
            paidAmount: 100000.0,
            cashShiftId: $shiftCaja3->id
        );

        // Validaciones Paso 2:
        expect($paidSale->status)->toBe('paid')
            ->and((float) $paidSale->paid_amount)->toBe(100000.0)
            ->and((float) $paidSale->change_amount)->toBe(22650.0);

        // Ahora el Kardex tiene los movimientos contables registrados
        $kardexEntries = InventoryMovement::where('reference_id', $order->id)->get();
        expect($kardexEntries)->toHaveCount(2)
            ->and($kardexEntries->first()->type)->toBe('sale');

        // =====================================================================
        // PASO 3: DESPACHO / ENTREGA
        // Cliente regresa a mostrador con tirilla sellada. Se entrega mercancía.
        // =====================================================================
        $deliveredSale = PosService::dispatchOrder($paidSale, $cashier->id);

        expect($deliveredSale->status)->toBe('delivered');
    });

    test('Scenario 2: Shift Opening, Daily Sales, and Cash Arqueo & Reconciliation with Discrepancy', function () {
        $admin = User::factory()->create(['name' => 'Admin Caja']);
        $admin->assignRole('admin');

        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-SCEN-2', 'is_active' => true]);
        $caja3 = CashRegister::create(['name' => 'Caja 3 - Central', 'warehouse_id' => $warehouse->id, 'is_active' => true]);

        $prod = Product::create(['sku' => 'DISC-01', 'name' => 'Disco Sierra 7-1/4', 'cost_price' => 20000, 'sale_price' => 35000, 'unit' => 'UND']);
        ProductStock::create(['product_id' => $prod->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 50]);

        // 1. Apertura de turno con base inicial de $150,000 COP
        $shift = CashShift::create([
            'cash_register_id' => $caja3->id,
            'user_id' => $admin->id,
            'opening_amount' => 150000.00,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        // 2. Venta en efectivo: $35,000 + 19% IVA ($6,650) = $41,650. Cliente paga $50,000, cambio $8,350. Efectivo neto ingresado = $41,650
        $sale1 = PosService::processSale(
            userId: $admin->id,
            warehouseId: $warehouse->id,
            customerId: null,
            paymentMethod: 'cash',
            items: [['product_id' => $prod->id, 'quantity' => 1, 'unit_price' => 35000, 'tax_rate' => 19, 'subtotal' => 35000]],
            paidAmount: 50000,
            cashShiftId: $shift->id
        );

        // 3. Venta con tarjeta (no afecta efectivo en caja): $70,000 + IVA
        $sale2 = PosService::processSale(
            userId: $admin->id,
            warehouseId: $warehouse->id,
            customerId: null,
            paymentMethod: 'card',
            items: [['product_id' => $prod->id, 'quantity' => 2, 'unit_price' => 35000, 'tax_rate' => 19, 'subtotal' => 70000]],
            paidAmount: 83300,
            cashShiftId: $shift->id
        );

        // Efectivo esperado en gaveta: Base $150,000 + Venta efectivo $41,650 = $191,650
        $expectedCash = 150000.00 + 41650.00;

        // 4. Cierre de turno: Al contar el dinero físico hay $190,000 (Faltante de $1,650)
        $this->actingAs($admin);
        Livewire::test(PosTerminal::class)
            ->set('activeCashShiftId', $shift->id)
            ->set('activeCashRegisterId', $caja3->id)
            ->call('openCloseShiftModal')
            ->assertSet('closeShiftModalOpen', true);

        // El shift expected_amount fue calculado
        expect((float) $shift->fresh()->expected_amount)->toBe($expectedCash);

        // Admin confirma arqueo con $190,000
        Livewire::test(PosTerminal::class)
            ->set('activeCashShiftId', $shift->id)
            ->set('activeCashRegisterId', $caja3->id)
            ->set('shiftClosingAmount', '190000')
            ->set('shiftClosingNotes', 'Faltante menor por redondeo de monedas')
            ->call('confirmCloseShift');

        $shift->refresh();
        expect($shift->status)->toBe('closed')
            ->and((float) $shift->closing_amount)->toBe(190000.00)
            ->and((float) $shift->difference)->toBe(-1650.00)
            ->and($shift->notes)->toContain('Faltante menor');
    });

    test('Scenario 3: B2B Commercial Workflow: Customer Price List Tiering and Quote Conversion to Sale', function () {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-SCEN-3', 'is_active' => true]);

        // Lista de precios Mayorista
        $mayoristaList = PriceList::create(['name' => 'Tarifa Mayorista', 'is_default' => false]);
        $prod = Product::create([
            'sku' => 'TUB-PVC-3',
            'name' => 'Tubo Sanitario PVC 3in x 6m',
            'cost_price' => 18000,
            'sale_price' => 32000, // Detal
            'unit' => 'UND',
        ]);
        ProductStock::create(['product_id' => $prod->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 100]);

        // Precio preferencial mayorista: $26,000
        PriceListItem::create([
            'price_list_id' => $mayoristaList->id,
            'product_id' => $prod->id,
            'price' => 26000.0,
        ]);

        // Cliente corporativo con cupo de crédito
        $client = Customer::create([
            'price_list_id' => $mayoristaList->id,
            'name' => 'Constructora Habitat',
            'document_type' => 'NIT',
            'document' => '901234567-8',
            'credit_limit' => 10000000.0,
            'current_debt' => 0.0,
            'is_active' => true,
        ]);

        // 1. Emisión de Cotización comercial
        $quote = Quote::create([
            'customer_id' => $client->id,
            'user_id' => $admin->id,
            'quote_number' => 'COT-01050',
            'valid_until' => now()->addDays(20),
            'subtotal' => 1300000.0, // 50 tubos * $26,000
            'tax_amount' => 247000.0, // 19% IVA
            'total' => 1547000.0,
            'status' => 'sent',
            'notes' => 'Cotización proyecto Torres del Parque',
        ]);

        QuoteItem::create([
            'quote_id' => $quote->id,
            'product_id' => $prod->id,
            'quantity' => 50,
            'unit_price' => 26000.0,
            'subtotal' => 1300000.0,
        ]);

        // 2. Cliente aprueba la cotización -> Se formaliza la venta a crédito
        $sale = PosService::processSale(
            userId: $admin->id,
            warehouseId: $warehouse->id,
            customerId: $client->id,
            paymentMethod: 'credit',
            items: [[
                'product_id' => $prod->id,
                'quantity' => 50,
                'unit_price' => 26000.0,
                'tax_rate' => 19.0,
                'subtotal' => 1300000.0,
            ]],
            paidAmount: 0.0,
            notes: "Convertida desde Cotización #{$quote->quote_number}"
        );

        $quote->update(['status' => 'accepted']);

        // Verificaciones:
        expect($quote->fresh()->status)->toBe('accepted')
            ->and($sale->invoice_number)->toStartWith('REM-')
            ->and((float) $sale->total)->toBe(1547000.0);

        // Inventario descontado
        expect((float) $prod->stocks()->first()->current_stock)->toBe(50.0);

        // Deuda de cartera del cliente actualizada
        $client->refresh();
        expect((float) $client->current_debt)->toBe(1547000.0)
            ->and($client->available_credit)->toBe(8453000.0);
    });

    test('Scenario 4: Warehouse Physical Inventory Audit & Discrepancy Reconciliation', function () {
        $auditor = User::factory()->create(['name' => 'Auditor Inventario']);
        $auditor->assignRole('auditor');
        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-AUDIT-01', 'is_active' => true]);

        $itemA = Product::create(['sku' => 'AUD-A', 'name' => 'Candado Bronce 50mm', 'cost_price' => 12000, 'sale_price' => 20000, 'unit' => 'UND']);
        $itemB = Product::create(['sku' => 'AUD-B', 'name' => 'Flexómetro 5m', 'cost_price' => 6000, 'sale_price' => 12000, 'unit' => 'UND']);

        ProductStock::create(['product_id' => $itemA->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 15]);
        ProductStock::create(['product_id' => $itemB->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 30]);

        // Conteo físico revela:
        // Item A: Físico = 20 (+5 unidades sobrantes de bonificación no registrada)
        // Item B: Físico = 27 (-3 unidades rotas/mermadas)

        // 1. Ajuste de entrada para Item A (+5)
        $inAdj = KardexService::registerAdjustment(
            product: $itemA,
            warehouseId: $warehouse->id,
            quantity: 5.0,
            type: 'adjustment_in',
            notes: 'Sobrante en auditoría física estantería E3',
            userId: $auditor->id
        );

        // 2. Ajuste de salida para Item B (-3)
        $outAdj = KardexService::registerAdjustment(
            product: $itemB,
            warehouseId: $warehouse->id,
            quantity: 3.0,
            type: 'adjustment_out',
            notes: 'Merma por caja aplastada en bodega',
            userId: $auditor->id
        );

        // Verificaciones de auditoría
        expect((float) $itemA->stocks()->first()->current_stock)->toBe(20.0)
            ->and((float) $inAdj->resulting_stock)->toBe(20.0)
            ->and((float) $itemB->stocks()->first()->current_stock)->toBe(27.0)
            ->and((float) $outAdj->resulting_stock)->toBe(27.0);

        // Verificar trazabilidad en Kardex
        $kardexA = InventoryMovement::where('product_id', $itemA->id)->first();
        expect($kardexA->type)->toBe('adjustment_in')
            ->and($kardexA->notes)->toContain('Sobrante en auditoría');
    });

    test('Scenario 5: Supply Chain Restock, Weighted Cost Recalculation, and Retail Sale', function () {
        $admin = User::factory()->create(['name' => 'Jefe de Compras']);
        $admin->assignRole('admin');
        $cashier = User::factory()->create(['name' => 'Cajero']);
        $cashier->assignRole('cashier');

        $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-RESTOCK', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Cementos Argos S.A.', 'nit' => '890900123-1']);

        // 1. Producto con inventario bajo
        $cemento = Product::create([
            'sku' => 'CEM-ARGOS-50KG',
            'name' => 'Cemento Gris Uso General 50kg',
            'cost_price' => 25000.0, // Costo anterior
            'sale_price' => 34000.0,
            'unit' => 'BOL',
        ]);
        ProductStock::create(['product_id' => $cemento->id, 'warehouse_id' => $warehouse->id, 'current_stock' => 10]);

        // Inversión actual = 10 * 25,000 = $250,000

        // 2. Llega camión con 40 bultos a un nuevo costo de $28,000 ($1,120,000)
        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'user_id' => $admin->id,
            'warehouse_id' => $warehouse->id,
            'invoice_number' => 'FAC-ARGOS-9988',
            'purchase_date' => now(),
            'subtotal' => 1120000.0,
            'total' => 1120000.0,
            'status' => 'completed',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $cemento->id,
            'quantity' => 40.0,
            'unit_cost' => 28000.0,
            'subtotal' => 1120000.0,
        ]);

        KardexService::processPurchase($purchase);

        // Costo promedio esperado:
        // Total Invertido = $250,000 + $1,120,000 = $1,370,000
        // Total Unidades = 10 + 40 = 50
        // Nuevo Costo = 1,370,000 / 50 = $27,400
        expect((float) $cemento->fresh()->cost_price)->toBe(27400.0)
            ->and((float) $cemento->stocks()->first()->current_stock)->toBe(50.0);

        // 3. Venta en mostrador de 5 bultos
        $sale = PosService::processSale(
            userId: $cashier->id,
            warehouseId: $warehouse->id,
            customerId: null,
            paymentMethod: 'cash',
            items: [['product_id' => $cemento->id, 'quantity' => 5, 'unit_price' => 34000, 'tax_rate' => 19, 'subtotal' => 170000]],
            paidAmount: 210000
        );

        expect((float) $cemento->stocks()->first()->current_stock)->toBe(45.0)
            ->and($sale->status)->toBe('completed');
    });
});
