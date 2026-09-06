# Handoff Report: Elimination of N+1 Queries in Master Data (M1 Part 1)

## 1. Observation

1. **`app/Filament/Resources/ProductResource.php`**:
   - Lines 283 & 289: Columns use `category.name` and `brand.name`.
   - Lines 326-340: Column `total_stock` calls `$record->total_stock` inside `->state()` and repeatedly inside `->color()` (evaluating `$record->total_stock <= 0` and `$record->total_stock <= 5`).
   - `ProductResource` does not override `getEloquentQuery()`.
2. **`app/Models/Product.php`**:
   - Lines 140-143:
     ```php
     public function getTotalStockAttribute(): float
     {
         return (float) $this->stocks()->sum('current_stock');
     }
     ```
     This method runs a fresh SQL query `select sum("current_stock") as "aggregate" from "product_stocks" where "product_stocks"."product_id" = ?` every time `$record->total_stock` is accessed.
3. **Livewire Execution & Query Log**:
   - Executing `Livewire::test(App\Filament\Resources\ProductResource\Pages\ListProducts::class)` with 10 records per page produced **58 database queries**.
   - Exactly **50 of those queries** were verbatim repetitions of:
     `select sum("current_stock") as "aggregate" from "product_stocks" where "product_stocks"."product_id" = ? and "product_stocks"."product_id" is not null` (queries [8] through [57]).
4. **Mutator Behavior when `withSum` is present**:
   - When calling `Product::withSum('stocks as total_stock', 'current_stock')->first()`, Eloquent populates `$attributes['total_stock'] = "15.00"`.
   - Accessing `$record->total_stock` STILL triggers the query because `getTotalStockAttribute()` does not accept `$value` or check `$this->attributes['total_stock']`.
   - Furthermore, when a product has 0 stock records in `product_stocks`, SQL `SUM()` returns `null`. A simple `$value ?? ...` coalesce evaluates to fallback query execution.
5. **`CategoryResource.php` and `BrandResource.php`**:
   - `CategoryResource.php` (lines 128-134) and `BrandResource.php` (lines 105-111) both define `Tables\Columns\TextColumn::make('products_count')->counts('products')`.
   - Filament's `InteractsWithTableQuery::applyRelationshipAggregates` automatically calls `$query->withCount(Arr::wrap($this->getRelationshipsToCount()))`.
   - Livewire test of `ListCategories` executes only 2 queries: 1 count aggregate and 1 select query with `(select count(*) from "products" ...)` subquery.
   - When `withCount('products')` was added to `getEloquentQuery()`, Eloquent generated duplicate subqueries in SQL: `select "categories".*, (select count(*) ...) as "products_count", (select count(*) ...) as "products_count"`.

---

## 2. Logic Chain

1. From Observation 1 and 2, accessing `$record->total_stock` triggers `$this->stocks()->sum('current_stock')` which queries the database for each product row.
2. From Observation 3, in a page of 10 products, because `total_stock` is referenced in `->state()` and multiple conditional statements in `->color()`, the database query is executed 5 times per product, yielding 50 identical queries.
3. From Observation 4, adding `withSum('stocks as total_stock', 'current_stock')` to `ProductResource::getEloquentQuery()` alone is insufficient because `Product::getTotalStockAttribute()` does not check `$this->attributes['total_stock']`.
4. Therefore, eliminating row-by-row queries requires a 2-part modification:
   - In `app/Models/Product.php`, modernize `getTotalStockAttribute($value = null)` to inspect `array_key_exists('total_stock', $this->attributes)` and return `(float) ($this->attributes['total_stock'] ?? 0)`. Also support `$this->relationLoaded('stocks')` using in-memory collection sum before falling back to the database query.
   - In `app/Filament/Resources/ProductResource.php`, override `getEloquentQuery()` to eager load `['category', 'brand']` and add `withSum('stocks as total_stock', 'current_stock')`.
5. Testing this combination in tinker proved that evaluating 10 full product table rows drops total queries from 58 down to exactly 3 queries (Product query with sum subquery + 1 category IN query + 1 brand IN query).
6. From Observation 5, neither `CategoryResource` nor `BrandResource` suffers from N+1 query execution because `TextColumn::make('products_count')->counts('products')` leverages Filament's native `InteractsWithTableQuery` aggregate. Overriding `getEloquentQuery()` with `withCount('products')` while retaining `->counts('products')` causes redundant duplicate subqueries in the SQL SELECT statement.

---

## 3. Caveats

- **Scope boundary**: This investigation was strictly read-only. No source files were modified.
- **Form components**: `ProductResource` form contains repeaters (`priceListItems`) and selects (`category_id`, `brand_id`), which are handled in edit/create contexts and do not affect table pagination performance.
- **Alternative Filament sum aggregation**: Filament's `TextColumn` supports `->sum('stocks', 'current_stock')`, which generates attribute `stocks_sum_current_stock`. Using `withSum('stocks as total_stock', 'current_stock')` in `getEloquentQuery()` was chosen because the existing table column is named `total_stock` and is tightly coupled with `$record->total_stock`.

---

## 4. Conclusion

1. **`app/Models/Product.php`**:
   Update `getTotalStockAttribute($value = null): float` to check:
   - `array_key_exists('total_stock', $this->attributes)` -> `(float) ($this->attributes['total_stock'] ?? 0)`
   - `$this->relationLoaded('stocks')` -> `(float) $this->stocks->sum('current_stock')`
   - Fallback -> `(float) $this->stocks()->sum('current_stock')`
2. **`app/Filament/Resources/ProductResource.php`**:
   - Add `use Illuminate\Database\Eloquent\Builder;`
   - Implement `getEloquentQuery(): Builder` with `parent::getEloquentQuery()->with(['category', 'brand'])->withSum('stocks as total_stock', 'current_stock');`
   - Enable `->sortable()` on `TextColumn::make('total_stock')`.
3. **`CategoryResource.php` and `BrandResource.php`**:
   - Keep `->counts('products')` on table columns.
   - Do NOT add redundant `withCount('products')` in `getEloquentQuery()` to avoid duplicate SQL subqueries.
4. Complete strategy details and automated Pest test blueprints are recorded in `/Users/angeldiaz/Documents/FerreterialasF/.agents/teamwork_preview_explorer_m1_1/strategy_report.md`.

---

## 5. Verification Method

1. **Verify query count in Pest Feature Test**:
   Run:
   ```bash
   "$HOME/Library/Application Support/Herd/bin/php" vendor/bin/pest tests/Feature/ProductCatalogTest.php
   ```
2. **Verify full test suite**:
   ```bash
   "$HOME/Library/Application Support/Herd/bin/php" artisan test --compact
   ```
3. **Independent Tinker Query Check**:
   ```bash
   "$HOME/Library/Application Support/Herd/bin/php" artisan tinker --execute '
   $user = \App\Models\User::first();
   \Illuminate\Support\Facades\Auth::login($user);
   \DB::enableQueryLog();
   \Livewire\Livewire::test(\App\Filament\Resources\ProductResource\Pages\ListProducts::class);
   dump("Total queries: " . count(\DB::getQueryLog()));
   '
   ```
   **Invalidation condition**: Any query log containing row-by-row `select sum("current_stock") ... where "product_id" = ?` queries. Total query count must be < 10 for a standard 10-item page.
