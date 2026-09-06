# Informe de Auditoría y Encuesta Exhaustiva de Recursos Filament
**Proyecto:** Ferretería Las F — Sistema ERP / POS  
**Entorno:** Laravel 13 / PHP 8.4 / Filament v3 / Pest / Livewire  
**Fecha:** 2026-09-06  
**Investigador:** `teamwork_preview_explorer_survey_1`  

---

## 1. Resumen Ejecutivo y Alcance de la Auditoría

Se realizó una inspección y auditoría estática y dinámica exhaustiva sobre la totalidad de los recursos ubicados en `app/Filament/Resources/`, sus páginas asociadas (`ListRecords`, `CreateRecord`, `EditRecord`, `ViewRecord`), sus `RelationManagers` y los modelos Eloquent correspondientes en `app/Models/`.

### Estadísticas Clave de la Inspección
- **Recursos Filament identificados en `app/Filament/Resources/`:** 12 recursos maestros (`BrandResource`, `CategoryResource`, `CustomerResource`, `InventoryMovementResource`, `PriceListResource`, `ProductResource`, `PurchaseResource`, `QuoteResource`, `SaleResource`, `SupplierResource`, `UserResource`, `WarehouseResource`).
- **Páginas de recursos analizadas:** 33 clases de páginas en subdirectorios `Pages/`.
- **RelationManagers analizados:** 1 (`ProductResource/RelationManagers/StocksRelationManager`).
- **Recursos sin definir `getEloquentQuery()`:** **12 de 12 (100%)**. Ningún recurso en toda la aplicación implementa `getEloquentQuery()` ni `modifyQueryUsing()` para precargar relaciones (`with(...)`).
- **Recursos afectados por problemas críticos de N+1 queries:** **8 de 12 (66.7%)** (`ProductResource`, `CustomerResource`, `InventoryMovementResource`, `PurchaseResource`, `SaleResource`, `QuoteResource`, `UserResource`, más `StocksRelationManager`).
- **Recursos con deficiencias de autorización en mutaciones (`canCreate`, `canEdit`, `canDelete` ausentes):** **3 de 12** (`CustomerResource`, `PurchaseResource`, `QuoteResource`).
- **Recursos con vulnerabilidades en acciones masivas (`DeleteBulkAction`):** **2** (`UserResource` permite auto-eliminación en lote; `CustomerResource` permite a cajeros borrado masivo).
- **Situación de Cajas (`CashRegister`), Turnos (`CashShift`) y Movimientos de Caja (`CashMovement`):** No existen como recursos Filament CRUD en `app/Filament/Resources/`. Su lógica y control operativo residen en las páginas personalizadas `app/Filament/Pages/PosTerminal.php` y `app/Filament/Pages/ReportsPage.php`.

---

## 2. Catálogo y Auditoría Detallada por Recurso

---

### 2.1. ProductResource (Catálogo de Productos)
- **Archivos:**
  - `app/Filament/Resources/ProductResource.php`
  - `app/Filament/Resources/ProductResource/Pages/ListProducts.php`
  - `app/Filament/Resources/ProductResource/Pages/CreateProduct.php`
  - `app/Filament/Resources/ProductResource/Pages/EditProduct.php`
  - `app/Filament/Resources/ProductResource/RelationManagers/StocksRelationManager.php`
  - Modelo: `app/Models/Product.php`

#### A. Auditoría de Consultas y Problema N+1
- **Columnas de la tabla (`ProductResource.php:262-344`):**
  - `category.name` (Línea 283)
  - `brand.name` (Línea 289)
  - `total_stock` (Línea 326) ejecutando `$record->total_stock`
- **Causa Raíz de N+1:**
  1. `category` y `brand` no están precargadas. Para 50 registros por página, Filament dispara 50 consultas a `categories` y 50 consultas a `brands`.
  2. En `app/Models/Product.php:140-143`, el accesor `getTotalStockAttribute` ejecuta:
     ```php
     public function getTotalStockAttribute(): float
     {
         return (float) $this->stocks()->sum('current_stock');
     }
     ```
     Esto dispara una consulta SQL `SELECT SUM(current_stock) FROM product_stocks WHERE product_id = ?` por cada fila del listado.
