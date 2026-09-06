# Handoff Report: Auditoría de Seguridad, Concurrencia, Integridad Transaccional y Flujo de Cajas POS

**Agent**: `teamwork_preview_explorer_survey_3`  
**Working Directory**: `/Users/angeldiaz/Documents/FerreterialasF/.agents/teamwork_preview_explorer_survey_3`  
**Fecha**: 2026-09-05  
**Handoff Type**: Hard (Task Complete)

---

## 1. Observation

Durante la inspección directa del código fuente, rutas, vistas Blade, migraciones y ejecución de pruebas, se observaron los siguientes hechos exactos:

1. **Flujo de Cajas POS**:
   - En `app/Filament/Pages/PosTerminal.php:188-215`, cuando el usuario con rol `admin` ingresa a la terminal y no tiene turno abierto, el código ejecuta:
     ```php
     if (! $openShift) {
         $openShift = CashShift::create([
             'cash_register_id' => $caja3->id,
             'user_id' => $user->id,
             'opening_amount' => 0.0,
             'status' => 'open',
             'opened_at' => now(),
         ]);
     }
     ```
     No se solicita ni se permite ingresar la base física de caja al Administrador. Además, en `PosTerminal.php:425`, al ejecutar `confirmCloseShift()`, invoca `$this->mount()`, re-creando de inmediato un turno con `$0.0`.
   - En `app/Models/CashRegister.php:40-48`, la determinación de si una caja es recaudadora o maneja efectivo se realiza por coincidencias de texto:
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
   - En `app/Filament/Pages/PosTerminal.php:270-320` (`selectCashRegister`), la comprobación de caja ocupada y la creación del registro en `CashShift` se ejecutan sin `DB::transaction` y sin `lockForUpdate`.
   - En `database/migrations/2026_09_05_170839_create_cash_registers_and_shifts_tables.php`, la tabla `cash_shifts` no tiene índice único que restrinja múltiples turnos en estado `'open'` para un mismo `cash_register_id`.

2. **Reglas de CSS Grid en Vistas Blade**:
   - En `resources/views/filament/pages/pos-terminal.blade.php:83`:
     ```css
     grid-template-columns: repeat(4, 1fr) !important;
     ```
   - En `pos-terminal.blade.php:810-828`:
     ```css
     .pos-orders-grid { grid-template-columns: 1fr !important; }
     @media (min-width: 768px) { .pos-orders-grid { grid-template-columns: repeat(2, 1fr) !important; } }
     @media (min-width: 1024px) { .pos-orders-grid { grid-template-columns: repeat(3, 1fr) !important; } }
     ```
     Violan la regla explícita de `.ai/rules/boost/filament-tailwind-css-grid.md` de usar siempre `minmax(0, Xfr)`.
   - En `pos-terminal.blade.php:726-734`, `.pos-main-grid` está definido únicamente como `display: flex; flex-direction: column;` sin media query para desktop (`min-width: 1024px`), impidiendo el layout horizontal de 2 columnas (catálogo izquierda 7/12, carrito derecha 5/12).

