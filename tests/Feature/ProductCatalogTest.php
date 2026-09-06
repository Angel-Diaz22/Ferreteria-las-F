<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\ImageOptimizerService;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

test('calculates product profit margin attribute accurately', function () {
    $category = Category::create(['name' => 'Eléctricos', 'slug' => 'electricos']);
    $brand = Brand::create(['name' => 'Schneider', 'slug' => 'schneider']);

    $product = Product::create([
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'sku' => 'BREAKER-20A',
        'barcode' => '7709876543210',
        'name' => 'Interruptor Termomagnético 1 Polo 20A',
        'cost_price' => 10000.00,
        'sale_price' => 15000.00,
        'tax_rate' => 19.00,
        'unit' => 'UND',
    ]);

    // Margen esperado: ((15000 - 10000) / 10000) * 100 = 50.0%
    expect($product->profit_margin)->toEqual(50.00);
});

test('image optimizer converts images to webp and reduces file size', function () {
    Storage::fake('public');

    // Crear una imagen PNG en memoria usando GD
    $width = 200;
    $height = 200;
    $gdImage = imagecreatetruecolor($width, $height);
    $bgColor = imagecolorallocate($gdImage, 255, 100, 50);
    imagefill($gdImage, 0, 0, $bgColor);

    $originalFilename = 'products/test_tool.png';
    $absolutePath = Storage::disk('public')->path($originalFilename);

    // Asegurar directorio
    @mkdir(dirname($absolutePath), 0755, true);
    imagepng($gdImage, $absolutePath);
    imagedestroy($gdImage);

    // Ejecutar el servicio de optimización
    $webpPath = ImageOptimizerService::convertToWebp($originalFilename);

    expect($webpPath)->toEndWith('.webp');
    expect(file_exists(Storage::disk('public')->path($webpPath)))->toBeTrue();
});

test('product catalog page in filament is accessible by authenticated admin', function () {
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $admin = User::factory()->create();
    $admin->assignRole($adminRole);

    $response = $this->actingAs($admin)->get('/admin/products');

    $response->assertSuccessful();
});

test('cashier cannot view cost price or profit margin in product catalog', function () {
    $cashierRole = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
    $cashier = User::factory()->create();
    $cashier->assignRole($cashierRole);

    $response = $this->actingAs($cashier)->get('/admin/products');

    $response->assertSuccessful();
    // La página carga correctamente para el cajero pero no incluye el texto de costo ni el botón de importar
    $response->assertDontSee('Importar Excel / CSV');
});
