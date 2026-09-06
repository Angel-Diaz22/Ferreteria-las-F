# Auditoría Integral: Seguridad, Concurrencia, Integridad Transaccional y Flujo de Cajas POS
**Proyecto**: Ferretería Las F (ERP / POS — Laravel 12 / PHP 8.4 / Filament v3 / Livewire)  
**Fecha de Evaluación**: 2026-09-05  
**Auditor**: Teamwork Explorer Survey 3  
**Estado**: Completado — Informe Técnico para Implementación

---

## 1. Resumen Ejecutivo

Se ha completado una auditoría integral, exhaustiva y de solo lectura sobre la arquitectura de software, seguridad, integridad transaccional, concurrencia y flujo de cajas POS en Ferretería Las F. 

Se detectaron hallazgos de alto impacto clasificados en cuatro dominios principales:
1. **Flujo de Cajas POS y Reglas de Negocio**: La separación operativa entre mostrador (Cajas 1 y 2) y caja central (Caja 3) está presente en la interfaz de usuario, pero adolece de un defecto crítico en la apertura de turno del Administrador (se auto-inicia en $0 sin permitir ingresar base física), y la identificación de cajas depende de coincidencias frágiles de texto (`str_contains`) en lugar de columnas de base de datos tipadas.
2. **Cumplimiento de Reglas de CSS Grid (Filament + Tailwind)**: Se identificaron violaciones directas a la regla de prevención de desbordamientos (*CSS Grid Blowouts*) en `pos-terminal.blade.php`, utilizando `1fr` y `repeat(N, 1fr)` en vez de `minmax(0, 1fr)`, y el contenedor principal `.pos-main-grid` carece de regla responsive de 2 columnas en pantallas de escritorio.
3. **Integridad Transaccional y Concurrencia**: Se descubrió un **bug crítico de duplicación de inventario** en `StocksRelationManager` (al asignar stock inicial se duplica la cantidad ingresada debido a la doble actualización entre Filament y `KardexService`), ausencia de bloqueos pesimistas (`lockForUpdate`) en ajustes manuales de Kardex, e inconsistencias en la bitácora de movimientos entre órdenes pendientes y canceladas.
4. **Autorización y Permisos**: Varios recursos de Filament (`QuoteResource`, `PurchaseResource`, `CustomerResource`) omiten implementar los métodos `canCreate`, `canEdit` o `canDelete`, permitiendo que usuarios con permisos de solo lectura o cajeros ejecuten modificaciones no autorizadas, sobrescriban precios unitarios a criterio personal o borren clientes con deudas activas.

---

## 2. Auditoría de Flujo de Cajas POS (Regla de Negocio)

**Referencia**: `.agents/rules/pos-cash-registers-flow.md`

### 2.1 Cajas 1 y 2 (Mostrador / Asesores de Venta)

| Requisito de la Regla | Estado | Observación en Código |
| :--- | :---: | :--- |
| **No manejan dinero físico, efectivo, tarjetas ni gaveta** | ✅ Cumple en UI | En `pos-terminal.blade.php` (línea 1396) el botón de cobro directo está condicionado a `@if($this->canConfirmPayments)`. En `PosTerminal.php` (líneas 716, 747) `openPaymentModal()` y `processSale()` bloquean a los cajeros si operan Cajas 1 y 2. |
| **Apertura de turno automática con base = $0 COP** | ✅ Cumple | En `pos-terminal.blade.php` (líneas 2237-2247), el modal oculta el input de base y muestra el aviso de puesto sin dinero. En `PosTerminal::selectCashRegister()` (línea 311): `$opening = $register->handlesCash() ? ... : 0.0;`. |
| **Prohibido acceso a Pestaña 2 (Confirmación de Pago / Caja Central)** | ✅ Cumple | Pestaña oculta con `@if($this->canConfirmPayments)` (línea 940) y bloqueada en servidor en `switchTab('caja')` (líneas 935-945). |
| **Carrito limitado a "Generar Pedido e Imprimir Tirilla"** | ✅ Cumple | El botón primario ejecuta `generateOrderAndPrint()` (`PosTerminal.php:956`). No hay acceso a checkout directo en mostrador. |
| **Cierre de turno: liberación directa sin arqueo** | ✅ Cumple | `openCloseShiftModal()` (línea 357) y `confirmCloseShift()` (línea 400) establecen `closing = 0.0` y `diff = 0.0` si `! $register->handlesCash()`. |

