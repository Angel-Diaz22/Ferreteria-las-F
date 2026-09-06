# Handoff Report: Milestone M1 Part 2 — Master Data & Stock Integrity

## 1. Observation

1. **StocksRelationManager Initial Stock Duplication**:
   - In `app/Filament/Resources/ProductResource/RelationManagers/StocksRelationManager.php` lines 57–64, `current_stock` is a form input defaulting to 0, required, and dehydrated on create.
   - In lines 114–137, `CreateAction` defines:
     ```php
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
   - In `app/Services/KardexService.php` lines 125–147, `registerAdjustment` executes:
     ```php
     $stockRecord = ProductStock::firstOrCreate(...);
     $previousStock = (float) $stockRecord->current_stock;
     $qty = abs($quantity);
     if ($type === 'adjustment_in') {
         $resultingStock = $previousStock + $qty;
     }
     $stockRecord->update(['current_stock' => $resultingStock]);
     ```
   - Direct verification via `artisan tinker`:
     When `$p->stocks()->create(['warehouse_id' => $w->id, 'current_stock' => 20.00])` is followed by `KardexService::registerAdjustment(..., quantity: 20.00)`, the resulting stock was observed to be:
     `current_stock = 40.00`, and `InventoryMovement` recorded `previous_stock = 20.00`, `quantity = 20.00`, `resulting_stock = 40.00`.
   - Also, `current_stock`, `min_stock`, and `max_stock` in `StocksRelationManager.php` lines 57–77 lack `->minValue(0)` validation bounds (Feature F06).

2. **WarehouseResource Unique Code Constraint**:
   - Migration `database/migrations/2026_09_05_170831_create_warehouses_table.php` line 17 defines:
     `$table->string('code')->unique()->nullable();`
   - In `app/Filament/Resources/WarehouseResource.php` lines 53–54:
     `Forms\Components\TextInput::make('code')->maxLength(255),`
     Validation rule `->unique(ignoreRecord: true)` is absent.
   - In migrations `create_cash_registers_and_shifts_tables.php`, `create_sales_and_returns_tables.php`, and `create_suppliers_and_purchases_tables.php`, `warehouses` is referenced by foreign keys with `restrictOnDelete`. `WarehouseResource::table` lines 92–98 currently defines unconstrained `DeleteAction` and `DeleteBulkAction`.

3. **SupplierResource and PriceListResource**:
   - `app/Filament/Resources/SupplierResource.php` defines table column `purchases_count` via `counts('purchases')`, but lacks an explicit `getEloquentQuery()` method. In addition, deleting a supplier with existing purchases causes a 500 error due to `restrictOnDelete` on `purchases.supplier_id`.
   - `app/Filament/Resources/PriceListResource.php`:
     - Form input `name` (lines 66–70) lacks `->unique(ignoreRecord: true)`.
     - `PriceList` model has no mechanism enforcing that only one price list can be `is_default = true`.
     - Table lacks an `items_count` column displaying configured products.
     - Table lacks an explicit `getEloquentQuery()` with eager counts (`withCount(['customers', 'items'])`).
     - Delete actions permit deleting the default price list.

---

## 2. Logic Chain

1. **Root Cause of Stock Duplication**:
   - *Premise 1*: Filament's `CreateAction` automatically persists all form attributes into the relationship model before executing the `after()` hook.
   - *Premise 2*: User inputs $X$ into `current_stock`. The new `ProductStock` is saved with `current_stock = X`.
   - *Premise 3*: The `after()` hook passes `quantity: X` to `KardexService::registerAdjustment()`.
   - *Premise 4*: `registerAdjustment()` reads `current_stock` (which is already $X$) and adds `quantity` ($X$), updating `current_stock` to $X + X = 2X$.
   - *Conclusion 1*: Stock duplication is caused by pre-populating `ProductStock.current_stock` prior to invoking the additive `registerAdjustment()` service method.
   - *Remedy*: Overriding the creation step via `CreateAction::using()` creates the row with `current_stock = 0.00` within a `DB::transaction`, then passes $X$ to `KardexService::registerAdjustment()`. `0.00 + X = X`. Stock equals $X$, Kardex previous is 0.00, and resulting is $X$.

2. **Root Cause of Warehouse 500 on Duplicate Code**:
   - *Premise 1*: The database table has a UNIQUE index on `code`.
   - *Premise 2*: `WarehouseResource` does not declare `->unique(ignoreRecord: true)`.
   - *Conclusion 2*: Duplicate codes bypass Laravel validation and trigger unhandled `PDOException 23000` (500 server error).
   - *Remedy*: Add `->unique(ignoreRecord: true)` to `Forms\Components\TextInput::make('code')`.

3. **Master Data Referential Delete Safety**:
   - *Premise 1*: Relational databases enforce `restrictOnDelete` on foreign keys from transactional tables to master tables.
   - *Premise 2*: Filament's `DeleteAction` attempts unconditional record deletion unless intercepted by a `before()` hook or policy.
   - *Conclusion 3*: Deleting a warehouse or supplier with historical data crashes with a 500 error.
   - *Remedy*: Intercept deletions in `before()` hooks, check for relationship existence, and halt with a user-friendly notification.

---

## 3. Caveats

- **Scope Boundary**: As an explorer agent, no source code was modified during this turn. All proposals are documented in `strategy_report.md` for immediate application by the implementer.
- **Transactional Migrations**: The database schemas for `warehouses`, `suppliers`, and `price_lists` are fixed and should not be modified; all fixes are implemented in the Filament Resource layer and Model event listeners.
- **Concurrency & Locking**: `KardexService::registerAdjustment` locking with `lockForUpdate()` is part of Milestone M3 (Feature F16), but the `using()` atomic pattern designed here is 100% compatible with it.

---

## 4. Conclusion

The exact fix strategy for Milestone M1 Part 2 has been fully investigated, validated in tinker, and documented in detail:
1. In `StocksRelationManager`, replace the `after()` hook with a transactional `using()` closure that initializes stock at 0.00 and delegates stock increment and audit logging to `KardexService::registerAdjustment`. Add `minValue(0)` bounds to all numeric inputs.
2. In `WarehouseResource`, add `->unique(ignoreRecord: true)` to `code`, and add referential delete safety guards to `DeleteAction` and `DeleteBulkAction`.
3. In `SupplierResource` and `PriceListResource`, add `getEloquentQuery()` with eager counts (`withCount`), add uniqueness to price list names, enforce single-default price list invariant via `PriceList::saving` model hook, and prevent deleting default price lists or suppliers with purchase history.

The complete strategy report is available at:
`/Users/angeldiaz/Documents/FerreterialasF/.agents/teamwork_preview_explorer_m1_2/strategy_report.md`

---

## 5. Verification Method

1. **Verify Strategy Report Artifact**:
   Inspect `/Users/angeldiaz/Documents/FerreterialasF/.agents/teamwork_preview_explorer_m1_2/strategy_report.md`.
2. **Execute Full Test Suite**:
   Run `"$HOME/Library/Application Support/Herd/bin/php" artisan test --compact` (currently passing 54/54 tests).
3. **Verify Implementation (When Applied)**:
   - Run Livewire Pest test for `StocksRelationManager` creating stock of 25.00: verify `ProductStock::current_stock` is exactly 25.00 and `InventoryMovement` has `previous_stock = 0.00`, `quantity = 25.00`, `resulting_stock = 25.00`.
   - Run Livewire Pest test for `CreateWarehouse` with duplicate code: verify validation error `assertHasFormErrors(['code' => 'unique'])`.
   - Run Livewire Pest test deleting a warehouse with sales: verify action is halted and warning notification is sent.
