<?php

namespace App\Models;

use App\Services\ImageOptimizerService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ============================================================================
 * MODELO: Product (Producto del Catálogo de Ferretería)
 * ============================================================================
 * Representa cualquier artículo físico que la ferretería comercializa.
 * Maneja la identificación única (SKU y Código de Barras), precios,
 * costos de adquisición y relaciones con bodegas, categorías y marcas.
 */
class Product extends Model
{
    use HasFactory;

    /**
     * Columnas habilitadas para asignación masiva.
     */
    protected $fillable = [
        'category_id', // ID de la categoría (Fijaciones, Plomería, Eléctricos...)
        'brand_id',    // ID de la marca fabricante (Stanley, DeWalt, Pavco...)
        'sku',         // Código alfanumérico interno único (ej: CLAVO-2PULG)
        'barcode',     // Código de barras EAN-13 para el lector láser USB
        'name',        // Nombre comercial del producto
        'image',       // Ruta relativa de la foto optimizada en WebP
        'description', // Especificaciones técnicas y detalles
        'unit',        // Unidad de medida: UND (Unidad), MTR (Metro), KG (Kilo), BOL (Bolsa)
        'cost_price',  // Precio al que la ferretería compra al proveedor
        'sale_price',  // Precio de venta estándar al público (Detal)
        'tax_rate',    // Porcentaje de IVA aplicable (ej: 19% en Colombia)
        'is_active',   // Permite ocultar el producto sin borrar su historial
    ];

    /**
     * Conversión automática de tipos de datos.
     */
    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * EVENTO BOOTED DEL MODELO:
     * Se ejecuta automáticamente cada vez que un producto se está guardando en la BD.
     * Si detecta una nueva foto subida, la intercepta y llama a ImageOptimizerService
     * para convertirla y comprimirla a WebP en segundo plano.
     */
    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if ($product->isDirty('image') && ! empty($product->image)) {
                $product->image = ImageOptimizerService::convertToWebp($product->image);
            }
        });
    }

    /**
     * RELACIÓN: Un producto PERTENECE A una categoría.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * RELACIÓN: Un producto PERTENECE A una marca fabricante.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * RELACIÓN: Un producto TIENE MUCHOS stocks (uno por cada bodega).
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    /**
     * RELACIÓN: Un producto TIENE MUCHOS precios en listas diferenciadas.
     */
    public function priceListItems(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }

    /**
     * RELACIÓN: Kardex de movimientos de inventario.
     */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * RELACIÓN: Detalle de ventas en las que ha participado este producto.
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * RELACIÓN: Detalle de compras a proveedores de este producto.
     */
    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * LÓGICA DE NEGOCIO: Margen Bruto de Ganancia (%)
     */
    public function getProfitMarginAttribute(): float
    {
        if ($this->cost_price <= 0) {
            return 0.0;
        }

        return round((($this->sale_price - $this->cost_price) / $this->cost_price) * 100, 2);
    }

    /**
     * LÓGICA DE NEGOCIO: Stock total sumando todas las bodegas.
     */
    public function getTotalStockAttribute(): float
    {
        return (float) $this->stocks()->sum('current_stock');
    }
}