### 2.2 Caja 3 (Caja Central / Administrador)

| Requisito de la Regla | Estado | Observación en Código |
| :--- | :---: | :--- |
| **Única caja recaudadora autorizada para cobros y Kardex** | ✅ Cumple | `confirmCashierPayment()` en `PosTerminal.php:1059` y `PosService::confirmPayment()` asientan pagos y Kardex. |
| **Usuario 'admin' entra automáticamente a Caja 3 sin modal** | ⚠️ Parcial con Defecto | `PosTerminal::mount()` (líneas 188-221) detecta `admin` y asigna Caja 3 sin mostrar modal. |
| **Manejo de base inicial en efectivo y arqueo contable** | ❌ Defecto Crítico | **DEFECTO DETECTADO**: En `PosTerminal.php:207-215`, cuando el Administrador ingresa y no tiene un turno abierto, el sistema auto-crea el turno con `'opening_amount' => 0.0`. El Administrador nunca tiene oportunidad de registrar su base física de caja para vueltas. Además, al cerrar turno (`confirmCloseShift:425`), invoca `$this->mount()`, lo que crea inmediatamente otro turno en $0.0 en bucle. |

### 2.3 Selección de Puestos y Detección de Cajas

- **Filtro de Puestos para Cajeros**: `PosTerminal::getCashRegistersWithStatusProperty()` (línea 1340) filtra con `$reg->isAttentionRegister()`, evitando que los cajeros visualicen Caja 3 en el modal de selección.
- **Riesgo de Arquitectura Frágil (Detección por Subcadena)**:  
  En `app/Models/CashRegister.php:40-48`:
  ```php
  public function isCashier(): bool
  {
      $lower = strtolower($this->name);
      return str_contains($lower, 'caja 3')
          || str_contains($lower, 'central')
          || str_contains($lower, 'cobro')
          || str_contains($lower, 'patio');
  }
  ```
  La lógica de seguridad financiera descansa sobre una búsqueda de texto en el nombre editable de la caja. Si un usuario renombra "Caja 3" a "Caja Gerencia Principal" o "Caja Don Ángel", la caja pierde sus permisos recaudadores. Si una caja de mostrador se nombra "Caja Mostrador Patio", adquiere indebidamente capacidades de recaudación.
  *Recomendación*: Agregar columna `type` (enum `'counter'`, `'cashier'`) o booleano `handles_cash` en la tabla `cash_registers`.

- **Modelo Huérfano `CashMovement`**:  
  La tabla `cash_movements` y el modelo `CashMovement` existen en el proyecto, pero no existe interfaz de usuario ni métodos en `PosTerminal` o Filament para registrar ingresos/egresos de efectivo menor (sangrías, pagos a fletes, base extra). El cálculo de efectivo esperado en `openCloseShiftModal()` (`PosTerminal.php:367-379`) solo suma ventas en efectivo menos cambios, ignorando por completo cualquier movimiento de caja.

---

## 3. Cumplimiento de Reglas de CSS Grid (Filament & Tailwind)

**Referencia**: `.ai/rules/boost/filament-tailwind-css-grid.md`

### 3.1 Violaciones Directas a la Regla Anti-Blowout (`minmax(0, Xfr)`)

El archivo de reglas exige taxativamente:
> *"Always use `minmax(0, Xfr)` instead of `Xfr` (e.g. `grid-template-columns: minmax(0, 8fr) minmax(0, 4fr)`)."*  
> *"Always set `min-width: 0 !important;` and `max-width: 100% !important;` on the grid container and its flex/grid children."*

En `resources/views/filament/pages/pos-terminal.blade.php`:
1. **Línea 83 (`.pos-payment-grid`)**:
   ```css
   /* INCUMPLIMIENTO: */
   grid-template-columns: repeat(4, 1fr) !important;
   /* CORRECCIÓN OBLIGATORIA: */
   grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
   ```
