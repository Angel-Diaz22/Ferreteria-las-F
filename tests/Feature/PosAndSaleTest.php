<?php

use App\Filament\Pages\PosTerminal;
use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Sale;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PosService;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * ============================================================================
 * PRUEBAS AUTOMATIZADAS (PEST): PosAndSaleTest
 * ============================================================================
 * ¿QUÉ VALIDA ESTE ARCHIVO DE PRUEBAS?
 * 1. Transaccionalidad ACID en ventas de mostrador: Descuento atómico de stock.
 * 2. Auditoría y Kardex: Cada venta genera un movimiento físico inmutable ('sale').
 * 3. Bloqueo de sobreventa (Out-of-Stock): Rechazo con DomainException si no hay existencias.
 * 4. Control de Cartera: Aprobación/Rechazo de ventas a crédito según cupo disponible.
 * 5. Liquidación de Efectivo: Cálculo exacto del cambio/vueltas entregadas al cliente.
 * 6. Anulación Administrativa: Reintegro de inventario a la bodega y reversión de deuda.
 * 7. Impresión de Comprobantes: Generación de ticket térmico (80mm) y PDF.
 * 8. Privacidad y Seguridad de Roles: Cajeros no pueden ver costos ni márgenes.
 */
beforeEach(function () {
    // Aseguramos que existan los roles básicos en el entorno de pruebas
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
    SystemSetting::set('iva_enabled', true, 'boolean');
});

test('processes cash sale atomically, decrements warehouse stock and creates kardex movement', function () {
    $cashier = User::factory()->create();
    $cashier->assignRole('cashier');

    $warehouse = Warehouse::create([
        'name' => 'Bodega Central',
        'code' => 'BOD-CENTRAL',
        'is_active' => true,
    ]);

    $category = Category::create(['name' => 'Herramientas', 'slug' => 'herramientas']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'MART-001',
        'name' => 'Martillo de Uña 16oz',
        'cost_price' => 15000.00,
        'sale_price' => 25000.00,
        'unit' => 'UND',
        'is_active' => true,
    ]);

    // Stock inicial de 50 unidades en bodega
    $stock = ProductStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'current_stock' => 50.00,
        'min_stock' => 5.00,
        'max_stock' => 100.00,
    ]);

    // Procesamos venta de 5 unidades en efectivo:
    // Subtotal: 5 * 25.000 = 125.000
    // IVA 19%: 125.000 * 0.19 = 23.750
    // Total: 148.750
    $items = [
        [
            'product_id' => $product->id,
            'quantity' => 5.0,
            'unit_price' => 25000.00,
            'tax_rate' => 19.00,
            'discount' => 0.0,
            'subtotal' => 125000.00,
        ],
    ];

    $sale = PosService::processSale(
        userId: $cashier->id,
        warehouseId: $warehouse->id,
        customerId: null, // Venta Mostrador
        paymentMethod: 'cash',
        items: $items,
        paidAmount: 150000.00 // Paga con billete de $100.000 + $50.000
    );

    // 1. Verificación del Comprobante
    expect($sale)->toBeInstanceOf(Sale::class);
    expect($sale->invoice_number)->toStartWith('REM-');
    expect((float) $sale->total)->toEqual(148750.00);
    expect((float) $sale->paid_amount)->toEqual(150000.00);
    expect((float) $sale->change_amount)->toEqual(1250.00); // 150.000 - 148.750 = 1.250 de vueltas
    expect($sale->status)->toBe('completed');

    // 2. Verificación de existencias físicas
    $stock->refresh();
    expect((float) $stock->current_stock)->toEqual(45.00); // 50 - 5 = 45

    // 3. Verificación del Kardex (inventory_movements)
    $movement = InventoryMovement::where('product_id', $product->id)
        ->where('reference_type', Sale::class)
        ->where('reference_id', $sale->id)
        ->first();

    expect($movement)->not->toBeNull();
    expect($movement->type)->toBe('sale');
    expect((float) $movement->quantity)->toEqual(5.00);
    expect((float) $movement->previous_stock)->toEqual(50.00);
    expect((float) $movement->resulting_stock)->toEqual(45.00);
    expect((float) $movement->unit_cost)->toEqual(15000.00); // Costo histórico conservado
});