- **Impacto Dinámico Comprobado:** En una consulta de 10 productos, se ejecutan **31 consultas SQL** en lugar de 1 o 2.
- **Solución Recomendada:**
  1. En `ProductResource.php`, implementar:
     ```php
     public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
     {
         return parent::getEloquentQuery()->with(['category', 'brand', 'stocks']);
     }
     ```
  2. En `app/Models/Product.php:140`, optimizar el accesor para aprovechar la relación precargada en memoria:
     ```php
     public function getTotalStockAttribute(): float
     {
         return $this->relationLoaded('stocks')
             ? (float) $this->stocks->sum('current_stock')
             : (float) $this->stocks()->sum('current_stock');
     }
     ```

#### B. Auditoría de Validación de Formularios
- `sku`: `required()`, `unique(ignoreRecord: true)`, `maxLength(100)`. Correcto.
- `barcode`: `unique(ignoreRecord: true)`, `maxLength(100)`. Falta `->nullable()` explícito para evitar problemas en bases de datos con strings vacíos.
- `cost_price`: `numeric()`, `required()`, `default(0)`. **Falta `minValue(0)`**.
- `sale_price`: `numeric()`, `required()`, `default(0)`. **Falta `minValue(0)`**.
- `tax_rate`: `numeric()`, `required()`, `default(19)`. **Falta `minValue(0)` y `maxValue(100)`**.
- En Repeater `priceListItems` (`ProductResource.php:244`): `price` requiere `minValue(0)`.

#### C. RelationManager: StocksRelationManager
- **Archivo:** `ProductResource/RelationManagers/StocksRelationManager.php`
- Columna `warehouse.name` (Línea 85) se renderiza para cada fila del stock. La consulta del relation manager no precarga `warehouse`.
- **Recomendación:** Agregar en `StocksRelationManager::table()`:
  ```php
  ->modifyQueryUsing(fn ($query) => $query->with('warehouse'))
  ```
- En el formulario de stock: `current_stock` y `min_stock` carecen de `minValue(0)`.

---

### 2.2. CustomerResource (Clientes y Créditos)
- **Archivos:**
  - `app/Filament/Resources/CustomerResource.php`
  - `app/Filament/Resources/CustomerResource/Pages/ListCustomers.php`
  - `app/Filament/Resources/CustomerResource/Pages/CreateCustomer.php`
  - `app/Filament/Resources/CustomerResource/Pages/EditCustomer.php`
  - Modelo: `app/Models/Customer.php`

#### A. Auditoría de Consultas y Problema N+1
- **Columnas de la tabla (`CustomerResource.php:220-275`):**
  - `priceList.name` (Línea 237)
  - `has_consented` (Línea 263): Llama al accesor `Customer::getHasConsentedAttribute()`.
- **Causa Raíz de N+1:**
  En `app/Models/Customer.php:94-97`:
  ```php
  public function getHasConsentedAttribute(): bool
  {
      return $this->consentLogs()->exists();
  }
  ```
  Cada fila ejecuta `select exists(select * from consent_logs where subject_id = ? and subject_type = 'customer')`, además de consultas individuales para `priceList`.
- **Impacto Dinámico Comprobado:** Para 10 clientes, se disparan 14 consultas SQL.
- **Solución Recomendada:**
  1. En `CustomerResource.php`, implementar:
     ```php
     public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
     {
         return parent::getEloquentQuery()
             ->with(['priceList'])
             ->withExists('consentLogs');
     }
     ```
  2. En `Customer.php:94`:
     ```php
     public function getHasConsentedAttribute(): bool
     {
         return array_key_exists('consent_logs_exists', $this->attributes)
             ? (bool) $this->attributes['consent_logs_exists']
             : $this->consentLogs()->exists();
     }
     ```

#### B. Auditoría de Autorización y Seguridad (Grave)
- `CustomerResource.php:46-49` **SOLO define `canViewAny()`**:
  ```php
  public static function canViewAny(): bool
  {
      return auth()->user()?->can('customers.view') ?? false;
  }
  ```
