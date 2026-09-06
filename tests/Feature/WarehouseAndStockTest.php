<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Warehouse;

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
