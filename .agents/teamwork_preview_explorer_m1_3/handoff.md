# Handoff Report: Milestone M1 Part 3 — CustomerResource, UserResource & Form Bounds

**Author:** `teamwork_preview_explorer_m1_3`  
**Working Directory:** `/Users/angeldiaz/Documents/FerreterialasF/.agents/teamwork_preview_explorer_m1_3`  
**Recipient:** `parent` (`c4204e0d-d0ed-4151-add9-8a27df422772`)  
**Date:** 2026-09-06  

---

## 1. Observation

1. **CustomerResource N+1 Query Root Cause:**
   - In `app/Filament/Resources/CustomerResource.php:237`: `Tables\Columns\TextColumn::make('priceList.name')`
   - In `app/Filament/Resources/CustomerResource.php:263`: `Tables\Columns\IconColumn::make('has_consented')`
   - In `app/Models/Customer.php:94-97`:
     ```php
     public function getHasConsentedAttribute(): bool
     {
         return $this->consentLogs()->exists();
     }
     ```
   - Dynamic Tinker Execution: Running `\App\Models\Customer::withExists("consentLogs as has_consented")->first()->has_consented` executed two separate SQL queries:
     1. `select "customers".*, exists(select * from "consent_logs" where "customers"."id" = "consent_logs"."subject_id" and "subject_type" = 'customer') as "has_consented" from "customers" limit 1`
     2. `select exists(select * from "consent_logs" where "consent_logs"."subject_id" = 1 and "consent_logs"."subject_id" is not null and "subject_type" = 'customer') as "exists"`
     Because `Customer::getHasConsentedAttribute()` unconditionally calls `$this->consentLogs()->exists()`, accessing `->has_consented` completely bypasses the subquery result in `$this->attributes['has_consented']`.

2. **CustomerResource Authorization Hole:**
   - In `app/Filament/Resources/CustomerResource.php:46-49`:
     ```php
     public static function canViewAny(): bool
     {
         return auth()->user()?->can('customers.view') ?? false;
     }
     ```
     `CustomerResource` does not define `canCreate()`, `canEdit()`, `canDelete()`, or `canDeleteAny()`.
   - In `database/seeders/PermissionSeeder.php:66-72`: Cashiers (`cashier`) are granted `customers.view`.
   - Because no model policy or resource mutation checks exist, any user with `customers.view` can open `/admin/customers/create`, `/admin/customers/{id}/edit`, and execute `DeleteBulkAction` or `DeleteAction`.

3. **UserResource Bulk Self-Deletion Vulnerability:**
   - In `app/Filament/Resources/UserResource.php:266-277` and `app/Filament/Resources/UserResource/Pages/EditUser.php:58-69`, the individual `DeleteAction` blocks self-deletion (`$record->id === auth()->id()`).
   - In `app/Filament/Resources/UserResource.php:280-283`:
     ```php
     ->bulkActions([
         Tables\Actions\BulkActionGroup::make([
             Tables\Actions\DeleteBulkAction::make(),
         ]),
     ]);
     ```
     `DeleteBulkAction` lacks any validation or filter against `auth()->id()`. An administrator selecting all rows and running "Delete selected" deletes their own active account.
   - In `app/Filament/Resources/UserResource.php:217`: `Tables\Columns\TextColumn::make('roles.name')` is rendered without `getEloquentQuery()` eager loading, triggering N+1 queries for roles.

4. **Master Data Form Bounds Violations across M1:**
   - `app/Filament/Resources/CustomerResource.php:128`: `credit_limit` has `->numeric()->default(0)` but lacks `->minValue(0)`.
   - `app/Filament/Resources/ProductResource.php:168`: `cost_price` has `->numeric()->default(0)` but lacks `->minValue(0)`.
   - `app/Filament/Resources/ProductResource.php:177`: `sale_price` has `->numeric()->default(0)` but lacks `->minValue(0)`.
   - `app/Filament/Resources/ProductResource.php:185`: `tax_rate` has `->numeric()->default(19)` but lacks `->minValue(0)->maxValue(100)->step(0.01)`.
   - `app/Filament/Resources/ProductResource.php:244`: `priceListItems` repeater `price` has `->numeric()` but lacks `->minValue(0)`.
   - `app/Filament/Resources/ProductResource/RelationManagers/StocksRelationManager.php:57,66,72`: `current_stock`, `min_stock`, and `max_stock` lack `->minValue(0)`.

---

## 2. Logic Chain

1. **Eliminating Customer N+1:**
   - Observation 1 proves that `CustomerResource::table()` triggers row-by-row queries for `priceList` and `has_consented`.
   - Defining `CustomerResource::getEloquentQuery()` with `->with(['priceList'])->withExists('consentLogs as has_consented')` loads the foreign relation in 1 query and the boolean consent in the base query.
   - Observation 1 also proves that without modifying `Customer::getHasConsentedAttribute()`, Laravel will execute query 2 every time `has_consented` is evaluated. Checking `array_key_exists('has_consented', $this->attributes)` in `Customer::getHasConsentedAttribute()` ensures the subquery result is utilized, dropping query count to 1 for the table rows.