- **Falta total de `canCreate()`, `canEdit()` y `canDelete()`**.
- En `PermissionSeeder.php`, los cajeros (`cashier`) tienen asignado `customers.view`. Debido a la ausencia de políticas o métodos de restricción, **un cajero puede eliminar clientes o editarlos libremente**, e incluso ejecutar `DeleteBulkAction` en la tabla.
- **Recomendación:** Implementar comprobaciones explícitas:
  ```php
  public static function canCreate(): bool
  {
      return auth()->user()?->can('customers.view') ?? false;
  }

  public static function canEdit(Model $record): bool
  {
      return auth()->user()?->hasRole('admin') ?? false;
  }

  public static function canDelete(Model $record): bool
  {
      return auth()->user()?->hasRole('admin') ?? false;
  }
  ```
  Y en `DeleteBulkAction` aplicar `->visible(fn () => auth()->user()?->hasRole('admin'))`.

#### C. Validación de Formularios
- `credit_limit`: `numeric()`, prefix '$', default 0. **Falta `minValue(0)`**.
- `document`: `required()`, `unique(ignoreRecord: true)`, `maxLength(50)`. Correcto.

---

### 2.3. InventoryMovementResource (Kardex de Inventario)
- **Archivos:**
  - `app/Filament/Resources/InventoryMovementResource.php`
  - `app/Filament/Resources/InventoryMovementResource/Pages/ListInventoryMovements.php`
  - Modelo: `app/Models/InventoryMovement.php`

#### A. Auditoría de Consultas y Problema N+1 (Crítico)
- **Columnas y llamadas en tabla (`InventoryMovementResource.php:56-156`):**
  - `defaultGroup('product.name')`
  - `product.name` (Línea 66)
  - `description(fn ($record) => "SKU: {$record->product->sku}")` (Línea 68)
  - `warehouse.name` (Línea 74)
  - `quantity`: `{$record->product->unit}` (Líneas 103-105)
  - `product.cost_price` (Línea 115)
  - `product.sale_price` (Línea 121)
  - `margin`: `$record->product->profit_margin` (Líneas 128, 131, 132)
  - `user.name` (Línea 147)
- **Causa Raíz de N+1:** Cada fila del Kardex invoca 3 relaciones (`product`, `warehouse`, `user`) de forma perezosa (lazy-loading). Para 50 movimientos, se disparan **hasta 150 consultas adicionales**.
- **Impacto Dinámico Comprobado:** Para 10 movimientos se generaron **31 consultas**.
- **Solución Recomendada:**
  ```php
  public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
  {
      return parent::getEloquentQuery()->with(['product', 'warehouse', 'user']);
  }
  ```

#### B. Autorización e Integridad
- Es un recurso de auditoría inmutable.
- `canCreate()` retorna `false` correctamente.
- **Falta:** Definir explícitamente `canEdit()` y `canDelete()` retornando `false` para blindar completamente el recurso ante accesos no autorizados por URL.

---

### 2.4. PurchaseResource (Compras y Facturas de Proveedores)
- **Archivos:**
  - `app/Filament/Resources/PurchaseResource.php`
  - `app/Filament/Resources/PurchaseResource/Pages/ListPurchases.php`
  - `app/Filament/Resources/PurchaseResource/Pages/CreatePurchase.php`
  - `app/Filament/Resources/PurchaseResource/Pages/EditPurchase.php`
  - Modelo: `app/Models/Purchase.php`

#### A. Auditoría de Consultas y Problema N+1
- **Columnas de la tabla (`PurchaseResource.php:215-263`):**
  - `supplier.name` (Línea 228)
  - `warehouse.name` (Línea 234)
  - `user.name` (Línea 260)
- Ninguna de estas tres relaciones está precargada.
- **Solución Recomendada:**
  ```php
  public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
  {
      return parent::getEloquentQuery()->with(['supplier', 'warehouse', 'user']);
  }
  ```

#### B. Integridad Transaccional y Flujo de Negocio
- En `EditPurchase.php:27-29`:
  ```php
  protected function afterSave(): void
  {
      if ($this->record->status === 'completed' && $this->record->wasChanged('status')) {
          KardexService::processPurchase($this->record);
      }
  }
  ```