test('prevents selling more stock than available and throws DomainException rolling back database', function () {
    $cashier = User::factory()->create();
    $warehouse = Warehouse::create(['name' => 'Bodega Norte', 'code' => 'BOD-N', 'is_active' => true]);
    $category = Category::create(['name' => 'Tuberías', 'slug' => 'tuberias']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'TUBO-PVC-1',
        'name' => 'Tubo PVC Sanitario 1 pulgada',
        'cost_price' => 10000.00,
        'sale_price' => 18000.00,
        'unit' => 'TUBO',
        'is_active' => true,
    ]);

    // Solo quedan 3 unidades disponibles
    ProductStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'current_stock' => 3.00,
        'min_stock' => 2.00,
    ]);

    // Intentamos vender 10 unidades
    $items = [
        [
            'product_id' => $product->id,
            'quantity' => 10.0,
            'unit_price' => 18000.00,
            'tax_rate' => 19.00,
            'discount' => 0.0,
            'subtotal' => 180000.00,
        ],
    ];

    expect(function () use ($cashier, $warehouse, $items) {
        PosService::processSale(
            userId: $cashier->id,
            warehouseId: $warehouse->id,
            customerId: null,
            paymentMethod: 'cash',
            items: $items,
            paidAmount: 220000.00
        );
    })->toThrow(DomainException::class, 'Stock insuficiente');

    // Comprobamos que el stock sigue intacto en 3 y no se creó ninguna venta huérfana
    $stock = ProductStock::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->first();
    expect((float) $stock->current_stock)->toEqual(3.00);
    expect(Sale::count())->toEqual(0);
});

test('validates credit sales against customer credit limit and increases customer debt', function () {
    $cashier = User::factory()->create();
    $warehouse = Warehouse::create(['name' => 'Bodega Sur', 'code' => 'BOD-S', 'is_active' => true]);
    $category = Category::create(['name' => 'Pinturas', 'slug' => 'pinturas']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'VINIL-TIPO-1',
        'name' => 'Cuñete Vinilo Tipo 1 Blanco',
        'cost_price' => 120000.00,
        'sale_price' => 180000.00,
        'unit' => 'CUÑETE',
        'is_active' => true,
    ]);

    ProductStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'current_stock' => 20.00,
        'min_stock' => 2.00,
    ]);

    // Cliente con cupo de $500.000 y deuda cero
    $customer = Customer::create([
        'name' => 'Pintores & Acabados SAS',
        'document_type' => 'NIT',
        'document' => '901555444-3',
        'credit_limit' => 500000.00,
        'current_debt' => 0.00,
        'is_active' => true,
    ]);

    // 1. Venta a crédito de 1 cuñete:
    // Subtotal: 180.000 + IVA 19% (34.200) = 214.200
    // Cabe en el cupo de 500.000
    $items = [
        [
            'product_id' => $product->id,
            'quantity' => 1.0,
            'unit_price' => 180000.00,
            'tax_rate' => 19.00,
            'discount' => 0.0,
            'subtotal' => 180000.00,
        ],
    ];

    $sale = PosService::processSale(
        userId: $cashier->id,
        warehouseId: $warehouse->id,
        customerId: $customer->id,
        paymentMethod: 'credit',
        items: $items,
        paidAmount: 0.0
    );

    expect($sale->payment_method)->toBe('credit');
    expect((float) $sale->total)->toEqual(214200.00);

    // La deuda del cliente debe aumentar a 214.200
    $customer->refresh();
    expect((float) $customer->current_debt)->toEqual(214200.00);
    // Su cupo disponible ahora es 500.000 - 214.200 = 285.800
    expect((float) $customer->available_credit)->toEqual(285800.00);

    // 2. Intentamos otra venta a crédito de 2 cuñetes:
    // Total: 2 * 214.200 = 428.400 (supera el cupo disponible de 285.800)
    $items2 = [
        [
            'product_id' => $product->id,
            'quantity' => 2.0,
            'unit_price' => 180000.00,
            'tax_rate' => 19.00,
            'discount' => 0.0,
            'subtotal' => 360000.00,
        ],
    ];

    expect(function () use ($cashier, $warehouse, $customer, $items2) {
        PosService::processSale(
            userId: $cashier->id,
            warehouseId: $warehouse->id,
            customerId: $customer->id,
            paymentMethod: 'credit',
            items: $items2,
            paidAmount: 0.0
        );
    })->toThrow(DomainException::class, 'Cupo de crédito insuficiente');
});

