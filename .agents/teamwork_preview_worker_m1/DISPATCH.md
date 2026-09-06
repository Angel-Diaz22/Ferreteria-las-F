## 2026-09-06T03:49:02Z

You are teamwork_preview_worker_m1.
Your working directory is: /Users/angeldiaz/Documents/FerreterialasF/.agents/teamwork_preview_worker_m1

MANDATORY FIRST STEP:
Read:
1. /Users/angeldiaz/Documents/FerreterialasF/.agents/ORIGINAL_REQUEST.md
2. /Users/angeldiaz/Documents/FerreterialasF/PROJECT.md
3. Strategy reports from M1 Explorers:
   - /Users/angeldiaz/Documents/FerreterialasF/.agents/teamwork_preview_explorer_m1_1/strategy_report.md
   - /Users/angeldiaz/Documents/FerreterialasF/.agents/teamwork_preview_explorer_m1_2/strategy_report.md
   - /Users/angeldiaz/Documents/FerreterialasF/.agents/teamwork_preview_explorer_m1_3/strategy_report.md

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. An auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

EXCLUSIVE WRITE FILE OWNERSHIP:
You own exclusively:
- app/Models/Product.php
- app/Filament/Resources/ProductResource.php
- app/Filament/Resources/ProductResource/RelationManagers/StocksRelationManager.php
- app/Filament/Resources/WarehouseResource.php
- app/Filament/Resources/SupplierResource.php
- app/Filament/Resources/PriceListResource.php
- app/Models/Customer.php
- app/Filament/Resources/CustomerResource.php
- app/Filament/Resources/UserResource.php
- tests/Feature/WarehouseAndStockTest.php or related existing tests if updating expectations

OBJECTIVES:
Implement the complete Milestone M1 remediation based on Explorer strategy reports:
1. Product & Stocks N+1 elimination:
   - In `app/Models/Product.php`: update `getTotalStockAttribute()` to check `array_key_exists('total_stock', $this->attributes)` first, then `$this->relationLoaded('stocks') ? (float) $this->stocks->sum('current_stock') : (float) $this->stocks()->sum('current_stock')`.
   - In `app/Filament/Resources/ProductResource.php`: add `getEloquentQuery()` with `parent::getEloquentQuery()->with(['category', 'brand'])->withSum('stocks as total_stock', 'current_stock')`. Make the `total_stock` column sortable.
   - Add numeric bounds `minValue(0)` on prices and `minValue(0)->maxValue(100)->step(0.01)` on `tax_rate`.
2. Stock Duplication Bug in `StocksRelationManager`:
   - In `app/Filament/Resources/ProductResource/RelationManagers/StocksRelationManager.php`:
     Fix the `CreateAction`: use `using()` within `DB::transaction()`. Set initial `current_stock = 0.00`, persist the record, and then invoke `KardexService::registerAdjustment` to increment it to the desired quantity while creating the proper initial Kardex audit record.
     Add `minValue(0)` on `current_stock`, `min_stock`, `max_stock`.
3. Warehouse, Supplier & PriceList hardening:
   - In `app/Filament/Resources/WarehouseResource.php`: add `unique(ignoreRecord: true)` to `code`. In `DeleteAction`, protect against deleting warehouses with active stock (`$record->stocks()->where('current_stock', '>', 0)->exists()`).
   - In `PriceListResource.php`: enforce single-default invariant if needed, and add bounds.
   - In `SupplierResource.php`: hardening validations as recommended by explorers.
4. Customer N+1 & Authorization:
   - In `app/Models/Customer.php`: update `getHasConsentedAttribute()` to check `array_key_exists('has_consented', $this->attributes)` first.
   - In `app/Filament/Resources/CustomerResource.php`: add `getEloquentQuery()` with `with(['priceList'])->withExists('consentLogs as has_consented')`.
   - Add authorization methods `canCreate()`, `canEdit()`, `canDelete()`, `canDeleteAny()` checking `auth()->user()?->hasRole('admin')`.
   - Add `minValue(0)` on `credit_limit` and delete guard preventing customer deletion if `current_debt > 0`.
5. UserResource self-deletion guard & N+1:
   - In `app/Filament/Resources/UserResource.php`: add `getEloquentQuery()` with `with(['roles'])`.
   - Prevent self-deletion: add `$table->checkIfRecordIsSelectableUsing(fn (User $record) => $record->id !== auth()->id())` and filter out `auth()->id()` in `DeleteBulkAction`.

VERIFICATION:
- Run tests: `"$HOME/Library/Application Support/Herd/bin/php" artisan test --compact`
- Run Pint: `"$HOME/Library/Application Support/Herd/bin/php" vendor/bin/pint --dirty --format agent`
- Verify 100% test pass.