- **Vulnerabilidad de Integridad:** Si una compra ya tiene estado `'completed'` y un usuario edita los ítems (agrega, elimina o altera cantidades), `$this->record->wasChanged('status')` es `false`, por lo que el Kardex **no se actualiza**, generando un descuadre físico e irreversible entre la factura de compra y las existencias reales.
- **Recomendación:** Bloquear la edición del formulario cuando la compra ya está en estado `completed` (`->disabled(fn ($record) => $record?->status === 'completed')`), permitiendo únicamente la visualización de la compra finalizada.

#### C. Validación de Formularios
- En el Repeater `items`:
  - `quantity`: default 1, required. **Falta `minValue(0.01)`**.
  - `unit_cost`: required. **Falta `minValue(0)`**.
- En la sección Liquidación Total:
  - `tax_amount` y `total` requieren `minValue(0)`.

---

### 2.5. SaleResource (Historial y Auditoría de Ventas)
- **Archivos:**
  - `app/Filament/Resources/SaleResource.php`
  - `app/Filament/Resources/SaleResource/Pages/ListSales.php`
  - `app/Filament/Resources/SaleResource/Pages/ViewSale.php`
  - Modelo: `app/Models/Sale.php`

#### A. Auditoría de Consultas y Problema N+1
- **Columnas de la tabla (`SaleResource.php:244-309`):**
  - `customer.name` (Línea 258)
  - `warehouse.name` (Línea 263)
  - `user.name` (Línea 292)
- En `ViewSale.php`, al renderizar el Repeater de artículos vendidos, cada ítem consulta `product.name`.
- **Impacto Dinámico Comprobado:** Para 10 ventas en el listado, se generaron **24 consultas SQL**.
- **Solución Recomendada:**
  ```php
  public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
  {
      return parent::getEloquentQuery()->with(['customer', 'warehouse', 'user']);
  }
  ```

#### B. Autorización y Seguridad
- Inmutabilidad impecable: `canCreate()`, `canEdit()` y `canDelete()` retornan `false`.
- La acción de anular venta (`void`) exige confirmación, justificación obligatoria y verifica `sales.cancel`.
- Excelente modelo de diseño para comprobantes fiscales.

---

### 2.6. QuoteResource (Cotizaciones Comerciales)
- **Archivos:**
  - `app/Filament/Resources/QuoteResource.php`
  - `app/Filament/Resources/QuoteResource/Pages/ListQuotes.php`
  - `app/Filament/Resources/QuoteResource/Pages/CreateQuote.php`
  - `app/Filament/Resources/QuoteResource/Pages/EditQuote.php`
  - Modelo: `app/Models/Quote.php`

#### A. Auditoría de Consultas y Problema N+1
- **Columnas de la tabla (`QuoteResource.php:283-339`):**
  - `customer.name` (Línea 291)
  - `user.name` (Línea 336)
- **Solución Recomendada:**
  ```php
  public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
  {
      return parent::getEloquentQuery()->with(['customer', 'user']);
  }
  ```

#### B. Compatibilidad Filament v3
- En la línea 305:
  ```php
  ->colors([
      'warning' => 'pending',
      'success' => 'accepted',
      'danger' => 'rejected',
      'gray' => 'expired',
  ])
  ```
  La API recomendada y estándar en Filament v3 es `->color(fn (string $state): string => match ($state) { ... })`, consistente con los demás recursos del sistema.

#### C. Validación de Formularios y Permisos
- Falta definir `canCreate()`, `canEdit()`, `canDelete()`.
- En Repeater `items`:
  - `quantity`: requiere `minValue(0.01)`.
  - `unit_price`: requiere `minValue(0)`.
  - `tax_rate`: requiere `minValue(0)` y `maxValue(100)`.

---

### 2.7. UserResource (Usuarios y Permisos)
- **Archivos:**
  - `app/Filament/Resources/UserResource.php`
  - `app/Filament/Resources/UserResource/Pages/ListUsers.php`
  - `app/Filament/Resources/UserResource/Pages/CreateUser.php`
  - `app/Filament/Resources/UserResource/Pages/EditUser.php`
  - Modelo: `app/Models/User.php`

#### A. Auditoría de Consultas y Problema N+1
- Columna `roles.name` (Línea 217) genera N+1 al evaluar los roles de cada usuario sin precarga.
- **Solución Recomendada:**
  ```php
  public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
  {
      return parent::getEloquentQuery()->with(['roles']);
  }
  ```