2. **Líneas 810–828 (`.pos-orders-grid`)**:
   ```css
   /* INCUMPLIMIENTO: */
   .pos-orders-grid {
       grid-template-columns: 1fr !important;
   }
   @media (min-width: 768px) {
       .pos-orders-grid {
           grid-template-columns: repeat(2, 1fr) !important;
       }
   }
   @media (min-width: 1024px) {
       .pos-orders-grid {
           grid-template-columns: repeat(3, 1fr) !important;
       }
   }
   /* CORRECCIÓN OBLIGATORIA: */
   .pos-orders-grid {
       grid-template-columns: minmax(0, 1fr) !important;
   }
   @media (min-width: 768px) {
       .pos-orders-grid {
           grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
       }
   }
   @media (min-width: 1024px) {
       .pos-orders-grid {
           grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
       }
   }
   ```

### 3.2 Defecto de Layout de Pantalla de Escritorio (`.pos-main-grid`)

En `pos-terminal.blade.php:726-734`:
```css
.pos-main-grid {
    display: flex !important;
    flex-direction: column !important;
    gap: 1.5rem !important;
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box !important;
}
```
A pesar de que el código Blade documenta:
`{{-- LAYOUT PRINCIPAL DEL POS (2 COLUMNAS) --}}`  
`{{-- COLUMNA IZQUIERDA (7/12) --}}` y `{{-- COLUMNA DERECHA (5/12) --}}`,  
nunca se define una media query para pantallas de escritorio (`@media (min-width: 1024px)`). En consecuencia, en monitores de 1080p, el catálogo queda apilado arriba y el carrito de compras debajo, desperdiciando el 50% del espacio horizontal y obligando al cajero a desplazarse verticalmente.

*Corrección requerida*:
```css
@media (min-width: 1024px) {
    .pos-main-grid {
        display: grid !important;
        grid-template-columns: minmax(0, 7fr) minmax(0, 5fr) !important;
        align-items: start !important;
    }
}
```

### 3.3 Clases Arbitrarias en Vistas Blade No Compiladas

El panel de Filament (`AdminPanelProvider.php`) no compila un tema personalizado (`->viteTheme()`).
En `resources/views/filament/pages/reports-page.blade.php`:
- Línea 281: `class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"`
- Línea 364: `class="grid grid-cols-2 gap-3 sm:grid-cols-4 ..."`
- Línea 420: `class="grid grid-cols-1 gap-6 lg:grid-cols-12"`
Clases como `lg:grid-cols-12` o `grid-cols-4` no están garantizadas en el bundle por defecto de Filament, generando riesgos de desalineación en navegadores que no tengan la clase en caché.

---

## 4. Auditoría de Integridad Transaccional y Concurrencia

### 4.1 Bug Crítico: Duplicación de Stock en `StocksRelationManager`

**Ubicación**: `app/Filament/Resources/ProductResource/RelationManagers/StocksRelationManager.php:125-137`
```php
Tables\Actions\CreateAction::make()
    ->label('Asignar a Otra Bodega')
    ->after(function (ProductStock $record) {
        if ($record->current_stock > 0) {
            KardexService::registerAdjustment(
                product: $record->product,
                warehouseId: $record->warehouse_id,
                quantity: (float) $record->current_stock,
                type: 'adjustment_in',
                notes: 'Inventario inicial al asignar producto a la bodega '.$record->warehouse->name,
                userId: auth()->id()
            );
        }
    }),
```
**Mecanismo del Fallo**:
1. El usuario completa el formulario de asignación indicando `current_stock = 10`.
2. Filament inserta el registro `ProductStock` en la base de datos con `current_stock = 10.00`.
3. El gancho `after()` se ejecuta y llama a `KardexService::registerAdjustment(quantity: 10, type: 'adjustment_in')`.
4. En `KardexService::registerAdjustment` (`KardexService.php:124-147`):
   ```php
   $previousStock = (float) $stockRecord->current_stock; // Lee 10.00
   $resultingStock = $previousStock + $qty;               // Calcula 10.00 + 10.00 = 20.00!
   $stockRecord->update(['current_stock' => $resultingStock]); // Guarda 20.00!
   ```
