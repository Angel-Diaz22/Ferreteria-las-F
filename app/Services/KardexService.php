<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Support\Facades\DB;

/**
 * ============================================================================
 * SERVICIO: KardexService (Control de Kardex y Costo Promedio Ponderado)
 * ============================================================================
 * ¿QUÉ ES EL KARDEX Y POR QUÉ ES TAN CRÍTICO EN UNA FERRETERÍA?
 * El Kardex es la bitácora financiera y física inmutable del inventario.
 * Registra cada unidad que entra o sale con su costo unitario exacto.
 *
 * ¿QUÉ ES EL COSTO PROMEDIO PONDERADO (CPP)?
 * Si compraste 10 martillos a $10.000 ($100.000) y semanas después compras
 * otros 10 martillos pero más caros a $14.000 ($140.000):
 * - No puedes decir que todos te costaron $14.000 (inflarías los costos).
 * - No puedes decir que todos te costaron $10.000 (subestimarías el costo).
 * - La norma contable exige promediar ponderadamente:
 *      Total Invertido = $100.000 + $140.000 = $240.000
 *      Total Unidades  = 10 + 10 = 20
 *      Nuevo Costo Unitario = $240.000 / 20 = $12.000
 */
class KardexService
{
    /**
     * Procesa una compra completada dentro de una transacción atómica.
     * Actualiza el inventario, registra el Kardex y recalcula el costo del producto.
     */
    public static function processPurchase(Purchase $purchase): void
    {
        // DB::transaction garantiza que todas las operaciones se guarden
        // con éxito. Si ocurre cualquier error, cancela todo (Rollback).
        DB::transaction(function () use ($purchase) {
            foreach ($purchase->items as $item) {
                self::processPurchaseItem($purchase, $item);
            }
        });
    }

    /**
     * Procesa cada ítem individual de una compra.
     */
    protected static function processPurchaseItem(Purchase $purchase, PurchaseItem $item): void
    {
        // 1. Bloqueo pesimista sobre el producto para evitar condiciones de carrera (concurrencia)
        /** @var Product $product */
        $product = Product::where('id', $item->product_id)->lockForUpdate()->firstOrFail();

        // 2. Obtener o crear el registro de stock para la bodega de destino
        /** @var ProductStock $stockRecord */
        $stockRecord = ProductStock::firstOrCreate(
            [
                'product_id' => $product->id,
                'warehouse_id' => $purchase->warehouse_id,
            ],
            [
                'current_stock' => 0.00,
                'min_stock' => 5.00,
            ]
        );

        $previousStockInWarehouse = (float) $stockRecord->current_stock;
        $quantityReceived = (float) $item->quantity;
        $unitCostInInvoice = (float) $item->unit_cost;

        // 3. CÁLCULO DEL COSTO PROMEDIO PONDERADO:
        // Considera el inventario consolidado que la ferretería ya poseía
        $currentTotalStock = max(0.0, (float) $product->stocks()->sum('current_stock'));
        $currentCost = (float) $product->cost_price;

        if ($currentTotalStock + $quantityReceived > 0) {
            $totalValorPrevio = $currentTotalStock * $currentCost;
            $totalValorNuevo = $quantityReceived * $unitCostInInvoice;
            $newWeightedCost = round(($totalValorPrevio + $totalValorNuevo) / ($currentTotalStock + $quantityReceived), 2);

            // Actualizamos el costo promedio en el producto
            $product->update([
                'cost_price' => $newWeightedCost,
            ]);
        }

        // 4. AUMENTO ATÓMICO DEL STOCK EN LA BODEGA
        $resultingStockInWarehouse = $previousStockInWarehouse + $quantityReceived;
        $stockRecord->update([
            'current_stock' => $resultingStockInWarehouse,
        ]);

        // 5. REGISTRO EN EL KARDEX (inventory_movements)
        InventoryMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $purchase->warehouse_id,
            'user_id' => $purchase->user_id ?? auth()->id(),
            'type' => 'purchase',
            'quantity' => $quantityReceived,
            'unit_cost' => $unitCostInInvoice,
            'previous_stock' => $previousStockInWarehouse,
            'resulting_stock' => $resultingStockInWarehouse,
            'reference_type' => Purchase::class,
            'reference_id' => $purchase->id,
            'notes' => "Entrada por Factura #{$purchase->invoice_number} - Proveedor: {$purchase->supplier->name}",
        ]);
    }

    /**
     * Registra un ajuste de inventario (manual, conteo físico, merma o asignación inicial).
     * Actualiza atómicamente el stock en la bodega y crea el movimiento inmutable en el Kardex.
     */
    public static function registerAdjustment(
        Product $product,
        int $warehouseId,
        float $quantity,
        string $type, // 'adjustment_in' | 'adjustment_out'
        ?string $notes = null,
        ?int $userId = null
    ): InventoryMovement {
        return DB::transaction(function () use ($product, $warehouseId, $quantity, $type, $notes, $userId) {
            /** @var ProductStock $stockRecord */
            $stockRecord = ProductStock::firstOrCreate(
                [
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouseId,
                ],
                [
                    'current_stock' => 0.00,
                    'min_stock' => 5.00,
                ]
            );

            /** @var ProductStock $stockRecord */
            $stockRecord = ProductStock::where('id', $stockRecord->id)->lockForUpdate()->firstOrFail();

            $previousStock = (float) $stockRecord->current_stock;
            $qty = abs($quantity);

            if ($type === 'adjustment_in') {
                $resultingStock = $previousStock + $qty;
            } else {
                $resultingStock = max(0.0, $previousStock - $qty);
            }

            $stockRecord->update([
                'current_stock' => $resultingStock,
            ]);

            return InventoryMovement::create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
                'user_id' => $userId ?? auth()->id(),
                'type' => $type,
                'quantity' => $qty,
                'unit_cost' => $product->cost_price,
                'previous_stock' => $previousStock,
                'resulting_stock' => $resultingStock,
                'reference_type' => ProductStock::class,
                'reference_id' => $stockRecord->id,
                'notes' => $notes ?? 'Ajuste manual de inventario en bodega.',
            ]);
        });
    }
}