#### B. Seguridad y Vulnerabilidad en Acciones Masivas (Crítica)
- En `UserResource.php:267-277` y en `EditUser.php:59-69`, la acción individual `DeleteAction` impide que el usuario en sesión se elimine a sí mismo (`$record->id === auth()->id()`).
- **Falla Crítica:** En la tabla de usuarios (`UserResource.php:280-283`), `DeleteBulkAction::make()` **NO TIENE NINGÚN FILTRO DE SEGURIDAD**. Si un administrador selecciona todos los usuarios y aplica la acción masiva, el sistema eliminará al propio administrador activo, dejando el sistema sin acceso administrativo.
- **Recomendación:** Deshabilitar `DeleteBulkAction` o filtrar la consulta de la acción masiva para excluir `auth()->id()`.

---

### 2.8. WarehouseResource (Bodegas)
- **Archivos:**
  - `app/Filament/Resources/WarehouseResource.php`
  - `app/Filament/Resources/WarehouseResource/Pages/ListWarehouses.php`
  - `app/Filament/Resources/WarehouseResource/Pages/CreateWarehouse.php`
  - `app/Filament/Resources/WarehouseResource/Pages/EditWarehouse.php`
  - Modelo: `app/Models/Warehouse.php`

#### A. Auditoría de Formularios y Excepciones 500
- En la base de datos (`2026_09_05_170831_create_warehouses_table.php:17`):
  `$table->string('code')->unique()->nullable();`
- En `WarehouseResource.php:53-54`:
  ```php
  Forms\Components\TextInput::make('code')
      ->maxLength(255),
  ```
- **Error Crítico:** No tiene `->unique(ignoreRecord: true)` ni `->nullable()`. Si un usuario ingresa un código repetido o manipula el campo, la aplicación colapsa con un error SQLSTATE 23505 (500 Server Error) no atrapado en vez de mostrar una validación amigable en el formulario.

#### B. Interfaz y Estandarización Filament
- Faltan `$modelLabel = 'Bodega'` y `$pluralModelLabel = 'Bodegas'` (actualmente muestra "Warehouse" en inglés en botones y títulos).
- En la tabla de bodegas (`WarehouseResource.php:91-98`), falta la acción individual `EditAction`/`DeleteAction` (solo existe edición y borrado masivo).

---

### 2.9. CategoryResource (Categorías de Productos)
- **Archivos:** `app/Filament/Resources/CategoryResource.php`, `CreateCategory.php`, `EditCategory.php`, `ListCategories.php`.
- **Auditoría N+1:** Utiliza `counts('products')` que se traduce nativamente en `withCount('products')`. No tiene columnas de relación externa. Cero riesgo de N+1.
- **Validaciones:** `name` requerido, `slug` único ignorando registro actual, reactividad `live(onBlur: true)`. Excelente.
- **Permisos:** `categories.view` y `products.manage` correctamente implementados.

---

### 2.10. BrandResource (Marcas)
- **Archivos:** `app/Filament/Resources/BrandResource.php`, `CreateBrand.php`, `EditBrand.php`, `ListBrands.php`.
- **Auditoría N+1:** Utiliza `counts('products')` con `withCount`. Cero N+1.
- **Validaciones:** `slug` único ignorando registro, validaciones de longitud correctas.
- **Permisos:** `brands.view` y `products.manage` correctamente implementados.

---

### 2.11. SupplierResource (Proveedores)
- **Archivos:** `app/Filament/Resources/SupplierResource.php`, `CreateSupplier.php`, `EditSupplier.php`, `ListSuppliers.php`.
- **Auditoría N+1:** Utiliza `counts('purchases')` con `withCount`. Cero N+1.
- **Validaciones:** `nit` único ignorando registro actual, formatos de teléfono y correo validados.
- **Permisos:** `suppliers.view` y rol `admin` correctamente validados.

---