5. **Resultado**: El inventario físico queda con el DOBLE de las existencias reales, y el Kardex registra un movimiento previo de 10 y resultante de 20 para un producto recién ingresado.

### 4.2 Falta de Bloqueo Pesimista en `KardexService::registerAdjustment`

**Ubicación**: `app/Services/KardexService.php:123-147`
```php
public static function registerAdjustment(...): InventoryMovement {
    return DB::transaction(function () use (...) {
        $stockRecord = ProductStock::firstOrCreate([...]);
        $previousStock = (float) $stockRecord->current_stock;
        // NO HAY lockForUpdate()
        $stockRecord->update(['current_stock' => $resultingStock]);
        ...
    });
}
```
Si dos ajustes manuales ocurren simultáneamente, o si ocurre un ajuste manual mientras un cajero vende en el POS:
- Ambos procesos leen el mismo `current_stock`.
- El que confirma último sobrescribe el saldo del primero (*Lost Update*).
*Corrección*: Agregar `ProductStock::where('id', $stockRecord->id)->lockForUpdate()->firstOrFail()`.

### 4.3 Inconsistencia en Bitácora de Kardex entre Pedidos Pendientes y Cancelados

**Ubicación**: `app/Services/PosService.php`
- `createPendingOrder()` (líneas 436-438) descuenta stock preventivamente para apartarlo:
  `$stockRecord->update(['current_stock' => $resultingStock]);`  
  **No genera ningún registro en `inventory_movements`**.
- Si el pedido se cobra en `confirmPayment()` (líneas 523-535), recién allí se genera el movimiento `sale` en Kardex.
- Pero si el cliente desiste y se ejecuta `cancelPendingOrder()` (líneas 597-609):
  Genera un movimiento Kardex de tipo `adjustment_in` ("Liberación de reserva").
- **Impacto contable**: En una auditoría de Kardex, si un pedido fue apartado y cancelado, el Kardex refleja una entrada `adjustment_in` (+5 unidades) sin ninguna salida previa registrada, descuadrando el balance histórico de movimientos contra el stock inicial.

### 4.4 Condición de Carrera en Selección y Apertura de Cajas

**Ubicación**: `app/Filament/Pages/PosTerminal.php:270-320` (`selectCashRegister`)
```php
$existingShift = CashShift::where('cash_register_id', $registerId)
    ->where('status', 'open')
    ->where('user_id', '!=', auth()->id())
    ->first();
...
$newShift = CashShift::create([...]);
```
- La consulta y la creación no están dentro de `DB::transaction`.
- No existe bloqueo a nivel de fila ni restricción única en la base de datos para `(cash_register_id, status = 'open')`.
- Si dos cajeros presionan la misma caja al mismo milisegundo, ambos evalúan `$existingShift === null` y se crean dos turnos abiertos sobre el mismo puesto físico.

### 4.5 Concurrencia en Generación de Consecutivos de Cotizaciones

**Ubicación**: `app/Filament/Resources/QuoteResource/Pages/CreateQuote.php:24-30`
```php
$lastQuote = Quote::orderByDesc('id')->first();
$nextNumber = ($lastQuote?->id ?? 0) + 1;
do {
    $quoteNumber = 'COT-'.str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
    $nextNumber++;
} while (Quote::where('quote_number', $quoteNumber)->exists());
```
- No hay transacción ni `lockForUpdate`.
- En creaciones concurrentes por múltiples asesores, ambos leen el mismo `$lastQuote` y generan el mismo `$quoteNumber`, provocando colisiones o excepciones de base de datos.
*(En contraste, `PosService::processSale` sí implementa `Sale::orderByDesc('id')->lockForUpdate()->first()`).*

---

## 5. Auditoría de Autorización y Permisos

### 5.1 Matriz de Recursos Filament y Métodos de Autorización