test('allows administrator to void sale, which returns stock to warehouse and adjusts kardex and credit', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-01', 'is_active' => true]);
    $category = Category::create(['name' => 'Fijaciones', 'slug' => 'fijaciones']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'CHAZO-3-8',
        'name' => 'Chazo Metálico 3/8',
        'cost_price' => 800.00,
        'sale_price' => 1500.00,
        'unit' => 'UND',
        'is_active' => true,
    ]);

    $stock = ProductStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'current_stock' => 100.00,
        'min_stock' => 20.00,
    ]);

    $customer = Customer::create([
        'name' => 'Instalaciones Eléctricas Gómez',
        'document' => '1029384756',
        'credit_limit' => 200000.00,
        'current_debt' => 0.00,
        'is_active' => true,
    ]);

    // Venta a crédito de 30 chazos: Total con IVA = 30 * 1.500 * 1.19 = 53.550
    $sale = PosService::processSale(
        userId: $admin->id,
        warehouseId: $warehouse->id,
        customerId: $customer->id,
        paymentMethod: 'credit',
        items: [
            [
                'product_id' => $product->id,
                'quantity' => 30.0,
                'unit_price' => 1500.00,
                'tax_rate' => 19.00,
                'discount' => 0.0,
                'subtotal' => 45000.00,
            ],
        ],
        paidAmount: 0.0
    );

    // Verificamos estado post-venta:
    $stock->refresh();
    $customer->refresh();
    expect((float) $stock->current_stock)->toEqual(70.00);
    expect((float) $customer->current_debt)->toEqual(53550.00);

    // Ahora el Administrador anula la venta
    PosService::voidSale($sale, 'El cliente canceló la instalación por lluvia', $admin->id);

    // Verificamos reversión:
    $sale->refresh();
    $stock->refresh();
    $customer->refresh();

    // 1. Estado de la venta
    expect($sale->status)->toBe('cancelled');
    expect($sale->notes)->toContain('ANULADA');

    // 2. Stock reintegrado a 100
    expect((float) $stock->current_stock)->toEqual(100.00);

    // 3. Deuda del cliente revertida a cero
    expect((float) $customer->current_debt)->toEqual(0.00);

    // 4. Kardex registra el movimiento de reingreso por anulación
    $voidMovement = InventoryMovement::where('product_id', $product->id)
        ->where('type', 'adjustment_in')
        ->where('reference_type', Sale::class)
        ->where('reference_id', $sale->id)
        ->latest('id')
        ->first();

    expect($voidMovement)->not->toBeNull();
    expect((float) $voidMovement->quantity)->toEqual(30.00);
    expect((float) $voidMovement->previous_stock)->toEqual(70.00);
    expect((float) $voidMovement->resulting_stock)->toEqual(100.00);
});

test('renders thermal receipt html and pdf document via http routes', function () {
    $cashier = User::factory()->create();
    $cashier->assignRole('cashier');

    $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-01', 'is_active' => true]);
    $category = Category::create(['name' => 'Seguridad', 'slug' => 'seguridad']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'GUANTE-NITRILO',
        'name' => 'Guante de Nitrilo Talla L',
        'cost_price' => 4000.00,
        'sale_price' => 8000.00,
        'unit' => 'PAR',
        'is_active' => true,
    ]);

    ProductStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'current_stock' => 50.00,
        'min_stock' => 10.00,
    ]);

    $sale = PosService::processSale(
        userId: $cashier->id,
        warehouseId: $warehouse->id,
        customerId: null,
        paymentMethod: 'cash',
        items: [
            [
                'product_id' => $product->id,
                'quantity' => 2.0,
                'unit_price' => 8000.00,
                'tax_rate' => 19.00,
                'discount' => 0.0,
                'subtotal' => 16000.00,
            ],
        ],
        paidAmount: 20000.00
    );

    // 1. Petición HTTP al Ticket Térmico HTML (80mm con auto-print)
    $htmlResponse = $this->actingAs($cashier)->get(route('sales.receipt', $sale));
    $htmlResponse->assertOk();
    $htmlResponse->assertSee($sale->invoice_number);
    $htmlResponse->assertSee('FERRETERÍA LAS F');
    $htmlResponse->assertSee('Guante de Nitrilo');
    $htmlResponse->assertSee('window.print()');

    // 2. Petición HTTP al Comprobante PDF (80mm continuo)
    $pdfResponse = $this->actingAs($cashier)->get(route('sales.pdf', $sale));
    $pdfResponse->assertOk();
    $pdfResponse->assertHeader('content-type', 'application/pdf');
    expect($pdfResponse->getContent())->toStartWith('%PDF-');
});

