# Project: Ferretería Las F ERP/POS Audit & Hardening

## Architecture
- **Framework**: Laravel 12 / PHP 8.4 / Filament v3 / Livewire 3
- **Database**: SQLite (dev/test), MySQL / PostgreSQL (prod-ready migrations)
- **Module Boundaries**:
  - `Catalog & Master Data`: Products, Categories, Brands, Warehouses, Suppliers, Customers, Price Lists, Users.
  - `Transactional Documents`: Purchases, Sales, Quotes, Inventory Movements, PDF generation.
  - `POS & Cash Registers`: Terminal, Cart, Order Queue, Shifts, Cash Movements, Kardex & Inventory Locking.
  - `Infrastructure & Quality`: Clean assets, Pest automated test suite, Laravel Pint formatting.

## Feature Inventory
| # | Feature | Description | Milestone | Source |
|---|---------|-------------|-----------|--------|
| F01 | Eager loading in Master Data tables | Eliminate N+1 queries in Product, Customer, User, Warehouse tables via `getEloquentQuery()` | M1 | Survey 1 |
| F02 | Stock Assignment Integrity | Eliminate stock duplication bug in `StocksRelationManager` upon initial warehouse assignment | M1 | Survey 3 |
| F03 | Warehouse Code Uniqueness Validation | Add `unique(ignoreRecord: true)` to `WarehouseResource` code to prevent 500 PDOExceptions | M1 | Survey 1 |
| F04 | Master Data Authorization Hardening | Add `canCreate`, `canEdit`, `canDelete` to `CustomerResource` preventing unauthorized cashier mutations | M1 | Survey 1, 3 |
| F05 | User Bulk Deletion Safety Guard | Restrict `UserResource::DeleteBulkAction` from deleting the authenticated user's own account | M1 | Survey 1 |
| F06 | Master Data Form Bounds & Validation | Add `minValue(0)` to numeric prices and stock fields; validate tax rates between 0 and 100 | M1 | Survey 1 |
| F07 | Eager loading in Transactional tables | Eliminate N+1 queries in Sale, Purchase, Quote, InventoryMovement tables via `getEloquentQuery()` | M2 | Survey 1 |
| F08 | Transactional Authorization Hardening | Add `canCreate`, `canEdit`, `canDelete` to `PurchaseResource` and `QuoteResource` | M2 | Survey 1, 3 |
| F09 | Document Download Route Authorization | Add authorization checks (`quotes.view`, `sales.view`) to `QuotePdfController` and `SaleReceiptController` | M2 | Survey 2, 3 |
| F10 | Quote Consecutive Atomic Generation | Wrap quote number generation in `DB::transaction` with atomic lock to prevent duplicate numbers | M2 | Survey 3 |
| F11 | Purchase Status & Stock Sync Guard | Prevent stock desynchronization when modifying items on completed purchases | M2 | Survey 1, 3 |
| F12 | POS Cash Registers Flow Compliance | Strict enforcement of `pos-cash-registers-flow.md`: Cajas 1 & 2 no cash/cobro with $0 base; Caja 3 Admin-only cash handling with physical base prompt | M3 | Survey 3 |
| F13 | POS Shift Mount & Close Lifecycle | Fix infinite shift auto-open loop in `PosTerminal::mount()` and `confirmCloseShift()` for Caja 3 | M3 | Survey 3 |
| F14 | Robust Cash Register Identification | Replace fragile string matching (`str_contains`) with explicit type/enum attribute in `CashRegister` | M3 | Survey 3 |
| F15 | POS CSS Grid Anti-Blowout Compliance | Replace `1fr` with `minmax(0, 1fr)` and add desktop 2-column layout in `pos-terminal.blade.php` | M3 | Survey 3 |
| F16 | Concurrency & Pessimistic Locking | Add `lockForUpdate()` in `KardexService::registerAdjustment` and `selectCashRegister` | M3 | Survey 3 |
| F17 | Safe Dead Asset Cleanup | Safely remove unreferenced `public/images/logo.jpg` preserving `logo.png` | M4 | Survey 2 |
| F18 | Framework Modernization & Pint Cleanliness | Enforce PHP 8.4 enums, typed methods, and clean Laravel Pint formatting | M4 | Survey 1, 2, 3 |
| F19 | Opaque-box E2E Test Suite | Comprehensive 4-tier E2E test suite covering catalog, transactions, POS, and cash shifts | E2E-Track | Dual-Track |
| F20 | Final Adversarial Coverage Hardening | Tier 5 white-box stress testing of race conditions, boundaries, and zero-tolerance integrity audit | Final-M | Dual-Track |

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| E2E | E2E Testing Track | Automated 4-tier test suite (Tiers 1-4) creating `TEST_INFRA.md` and `TEST_READY.md` | none | PLANNED |
| M1 | Master Data & Catalog Hardening | F01, F02, F03, F04, F05, F06 (Product, Category, Brand, Warehouse, Customer, User, etc.) | none | PLANNED |
| M2 | Transactional Resources & Documents | F07, F08, F09, F10, F11 (Purchases, Sales, Quotes, Movements, PDF Controllers) | M1 | PLANNED |
| M3 | POS Cash Registers & Concurrency | F12, F13, F14, F15, F16 (PosTerminal, CashRegister, CashShift, KardexService, pos-terminal view) | M1, M2 | PLANNED |
| M4 | Dead Code Cleanup & Modernization | F17, F18 (Safe asset cleanup, code formatting, style verification) | M3 | PLANNED |
| Final | E2E Pass & Adversarial Hardening | F19, F20 (100% E2E test pass, Tier 5 adversarial hardening, Forensic Audit) | E2E, M4 | PLANNED |

## Interface Contracts

### ProductStock ↔ KardexService
- **Assignment Contract**: When assigning stock to a warehouse via `StocksRelationManager`, the `ProductStock` record must not be pre-populated with stock if `KardexService::registerAdjustment` is invoked; or `KardexService` must record the movement without adding to an already-persisted stock amount. Stock must equal initial amount `X`, never `2X`.
- **Locking Contract**: All mutations to `ProductStock::current_stock` inside `KardexService` must acquire `lockForUpdate()` on the stock record within a `DB::transaction`.

### PosTerminal ↔ CashShift & CashRegister
- **Shift Opening Contract**:
  - Cajas 1 & 2: Automatic opening with `opening_amount = 0.0`. Cash operations disabled.
  - Caja 3: Must prompt Admin for physical cash `opening_amount >= 0.0`. Do not auto-reopen shift on close.
- **Cashier Determination Contract**: `CashRegister::isCashier()` must return boolean based on explicit register configuration/type, not fragile name substring search.

### Controllers ↔ Authorization Middleware/Policies
- `QuotePdfController::download($quote)`: Requires authenticated user with `quotes.view` permission.
- `SaleReceiptController::print($sale)` / `pdf($sale)`: Requires authenticated user with `sales.view` permission.

## Code Layout
- `app/Filament/Resources/`: Filament resources, list/create/edit pages, relation managers.
- `app/Filament/Pages/`: Custom Filament pages (`PosTerminal.php`, `ReportsPage.php`, `SettingsPage.php`).
- `app/Http/Controllers/`: HTTP document download controllers (`QuotePdfController.php`, `SaleReceiptController.php`).
- `app/Services/`: Domain business logic (`KardexService.php`, `PosService.php`).
- `app/Models/`: Eloquent models and business relationships.
- `resources/views/filament/pages/`: Custom Blade views (`pos-terminal.blade.php`).
- `tests/Feature/`: Pest feature tests.