| Recurso Filament | `canViewAny` | `canCreate` | `canEdit` | `canDelete` | Vulnerabilidad Detectada |
| :--- | :---: | :---: | :---: | :---: | :--- |
| **`SaleResource`** | ✅ `sales.view` | ✅ `false` | ✅ `false` | ✅ `false` | Correcto. Anulación protegida con `sales.cancel`. |
| **`QuoteResource`** | ✅ `quotes.view` | ❌ No definido | ❌ No definido | ❌ No definido | **ALTA**: Cajeros con `quotes.view` pueden crear, editar cualquier cotización y borrarlas (incluyendo bulk delete). |
| **`PurchaseResource`** | ✅ `purchases.view` | ❌ No definido | ❌ No definido | ❌ No definido | **ALTA**: Cualquier usuario con `purchases.view` tiene permisos plenos de creación, edición y borrado de compras a proveedores. |
| **`CustomerResource`** | ✅ `customers.view` | ❌ No definido | ❌ No definido | ❌ No definido | **MEDIA-ALTA**: Cajeros pueden editar y eliminar clientes directamente. |
| **`ProductResource`** | ✅ `products.view` | ✅ `products.manage` | ✅ `products.manage` | ✅ `products.manage` | Correcto en el recurso principal. |
| **`StocksRelationManager`** | Hereda `Product` | — | — | — | **MEDIA**: Acción `adjust_stock` no valida permiso específico de inventario. |
| **`PriceListResource`** | ✅ `price_lists.view` | ✅ `products.manage` | ✅ `products.manage` | ✅ `products.manage` | Correcto. |
| **`WarehouseResource`** | ✅ `warehouses.view` | ✅ `products.manage` | ✅ `products.manage` | ✅ `products.manage` | Correcto. |
| **`SupplierResource`** | ✅ `suppliers.view` | ✅ `hasRole('admin')`| ✅ `hasRole('admin')`| ✅ `hasRole('admin')`| Correcto. |
| **`UserResource`** | ✅ `users.manage` | ✅ `users.manage` | ✅ `users.manage` | ✅ `users.manage` | Correcto. |
| **`InventoryMovementResource`**| ✅ `inventory.view` | ✅ `false` | ✅ `false` (no rutas) | ✅ `false` | Correcto (Kardex inmutable). |

### 5.2 Sobrescritura Arbitraria de Precios en Cotizaciones

**Ubicación**: `app/Filament/Resources/QuoteResource.php:200-210`
```php
Forms\Components\TextInput::make('unit_price')
    ->label('Precio Unit.')
    ->numeric()
    ->prefix('$')
    ->required()
    ->live(onBlur: true)
```
Cualquier usuario con acceso a Cotizaciones puede ingresar cualquier precio unitario arbitrario (incluso $1 COP o valores por debajo del costo de compra `cost_price`), sin requerir autorización administrativa ni advertencia de rentabilidad negativa.

### 5.3 Modificación Insegura de Compras en Estado 'Completed'

**Ubicación**: `app/Filament/Resources/PurchaseResource/Pages/EditPurchase.php:25-30`
```php
protected function afterSave(): void
{
    if ($this->record->status === 'completed' && $this->record->wasChanged('status')) {
        KardexService::processPurchase($this->record);
    }
}
```
- Si una compra ya está en estado `completed` y un usuario entra a editarla (`EditPurchase`), el formulario permite alterar productos, cantidades y bodegas.
- Como el estado ya era `completed`, `$this->record->wasChanged('status')` es `false`, por lo que el Kardex NO se recalcula. El documento de compra queda con valores modificados pero el inventario físico no refleja dichos cambios.
- Si el usuario cambia el estado de `completed` a `cancelled`, el inventario ingresado nunca se revierte ni se descuenta del Kardex.

### 5.4 Eliminación de Clientes con Cartera Pendiente

**Ubicación**: `app/Filament/Resources/CustomerResource.php:292` (`DeleteBulkAction`), y `EditCustomer.php` (`DeleteAction`).
- La migración `2026_09_05_170840_create_sales_and_returns_tables.php` establece:
  `$table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();`
- Si un cajero o usuario elimina un cliente que tiene saldo en mora (`current_debt > 0`), las ventas históricas asociadas quedan con `customer_id = null`, desvinculando la deuda contable del deudor.

### 5.5 Rutas de Descarga PDF / Tirilla sin Verificación de Permisos