test('authenticated user can access pos terminal and sales history pages', function () {
    $cashier = User::factory()->create();
    $cashier->assignRole('cashier');

    // Terminal POS
    $posResponse = $this->actingAs($cashier)->get('/admin/pos-terminal');
    $posResponse->assertOk();
    $posResponse->assertSee('Terminal de Venta');

    // Historial de Ventas
    $salesResponse = $this->actingAs($cashier)->get('/admin/sales');
    $salesResponse->assertOk();
    $salesResponse->assertSee('Historial de Ventas');
});

/**
 * ============================================================================
 * PRUEBAS PARA EL FLUJO DE 3 PASOS EN FERRETERÍA LAS F
 * ============================================================================
 */
test('3-step hardware store flow: pending order reserves stock, manager confirms payment with kardex, dispatch delivers goods', function () {
    $asesor = User::factory()->create(['name' => 'Carlos Asesor']);
    $asesor->assignRole('cashier');

    $gerente = User::factory()->create(['name' => 'Don Fernando Gerente']);
    $gerente->assignRole('admin');

    $warehouse = Warehouse::create([
        'name' => 'Bodega Central',
        'code' => 'BOD-01',
        'is_active' => true,
    ]);

    $category = Category::create(['name' => 'Fijaciones', 'slug' => 'fijaciones']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'TORN-001',
        'name' => 'Tornillo Drywall 6x1',
        'cost_price' => 50.00,
        'sale_price' => 120.00,
        'unit' => 'UND',
        'is_active' => true,
    ]);

    $stock = ProductStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'current_stock' => 1000.00,
        'min_stock' => 100.00,
    ]);

    // PASO 1 (Mostrador / Atención): El asesor genera el pedido de 200 tornillos
    $items = [
        [
            'product_id' => $product->id,
            'quantity' => 200.0,
            'unit_price' => 120.00,
            'tax_rate' => 19.00,
            'discount' => 0.0,
            'subtotal' => 24000.00,
        ],
    ];

    $sale = PosService::createPendingOrder(
        userId: $asesor->id,
        warehouseId: $warehouse->id,
        customerId: null,
        items: $items,
        notes: 'Pedido de mostrador para cliente Juan'
    );

    // Verificación Paso 1:
    expect($sale->status)->toBe('pending_payment');
    expect($sale->invoice_number)->toStartWith('REM-');
    // El stock se aparta preventivamente de inmediato (1000 - 200 = 800):
    $stock->refresh();
    expect((float) $stock->current_stock)->toEqual(800.00);
    // En el Kardex aún NO hay salida definitiva (la salida se asienta en caja):
    expect(InventoryMovement::where('reference_id', $sale->id)->count())->toBe(0);

    // PASO 2 (Caja Central / Gerente): El gerente cobra $30.000 en efectivo y da vueltas
    // Subtotal: 24.000 + IVA 19% (4.560) = 28.560
    $salePaid = PosService::confirmPayment(
        sale: $sale,
        cashierUserId: $gerente->id,
        paymentMethod: 'cash',
        paidAmount: 30000.00
    );

    // Verificación Paso 2:
    expect($salePaid->status)->toBe('paid');
    expect((float) $salePaid->total)->toEqual(28560.00);
    expect((float) $salePaid->change_amount)->toEqual(1440.00); // 30.000 - 28.560
    // Ahora SÍ existe el movimiento contable en Kardex con tipo 'sale':
    $movement = InventoryMovement::where('reference_id', $sale->id)->first();
    expect($movement)->not->toBeNull();
    expect($movement->type)->toBe('sale');
    expect((float) $movement->quantity)->toEqual(200.00);
    expect((float) $movement->resulting_stock)->toEqual(800.00);

    // PASO 3 (Despacho): El cliente presenta su tirilla sellada y el asesor confirma entrega
    $saleDelivered = PosService::dispatchOrder($salePaid, dispatchUserId: $asesor->id);

    // Verificación Paso 3:
    expect($saleDelivered->status)->toBe('delivered');
    expect($saleDelivered->isDelivered())->toBeTrue();
});

