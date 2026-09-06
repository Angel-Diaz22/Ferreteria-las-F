<?php

namespace App\Filament\Imports;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Warehouse;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

/**
 * ============================================================================
 * IMPORTADOR FILAMENT: ProductImporter (Carga Masiva Excel / CSV)
 * ============================================================================
 * ¿CÓMO PROCESA LARAVEL UN ARCHIVO DE 5.000 O 10.000 PRODUCTOS SIN CAERSE?
 * En un sistema web tradicional, subir un Excel grande satura la memoria RAM
 * (Error: "Allowed memory size exhausted").
 *
 * Filament y Laravel resuelven esto de forma brillante:
 * 1. Dividen el archivo en bloques (chunks) procesados secuencialmente.
 * 2. Validan cada fila antes de guardarla.
 * 3. Si una fila tiene error, la aíslan en la tabla `failed_import_rows` y
 *    continúan importando las demás sin abortar todo el proceso.
 */
class ProductImporter extends Importer
{
    protected static ?string $model = Product::class;

    /**
     * Mapeo de columnas entre el archivo Excel/CSV y el modelo Product.
     */
    public static function getColumns(): array
    {
        return [
            ImportColumn::make('sku')
                ->label('SKU (Código Único)')
                ->requiredMapping()
                ->rules(['required', 'max:100']),

            ImportColumn::make('barcode')
                ->label('Código de Barras')
                ->rules(['nullable', 'max:100']),

            ImportColumn::make('name')
                ->label('Nombre del Producto')
                ->requiredMapping()
                ->rules(['required', 'max:255']),

            ImportColumn::make('category')
                ->label('Categoría')
                ->relationship(resolveUsing: 'name'),

            ImportColumn::make('brand')
                ->label('Marca')
                ->relationship(resolveUsing: 'name'),

            ImportColumn::make('unit')
                ->label('Unidad de Medida')
                ->rules(['nullable', 'max:20']),

            ImportColumn::make('cost_price')
                ->label('Precio de Costo ($)')
                ->numeric()
                ->rules(['required', 'numeric', 'min:0']),

            ImportColumn::make('sale_price')
                ->label('Precio de Venta ($)')
                ->numeric()
                ->rules(['required', 'numeric', 'min:0']),

            ImportColumn::make('tax_rate')
                ->label('IVA (%)')
                ->numeric()
                ->rules(['nullable', 'numeric']),
        ];
    }

    /**
     * Resuelve si el registro se debe crear como nuevo o actualizar uno existente.
     * Si ya existe un producto con el mismo SKU, lo actualiza en vez de duplicarlo.
     */
    public function resolveRecord(): ?Product
    {
        return Product::firstOrNew([
            'sku' => $this->data['sku'],
        ]);
    }

    /**
     * Gancho posterior al guardado de cada fila del Excel:
     * Si el producto no tiene stock asignado aún en la bodega principal,
     * le creamos un registro inicial con stock en 0 o el valor que venga.
     */
    protected function afterSave(): void
    {
        $defaultWarehouse = Warehouse::where('is_active', true)->first();

        if ($defaultWarehouse && $this->record instanceof Product) {
            ProductStock::firstOrCreate(
                [
                    'product_id' => $this->record->id,
                    'warehouse_id' => $defaultWarehouse->id,
                ],
                [
                    'current_stock' => 0.00,
                    'min_stock' => 5.00,
                ]
            );
        }
    }

    /**
     * Notificación amigable que se muestra al usuario cuando termina la importación.
     */
    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Se completó la importación: '.number_format($import->successful_rows).' productos importados exitosamente.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' Sin embargo, '.number_format($failedRowsCount).' filas tuvieron errores y fueron omitidas.';
        }

        return $body;
    }
}
