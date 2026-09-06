<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * ============================================================================
 * SEEDER: ProductCatalogSeeder (Catálogo Real de Ferretería Las F)
 * ============================================================================
 * ¿QUÉ HACE ESTE SEEDER?
 * Crea el flujo integral y completo del catálogo comercial para pruebas:
 * 1. 8 Categorías reales de ferretería colombiana.
 * 2. 14 Marcas reconocidas del sector ferretero y de construcción.
 * 3. 5 Proveedores nacionales con NITs y contactos comerciales.
 * 4. 39 Artículos ferreteros de alta rotación con costos, precios y unidades.
 * 5. Existencias físicas (ProductStock) en Bodega Principal.
 * 6. Asiento contable en Kardex (InventoryMovement) para cada producto.
 * 7. Listas de precios Mayorista (-10%) y Constructor (-15%) en PriceListItem.
 * 8. Clientes reales con diferentes listas de precios y cupos de crédito.
 * 9. Órdenes de compra históricas (Purchase) recibidas para probar Compras.
 */
class ProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@ferreteria.com')->first()
            ?? User::first()
            ?? User::factory()->create(['name' => 'Administrador']);

        $warehouse = Warehouse::where('is_active', true)->first()
            ?? Warehouse::create([
                'name' => 'Bodega Principal',
                'code' => 'BOD-01',
                'is_active' => true,
            ]);

        // ---------------------------------------------------------------------
        // 1. LISTAS DE PRECIOS
        // ---------------------------------------------------------------------
        $detalList = PriceList::firstOrCreate(
            ['name' => 'Detal'],
            ['description' => 'Precio al público general', 'is_default' => true, 'is_active' => true]
        );

        $mayoristaList = PriceList::firstOrCreate(
            ['name' => 'Mayorista'],
            ['description' => 'Tarifa preferencial para compras por volumen (10% dto)', 'is_default' => false, 'is_active' => true]
        );

        $constructorList = PriceList::firstOrCreate(
            ['name' => 'Constructor'],
            ['description' => 'Tarifa profesional para maestros de obra y constructoras (15% dto)', 'is_default' => false, 'is_active' => true]
        );

        // ---------------------------------------------------------------------
        // 2. CATEGORÍAS DE FERRETERÍA
        // ---------------------------------------------------------------------
        $categoriesData = [
            'Herramientas Manuales' => 'Martillos, llaves, alicates, destornilladores y flexómetros',
            'Herramientas Eléctricas' => 'Taladros, pulidoras, sierras y accesorios de corte',
            'Construcción y Obra Gris' => 'Cementos, impermeabilizantes, palas y carretillas',
            'Tuberías y Plomería' => 'Tubos PVC presión y sanitario, pegamentos y grifería',
            'Materiales Eléctricos' => 'Cables THHN, breakers, tomacorrientes y bombillos LED',
            'Pinturas y Acabados' => 'Vinilos tipo 1, esmaltes, brochas, rodillos y siliconas',
            'Tornillería y Fijaciones' => 'Tornillos drywall, chazos, pernos y tuercas',
            'Cerrajería y Seguridad' => 'Cerraduras, candados, guantes y cascos de protección',
        ];

        $categories = [];
        foreach ($categoriesData as $name => $desc) {
            $categories[$name] = Category::firstOrCreate(
                ['name' => $name],
                ['slug' => Str::slug($name), 'description' => $desc]
            );
        }

        // ---------------------------------------------------------------------
        // 3. MARCAS COMERCIALES
        // ---------------------------------------------------------------------
        $brandsData = [
            'Stanley',
            'DeWalt',
            'Bosch',
            'Truper',
            'Argos',
            'Pavco Wavin',
            'Pintuco',
            'Schneider Electric',
            'Centelsa',
            '3M',
            'Yale',
            'Sika',
            'Herragro',
            'Grival / Corona',
        ];

        $brands = [];
        foreach ($brandsData as $name) {
            $brands[$name] = Brand::firstOrCreate(
                ['name' => $name],
                ['slug' => Str::slug($name), 'is_active' => true]
            );
        }

        // ---------------------------------------------------------------------
        // 4. PROVEEDORES NACIONALES
        // ---------------------------------------------------------------------
        $suppliersData = [
            [
                'name' => 'Distribuidora Ferretera Nacional S.A.S.',
                'nit' => '800123456-1',
                'phone' => '6013210000',
                'email' => 'ventas@ferreteranacional.com',
                'address' => 'Zona Industrial Montevideo, Bogotá',
                'contact_person' => 'Javier Morales',
            ],
            [
                'name' => 'Cementos Argos S.A.',
                'nit' => '890900161-3',
                'phone' => '6044440000',
                'email' => 'pedidos@argos.co',
                'address' => 'Vía 40 # 73-290, Barranquilla',
                'contact_person' => 'Claudia Restrepo',
            ],
            [
                'name' => 'Pavco Wavin Colombia S.A.S.',
                'nit' => '860007824-9',
                'phone' => '6017825000',
                'email' => 'servicio.cliente@wavin.com',
                'address' => 'Autopista Sur # 71-75, Bogotá',
                'contact_person' => 'Andrés Gómez',
            ],
            [
                'name' => 'Pinturas Pintuco S.A.S.',
                'nit' => '890900320-1',
                'phone' => '6043707000',
                'email' => 'comercial@pintuco.com',
                'address' => 'Calle 19A # 43B-41, Medellín',
                'contact_person' => 'Mauricio Londoño',
            ],
            [
                'name' => 'Stanley Black & Decker Colombia',
                'nit' => '860012345-6',
                'phone' => '6015942000',
                'email' => 'ventas.colombia@sbdinc.com',
                'address' => 'Carrera 7 # 156-68, Bogotá',
                'contact_person' => 'Luisa Fernanda Vega',
            ],
        ];

        $suppliers = [];
        foreach ($suppliersData as $sup) {
            $suppliers[$sup['name']] = Supplier::firstOrCreate(
                ['nit' => $sup['nit']],
                array_merge($sup, ['is_active' => true])
            );
        }

        // ---------------------------------------------------------------------
        // 5. ARTÍCULOS Y PRODUCTOS DEL CATÁLOGO (39 PRODUCTOS REALES)
        // ---------------------------------------------------------------------
        $productsCatalog = [
            // Herramientas Eléctricas
            [
                'sku' => 'TAL-DEW-DWD024',
                'barcode' => '770123400001',
                'name' => 'Taladro Percutor 1/2 Pulgada 650W DeWalt',
                'category' => 'Herramientas Eléctricas',
                'brand' => 'DeWalt',
                'unit' => 'UND',
                'cost_price' => 180000.00,
                'sale_price' => 245000.00,
                'stock' => 15.0,
                'min_stock' => 3.0,
            ],
            [
                'sku' => 'PUL-BOS-GWS700',
                'barcode' => '770123400002',
                'name' => 'Pulidora Angular 4-1/2 Pulgada 710W Bosch',
                'category' => 'Herramientas Eléctricas',
                'brand' => 'Bosch',
                'unit' => 'UND',
                'cost_price' => 165000.00,
                'sale_price' => 220000.00,
                'stock' => 12.0,
                'min_stock' => 2.0,
            ],
            [
                'sku' => 'SIE-CIR-SKIL',
                'barcode' => '770123400011',
                'name' => 'Sierra Circular 7-1/4 Pulgada 1400W Bosch/Skil',
                'category' => 'Herramientas Eléctricas',
                'brand' => 'Bosch',
                'unit' => 'UND',
                'cost_price' => 240000.00,
                'sale_price' => 320000.00,
                'stock' => 8.0,
                'min_stock' => 2.0,
            ],

            // Herramientas Manuales
            [
                'sku' => 'MAR-MAN-FIB-16',
                'barcode' => '770123400009',
                'name' => 'Martillo de Uña Curva 16oz Mango Fibra de Vidrio Stanley',
                'category' => 'Herramientas Manuales',
                'brand' => 'Stanley',
                'unit' => 'UND',
                'cost_price' => 22000.00,
                'sale_price' => 34000.00,
                'stock' => 45.0,
                'min_stock' => 10.0,
            ],
            [
                'sku' => 'DIS-COR-412',
                'barcode' => '770123400003',
                'name' => 'Disco de Corte Fino Metal 4-1/2 Pulgada Stanley',
                'category' => 'Herramientas Manuales',
                'brand' => 'Stanley',
                'unit' => 'UND',
                'cost_price' => 3500.00,
                'sale_price' => 5500.00,
                'stock' => 350.0,
                'min_stock' => 50.0,
            ],
            [
                'sku' => 'DIS-DIA-412',
                'barcode' => '770123400012',
                'name' => 'Disco Diamantado Segmentado Concreto 4-1/2 Truper',
                'category' => 'Herramientas Manuales',
                'brand' => 'Truper',
                'unit' => 'UND',
                'cost_price' => 14000.00,
                'sale_price' => 22000.00,
                'stock' => 75.0,
                'min_stock' => 15.0,
            ],
            [
                'sku' => 'FLEX-STA-5M',
                'barcode' => '770123400013',
                'name' => 'Flexómetro Cinta Métrica 5m x 19mm Antichoque Stanley',
                'category' => 'Herramientas Manuales',
                'brand' => 'Stanley',
                'unit' => 'UND',
                'cost_price' => 15000.00,
                'sale_price' => 24000.00,
                'stock' => 55.0,
                'min_stock' => 10.0,
            ],
            [
                'sku' => 'NIV-ALU-24',
                'barcode' => '770123400014',
                'name' => 'Nivel Tubular de Aluminio 24 Pulgadas Stanley',
                'category' => 'Herramientas Manuales',
                'brand' => 'Stanley',
                'unit' => 'UND',
                'cost_price' => 28000.00,
                'sale_price' => 42000.00,
                'stock' => 22.0,
                'min_stock' => 5.0,
            ],
            [
                'sku' => 'ALI-PRE-8',
                'barcode' => '770123400015',
                'name' => 'Alicate Universal Electricista 8 Pulgadas Aislado Truper',
                'category' => 'Herramientas Manuales',
                'brand' => 'Truper',
                'unit' => 'UND',
                'cost_price' => 18000.00,
                'sale_price' => 28000.00,
                'stock' => 35.0,
                'min_stock' => 8.0,
            ],
            [
                'sku' => 'LLA-AJU-10',
                'barcode' => '770123400016',
                'name' => 'Llave Ajustable Expansiva 10 Pulgadas Cromo Vanadio Truper',
                'category' => 'Herramientas Manuales',
                'brand' => 'Truper',
                'unit' => 'UND',
                'cost_price' => 25000.00,
                'sale_price' => 38000.00,
                'stock' => 28.0,
                'min_stock' => 6.0,
            ],
            [
                'sku' => 'JGO-DES-6',
                'barcode' => '770123400017',
                'name' => 'Juego de Destornilladores Pala y Estrella 6 Piezas Stanley',
                'category' => 'Herramientas Manuales',
                'brand' => 'Stanley',
                'unit' => 'JGO',
                'cost_price' => 32000.00,
                'sale_price' => 49000.00,
                'stock' => 20.0,
                'min_stock' => 5.0,
            ],

            // Construcción y Obra Gris
            [
                'sku' => 'CEM-GRI-50KG',
                'barcode' => '770123400018',
                'name' => 'Cemento Gris Estructural Tipo UG x 50kg Argos',
                'category' => 'Construcción y Obra Gris',
                'brand' => 'Argos',
                'unit' => 'BLS',
                'cost_price' => 28500.00,
                'sale_price' => 34000.00,
                'stock' => 450.0,
                'min_stock' => 50.0,
            ],
            [
                'sku' => 'CEM-BLA-20KG',
                'barcode' => '770123400019',
                'name' => 'Cemento Blanco Uso General x 20kg Argos',
                'category' => 'Construcción y Obra Gris',
                'brand' => 'Argos',
                'unit' => 'BLS',
                'cost_price' => 26000.00,
                'sale_price' => 33000.00,
                'stock' => 120.0,
                'min_stock' => 20.0,
            ],
            [
                'sku' => 'MAS-PLA-CUN',
                'barcode' => '770123400020',
                'name' => 'Masilla Plástica Lista para Drywall Cuñete 5 Galones Gyplac',
                'category' => 'Construcción y Obra Gris',
                'brand' => 'Sika',
                'unit' => 'CNT',
                'cost_price' => 48000.00,
                'sale_price' => 65000.00,
                'stock' => 35.0,
                'min_stock' => 8.0,
            ],
            [
                'sku' => 'SIK-IMP-4KG',
                'barcode' => '770123400021',
                'name' => 'Aditivo Impermeabilizante Integral Sika-1 x 4kg',
                'category' => 'Construcción y Obra Gris',
                'brand' => 'Sika',
                'unit' => 'GLN',
                'cost_price' => 32000.00,
                'sale_price' => 45000.00,
                'stock' => 50.0,
                'min_stock' => 10.0,
            ],
            [
                'sku' => 'PAL-RED-CAB',
                'barcode' => '770123400022',
                'name' => 'Pala Redonda Mango Largo Herragro con Cabo Madera',
                'category' => 'Construcción y Obra Gris',
                'brand' => 'Herragro',
                'unit' => 'UND',
                'cost_price' => 35000.00,
                'sale_price' => 52000.00,
                'stock' => 30.0,
                'min_stock' => 6.0,
            ],
            [
                'sku' => 'BAR-DES-18',
                'barcode' => '770123400023',
                'name' => 'Barra de Uña Palanca Desencofradora 18 Pulgadas Herragro',
                'category' => 'Construcción y Obra Gris',
                'brand' => 'Herragro',
                'unit' => 'UND',
                'cost_price' => 24000.00,
                'sale_price' => 36000.00,
                'stock' => 25.0,
                'min_stock' => 5.0,
            ],
            [
                'sku' => 'CAR-OBR-TRU',
                'barcode' => '770123400024',
                'name' => 'Carretilla Obra Reforzada 6 Pies Cúbicos Llanta Neumática Truper',
                'category' => 'Construcción y Obra Gris',
                'brand' => 'Truper',
                'unit' => 'UND',
                'cost_price' => 195000.00,
                'sale_price' => 265000.00,
                'stock' => 12.0,
                'min_stock' => 3.0,
            ],

            // Tuberías y Plomería
            [
                'sku' => 'TUB-PVC-12',
                'barcode' => '770123400004',
                'name' => 'Tubo PVC Presión 1/2 pulgada x 6 metros Pavco',
                'category' => 'Tuberías y Plomería',
                'brand' => 'Pavco Wavin',
                'unit' => 'UND',
                'cost_price' => 14000.00,
                'sale_price' => 21000.00,
                'stock' => 110.0,
                'min_stock' => 20.0,
            ],
            [
                'sku' => 'TUB-SAN-2',
                'barcode' => '770123400025',
                'name' => 'Tubo PVC Sanitario 2 Pulgadas x 6 Metros Pavco',
                'category' => 'Tuberías y Plomería',
                'brand' => 'Pavco Wavin',
                'unit' => 'UND',
                'cost_price' => 26000.00,
                'sale_price' => 38000.00,
                'stock' => 75.0,
                'min_stock' => 15.0,
            ],
            [
                'sku' => 'SOL-PVC-250',
                'barcode' => '770123400005',
                'name' => 'Soldadura Líquida PVC Pegacor Transparente 250ml Pavco',
                'category' => 'Tuberías y Plomería',
                'brand' => 'Pavco Wavin',
                'unit' => 'UND',
                'cost_price' => 11500.00,
                'sale_price' => 16500.00,
                'stock' => 70.0,
                'min_stock' => 15.0,
            ],
            [
                'sku' => 'CIN-TEF-34',
                'barcode' => '770123400026',
                'name' => 'Cinta Teflón Sellador de Roscas 3/4 Pulgada x 10m',
                'category' => 'Tuberías y Plomería',
                'brand' => 'Pavco Wavin',
                'unit' => 'RLL',
                'cost_price' => 1200.00,
                'sale_price' => 2500.00,
                'stock' => 220.0,
                'min_stock' => 40.0,
            ],
            [
                'sku' => 'GRI-LAV-CIE',
                'barcode' => '770123400027',
                'name' => 'Grifería Lavaplatos Monocontrol Flexible Cuello Cisne Cromo Grival',
                'category' => 'Tuberías y Plomería',
                'brand' => 'Grival / Corona',
                'unit' => 'UND',
                'cost_price' => 85000.00,
                'sale_price' => 125000.00,
                'stock' => 18.0,
                'min_stock' => 4.0,
            ],

            // Materiales Eléctricos
            [
                'sku' => 'CAB-THHN-12-BLA',
                'barcode' => '770123400006',
                'name' => 'Cable Eléctrico 7 Hilos Calibre 12 Blanco x Metro Centelsa',
                'category' => 'Materiales Eléctricos',
                'brand' => 'Centelsa',
                'unit' => 'MTR',
                'cost_price' => 2800.00,
                'sale_price' => 4200.00,
                'stock' => 850.0,
                'min_stock' => 100.0,
            ],
            [
                'sku' => 'CAB-THHN-14-NEG',
                'barcode' => '770123400028',
                'name' => 'Cable Eléctrico 7 Hilos Calibre 14 Negro x Metro Centelsa',
                'category' => 'Materiales Eléctricos',
                'brand' => 'Centelsa',
                'unit' => 'MTR',
                'cost_price' => 2100.00,
                'sale_price' => 3300.00,
                'stock' => 900.0,
                'min_stock' => 100.0,
            ],
            [
                'sku' => 'BRE-SQU-20A',
                'barcode' => '770123400007',
                'name' => 'Interruptor Automático Breaker Enchufable 1x20A Schneider',
                'category' => 'Materiales Eléctricos',
                'brand' => 'Schneider Electric',
                'unit' => 'UND',
                'cost_price' => 14000.00,
                'sale_price' => 22000.00,
                'stock' => 80.0,
                'min_stock' => 15.0,
            ],
            [
                'sku' => 'CIN-AIS-3M',
                'barcode' => '770123400008',
                'name' => 'Cinta Aislante Eléctrica Scotch 3M Negra 20m',
                'category' => 'Materiales Eléctricos',
                'brand' => '3M',
                'unit' => 'UND',
                'cost_price' => 4200.00,
                'sale_price' => 6800.00,
                'stock' => 140.0,
                'min_stock' => 25.0,
            ],
            [
                'sku' => 'TOM-PO-SCH',
                'barcode' => '770123400029',
                'name' => 'Tomacorriente Doble con Polo a Tierra Blanco Schneider',
                'category' => 'Materiales Eléctricos',
                'brand' => 'Schneider Electric',
                'unit' => 'UND',
                'cost_price' => 7500.00,
                'sale_price' => 12000.00,
                'stock' => 95.0,
                'min_stock' => 20.0,
            ],
            [
                'sku' => 'BOM-LED-9W',
                'barcode' => '770123400030',
                'name' => 'Bombillo LED Ahorrador 9W E27 Luz Blanca 6500K Sylvania',
                'category' => 'Materiales Eléctricos',
                'brand' => 'Schneider Electric',
                'unit' => 'UND',
                'cost_price' => 4500.00,
                'sale_price' => 7500.00,
                'stock' => 160.0,
                'min_stock' => 30.0,
            ],

            // Pinturas y Acabados
            [
                'sku' => 'VIN-BLA-GLN',
                'barcode' => '770123400031',
                'name' => 'Pintura Vinilo Tipo 1 Blanco Nieve Galón Pintuco',
                'category' => 'Pinturas y Acabados',
                'brand' => 'Pintuco',
                'unit' => 'GLN',
                'cost_price' => 38000.00,
                'sale_price' => 55000.00,
                'stock' => 65.0,
                'min_stock' => 12.0,
            ],
            [
                'sku' => 'ESM-NEG-GLN',
                'barcode' => '770123400032',
                'name' => 'Esmalte Sintético Pintulux Negro Brillante Galón Pintuco',
                'category' => 'Pinturas y Acabados',
                'brand' => 'Pintuco',
                'unit' => 'GLN',
                'cost_price' => 45000.00,
                'sale_price' => 68000.00,
                'stock' => 32.0,
                'min_stock' => 8.0,
            ],
            [
                'sku' => 'BRO-MON-3',
                'barcode' => '770123400033',
                'name' => 'Brocha Mona Profesional Cerdas Finas 3 Pulgadas Corona',
                'category' => 'Pinturas y Acabados',
                'brand' => 'Grival / Corona',
                'unit' => 'UND',
                'cost_price' => 6500.00,
                'sale_price' => 10500.00,
                'stock' => 80.0,
                'min_stock' => 15.0,
            ],
            [
                'sku' => 'ROD-FEL-9',
                'barcode' => '770123400034',
                'name' => 'Rodillo Epóxico Felpa Antigoteo 9 Pulgadas con Maneral Pintuco',
                'category' => 'Pinturas y Acabados',
                'brand' => 'Pintuco',
                'unit' => 'UND',
                'cost_price' => 11000.00,
                'sale_price' => 17500.00,
                'stock' => 55.0,
                'min_stock' => 10.0,
            ],
            [
                'sku' => 'SIL-TRA-280',
                'barcode' => '770123400035',
                'name' => 'Silicona Transparente Multiuso Antihongos Tubo 280ml Sika',
                'category' => 'Pinturas y Acabados',
                'brand' => 'Sika',
                'unit' => 'TBO',
                'cost_price' => 12500.00,
                'sale_price' => 19000.00,
                'stock' => 85.0,
                'min_stock' => 15.0,
            ],

            // Tornillería y Fijaciones
            [
                'sku' => 'TOR-ENS-6X1',
                'barcode' => '770123400010',
                'name' => 'Tornillo Drywall Ensamble 6 x 1 Pulgada x 100 Unds Stanley',
                'category' => 'Tornillería y Fijaciones',
                'brand' => 'Stanley',
                'unit' => 'CJA',
                'cost_price' => 4500.00,
                'sale_price' => 7500.00,
                'stock' => 240.0,
                'min_stock' => 30.0,
            ],
            [
                'sku' => 'CHA-TOR-14',
                'barcode' => '770123400036',
                'name' => 'Chazo Plástico con Tornillo 1/4 Pulgada x 100 Unds Truper',
                'category' => 'Tornillería y Fijaciones',
                'brand' => 'Truper',
                'unit' => 'CJA',
                'cost_price' => 5500.00,
                'sale_price' => 9000.00,
                'stock' => 170.0,
                'min_stock' => 25.0,
            ],

            // Cerrajería y Seguridad
            [
                'sku' => 'CER-EMB-YAL',
                'barcode' => '770123400037',
                'name' => 'Cerradura de Seguridad Embutir Cilindro Doble Llave Yale',
                'category' => 'Cerrajería y Seguridad',
                'brand' => 'Yale',
                'unit' => 'UND',
                'cost_price' => 65000.00,
                'sale_price' => 95000.00,
                'stock' => 28.0,
                'min_stock' => 5.0,
            ],
            [
                'sku' => 'CAN-HIE-50',
                'barcode' => '770123400038',
                'name' => 'Candado de Seguridad Hierro Macizo 50mm con 3 Llaves Yale',
                'category' => 'Cerrajería y Seguridad',
                'brand' => 'Yale',
                'unit' => 'UND',
                'cost_price' => 26000.00,
                'sale_price' => 39000.00,
                'stock' => 40.0,
                'min_stock' => 8.0,
            ],
            [
                'sku' => 'GUA-VAQ-ING',
                'barcode' => '770123400039',
                'name' => 'Guante de Vaqueta Ingeniero Reforzado Cuero Carnaza Truper',
                'category' => 'Cerrajería y Seguridad',
                'brand' => 'Truper',
                'unit' => 'PAR',
                'cost_price' => 9000.00,
                'sale_price' => 15000.00,
                'stock' => 110.0,
                'min_stock' => 20.0,
            ],
        ];

        foreach ($productsCatalog as $pData) {
            $cat = $categories[$pData['category']];
            $brd = $brands[$pData['brand']];

            /** @var Product $product */
            $product = Product::updateOrCreate(
                ['sku' => $pData['sku']],
                [
                    'category_id' => $cat->id,
                    'brand_id' => $brd->id,
                    'barcode' => $pData['barcode'],
                    'name' => $pData['name'],
                    'description' => "Artículo de alta calidad para ferretería y construcción de la marca {$brd->name}.",
                    'unit' => $pData['unit'],
                    'cost_price' => $pData['cost_price'],
                    'sale_price' => $pData['sale_price'],
                    'tax_rate' => 19.00,
                    'is_active' => true,
                ]
            );

            // Existencias en Bodega
            ProductStock::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                ],
                [
                    'current_stock' => $pData['stock'],
                    'min_stock' => $pData['min_stock'],
                    'max_stock' => $pData['stock'] * 4,
                ]
            );

            // Registro en Kardex (para que el Kardex y CPP tengan historia contable completa)
            InventoryMovement::firstOrCreate(
                [
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'type' => 'adjustment_in',
                    'reference_type' => ProductStock::class,
                ],
                [
                    'user_id' => $admin->id,
                    'quantity' => $pData['stock'],
                    'unit_cost' => $pData['cost_price'],
                    'previous_stock' => 0.00,
                    'resulting_stock' => $pData['stock'],
                    'notes' => 'Carga de inventario inicial para pruebas del catálogo de Ferretería Las F',
                ]
            );

            // Tarifa Mayorista (~10% descuento redondeado)
            $mayoristaPrice = round($pData['sale_price'] * 0.90, -2);
            PriceListItem::updateOrCreate(
                [
                    'price_list_id' => $mayoristaList->id,
                    'product_id' => $product->id,
                ],
                ['price' => $mayoristaPrice]
            );

            // Tarifa Constructor (~15% descuento redondeado)
            $constructorPrice = round($pData['sale_price'] * 0.85, -2);
            PriceListItem::updateOrCreate(
                [
                    'price_list_id' => $constructorList->id,
                    'product_id' => $product->id,
                ],
                ['price' => $constructorPrice]
            );
        }

        // ---------------------------------------------------------------------
        // 6. CLIENTES REALES CON DIFERENTES LISTAS Y CUPOS DE CRÉDITO
        // ---------------------------------------------------------------------
        $customersData = [
            [
                'name' => 'Constructora Bolívar S.A.S.',
                'document_type' => 'NIT',
                'document' => '900123987-5',
                'phone' => '3151234567',
                'email' => 'compras@constructorabolivar.com',
                'address' => 'Av. El Dorado # 68-45, Bogotá',
                'city' => 'Bogotá',
                'credit_limit' => 15000000.00,
                'current_debt' => 3200000.00,
                'price_list_id' => $constructorList->id,
            ],
            [
                'name' => 'Maestro Juan David Pérez',
                'document_type' => 'CC',
                'document' => '79845123',
                'phone' => '3145558899',
                'email' => 'maestrojuanperez@gmail.com',
                'address' => 'Calle 45 # 12-30',
                'city' => 'Colombia',
                'credit_limit' => 4000000.00,
                'current_debt' => 0.00,
                'price_list_id' => $constructorList->id,
            ],
            [
                'name' => 'Obras & Estructuras Civiles S.A.S.',
                'document_type' => 'NIT',
                'document' => '901456789-2',
                'phone' => '3004443322',
                'email' => 'adquisiciones@obrasciviles.com',
                'address' => 'Carrera 50 # 100-20',
                'city' => 'Colombia',
                'credit_limit' => 20000000.00,
                'current_debt' => 4500000.00,
                'price_list_id' => $mayoristaList->id,
            ],
            [
                'name' => 'Ferretería El Roble de la 21',
                'document_type' => 'NIT',
                'document' => '900333222-1',
                'phone' => '3128889900',
                'email' => 'elroble21@ferreteria.com',
                'address' => 'Calle 21 # 50-12',
                'city' => 'Colombia',
                'credit_limit' => 8000000.00,
                'current_debt' => 1200000.00,
                'price_list_id' => $mayoristaList->id,
            ],
            [
                'name' => 'María Gladys Gómez',
                'document_type' => 'CC',
                'document' => '52147896',
                'phone' => '3102223344',
                'email' => 'mariagomez@hotmail.com',
                'address' => 'Barrio Los Olivos',
                'city' => 'Colombia',
                'credit_limit' => 1000000.00,
                'current_debt' => 0.00,
                'price_list_id' => $detalList->id,
            ],
        ];

        foreach ($customersData as $cData) {
            $customer = Customer::updateOrCreate(
                ['document' => $cData['document']],
                array_merge($cData, ['is_active' => true])
            );

            if (! $customer->has_consented) {
                $customer->consentLogs()->create([
                    'subject_type' => 'customer',
                    'policy_version' => 'v1.0',
                    'consented_at' => now(),
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'System Seeder',
                    'channel' => 'pos_terminal',
                    'notes' => 'Autorización Habeas Data Ley 1581 registrada por Seeder.',
                ]);
            }
        }

        // ---------------------------------------------------------------------
        // 7. COMPRAS HISTÓRICAS DE MUESTRA (PARA VER EL MÓDULO DE COMPRAS ACTIVO)
        // ---------------------------------------------------------------------
        $argosSup = $suppliers['Cementos Argos S.A.'] ?? Supplier::first();
        if ($argosSup) {
            $cemento = Product::where('sku', 'CEM-GRI-50KG')->first();
            if ($cemento) {
                $purchase = Purchase::firstOrCreate(
                    ['invoice_number' => 'FAC-ARGOS-8821'],
                    [
                        'supplier_id' => $argosSup->id,
                        'user_id' => $admin->id,
                        'warehouse_id' => $warehouse->id,
                        'purchase_date' => now()->subDays(5),
                        'subtotal' => 2850000.00,
                        'tax_amount' => 541500.00,
                        'total' => 3391500.00,
                        'status' => 'completed',
                        'notes' => 'Compra de 100 bultos de cemento gris para stock.',
                    ]
                );

                PurchaseItem::firstOrCreate(
                    ['purchase_id' => $purchase->id, 'product_id' => $cemento->id],
                    [
                        'quantity' => 100.00,
                        'unit_cost' => 28500.00,
                        'tax_rate' => 19.00,
                        'subtotal' => 2850000.00,
                    ]
                );
            }
        }

        $sbdSup = $suppliers['Stanley Black & Decker Colombia'] ?? Supplier::first();
        if ($sbdSup) {
            $taladro = Product::where('sku', 'TAL-DEW-DWD024')->first();
            if ($taladro) {
                $purchase2 = Purchase::firstOrCreate(
                    ['invoice_number' => 'FAC-SBD-4412'],
                    [
                        'supplier_id' => $sbdSup->id,
                        'user_id' => $admin->id,
                        'warehouse_id' => $warehouse->id,
                        'purchase_date' => now()->subDays(2),
                        'subtotal' => 900000.00,
                        'tax_amount' => 171000.00,
                        'total' => 1071000.00,
                        'status' => 'completed',
                        'notes' => 'Compra de 5 taladros percutores DeWalt.',
                    ]
                );

                PurchaseItem::firstOrCreate(
                    ['purchase_id' => $purchase2->id, 'product_id' => $taladro->id],
                    [
                        'quantity' => 5.00,
                        'unit_cost' => 180000.00,
                        'tax_rate' => 19.00,
                        'subtotal' => 900000.00,
                    ]
                );
            }
        }
    }
}
