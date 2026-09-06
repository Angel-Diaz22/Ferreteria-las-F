# Reporte de Auditoría: Código Muerto, Rutas, Controladores, Vistas y Componentes

**Fecha de ejecución:** 2026-09-06T03:42:00Z  
**Explorador:** `teamwork_preview_explorer_survey_2`  
**Ámbito:** Identificación de código muerto, rutas huérfanas, controladores sin uso, componentes Livewire descontinuados y vistas Blade redundantes en Ferretería Las F (Laravel 12 / PHP 8.4 / Filament v3).

---

## 1. Resumen Ejecutivo

Se ha completado una inspección exhaustiva y transversal del 100% de los archivos del proyecto relacionados con enrutamiento, controladores, componentes de interfaz (Filament/Livewire), plantillas Blade y modelos de datos.

| Área Inspeccionada | Total Archivos | Activos / Requeridos | Huérfanos / Código Muerto | Estado |
|---|---|---|---|---|
| **Rutas (`routes/`)** | 2 (`web.php`, `console.php`) | 2 | 0 | Limpio y Consistente |
| **Controladores (`app/Http/Controllers/`)** | 3 (`Controller`, `QuotePdfController`, `SaleReceiptController`) | 3 | 0 | 100% enlazados y testeados |
| **Vistas Blade (`resources/views/`)** | 7 plantillas `.blade.php` | 7 | 0 | 100% renderizadas en producción |
| **Componentes Livewire / Filament Pages** | 3 páginas (`PosTerminal`, `ReportsPage`, `SettingsPage`) + 12 Resources | Todas activas | 0 | 100% descubiertas y navegables |
| **Modelos Eloquent (`app/Models/`)** | 24 modelos | 22 activos + 2 latentes (`SaleReturn`, `SaleReturnItem`) | 0 eliminables | Integridad relacional preservada |
| **Archivos Estáticos / Assets (`public/images/`)** | 2 archivos de imagen | 1 (`logo.png`) | 1 (`logo.jpg` sin referencias) | Candidato a remoción segura |

---

## 2. Auditoría Detallada por Componente

### 2.1. Rutas de la Aplicación (`routes/`)

Se verificó el catálogo completo de rutas mediante `php artisan route:list --json` y análisis estático de `routes/web.php` y `bootstrap/app.php`.

#### Archivos Analizados:
- `routes/web.php` (20 líneas):
  - Línea 7: `Route::get('/', ...)` -> Redirección inmediata a `/admin`. Probada en `tests/Feature/ExampleTest.php:15`.
  - Línea 14: `Route::get('/admin/quotes/{quote}/pdf', [QuotePdfController::class, 'download'])->name('quotes.pdf')` -> Generación y descarga de cotizaciones comerciales en PDF.
  - Línea 17: `Route::get('/admin/sales/{sale}/receipt', [SaleReceiptController::class, 'print'])->name('sales.receipt')` -> Despliegue de tirilla térmica POS (80mm) con disparador automático `window.print()`.
  - Línea 18: `Route::get('/admin/sales/{sale}/pdf', [SaleReceiptController::class, 'pdf'])->name('sales.pdf')` -> Renderizado DomPDF de la tirilla térmica.
- `routes/console.php` (9 líneas):
  - Línea 6: Comando `inspire` estándar de Laravel.
- `routes/api.php`: No existe. En `bootstrap/app.php:9-13` no se monta ningún archivo de API, manteniendo la superficie de ataque mínima.

#### Verificación de Seguridad y Rate Limiting:
Las 3 rutas de generación de documentos (`quotes.pdf`, `sales.receipt`, `sales.pdf`) están protegidas por:
- `auth`: Exige sesión autenticada activa.
- `throttle:60,1`: Previene denegación de servicio (DoS) por renderizado masivo en CPU de DomPDF. Verificado en `tests/Feature/SecurityAndStabilityTest.php:258-266`.

**Hallazgo:** **0 rutas huérfanas o apuntando a controladores inexistentes.**

---

### 2.2. Controladores HTTP (`app/Http/Controllers/`)

Se examinaron todos los controladores presentes en `app/Http/Controllers/`:

1. **`app/Http/Controllers/Controller.php`** (9 líneas):
   - Clase abstracta base del framework.
   - Estado: Requerida por la convención de Laravel.

2. **`app/Http/Controllers/QuotePdfController.php`** (70 líneas):
   - Método `download(Request $request, Quote $quote): Response`.
   - Carga con eager loading las relaciones requeridas: `customer.priceList`, `user`, `items.product.category`, `items.product.brand` (Líneas 30-35) para prevenir N+1 al compilar `pdf.quote`.
   - Referenciado en:
     - `routes/web.php:14`
     - `app/Filament/Resources/QuoteResource.php:356`
     - `app/Filament/Resources/QuoteResource/Pages/EditQuote.php:20`
     - `tests/Feature/QuoteAndCustomerTest.php:183`
     - `tests/Feature/SecurityAndStabilityTest.php:259`
   - Estado: 100% activo, sin métodos muertos.

3. **`app/Http/Controllers/SaleReceiptController.php`** (83 líneas):
   - Métodos:
     - `print(Sale $sale): View` (Línea 29) -> Retorna vista `pdf.receipt`.
     - `pdf(Request $request, Sale $sale): Response` (Línea 41) -> Genera PDF térmico ajustado a 80mm continuo (`setPaper([0, 0, 226.77, $calculatedHeight])`).
     - `getCompanyInfo(): array` (Línea 68) -> Helper institucional compartido.
   - Referenciado en:
     - `routes/web.php:17, 18`
     - `app/Filament/Resources/SaleResource.php:341, 349`
     - `app/Filament/Resources/SaleResource/Pages/ViewSale.php:37, 45`
     - `resources/views/pdf/receipt.blade.php:124`
     - `tests/Feature/PosAndSaleTest.php:387, 395, 657`
     - `tests/Feature/SecurityAndStabilityTest.php:259`
   - Estado: 100% activo, sin métodos muertos.

**Hallazgo:** **0 controladores huérfanos. No existen controladores residuales de prototipos o borradores.**

---

### 2.3. Componentes Livewire y Páginas de Filament

La arquitectura del sistema utiliza la integración nativa de **Filament v3**, donde las páginas personalizadas y los recursos actúan como componentes Livewire reactivos:

1. **`App\Filament\Pages\PosTerminal` (`app/Filament/Pages/PosTerminal.php`, 1.417 líneas):**
   - Página principal del Punto de Venta.
   - 3 Pestañas operativas:
     - Tab 1: Terminal de Mostrador (catálogo visual, lector de código de barras USB, carrito, clientes rápidos).
     - Tab 2: Caja Central de Cobro (recaudo exclusivo Caja 3, validación de billetes, cambio).
     - Tab 3: Despacho de Mercancía (entrega física con sello "PAGADO").
   - Todos los métodos públicos (`addToCart`, `updateQuantity`, `scanBarcode`, `saveQuickCustomer`, `generateOrderAndPrint`, `confirmCashierPayment`, `dispatchOrder`, `selectCashRegister`, `confirmCloseShift`, etc.) tienen enlaces biunívocos con directivas `wire:click` y formularios en `pos-terminal.blade.php`.
   - Estado: Totalmente activo y probado en `PosAndSaleTest.php` y `ReportsAndCashRegisterTest.php`.

2. **`App\Filament\Pages\ReportsPage` (`app/Filament/Pages/ReportsPage.php`, 345 líneas):**
   - Panel de control de estadísticas y métricas clave (KPIs).
   - Métodos: `applyPreset()`, `downloadPdf()`, `updatedStartDate()`, `getKpisProperty()`, `getDailyHistogramProperty()`, etc.
   - Totalmente integrado con Chart.js y `reports-page.blade.php`.
   - Estado: Totalmente activo y probado en `UserPermissionAndSettingsTest.php` y `ReportsAndCashRegisterTest.php`.

3. **`App\Filament\Pages\SettingsPage` (`app/Filament/Pages/SettingsPage.php`, 151 líneas):**
   - Panel de parametrización institucional y tributaria (control de régimen No Responsable de IVA, porcentaje legal, razón social, teléfonos).
   - Acceso restringido exclusivamente al rol `admin` (`canAccess()`).
   - Estado: Totalmente activo y probado en `SystemSettingsAndTaxTest.php`.