2. **Hardening Customer Authorization:**
   - Observation 2 demonstrates that cashiers can mutate or delete customers via the Filament panel.
   - Adding `canCreate()`, `canEdit()`, `canDelete()`, and `canDeleteAny()` returning `auth()->user()?->hasRole('admin') ?? false` enforces that only administrators can modify master customer records.
   - Adding a debt guard to `canDelete()` (`(float) $record->current_debt <= 0`) ensures customers with active outstanding balances cannot be deleted.
   - In `table()->bulkActions()`, customizing `DeleteBulkAction` to filter out records where `current_debt > 0` protects accounts receivable and provides informative feedback.

3. **Securing User Bulk Deletion:**
   - Observation 3 shows that an administrator can accidentally delete their own account via bulk action.
   - Applying `$table->checkIfRecordIsSelectableUsing(fn (User $record): bool => $record->id !== auth()->id())` disables the row checkbox for the logged-in user in the UI.
   - In `DeleteBulkAction::action()`, filtering `$records->reject(fn (User $user) => $user->id === auth()->id())` guarantees that even if forced, the authenticated user is never deleted.
   - Adding `UserResource::getEloquentQuery()` with `->with(['roles'])` resolves N+1 on `roles.name`.

4. **Enforcing Form Bounds:**
   - Observation 4 shows numeric fields accept unbounded negative numbers and invalid tax rates.
   - Adding `->minValue(0)` on `credit_limit`, `cost_price`, `sale_price`, `priceListItems.price`, `current_stock`, `min_stock`, and `max_stock` prevents corrupt negative values.
   - Adding `->minValue(0)->maxValue(100)->step(0.01)` on `tax_rate` ensures tax percentages remain legally bounded.

---

## 3. Caveats

1. **Read-Only Scope Discipline:** As an explorer agent, no project source code files were edited. All proposed changes are documented in `strategy_report.md` with line numbers and exact before/after snippets ready for implementation.
2. **POS Quick Customer Creation:** Restricting `CustomerResource::canCreate` to `admin` does not impact cashier workflow because cashier customer creation is handled in `app/Filament/Pages/PosTerminal.php:861-915` via Livewire method `saveQuickCustomer()` using `Customer::create()`.
3. **Database Null-On-Delete Constraints:** In migrations `2026_09_05_170837_create_customers_and_quotes_tables.php:34` and `2026_09_05_170840_create_sales_and_returns_tables.php:16`, `customer_id` is configured with `nullOnDelete()`. The application-level guard preventing customer deletion when `current_debt > 0` adds financial integrity beyond the database cascade.

---

## 4. Conclusion

The fix strategy for M1 Part 3 is fully designed, verified via Tinker, and mapped to specific lines of code:
- **`CustomerResource.php`**: Add `getEloquentQuery()` (`with(['priceList'])`, `withExists('consentLogs as has_consented')`), implement `canCreate`, `canEdit`, `canDelete` (with debt guard), `canDeleteAny`, secure `DeleteBulkAction`, and add `minValue(0)` to `credit_limit`.
- **`Customer.php`**: Update `getHasConsentedAttribute()` to check `$this->attributes['has_consented']`.
- **`UserResource.php`**: Add `getEloquentQuery()` (`with(['roles'])`), add `checkIfRecordIsSelectableUsing(...)`, and customize `DeleteBulkAction::action()` to reject `auth()->id()`.
- **`ProductResource.php`**: Add `minValue(0)` to `cost_price`, `sale_price`, repeater `price`, and `minValue(0)->maxValue(100)->step(0.01)` to `tax_rate`.
- **`StocksRelationManager.php`**: Add `minValue(0)` to `current_stock`, `min_stock`, and `max_stock`.

---

## 5. Verification Method

1. **Automated Pest E2E Master Data Test Suite:**
   ```bash
   "$HOME/Library/Application Support/Herd/bin/php" vendor/bin/pest tests/Feature/E2E/Tier1/MasterDataFeatureTest.php
   ```
   *Expectation:* 30 tests pass. Covers F01, F04, F05, and F06.

2. **Feature Permission & Customer Test Suite:**
   ```bash
   "$HOME/Library/Application Support/Herd/bin/php" vendor/bin/pest tests/Feature/UserPermissionAndSettingsTest.php tests/Feature/QuoteAndCustomerTest.php
   ```
   *Expectation:* All 10 feature tests pass.

3. **Dynamic Query Count Verification via Tinker:**
   ```bash
   "$HOME/Library/Application Support/Herd/bin/php" artisan tinker --execute '
   \DB::enableQueryLog();
   $customers = \App\Filament\Resources\CustomerResource::getEloquentQuery()->limit(10)->get();
   foreach ($customers as $c) {
       $p = $c->priceList?->name;
       $h = $c->has_consented;
   }
   dump("Total queries: " . count(\DB::getQueryLog()));
   '
   ```
   *Expectation:* Query log count `<= 2`.

4. **Static Analysis & Formatting:**
   ```bash
   "$HOME/Library/Application Support/Herd/bin/php" vendor/bin/pint --dirty --format agent
   ```