3. **Integridad Transaccional y Concurrencia**:
   - En `app/Filament/Resources/ProductResource/RelationManagers/StocksRelationManager.php:125-136`:
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
         })
     ```
     Al ejecutarse, `CreateAction` guarda `current_stock = X`, y luego `registerAdjustment` lee `X` y le suma `X`, actualizando `ProductStock` a `2X` (**duplicación directa de inventario**).
   - En `app/Services/KardexService.php:123-147` (`registerAdjustment`), no se utiliza `lockForUpdate()` sobre el registro de `ProductStock`.
   - En `app/Filament/Resources/QuoteResource/Pages/CreateQuote.php:24-30`, la generación de consecutivos `COT-XXXXX` se ejecuta fuera de transacción y sin bloqueo sobre la tabla `quotes`.
   - En `app/Services/PosService.php:436-438`, `createPendingOrder()` descuenta el stock de `ProductStock` sin registrar el movimiento en `inventory_movements`, pero `cancelPendingOrder()` (líneas 597-609) crea un movimiento `adjustment_in`, distorsionando la bitácora histórica del Kardex.

4. **Autorización y Permisos**:
   - En `app/Filament/Resources/QuoteResource.php:46-50`, `app/Filament/Resources/PurchaseResource.php:44-48`, y `app/Filament/Resources/CustomerResource.php:46-50`, solo está implementado `canViewAny()`. Los métodos `canCreate()`, `canEdit()` y `canDelete()` no existen.
   - En `app/Filament/Resources/QuoteResource.php:200-210`, el campo `unit_price` es un `TextInput` editable sin validación contra costo mínimo ni requerimiento de autorización administrativa.
   - En `app/Filament/Resources/PurchaseResource/Pages/EditPurchase.php:25-30`, `afterSave()` solo procesa compras cuando `wasChanged('status')` es verdadero. Una compra en estado `completed` puede ser editada en sus líneas sin que el Kardex o el stock se actualicen.
   - En `app/Http/Controllers/QuotePdfController.php:27` y `SaleReceiptController.php:29`, las acciones de descarga no validan que el usuario autenticado posea los permisos `quotes.view` o `sales.view`.

5. **Verificación de Pruebas Existentes**:
   - Comando ejecutado: `"$HOME/Library/Application Support/Herd/bin/php" artisan test --compact`
   - Resultado: 54 pruebas ejecutadas, 54 aprobadas (258 aserciones, duración ~5.0s).

---

## 2. Logic Chain

1. **De la observación 1 a la conclusión sobre el flujo de Caja 3**:  
   Dado que `mount()` auto-crea el turno del Administrador con `opening_amount = 0.0` y nunca abre un modal para solicitar la base física, y dado que `confirmCloseShift()` re-ejecuta `mount()`, el Administrador no puede cumplir la regla de negocio que exige operar con base inicial en efectivo y realizar arqueo físico verídico.

2. **De la observación 1 a la conclusión sobre fragilidad en identificación de cajas**:  
   Dado que `isCashier()` en `CashRegister` utiliza `str_contains` sobre `$this->name`, cualquier cambio cosmético al nombre de la caja desactiva o transfiere inadvertidamente las facultades de cobro de dinero.

3. **De la observación 2 a la conclusión sobre CSS Grid Blowouts y Layout Desktop**:  
   Dado que `.ai/rules/boost/filament-tailwind-css-grid.md` exige `minmax(0, Xfr)` y que las líneas 83, 812, 820 y 826 usan `1fr` directo, cualquier contenido extenso o etiqueta no divisible provocará un desbordamiento horizontal en pantallas estrechas. Además, al carecer `.pos-main-grid` de media query para desktop, la vista permanece en columna vertical.

4. **De la observación 3 a la conclusión sobre la duplicación de inventario en `StocksRelationManager`**:  
   Cuando un usuario crea la relación con `current_stock = 10`, Filament inserta el registro con `10`. Luego el hook `after()` invoca `KardexService::registerAdjustment(..., 10, 'adjustment_in')`, que suma 10 al saldo existente (10), dejando el saldo en `20`. Esto constituye una violación severa de integridad transaccional que afecta la contabilidad física.

5. **De la observación 4 a la conclusión sobre brechas de autorización**:  
   En Filament v3, la ausencia de los métodos `canCreate()`, `canEdit()` y `canDelete()` en un `Resource` hace que Filament asuma `true` para cualquier usuario que supere `canViewAny()`. Por lo tanto, los cajeros (que poseen `quotes.view` y `customers.view`) pueden crear y borrar cotizaciones y clientes, además de manipular precios unitarios en cotizaciones.

---

## 3. Caveats

- **No modificaciones en código fuente**: Esta auditoría se mantuvo en modo estrictamente de solo lectura conforme a las restricciones del rol Explorer.
- **Entorno de base de datos**: Las pruebas automatizadas corren sobre SQLite en memoria; algunas condiciones de carrera de concurrencia milimétrica (como `selectCashRegister`) solo se manifiestan bajo concurrencia real de múltiples conexiones HTTP en PostgreSQL o MySQL.
- **Interacción externa de impresión**: No se evaluó la conexión física de puertos USB/red a impresoras térmicas de hardware, solo la generación del stream HTML/PDF.

---

## 4. Conclusion

El sistema cuenta con una base sólida de pruebas automatizadas (54 pruebas aprobadas al 100%) y cumple con la mayoría de las restricciones visuales para cajeros en el POS (bloqueo de pestaña 2, cobro directo oculto). Sin embargo, existen **5 brechas críticas** que deben remediarse antes de entrar a producción:
1. Corregir el bug de duplicación de stock en `StocksRelationManager`.
2. Habilitar la solicitud obligatoria de base en efectivo para el Administrador al iniciar turno en Caja 3 y desvincular el bucle de auto-apertura en el cierre de turno.
3. Blindar los estilos CSS Grid de `pos-terminal.blade.php` con `minmax(0, 1fr)` y restaurar la cuadrícula de 2 columnas para monitores de escritorio.
4. Definir explícitamente `canCreate`, `canEdit` y `canDelete` en `QuoteResource`, `PurchaseResource` y `CustomerResource`.
5. Incorporar bloqueos pesimistas (`lockForUpdate`) en `KardexService::registerAdjustment` y en la apertura de cajas (`selectCashRegister`).

---

## 5. Verification Method

Para verificar independientemente estos hallazgos:

1. **Verificación de duplicación de stock**:
   - Inspeccionar `app/Filament/Resources/ProductResource/RelationManagers/StocksRelationManager.php:125-137` y contrastar con `app/Services/KardexService.php:136-146`.
2. **Verificación de reglas de CSS Grid**:
   - Inspeccionar líneas 83 y 810–828 de `resources/views/filament/pages/pos-terminal.blade.php` buscando `1fr !important;` frente a la regla estipulada en `.ai/rules/boost/filament-tailwind-css-grid.md`.
3. **Verificación de permisos en Recursos Filament**:
   - Abrir `app/Filament/Resources/QuoteResource.php` y comprobar la ausencia de `canCreate`, `canEdit` y `canDelete`.
4. **Verificación de la suite de pruebas del proyecto**:
   - Ejecutar: `"$HOME/Library/Application Support/Herd/bin/php" artisan test --compact`
   - Validar que las 54 pruebas pasen determinísticamente.