4. **Recursos Filament (`app/Filament/Resources/`, 46 archivos):**
   - 12 Recursos con sus respectivas páginas List, Create, Edit y View:
     - `BrandResource` (List, Create, Edit)
     - `CategoryResource` (List, Create, Edit)
     - `CustomerResource` (List, Create, Edit)
     - `InventoryMovementResource` (List - histórico inmutable de auditoría)
     - `PriceListResource` (List, Create, Edit)
     - `ProductResource` (List, Create, Edit + `StocksRelationManager`)
     - `PurchaseResource` (List, Create, Edit)
     - `QuoteResource` (List, Create, Edit)
     - `SaleResource` (List, View - creación centralizada en POS Terminal)
     - `SupplierResource` (List, Create, Edit)
     - `UserResource` (List, Create, Edit)
     - `WarehouseResource` (List, Create, Edit)
   - Todos los recursos son descubiertos automáticamente por `AdminPanelProvider::discoverResources()`.

**Hallazgo:** **No existen componentes Livewire huérfanos o descontinuados.**

---

### 2.4. Vistas Blade (`resources/views/`)

Se realizó un inventario completo de todas las plantillas en `resources/views/`:

1. `resources/views/filament/custom-sidebar-styles.blade.php` (88 líneas):
   - Inyectado vía `PanelsRenderHook::HEAD_END` en `AdminPanelProvider.php:60`.
   - Define la paleta corporativa naranja, estados `hover`, efectos de elevación y resaltado del módulo activo.
   - Estado: Activo.

2. `resources/views/filament/pages/pos-terminal.blade.php` (2.432 líneas):
   - Vista reactiva del POS.
   - Cumple con las restricciones de CSS Grid estipuladas en `.ai/rules/boost/filament-tailwind-css-grid.md`.
   - Estado: Activo.

3. `resources/views/filament/pages/reports-page.blade.php` (741 líneas):
   - Vista del módulo de informes con histogramas Chart.js y tabla de ventas paginada.
   - Estado: Activo.

4. `resources/views/filament/pages/settings-page.blade.php` (17 líneas):
   - Formulario de configuración de Filament.
   - Estado: Activo.

5. `resources/views/pdf/quote.blade.php` (428 líneas):
   - Plantilla HTML/CSS para DomPDF de cotizaciones comerciales (formato Carta).
   - Compilada por `QuotePdfController@download`.
   - Estado: Activo.

6. `resources/views/pdf/receipt.blade.php` (298 líneas):
   - Plantilla de tirilla térmica POS de 80mm con cuadro para sello físico de caja (anti-fraude).
   - Renderizada por `SaleReceiptController@print` y `@pdf`.
   - Estado: Activo.

7. `resources/views/pdf/sales-report.blade.php` (237 líneas):
   - Plantilla formal de reporte de ventas gerencial.
   - Compilada por `ReportsPage@downloadPdf`.
   - Estado: Activo.

**Hallazgo:** **0 vistas Blade huérfanas o redundantes. Las 7 vistas son requeridas y operativas.**

---

### 2.5. Modelos Eloquent y Esquema de Base de Datos

Se auditaron los 24 modelos en `app/Models/`:

- **Modelos totalmente activos con operaciones CRUD / Servicios:**
  - `Brand`, `Category`, `Customer`, `ConsentLog`, `PriceList`, `PriceListItem`, `Product`, `ProductStock`, `Purchase`, `PurchaseItem`, `Quote`, `QuoteItem`, `Sale`, `SaleItem`, `Supplier`, `SystemSetting`, `User`, `Warehouse`, `CashRegister`, `CashShift`, `CashMovement`, `InventoryMovement`.