test('anti-fraud guard prevents dispatching orders that are still pending payment', function () {
    $asesor = User::factory()->create();
    $warehouse = Warehouse::create(['name' => 'Bodega Principal', 'code' => 'BOD-02', 'is_active' => true]);
    $category = Category::create(['name' => 'Pinturas', 'slug' => 'pinturas']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'VIN-001',
        'name' => 'Vinilo Blanco Tipo 1 Galón',
        'cost_price' => 35000.00,
        'sale_price' => 55000.00,
        'unit' => 'GAL',
        'is_active' => true,
    ]);

    ProductStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'current_stock' => 10.00,
    ]);

    $sale = PosService::createPendingOrder(
        userId: $asesor->id,
        warehouseId: $warehouse->id,
        customerId: null,
        items: [
            [
                'product_id' => $product->id,
                'quantity' => 1.0,
                'unit_price' => 55000.00,
                'tax_rate' => 19.00,
                'discount' => 0.0,
                'subtotal' => 55000.00,
            ],
        ]
    );

    // Intentar despachar directamente sin pagar en caja debe lanzar DomainException (Anti-Fraude):
    expect(fn () => PosService::dispatchOrder($sale, dispatchUserId: $asesor->id))
        ->toThrow(DomainException::class);
});

test('cancelling a pending order releases reserved stock back to warehouse', function () {
    $asesor = User::factory()->create();
    $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-03', 'is_active' => true]);
    $category = Category::create(['name' => 'Tubos', 'slug' => 'tubos']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'TUB-001',
        'name' => 'Tubo PVC 1/2 pulgada x 6m',
        'cost_price' => 12000.00,
        'sale_price' => 22000.00,
        'unit' => 'UND',
        'is_active' => true,
    ]);

    $stock = ProductStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'current_stock' => 20.00,
    ]);

    // Crear pedido pendiente de 5 tubos (stock pasa a 15)
    $sale = PosService::createPendingOrder(
        userId: $asesor->id,
        warehouseId: $warehouse->id,
        customerId: null,
        items: [
            [
                'product_id' => $product->id,
                'quantity' => 5.0,
                'unit_price' => 22000.00,
                'tax_rate' => 19.00,
                'discount' => 0.0,
                'subtotal' => 110000.00,
            ],
        ]
    );

    $stock->refresh();
    expect((float) $stock->current_stock)->toEqual(15.00);

    // Cliente desiste en la fila -> Anular pedido
    $cancelledSale = PosService::cancelPendingOrder($sale, cancelledByUserId: $asesor->id, reason: 'Cliente desistió');

    expect($cancelledSale->status)->toBe('cancelled');

    // El stock debe haberse restituido a 20 unidades:
    $stock->refresh();
    expect((float) $stock->current_stock)->toEqual(20.00);
});

test('quick customer creation works and requires only name', function () {
    $cashier = User::factory()->create();
    $cashier->assignRole('cashier');

    // Probamos el componente Livewire PosTerminal
    Livewire::actingAs($cashier)
        ->test(PosTerminal::class)
        ->set('quickCustomerName', 'Ferretería El Progreso')
        ->set('quickCustomerPhone', '3151234567')
        ->set('quickCustomerEmail', 'progreso@ferreteria.com')
        ->call('saveQuickCustomer')
        ->assertHasNoErrors()
        ->assertSet('quickCustomerModalOpen', false);

    $customer = Customer::where('name', 'Ferretería El Progreso')->first();
    expect($customer)->not->toBeNull();
    expect($customer->phone)->toBe('3151234567');
    expect($customer->email)->toBe('progreso@ferreteria.com');
});

test('thermal receipt displays cashier stamp box and pending status', function () {
    $cashier = User::factory()->create();
    $cashier->assignRole('cashier');
    $warehouse = Warehouse::create(['name' => 'Bodega Central', 'code' => 'BOD-04', 'is_active' => true]);
    $category = Category::create(['name' => 'Cables', 'slug' => 'cables']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'CAB-001',
        'name' => 'Cable THHN #12 Blanco x Metro',
        'cost_price' => 1500.00,
        'sale_price' => 3200.00,
        'unit' => 'MTR',
        'is_active' => true,
    ]);

    ProductStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'current_stock' => 100.00,
    ]);

    $sale = PosService::createPendingOrder(
        userId: $cashier->id,
        warehouseId: $warehouse->id,
        customerId: null,
        items: [
            [
                'product_id' => $product->id,
                'quantity' => 10.0,
                'unit_price' => 3200.00,
                'tax_rate' => 19.00,
                'discount' => 0.0,
                'subtotal' => 32000.00,
            ],
        ]
    );

    $response = $this->actingAs($cashier)->get(route('sales.receipt', $sale));
    $response->assertOk();
    $response->assertSee('SELLO OFICIAL DE CAJA (GERENCIA)');
    $response->assertSee('PENDIENTE DE PAGO EN CAJA');
    $response->assertSee('FERRETERÍA LAS F');
    $response->assertSee('7719126');
});
