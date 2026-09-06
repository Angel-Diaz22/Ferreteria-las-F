<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SystemSetting;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * ============================================================================
 * SERVICIO: PosService (Motor Atómico de Ventas de Mostrador)
 * ============================================================================
 * ¿QUÉ HACE ESTE SERVICIO?
 * Coordina en una sola transacción ACID infalible:
 * 1. Generación del comprobante de venta interno correlativo (REM-XXXXXX).
 * 2. Validación de disponibilidad de stock con bloqueo pesimista (lockForUpdate)
 *    para evitar que dos cajeros vendan la misma unidad al tiempo (concurrencia).
 * 3. Validación de crédito y cupo de cartera del cliente si la venta es a plazo.
 * 4. Descuento físico de unidades en la bodega seleccionada.
 * 5. Registro contable inmediato en el Kardex (inventory_movements) con tipo 'sale'.
 * 6. Cálculo matemático de cambio / vueltas.
 */
class PosService
{
    /**
     * Procesa una venta de mostrador completa de forma atómica.
     *
     * @param  array<int, array{product_id: int, quantity: float, unit_price: float, tax_rate?: float, discount?: float, subtotal: float}>  $items
     */
    public static function processSale(
        int $userId,
        int $warehouseId,
        ?int $customerId,
        string $paymentMethod,
        array $items,
        float $paidAmount,
        ?string $notes = null,
        ?int $cashShiftId = null
    ): Sale {
        if (empty($items)) {
            throw new DomainException('No se pueden procesar ventas sin artículos en el carrito.');
        }

        return DB::transaction(function () use (
            $userId,
            $warehouseId,
            $customerId,
            $paymentMethod,
            $items,
            $paidAmount,
            $notes,
            $cashShiftId
        ) {
            // 1. CÁLCULO DE TOTALES Y VALIDACIÓN PREVIA DE STOCK
            $subtotal = 0.0;
            $taxAmount = 0.0;
            $discountAmount = 0.0;
            $isIvaEnabled = SystemSetting::isIvaEnabled();
            $defaultTaxRate = $isIvaEnabled ? SystemSetting::getIvaRate() : 0.0;

            foreach ($items as $item) {
                $qty = (float) ($item['quantity'] ?? 0);
                $price = (float) ($item['unit_price'] ?? 0);
                $taxRate = array_key_exists('tax_rate', $item)
                    ? (float) $item['tax_rate']
                    : ($isIvaEnabled ? $defaultTaxRate : 0.0);
                $disc = (float) ($item['discount'] ?? 0.0);

                if ($qty <= 0) {
                    throw new DomainException('La cantidad de cada artículo debe ser mayor a cero.');
                }

                if ($price < 0) {
                    throw new DomainException('El precio unitario no puede ser negativo.');
                }

                if ($disc < 0) {
                    throw new DomainException('El descuento no puede ser negativo.');
                }

                if ($disc > round($qty * $price, 2)) {
                    throw new DomainException('El descuento no puede superar el valor total del producto.');
                }

                if ($taxRate < 0) {
                    throw new DomainException('La tasa de impuesto no puede ser negativa.');
                }

                // Bloqueo pesimista sobre el stock en la bodega de despacho:
                /** @var ProductStock|null $stockRecord */
                $stockRecord = ProductStock::where('product_id', $item['product_id'])
                    ->where('warehouse_id', $warehouseId)
                    ->lockForUpdate()
                    ->first();

                $availableStock = (float) ($stockRecord?->current_stock ?? 0.0);
                if ($availableStock < $qty) {
                    $productName = Product::where('id', $item['product_id'])->value('name') ?? 'Producto';
                    throw new DomainException("Stock insuficiente para '{$productName}'. Disponible en bodega: {$availableStock}. Solicitado: {$qty}.");
                }

                $lineSubtotal = round(($qty * $price) - $disc, 2);
                $lineTax = $isIvaEnabled ? round($lineSubtotal * ($taxRate / 100), 2) : 0.0;

                $subtotal += $lineSubtotal;
                $taxAmount += $lineTax;
                $discountAmount += $disc;
            }

            $total = round($subtotal + $taxAmount, 2);

            // 2. VALIDACIÓN DE VENTA A CRÉDITO (CARTERA)
            $customer = null;
            if ($customerId) {
                $customer = Customer::where('id', $customerId)->lockForUpdate()->first();
            }

            if ($paymentMethod === 'credit') {
                if (! $customer) {
                    throw new DomainException('Las ventas a crédito requieren seleccionar un cliente registrado (no permitido para Cliente Mostrador).');
                }

                if (! $customer->is_active) {
                    throw new DomainException("El cliente '{$customer->name}' se encuentra inactivo.");
                }

                if (! $customer->hasAvailableCredit($total)) {
                    $available = number_format($customer->available_credit, 0, ',', '.');
                    $totalFormatted = number_format($total, 0, ',', '.');
                    throw new DomainException("Cupo de crédito insuficiente para {$customer->name}. Cupo disponible: \${$available} COP. Total venta: \${$totalFormatted} COP.");
                }

                // Aumentamos la deuda actual del cliente por el valor fiado
                $customer->current_debt = (float) $customer->current_debt + $total;
                $customer->save();

                $paidAmount = 0.0;
                $changeAmount = 0.0;
            } elseif ($paymentMethod === 'cash') {
                if ($paidAmount < $total) {
                    $missing = number_format($total - $paidAmount, 0, ',', '.');
                    throw new DomainException("Monto recibido insuficiente. Faltan \${$missing} COP para cubrir el total de la venta.");
                }
                $changeAmount = round($paidAmount - $total, 2);
            } else {
                // Tarjeta / Transferencia
                $paidAmount = $total;
                $changeAmount = 0.0;
            }

            // 3. GENERAR CONSECUTIVO ÚNICO DE COMPROBANTE DE VENTA (REM-XXXXXX)
            $lastSale = Sale::orderByDesc('id')->lockForUpdate()->first();
            $nextNumber = ($lastSale?->id ?? 0) + 1;
            do {
                $invoiceNumber = 'REM-'.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
                $nextNumber++;
            } while (Sale::where('invoice_number', $invoiceNumber)->exists());

            // 4. INSERTAR ENCABEZADO DE LA VENTA
            $sale = Sale::create([
                'customer_id' => $customerId,
                'user_id' => $userId,
                'warehouse_id' => $warehouseId,
                'cash_shift_id' => $cashShiftId,
                'invoice_number' => $invoiceNumber,
                'payment_method' => $paymentMethod,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'total' => $total,
                'paid_amount' => $paidAmount,
                'change_amount' => $changeAmount,
                'status' => 'completed',
                'notes' => $notes,
            ]);

            // 5. INSERTAR ÍTEMS, DESCONTAR STOCK Y REGISTRAR KARDEX
            foreach ($items as $item) {
                /** @var Product $product */
                $product = Product::findOrFail($item['product_id']);

                /** @var ProductStock $stockRecord */
                $stockRecord = ProductStock::where('product_id', $product->id)
                    ->where('warehouse_id', $warehouseId)
                    ->firstOrFail();

                $qty = (float) $item['quantity'];
                $price = (float) $item['unit_price'];
                $taxRate = $isIvaEnabled ? (float) ($item['tax_rate'] ?? $defaultTaxRate) : 0.0;
                $disc = (float) ($item['discount'] ?? 0.0);
                $lineSubtotal = round(($qty * $price) - $disc, 2);

                $previousStock = (float) $stockRecord->current_stock;
                $resultingStock = max(0.0, $previousStock - $qty);

                // Actualizar existencia física en la bodega
                $stockRecord->update([
                    'current_stock' => $resultingStock,
                ]);

                // Guardar ítem de venta
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_cost' => $product->cost_price, // Registra el costo de compra en ese instante para calcular utilidad
                    'unit_price' => $price,
                    'tax_rate' => $taxRate,
                    'discount' => $disc,
                    'subtotal' => $lineSubtotal,
                ]);

                // Registrar salida en el Kardex
                InventoryMovement::create([
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouseId,
                    'user_id' => $userId,
                    'type' => 'sale',
                    'quantity' => $qty,
                    'unit_cost' => $product->cost_price,
                    'previous_stock' => $previousStock,
                    'resulting_stock' => $resultingStock,
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'notes' => "Salida por Venta Comprobante #{$sale->invoice_number}".($customer ? " a {$customer->name}" : ' - Mostrador'),
                ]);
            }

            return $sale;
        });
    }

    /**
     * Anula una venta previamente completada (Solo administradores).
     * Reintegra las existencias al inventario, registra el reingreso en Kardex
     * y descuenta la deuda si fue a crédito.
     */
    public static function voidSale(Sale $sale, string $reason, int $userId): void
    {
        DB::transaction(function () use ($sale, $reason, $userId) {
            /** @var Sale $lockedSale */
            $lockedSale = Sale::where('id', $sale->id)->lockForUpdate()->firstOrFail();

            if ($lockedSale->status === 'cancelled') {
                throw new DomainException('Esta venta ya se encuentra anulada.');
            }

            // 1. Reintegrar stock y registrar en Kardex cada ítem
            foreach ($lockedSale->items as $item) {
                /** @var ProductStock $stockRecord */
                $stockRecord = ProductStock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $lockedSale->warehouse_id)
                    ->lockForUpdate()
                    ->first();

                if (! $stockRecord) {
                    $stockRecord = ProductStock::create([
                        'product_id' => $item->product_id,
                        'warehouse_id' => $lockedSale->warehouse_id,
                        'current_stock' => 0.00,
                        'min_stock' => 5.00,
                    ]);
                }

                $previousStock = (float) $stockRecord->current_stock;
                $resultingStock = $previousStock + (float) $item->quantity;

                $stockRecord->update([
                    'current_stock' => $resultingStock,
                ]);

                InventoryMovement::create([
                    'product_id' => $item->product_id,
                    'warehouse_id' => $lockedSale->warehouse_id,
                    'user_id' => $userId,
                    'type' => 'adjustment_in',
                    'quantity' => (float) $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'previous_stock' => $previousStock,
                    'resulting_stock' => $resultingStock,
                    'reference_type' => Sale::class,
                    'reference_id' => $lockedSale->id,
                    'notes' => "Reintegro por Anulación de Venta #{$lockedSale->invoice_number}. Motivo: {$reason}",
                ]);
            }

            // 2. Si la venta fue a crédito, descontar la deuda al cliente
            if ($lockedSale->payment_method === 'credit' && $lockedSale->customer_id) {
                $customer = Customer::where('id', $lockedSale->customer_id)->lockForUpdate()->first();
                if ($customer) {
                    $customer->current_debt = max(0.0, (float) $customer->current_debt - (float) $lockedSale->total);
                    $customer->save();
                }
            }

            // 3. Marcar venta como anulada
            $lockedSale->update([
                'status' => 'cancelled',
                'notes' => trim(($lockedSale->notes ?? '')." | ANULADA por usuario #{$userId}: {$reason}"),
            ]);
        });
    }

    // =========================================================================
    // NUEVO FLUJO DE 3 PASOS: ATENCIÓN ➔ GERENCIA ➔ DESPACHO
    // =========================================================================

    /**
     * PASO 1 - CAJA DE ATENCIÓN:
     * Genera el pedido en estado 'pending_payment' e inmediatamente RESERVA
     * el stock de los productos para que ningún otro asesor los venda mientras
     * el cliente camina a la caja central a pagar.
     *
     * @param  array<int, array{product_id: int, quantity: float, unit_price: float, tax_rate?: float, discount?: float, subtotal: float}>  $items
     */
    public static function createPendingOrder(
        int $userId,
        int $warehouseId,
        ?int $customerId,
        array $items,
        ?string $notes = null,
        ?int $cashShiftId = null
    ): Sale {
        if (empty($items)) {
            throw new DomainException('No se puede generar un pedido sin artículos en el carrito.');
        }

        return DB::transaction(function () use ($userId, $warehouseId, $customerId, $items, $notes, $cashShiftId) {
            $subtotal = 0.0;
            $taxAmount = 0.0;
            $discountAmount = 0.0;
            $isIvaEnabled = SystemSetting::isIvaEnabled();
            $defaultTaxRate = $isIvaEnabled ? SystemSetting::getIvaRate() : 0.0;

            // 1. Validar y apartar el stock físico de cada producto
            foreach ($items as $item) {
                $qty = (float) ($item['quantity'] ?? 0);
                $price = (float) ($item['unit_price'] ?? 0);
                $taxRate = array_key_exists('tax_rate', $item)
                    ? (float) $item['tax_rate']
                    : ($isIvaEnabled ? $defaultTaxRate : 0.0);
                $disc = (float) ($item['discount'] ?? 0.0);

                if ($qty <= 0) {
                    throw new DomainException('La cantidad de cada artículo debe ser mayor a cero.');
                }

                if ($price < 0) {
                    throw new DomainException('El precio unitario no puede ser negativo.');
                }

                if ($disc < 0) {
                    throw new DomainException('El descuento no puede ser negativo.');
                }

                if ($disc > round($qty * $price, 2)) {
                    throw new DomainException('El descuento no puede superar el valor total del producto.');
                }

                if ($taxRate < 0) {
                    throw new DomainException('La tasa de impuesto no puede ser negativa.');
                }

                /** @var ProductStock|null $stockRecord */
                $stockRecord = ProductStock::where('product_id', $item['product_id'])
                    ->where('warehouse_id', $warehouseId)
                    ->lockForUpdate()
                    ->first();

                $availableStock = (float) ($stockRecord?->current_stock ?? 0.0);
                if ($availableStock < $qty) {
                    $productName = Product::where('id', $item['product_id'])->value('name') ?? 'Producto';
                    throw new DomainException("Stock insuficiente para '{$productName}'. Disponible en bodega: {$availableStock}. Solicitado: {$qty}.");
                }

                $lineSubtotal = round(($qty * $price) - $disc, 2);
                $lineTax = $isIvaEnabled ? round($lineSubtotal * ($taxRate / 100), 2) : 0.0;

                $subtotal += $lineSubtotal;
                $taxAmount += $lineTax;
                $discountAmount += $disc;
            }

            $total = round($subtotal + $taxAmount, 2);

            // 2. Generar correlativo único de pedido / comprobante
            $lastSale = Sale::orderByDesc('id')->lockForUpdate()->first();
            $nextNumber = ($lastSale?->id ?? 0) + 1;
            do {
                $invoiceNumber = 'REM-'.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
                $nextNumber++;
            } while (Sale::where('invoice_number', $invoiceNumber)->exists());

            // 3. Crear la venta con estado 'pending_payment'
            $sale = Sale::create([
                'customer_id' => $customerId,
                'user_id' => $userId,
                'warehouse_id' => $warehouseId,
                'cash_shift_id' => $cashShiftId,
                'invoice_number' => $invoiceNumber,
                'payment_method' => 'pending',
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'total' => $total,
                'paid_amount' => 0.0,
                'change_amount' => 0.0,
                'status' => 'pending_payment',
                'notes' => $notes,
            ]);

            // 4. Guardar los ítems y RESERVAR el stock restándolo del disponible
            foreach ($items as $item) {
                /** @var Product $product */
                $product = Product::findOrFail($item['product_id']);

                /** @var ProductStock $stockRecord */
                $stockRecord = ProductStock::where('product_id', $product->id)
                    ->where('warehouse_id', $warehouseId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $qty = (float) $item['quantity'];
                $price = (float) $item['unit_price'];
                $taxRate = $isIvaEnabled ? (float) ($item['tax_rate'] ?? $defaultTaxRate) : 0.0;
                $disc = (float) ($item['discount'] ?? 0.0);
                $lineSubtotal = round(($qty * $price) - $disc, 2);

                // Descontamos preventivamente para apartarlo
                $previousStock = (float) $stockRecord->current_stock;
                $resultingStock = max(0.0, $previousStock - $qty);
                $stockRecord->update(['current_stock' => $resultingStock]);

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_cost' => $product->cost_price,
                    'unit_price' => $price,
                    'tax_rate' => $taxRate,
                    'discount' => $disc,
                    'subtotal' => $lineSubtotal,
                ]);
            }

            return $sale;
        });
    }

    /**
     * PASO 2 - CAJA CENTRAL (GERENTE):
     * Confirma el pago recibido, valida el efectivo / vueltas o crédito,
     * asienta el movimiento en el Kardex y cambia el estado a 'paid'.
     */
    public static function confirmPayment(
        Sale $sale,
        int $cashierUserId,
        string $paymentMethod,
        float $paidAmount,
        ?int $cashShiftId = null
    ): Sale {
        return DB::transaction(function () use ($sale, $cashierUserId, $paymentMethod, $paidAmount, $cashShiftId) {
            /** @var Sale $lockedSale */
            $lockedSale = Sale::where('id', $sale->id)->lockForUpdate()->firstOrFail();

            if ($lockedSale->status !== 'pending_payment') {
                throw new DomainException("El pedido #{$lockedSale->invoice_number} no se puede cobrar porque su estado actual es '{$lockedSale->status}'.");
            }

            $total = (float) $lockedSale->total;
            $changeAmount = 0.0;

            // Validación de medios de pago
            if ($paymentMethod === 'credit') {
                $customer = Customer::where('id', $lockedSale->customer_id)->lockForUpdate()->first();
                if (! $customer) {
                    throw new DomainException('Las ventas a crédito requieren un cliente registrado.');
                }
                if (! $customer->hasAvailableCredit($total)) {
                    $available = number_format($customer->available_credit, 0, ',', '.');
                    throw new DomainException("Cupo de crédito insuficiente para {$customer->name} (Disponible: \${$available} COP).");
                }

                $customer->current_debt = (float) $customer->current_debt + $total;
                $customer->save();
                $paidAmount = 0.0;
            } elseif ($paymentMethod === 'cash') {
                if ($paidAmount < $total) {
                    $missing = number_format($total - $paidAmount, 0, ',', '.');
                    throw new DomainException("Monto recibido insuficiente. Faltan \${$missing} COP.");
                }
                $changeAmount = round($paidAmount - $total, 2);
            } else {
                $paidAmount = $total;
            }

            // Actualizar la venta a estado 'paid'
            $lockedSale->update([
                'payment_method' => $paymentMethod,
                'paid_amount' => $paidAmount,
                'change_amount' => $changeAmount,
                'cash_shift_id' => $cashShiftId,
                'status' => 'paid',
                'notes' => trim(($lockedSale->notes ?? '')." | Cobrado en caja central por usuario #{$cashierUserId}"),
            ]);

            // Asentar formalmente la salida en el Kardex contable
            foreach ($lockedSale->items as $item) {
                /** @var ProductStock|null $stockRecord */
                $stockRecord = ProductStock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $lockedSale->warehouse_id)
                    ->lockForUpdate()
                    ->first();

                $currentStock = (float) ($stockRecord?->current_stock ?? 0.0);

                InventoryMovement::create([
                    'product_id' => $item->product_id,
                    'warehouse_id' => $lockedSale->warehouse_id,
                    'user_id' => $cashierUserId,
                    'type' => 'sale',
                    'quantity' => (float) $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'previous_stock' => $currentStock + (float) $item->quantity,
                    'resulting_stock' => $currentStock,
                    'reference_type' => Sale::class,
                    'reference_id' => $lockedSale->id,
                    'notes' => "Salida por Venta Comprobante #{$lockedSale->invoice_number} (Confirmada en Caja)",
                ]);
            }

            return $lockedSale;
        });
    }

    /**
     * PASO 3 - DESPACHO (CAJA DE ATENCIÓN):
     * Verifica que el pedido esté en estado 'paid' (anti-fraude) y marca
     * la entrega física de los productos al cliente como completada.
     */
    public static function dispatchOrder(Sale $sale, int $dispatchUserId): Sale
    {
        return DB::transaction(function () use ($sale, $dispatchUserId) {
            /** @var Sale $lockedSale */
            $lockedSale = Sale::where('id', $sale->id)->lockForUpdate()->firstOrFail();

            if ($lockedSale->status !== 'paid') {
                throw new DomainException("¡ALERTA ANTI-FRAUDE! El pedido #{$lockedSale->invoice_number} no puede ser despachado porque su estado en el sistema es '{$lockedSale->status}' (no está confirmado como pagado).");
            }

            $lockedSale->update([
                'status' => 'delivered',
                'notes' => trim(($lockedSale->notes ?? '')." | Despachado por usuario #{$dispatchUserId} el ".now()->format('d/m/Y H:i')),
            ]);

            return $lockedSale;
        });
    }

    /**
     * ANULACIÓN DE PEDIDO PENDIENTE:
     * Si el cliente se arrepiente antes de pagar, libera de inmediato
     * el stock reservado devolviéndolo al inventario disponible y auditando en Kardex.
     */
    public static function cancelPendingOrder(Sale $sale, int $cancelledByUserId, ?string $reason = null): Sale
    {
        return DB::transaction(function () use ($sale, $cancelledByUserId, $reason) {
            /** @var Sale $lockedSale */
            $lockedSale = Sale::where('id', $sale->id)->lockForUpdate()->firstOrFail();

            if ($lockedSale->status !== 'pending_payment') {
                throw new DomainException("Solo se pueden anular pedidos en estado 'pending_payment'. Para ventas pagadas use la anulación formal.");
            }

            // Devolver las existencias que estaban apartadas y asentar en Kardex
            foreach ($lockedSale->items as $item) {
                /** @var ProductStock|null $stockRecord */
                $stockRecord = ProductStock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $lockedSale->warehouse_id)
                    ->lockForUpdate()
                    ->first();

                if ($stockRecord) {
                    $previousStock = (float) $stockRecord->current_stock;
                    $resultingStock = $previousStock + (float) $item->quantity;

                    $stockRecord->update([
                        'current_stock' => $resultingStock,
                    ]);

                    InventoryMovement::create([
                        'product_id' => $item->product_id,
                        'warehouse_id' => $lockedSale->warehouse_id,
                        'user_id' => $cancelledByUserId,
                        'type' => 'adjustment_in',
                        'quantity' => (float) $item->quantity,
                        'unit_cost' => $item->unit_cost,
                        'previous_stock' => $previousStock,
                        'resulting_stock' => $resultingStock,
                        'reference_type' => Sale::class,
                        'reference_id' => $lockedSale->id,
                        'notes' => "Liberación de reserva por anulación de pedido #{$lockedSale->invoice_number}. Motivo: ".($reason ?? 'Desistimiento del cliente'),
                    ]);
                }
            }

            $lockedSale->update([
                'status' => 'cancelled',
                'notes' => trim(($lockedSale->notes ?? '')." | Anulado en mostrador por usuario #{$cancelledByUserId}. Motivo: ".($reason ?? 'Desistimiento del cliente')),
            ]);

            return $lockedSale;
        });
    }
}