- **Modelos Latentes (Dormant Schema Models):**
  - **`app/Models/SaleReturn.php`** y **`app/Models/SaleReturnItem.php`**:
    - Creados en migración `2026_09_05_170840_create_sales_and_returns_tables.php`.
    - Cuentan con factorías (`database/factories/SaleReturnFactory.php`, `SaleReturnItemFactory.php`).
    - Relacionados formalmente con `Sale` (`Sale::returns()`) y `SaleItem` (`SaleItem::returnItems()`).
    - Actualmente, las cancelaciones de venta se efectúan a través de `PosService::voidSale()`, que cambia el estado de la venta a `cancelled` y reversa el stock en Kardex como ajuste. La creación de devoluciones parciales y notas crédito con `SaleReturn` está preparada en el esquema relacional para etapas futuras.
    - **Recomendación:** **NO ELIMINAR.** Forman parte integral de las restricciones de clave foránea (`foreignId('sale_return_id')->constrained('sale_returns')->cascadeOnDelete()`) y el modelo relacional del ERP.

---

### 2.6. Archivos Estáticos y Assets (`public/`)

- `public/images/logo.png`:
  - Utilizado en `AdminPanelProvider.php`, `pos-terminal.blade.php`, `pdf/quote.blade.php`, `pdf/receipt.blade.php`, `pdf/sales-report.blade.php`.
  - Peso: 78 KB. Transparente institucional.
- `public/images/logo.jpg`:
  - **No está referenciado en ningún archivo del código fuente.**
  - Quedó como residuo de la conversión a PNG transparente.
  - **Candidato a eliminación segura** (ahorro de espacio sin impacto funcional).

---

### 2.7. Pruebas Automatizadas (`tests/`)

- `tests/Unit/ExampleTest.php`:
  - Contiene `test_that_true_is_true()` (`$this->assertTrue(true)`).
  - Es el test unitario de ejemplo por defecto de Laravel.
  - Siguiendo la regla de Pest en `AGENTS.md` ("Do not delete tests or test files without approval"), debe mantenerse hasta que se incorporen pruebas unitarias específicas de servicios o helpers.
- `tests/Feature/ExampleTest.php`:
  - Valida la redirección de `/` a `/admin`. Totalmente funcional y útil.

---

### 2.8. Observación sobre Datos Semilla (`SalesReportDataSeeder.php`)

- En `database/seeders/SalesReportDataSeeder.php` (Línea 49):
  - Se asigna un `'opening_amount' => 200000.00` a `Caja 1`.
  - De acuerdo con la regla de negocio `.agents/rules/pos-cash-registers-flow.md`, las Cajas 1 y 2 son puestos de mostrador sin dinero físico ni base en efectivo (apertura automática con $0 COP).
  - En tiempo de ejecución (`PosTerminal.php:306`), la regla se cumple estrictamente (base = $0 COP). La discrepancia solo existe como dato histórico simulado en el seeder de pruebas.

---

## 3. Matriz de Inventario y Recomendaciones

| Archivo / Componente | Clasificación | Estado Actual | Acción Recomendada | Riesgo |
|---|---|---|---|---|
| `routes/web.php` (4 rutas) | Rutas activas | En uso y testeadas | Mantener | Cero |
| `routes/console.php` (`inspire`) | Comando CLI | Por defecto Laravel | Mantener | Cero |
| `app/Http/Controllers/QuotePdfController.php` | Controlador | En uso (`quotes.pdf`) | Mantener | Cero |
| `app/Http/Controllers/SaleReceiptController.php` | Controlador | En uso (`sales.receipt`, `sales.pdf`) | Mantener | Cero |
| `resources/views/filament/**` (4 vistas) | Blade Filament | En uso | Mantener | Cero |
| `resources/views/pdf/**` (3 vistas) | Blade DomPDF | En uso | Mantener | Cero |
| `app/Models/SaleReturn.php` | Modelo Relacional | Latente / Sin CRUD | Mantener (Preservar integridad de BD) | Nulo si se mantiene |
| `app/Models/SaleReturnItem.php` | Modelo Relacional | Latente / Sin CRUD | Mantener (Preservar integridad de BD) | Nulo si se mantiene |
| `public/images/logo.jpg` | Asset estático | Sin referencias | Puede eliminarse con seguridad | Nulo |
| `tests/Unit/ExampleTest.php` | Test unitario | Placeholder Laravel | Mantener (o reemplazar por test real de `ImageOptimizerService`) | Nulo |