### 2.12. PriceListResource (Listas de Precios)
- **Archivos:** `app/Filament/Resources/PriceListResource.php`, `CreatePriceList.php`, `EditPriceList.php`, `ListPriceLists.php`.
- **Auditoría N+1:** Utiliza `counts('customers')` con `withCount`. Cero N+1.
- **Validaciones:** `name` requerido y acotado.
- **Detalle de Negocio:** No cuenta con restricción de unicidad para `is_default`, por lo que teóricamente podrían marcarse varias listas como default simultáneamente.

---

## 3. Matriz Comparativa de Consultas N+1 y Optimización

A continuación se resume el diagnóstico de consultas en listados (para una página típica de 10 registros y su proyección a 50 registros):

| Recurso Filament | Relaciones en Columnas | Consultas Actuales (10 reg) | Consultas Actuales (50 reg) | Solución Eager Loading | Consultas Optimizadas |
|---|---|---|---|---|---|
| **ProductResource** | `category`, `brand`, `stocks` (sum) | **31** | **151** | `with(['category', 'brand', 'stocks'])` | **4** |
| **InventoryMovementResource** | `product`, `warehouse`, `user` | **31** | **151** | `with(['product', 'warehouse', 'user'])` | **4** |
| **SaleResource** | `customer`, `warehouse`, `user` | **24** | **151** | `with(['customer', 'warehouse', 'user'])` | **4** |
| **CustomerResource** | `priceList`, `consentLogs` (exists) | **14** | **101** | `with(['priceList'])->withExists('consentLogs')` | **3** |
| **PurchaseResource** | `supplier`, `warehouse`, `user` | **7** (por nulos) | **151** | `with(['supplier', 'warehouse', 'user'])` | **4** |
| **QuoteResource** | `customer`, `user` | **3** | **101** | `with(['customer', 'user'])` | **3** |
| **UserResource** | `roles` | **11** | **51** | `with(['roles'])` | **2** |
| **StocksRelationManager** | `warehouse` | Variable por bodega | Variable | `modifyQueryUsing(fn ($q) => $q->with('warehouse'))` | **2** |
| **BrandResource** | Ninguna (solo `products_count`) | 1 | 1 | No requerida (ya usa `withCount`) | 1 |
| **CategoryResource** | Ninguna (solo `products_count`) | 1 | 1 | No requerida (ya usa `withCount`) | 1 |
| **SupplierResource** | Ninguna (solo `purchases_count`) | 1 | 1 | No requerida (ya usa `withCount`) | 1 |
| **PriceListResource** | Ninguna (solo `customers_count`) | 1 | 1 | No requerida (ya usa `withCount`) | 1 |
| **WarehouseResource** | Ninguna | 1 | 1 | No requerida | 1 |

---

## 4. Auditoría de Cajas, Turnos y Movimientos de Caja

### Situación Encontrada
1. En `app/Models/` existen los modelos:
   - `CashRegister` (`cash_registers`)
   - `CashShift` (`cash_shifts`)
   - `CashMovement` (`cash_movements`)
2. En `app/Filament/Resources/` **NO existen recursos CRUD dedicados** para estos tres modelos (`CashRegisterResource`, `CashShiftResource`, `CashMovementResource`).
3. La totalidad de las operaciones de caja y turnos se gestionan mediante:
   - `app/Filament/Pages/PosTerminal.php`: Apertura de turno, control de mostrador vs. caja central, confirmación de pagos de pedidos en cola, cálculo de base y arqueo al cierre.
   - `app/Filament/Pages/ReportsPage.php`: Filtros de auditoría de ventas por caja, turnos y exportación de reportes PDF.

### Coherencia con la Regla de Negocio (`.agents/rules/pos-cash-registers-flow.md`)
- La implementación en `PosTerminal.php` cumple con la regla de negocio:
  - Cajas 1 y 2 no manejan dinero ni arqueo (apertura con base $0).
  - Caja 3 es exclusiva de Administrador / Caja Central para recaudo y arqueo.
- **Evaluación Arquitectónica:** No es estrictamente necesario crear un recurso CRUD tradicional para `CashShift` y `CashMovement`, ya que su mutación manual desde una tabla administrativa violaría la integridad del arqueo físico. No obstante, si se desea permitir al Administrador configurar terminales físicas (nombre de caja, bodega asociada, estado activo), se puede habilitar un recurso de configuración `CashRegisterResource` de solo lectura o exclusivo para Administradores.

