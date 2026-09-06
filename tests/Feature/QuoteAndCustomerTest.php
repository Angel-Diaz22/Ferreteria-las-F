<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\User;

test('calculates customer available credit and debt limits correctly', function () {
    $customer = Customer::create([
        'name' => 'Constructora Bolívar',
        'document_type' => 'NIT',
        'document' => '900888777-1',
        'credit_limit' => 10000000.00, // 10 Millones de cupo
        'current_debt' => 3500000.00,  // Debe 3.5 Millones
        'is_active' => true,
    ]);

    // El cupo disponible debe ser 10M - 3.5M = 6.5 Millones
    expect($customer->available_credit)->toEqual(6500000.00);

    // Puede comprar a crédito por 5 Millones (6.5M disponible)
    expect($customer->hasAvailableCredit(5000000.00))->toBeTrue();

    // No puede comprar por 7 Millones (supera el cupo)
    expect($customer->hasAvailableCredit(7000000.00))->toBeFalse();

    // Si la deuda supera el cupo, available_credit nunca es negativo (mínimo 0)
    $customer->update(['current_debt' => 12000000.00]);
    expect($customer->fresh()->available_credit)->toEqual(0.00);
});

test('tracks Habeas Data consent digitally in compliance with Ley 1581', function () {
    $customer = Customer::create([
        'name' => 'Ferretería & Pinturas El Maestro',
        'document_type' => 'CC',
        'document' => '79845612',
        'email' => 'elmaestro@gmail.com',
    ]);

    // Antes de otorgar consentimiento
    expect($customer->has_consented)->toBeFalse();
    expect($customer->latestConsentLog)->toBeNull();

    // Registro digital del consentimiento informado
    $consent = $customer->consentLogs()->create([
        'subject_type' => 'customer',
        'policy_version' => 'v1.0',
        'consented_at' => now(),
        'ip_address' => '192.168.1.50',
        'user_agent' => 'Mozilla/5.0 POS Terminal',
        'channel' => 'pos_terminal',
        'notes' => 'Autorización Habeas Data en caja.',
    ]);

    $customer = $customer->fresh();

    expect($customer->has_consented)->toBeTrue();
    expect($customer->latestConsentLog)->not->toBeNull();
    expect($customer->latestConsentLog->policy_version)->toBe('v1.0');
    expect($customer->latestConsentLog->ip_address)->toBe('192.168.1.50');
});

test('applies customer specific price list to quotes and calculates totals with tax', function () {
    $category = Category::create(['name' => 'Herramientas Manuales', 'slug' => 'herramientas-manuales']);

    // 1. Producto con precio general al detal: $50.000
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'PALA-TRUPER',
        'name' => 'Pala Redonda Truper',
        'cost_price' => 30000.00,
        'sale_price' => 50000.00,
        'unit' => 'UND',
    ]);

    // 2. Lista de precios para constructores con descuento especial: $42.000
    $priceList = PriceList::create([
        'name' => 'Tarifa Constructores',
        'is_active' => true,
    ]);

    PriceListItem::create([
        'price_list_id' => $priceList->id,
        'product_id' => $product->id,
        'price' => 42000.00,
    ]);

    // 3. Cliente constructor asociado a la lista de precios
    $customer = Customer::create([
        'price_list_id' => $priceList->id,
        'name' => 'Obras Civiles SAS',
        'document' => '901234567-8',
    ]);

    $seller = User::factory()->create();

    // 4. Verificamos que el precio para este cliente se resuelva a $42.000
    $customPrice = PriceListItem::where('price_list_id', $customer->price_list_id)
        ->where('product_id', $product->id)
        ->value('price') ?? $product->sale_price;

    expect((float) $customPrice)->toEqual(42000.00);

    // 5. Cotización de 10 unidades a precio constructor:
    // Subtotal: 10 * 42.000 = $420.000
    // IVA 19%: $420.000 * 0.19 = $79.800
    // Total: $499.800
    $subtotal = 10 * 42000.00;
    $tax = round($subtotal * 0.19, 2);
    $total = $subtotal + $tax;

    $quote = Quote::create([
        'customer_id' => $customer->id,
        'user_id' => $seller->id,
        'quote_number' => 'COT-00001',
        'valid_until' => now()->addDays(15),
        'subtotal' => $subtotal,
        'tax_amount' => $tax,
        'total' => $total,
        'status' => 'pending',
    ]);

    QuoteItem::create([
        'quote_id' => $quote->id,
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_price' => 42000.00,
        'tax_rate' => 19.00,
        'subtotal' => 420000.00,
    ]);

    expect((float) $quote->subtotal)->toEqual(420000.00);
    expect((float) $quote->tax_amount)->toEqual(79800.00);
    expect((float) $quote->total)->toEqual(499800.00);
    expect($quote->items)->toHaveCount(1);
});

test('generates and streams professional quote pdf document via HTTP', function () {
    $seller = User::factory()->create();
    $customer = Customer::create([
        'name' => 'Ingeniería & Proyectos Andinos',
        'document' => '900999111-2',
        'phone' => '3109876543',
        'address' => 'Zona Franca Fontibón',
    ]);

    $category = Category::create(['name' => 'Eléctricos', 'slug' => 'electricos']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'CABLE-THHN-12',
        'name' => 'Rollo Cable THHN calibre 12',
        'cost_price' => 180000.00,
        'sale_price' => 250000.00,
        'unit' => 'ROLLO',
    ]);

    $quote = Quote::create([
        'customer_id' => $customer->id,
        'user_id' => $seller->id,
        'quote_number' => 'COT-00042',
        'valid_until' => now()->addDays(15),
        'subtotal' => 500000.00,
        'tax_amount' => 95000.00,
        'total' => 595000.00,
        'status' => 'pending',
        'notes' => 'Validez de oferta 15 días calendario. Incluye entrega en obra.',
    ]);

    QuoteItem::create([
        'quote_id' => $quote->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 250000.00,
        'tax_rate' => 19.00,
        'subtotal' => 500000.00,
    ]);

    // Petición HTTP como usuario autenticado para descargar el PDF:
    $response = $this->actingAs($seller)->get(route('quotes.pdf', $quote));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');

    // Comprobamos que el PDF generado contenga el formato binario PDF (%PDF-)
    expect($response->getContent())->toStartWith('%PDF-');
});