**Ubicación**: `app/Http/Controllers/QuotePdfController.php:27` y `SaleReceiptController.php:29, 41`
- `routes/web.php` aplica `['auth', 'throttle:60,1']`.
- Sin embargo, los controladores no verifican si el usuario autenticado tiene el permiso `quotes.view` o `sales.view`.
- Cualquier usuario autenticado (incluso con permisos restringidos) puede enumerar IDs (`/admin/quotes/1/pdf`, `/admin/sales/45/receipt`) y visualizar comprobantes de venta y datos de clientes.

---

## 6. Plan de Remediación Priorizado

| Prioridad | Vulnerabilidad / Riesgo | Archivo Afectado | Solución Técnica Propuesta |
| :---: | :--- | :--- | :--- |
| **P0 (Crítica)** | Duplicación de stock al asignar bodega inicial | `app/Filament/Resources/ProductResource/RelationManagers/StocksRelationManager.php` | En `CreateAction`, si se crea el registro con `current_stock > 0`, crear el registro con `current_stock = 0` o no invocar `registerAdjustment`, o pasar la cantidad a `registerAdjustment` delegando la creación física a `KardexService`. |
| **P0 (Crítica)** | Auto-apertura de turno Caja 3 en $0 sin pedir base al Administrador | `app/Filament/Pages/PosTerminal.php:207-215` y `425` | Si el admin no tiene turno abierto, disparar un modal específico para ingresar la base inicial en efectivo en lugar de auto-crear turno en $0.0 en el `mount()`. Al cerrar turno, no invocar `mount()` que re-abre el turno en bucle. |
| **P1 (Alta)** | Ausencia de `canCreate`, `canEdit`, `canDelete` en Recursos Sensibles | `QuoteResource.php`, `PurchaseResource.php`, `CustomerResource.php` | Implementar `canCreate`, `canEdit`, `canDelete` asociándolos a los permisos Spatie correspondientes (`quotes.manage`, `purchases.manage`, `customers.manage`). |
| **P1 (Alta)** | Falta de transaccionalidad y locking en apertura de caja (`selectCashRegister`) | `app/Filament/Pages/PosTerminal.php:244-325` | Envolver la verificación y creación en `DB::transaction(...)` con bloqueo sobre la caja o índice único condicional en base de datos. |
| **P1 (Alta)** | Falta de `lockForUpdate` en `KardexService::registerAdjustment` | `app/Services/KardexService.php:123-148` | Bloquear pesimistamente el registro `ProductStock` antes de calcular `resultingStock`. |
| **P1 (Alta)** | Edición no restringida de compras `completed` | `app/Filament/Resources/PurchaseResource.php` | Bloquear formulario (`disabled()`) o impedir edición si `status === 'completed'`. Permitir únicamente anulación controlada con reversión de Kardex. |
| **P2 (Media)** | Violación de CSS Grid Blowout (`1fr`) y falta de 2 columnas en Desktop | `resources/views/filament/pages/pos-terminal.blade.php` | Reemplazar `1fr` por `minmax(0, 1fr)` en líneas 83, 812, 820, 826. Agregar media query `@media (min-width: 1024px)` para `.pos-main-grid` con 2 columnas. |
| **P2 (Media)** | Detección de Caja 3 por subcadena de texto | `app/Models/CashRegister.php` | Migrar a columna `type` (enum) o booleano `handles_cash` en la tabla `cash_registers`. |
| **P2 (Media)** | Sobrescritura de precios en Cotizaciones sin margen mínimo | `app/Filament/Resources/QuoteResource.php` | Restringir campo `unit_price` a usuarios autorizados o validar contra `cost_price`. |
| **P3 (Baja)** | Falta de autorización en controladores PDF/Tirillas | `QuotePdfController.php`, `SaleReceiptController.php` | Agregar `$this->authorize('view', $sale)` o `abort_unless(auth()->user()->can('sales.view'), 403)`. |
| **P3 (Baja)** | Borrado de clientes con deuda activa | `app/Filament/Resources/CustomerResource.php` | Impedir borrado de clientes si `current_debt > 0`. |