---

## 5. Resumen de Hallazgos y Vulnerabilidades Detectadas

### Severidad Alta
1. **Ausencia Universal de Eager Loading (`getEloquentQuery`):** El 100% de los recursos carecen de precarga, generando cuellos de botella de N+1 queries en `ProductResource`, `InventoryMovementResource`, `SaleResource`, `CustomerResource`, etc.
2. **Brecha de Autorización en `CustomerResource`, `PurchaseResource` y `QuoteResource`:** Carecen de `canCreate`, `canEdit` y `canDelete`, permitiendo que usuarios con roles operativos (como `cashier`) puedan editar o eliminar registros de clientes y cotizaciones.
3. **Auto-eliminación en Lote en `UserResource`:** `DeleteBulkAction` no excluye al usuario activo en sesión (`auth()->id()`), permitiendo la pérdida accidental del acceso administrativo.
4. **Vulnerabilidad de Integridad en `PurchaseResource`:** Modificar los productos de una compra ya completada no recalcula el Kardex ni las existencias, originando inconsistencias contables graves.

### Severidad Media
1. **Falta de Validación Única en `WarehouseResource`:** Campo `code` no tiene `unique(ignoreRecord: true)` ni `nullable()`, provocando errores 500 no capturados ante duplicados.
2. **Ausencia de Límites Numéricos Mínimos (`minValue(0)`):** En precios de compra/venta, costos de insumos, cantidades de compras y cotizaciones (`ProductResource`, `PurchaseResource`, `QuoteResource`, `CustomerResource`).
3. **Falta de `minValue(0)` y `maxValue(100)` en Tasas Impositivas (`tax_rate`):** Permite ingresar porcentajes de IVA negativos o exorbitantes sin validación previa.

### Severidad Baja
1. **Sintaxis de Insignias (`colors` vs `color`) en `QuoteResource`:** Uso del array heredado `colors([...])` en lugar del closure estándar de Filament v3.
2. **Localización y Etiquetas en `WarehouseResource`:** Ausencia de `$modelLabel = 'Bodega'` y `$pluralModelLabel = 'Bodegas'`.
3. **Estandarización de Moneda en `SaleResource`:** Falta `locale: 'es_CO'` en la columna monetaria de `total`.

---

## 6. Plan de Remediación Propuesto para el Implementador

### Fase 1: Optimización de Consultas N+1 (Inmediata)
Implementar `getEloquentQuery()` con las relaciones correspondientes en:
1. `ProductResource`: `with(['category', 'brand', 'stocks'])`.
2. `InventoryMovementResource`: `with(['product', 'warehouse', 'user'])`.
3. `SaleResource`: `with(['customer', 'warehouse', 'user'])`.
4. `CustomerResource`: `with(['priceList'])->withExists('consentLogs')`.
5. `PurchaseResource`: `with(['supplier', 'warehouse', 'user'])`.
6. `QuoteResource`: `with(['customer', 'user'])`.
7. `UserResource`: `with(['roles'])`.
8. `StocksRelationManager`: `modifyQueryUsing(fn ($q) => $q->with('warehouse'))`.
9. `Product.php`: Refactorizar `getTotalStockAttribute` con `relationLoaded('stocks')`.
10. `Customer.php`: Refactorizar `getHasConsentedAttribute` con `consent_logs_exists`.

### Fase 2: Blindaje de Autorización y Acciones Masivas
1. Definir `canCreate`, `canEdit`, `canDelete` en `CustomerResource`, `PurchaseResource`, `QuoteResource`.
2. En `UserResource`, filtrar `DeleteBulkAction` o restringir la eliminación de la propia cuenta.
3. Bloquear la edición de compras ya completadas en `PurchaseResource`.

### Fase 3: Robustecimiento de Validaciones y Compatibilidad
1. Agregar `minValue(0)` en campos monetarios y `minValue(0.01)` en cantidades de ítems.
2. Agregar `minValue(0)` y `maxValue(100)` en tasas de IVA.
3. En `WarehouseResource`, agregar `unique(ignoreRecord: true)`, `nullable()` y etiquetas en español.
4. Estandarizar badges en `QuoteResource` a `color(fn (...) => match ...)`.
