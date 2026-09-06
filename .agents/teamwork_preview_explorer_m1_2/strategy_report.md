# Strategy Report: Milestone M1 (Part 2) Master Data & Stock Integrity

**Author**: `teamwork_preview_explorer_m1_2`  
**Date**: 2026-09-06  
**Status**: COMPLETE / READY FOR IMPLEMENTATION  
**Target Scope**:
1. `StocksRelationManager`: Fix stock duplication bug ($2X$ stock on initial warehouse assignment).
2. `WarehouseResource`: Fix missing `unique(ignoreRecord: true)` on warehouse code and add referential delete guards.
3. `SupplierResource` & `PriceListResource`: Column audit, eager loading optimization, validation constraints, and business invariants.

---

## 1. Executive Summary

During the audit of the ERP/POS Master Data and Catalog modules, three critical areas were analyzed:
- **Stock Duplication Bug (P0 Severity)**: When assigning a product to a new warehouse via `StocksRelationManager`, entering an initial stock of $X$ resulted in the warehouse holding $2X$ physical stock. This corrupted both the physical inventory records and the Kardex balance sheet from the first moment of product distribution.
- **Warehouse Code 500 PDOException (P1 Severity)**: The database migration defines `$table->string('code')->unique()->nullable()`, but `WarehouseResource::form` omitted uniqueness validation, causing unhandled 500 server crashes upon duplicate entry.
- **Supplier & Price List Integrity & Eager Loading (P2 Severity)**: While basic CRUD operations functioned, both resources lacked explicit query optimization hooks (`getEloquentQuery` eager counting), delete guards for restricted foreign keys (e.g. suppliers with purchases), and business rule enforcement (e.g., uniqueness of price list names and single-default invariant for price lists).

This report outlines the verified root causes, evaluates architectural alternatives against `PROJECT.md` interface contracts, and presents exact drop-in implementation blueprints.

---

## 2. Issue 1: StocksRelationManager Stock Duplication Bug

### 2.1 Root Cause Analysis

**File**: `app/Filament/Resources/ProductResource/RelationManagers/StocksRelationManager.php`  
**Lines**: 57–64, 114–137

#### Observed Execution Flow
In `StocksRelationManager`, the `form()` method defines:
```php
Forms\Components\TextInput::make('current_stock')
    ->label('Stock Actual Disponible')
    ->numeric()
    ->default(0)
    ->disabled(fn (string $operation): bool => $operation === 'edit')
    ->dehydrated(fn (string $operation): bool => $operation !== 'edit')
    ->required()
```
And `table()` defines the `CreateAction`:
```php
Tables\Actions\CreateAction::make()
    ->label('Asignar a Otra Bodega')
    ...
    ->after(function (ProductStock $record) {
        // Si se asignó con stock inicial > 0, lo registramos en el Kardex
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

#### What Happens in the Database:
1. **Filament Default Create Execution**: Filament's `CreateAction` takes the submitted form values (`warehouse_id`, `current_stock = X`, `min_stock`, `max_stock`) and executes `$relationship->save($record)`. The new `ProductStock` record is persisted in the database with `current_stock = X` (e.g., 20.00).
2. **The `after()` Hook Runs**: Filament triggers `after(function (ProductStock $record) { ... })`. Because `$record->current_stock` is 20.00, it calls `KardexService::registerAdjustment(..., quantity: 20.00)`.
3. **KardexService Adds Again**: `KardexService::registerAdjustment` treats the incoming `quantity` parameter as an *adjustment delta* to add to the existing stock:
   ```php
   $previousStock = (float) $stockRecord->current_stock; // Reads 20.00!
   $resultingStock = $previousStock + $qty; // 20.00 + 20.00 = 40.00!
   $stockRecord->update(['current_stock' => $resultingStock]); // Updates to 40.00!
   ```
4. **Audit Trail Corruption**:
   - `ProductStock.current_stock` ends up at `40.00` ($2X$).
   - `inventory_movements` logs: `previous_stock = 20.00`, `quantity = 20.00`, `resulting_stock = 40.00`. The Kardex falsely implies the warehouse already held 20 units and an extra 20 units were received!

#### Empirical Verification
Running the sequence in app context via Tinker reproduced the exact anomaly:
```
After Filament create: current_stock = 20.00
After KardexService registerAdjustment: current_stock = 40.00
Movement: prev=20.00, qty=20.00, result=40.00
```

---

### 2.2 Interface Contract Compliance

In `PROJECT.md` (lines 48–51):
> **ProductStock ↔ KardexService**
> - **Assignment Contract**: When assigning stock to a warehouse via `StocksRelationManager`, the `ProductStock` record must not be pre-populated with stock if `KardexService::registerAdjustment` is invoked; or `KardexService` must record the movement without adding to an already-persisted stock amount. Stock must equal initial amount $X$, never $2X$.
> - **Locking Contract**: All mutations to `ProductStock::current_stock` inside `KardexService` must acquire `lockForUpdate()` on the stock record within a `DB::transaction`.

### 2.3 Evaluation of Solution Alternatives

| Solution Strategy | Mechanism | Pros | Cons | Verdict |
| :--- | :--- | :--- | :--- | :--- |
| **Strategy A: Action `using()` override** | In `CreateAction`, provide `->using(function (array $data, RelationManager $livewire) { ... })`. Inside a single `DB::transaction`, create `ProductStock` with `current_stock = 0.00`, call `KardexService::registerAdjustment` if initial stock > 0, and refresh the record. | 1. Atomic: wrapped in one `DB::transaction`.<br>2. Single source of truth: all inventory increments pass strictly through `KardexService`.<br>3. Accurate audit trail: Kardex movement logs `previous: 0.00`, `resulting: X`.<br>4. Resulting stock is exactly $X$. | None. Fully supported in Filament v3 `CanCustomizeProcess`. | **RECOMMENDED** |
| **Strategy B: `mutateFormDataUsing()` hook** | Mutate form data before create to force `current_stock = 0`, then use `after()` hook. | Keeps default create logic. | Overwrites `$data['current_stock']` in Filament action state, making user input unavailable to `after()` hook without caching to instance properties. Non-atomic. | Not recommended |
| **Strategy C: Bypass KardexService on create** | Leave Filament creating `current_stock = X`, but do not call `registerAdjustment()`, or manually insert an `InventoryMovement`. | Simple. | Violates the principle that Kardex is the central ledger; duplicates movement insertion logic; bypasses locking and central event dispatching. | Violates Architecture |

---

### 2.4 Recommended Implementation Blueprint

#### Target File: `app/Filament/Resources/ProductResource/RelationManagers/StocksRelationManager.php`

1. **Add Form Validation Bounds (F06)**:
   Add `->minValue(0)` to `current_stock`, `min_stock`, and `max_stock`.
2. **Replace `after()` with Atomic `using()` on `CreateAction`**:
   Replace lines 114–137 with:

```php
Tables\Actions\CreateAction::make()
    ->label('Asignar a Otra Bodega')
    ->modalHeading('Asignar Producto a Nueva Bodega')
    ->modalDescription('Crea la ficha de inventario para una bodega donde el producto aún no tiene presencia.')
    ->visible(function (): bool {
        $totalActive = Warehouse::where('is_active', true)->count();
        $alreadyAssigned = $this->getOwnerRecord()->stocks()->count();

        return $alreadyAssigned < $totalActive;
    })
    ->using(function (array $data, RelationManager $livewire): ProductStock {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($data, $livewire): ProductStock {
            $initialStock = (float) ($data['current_stock'] ?? 0);
            $product = $livewire->getOwnerRecord();

            // 1. Crear la ficha de stock con existencia inicial 0 para que KardexService
            // sea la única fuente de verdad responsable de aumentar el inventario.
            /** @var ProductStock $record */
            $record = $product->stocks()->create([
                'warehouse_id' => $data['warehouse_id'],
                'current_stock' => 0.00,
                'min_stock' => $data['min_stock'] ?? 5.00,
                'max_stock' => $data['max_stock'] ?? null,
            ]);

            // 2. Si el usuario ingresó stock inicial > 0, registrar atómicamente el movimiento
            // en KardexService, el cual sumará el inventario (0 + X = X) y generará el registro auditable.
            if ($initialStock > 0) {
                \App\Services\KardexService::registerAdjustment(
                    product: $product,
                    warehouseId: (int) $data['warehouse_id'],
                    quantity: $initialStock,
                    type: 'adjustment_in',
                    notes: 'Inventario inicial al asignar producto a la bodega ' . ($record->warehouse?->name ?? ''),
                    userId: auth()->id()
                );

                $record->refresh();
            }

            return $record;
        });
    }),
```

#### Why this guarantees zero duplication:
- `ProductStock` is initially created with `current_stock = 0.00`.
- `KardexService::registerAdjustment` reads `previous_stock = 0.00`.
- `resulting_stock = 0.00 + initialStock = initialStock` ($X$).
- Movement record in `inventory_movements` records: `previous_stock = 0.00`, `quantity = X`, `resulting_stock = X`.
- Total stock in warehouse equals exactly $X$, never $2X$.
- If `initialStock == 0`, no kardex adjustment is fired, and stock remains `0.00`.

---

## 3. Issue 2: WarehouseResource Uniqueness & Referential Integrity

### 3.1 Root Cause Analysis

**File**: `app/Filament/Resources/WarehouseResource.php`  
**Migration**: `database/migrations/2026_09_05_170831_create_warehouses_table.php` (Line 17: `$table->string('code')->unique()->nullable();`)

In `WarehouseResource::form()`:
```php
Forms\Components\TextInput::make('code')
    ->maxLength(255),
```
Because `unique(ignoreRecord: true)` is missing:
- If a user attempts to create a warehouse with an existing `code` (or update an existing warehouse to use another warehouse's code), Filament's client/server validation passes without warnings.
- SQLite/PostgreSQL/MySQL throws an `IntegrityConstraintViolationException` (`UNIQUE constraint failed: warehouses.code`), crashing with a 500 error page.

### 3.2 Additional Vulnerabilities Discovered in `WarehouseResource`

1. **Missing Referential Delete Protection**:
   The following migrations define strict foreign keys targeting `warehouses.id`:
   - `cash_registers`: `$table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();`
   - `purchases`: `$table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();`
   - `sales`: `$table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();`
   - `inventory_movements`: `$table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();`

   If an administrator tries to delete a warehouse that already has sales, purchases, cash registers, or inventory movements, the database throws an unhandled foreign key violation (500).
   `WarehouseResource::table` currently uses unconstrained `DeleteAction::make()` and `DeleteBulkAction::make()`.

2. **Form UX Enhancements**:
   Labels, default values, and placeholders are unlocalized or minimal.

### 3.3 Recommended Implementation Blueprint

#### Target File: `app/Filament/Resources/WarehouseResource.php`

1. **Form Schema Enhancement**:
```php
public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nombre de la Bodega / Sucursal')
                ->placeholder('Ej: Bodega Principal, Sucursal Norte')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('code')
                ->label('Código Interno')
                ->placeholder('Ej: BOD-01, SUC-NORTE')
                ->unique(ignoreRecord: true)
                ->maxLength(50)
                ->helperText('Identificador único para transferencias y reportes'),

            Forms\Components\TextInput::make('address')
                ->label('Dirección Física')
                ->placeholder('Ej: Calle 10 # 20-30')
                ->maxLength(255),

            Forms\Components\TextInput::make('phone')
                ->label('Teléfono de Contacto')
                ->tel()
                ->maxLength(30),

            Forms\Components\Toggle::make('is_active')
                ->label('Bodega Operativa / Activa')
                ->default(true)
                ->helperText('Desactivar para impedir nuevas compras o ventas en esta sede'),
        ]);
}
```

2. **Delete Safety Guards in Table Actions**:
```php
->actions([
    Tables\Actions\EditAction::make(),
    Tables\Actions\DeleteAction::make()
        ->before(function (Warehouse $record, Tables\Actions\DeleteAction $action) {
            if ($record->sales()->exists() || $record->purchases()->exists() || $record->inventoryMovements()->exists() || $record->cashRegisters()->exists()) {
                \Filament\Notifications\Notification::make()
                    ->title('No se puede eliminar la bodega')
                    ->body('La bodega tiene registros históricos (ventas, compras, movimientos o cajas). Desactívela en su lugar.')
                    ->danger()
                    ->send();

                $action->halt();
            }
        }),
])
->bulkActions([
    Tables\Actions\BulkActionGroup::make([
        Tables\Actions\DeleteBulkAction::make()
            ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                $deletedCount = 0;
                $skippedCount = 0;

                foreach ($records as $record) {
                    if ($record->sales()->exists() || $record->purchases()->exists() || $record->inventoryMovements()->exists() || $record->cashRegisters()->exists()) {
                        $skippedCount++;
                        continue;
                    }
                    $record->delete();
                    $deletedCount++;
                }

                if ($skippedCount > 0) {
                    \Filament\Notifications\Notification::make()
                        ->title('Eliminación parcial de bodegas')
                        ->body("Se eliminaron {$deletedCount} bodegas. Se omitieron {$skippedCount} bodegas con movimientos históricos.")
                        ->warning()
                        ->send();
                } elseif ($deletedCount > 0) {
                    \Filament\Notifications\Notification::make()
                        ->title('Bodegas eliminadas con éxito')
                        ->success()
                        ->send();
                }
            }),
    ]),
]);
```

---

## 4. Issue 3: SupplierResource & PriceListResource Audit

### 4.1 SupplierResource Audit & Optimization

**File**: `app/Filament/Resources/SupplierResource.php`  
**Model**: `app/Models/Supplier.php`  
**Migration**: `database/migrations/2026_09_05_170836_create_suppliers_and_purchases_tables.php`

#### A. Table Columns & Query Optimization
- **Current Columns**: `nit`, `name`, `contact_person`, `phone`, `purchases_count` (via `counts('purchases')`), `is_active`.
- **Eager Loading Evaluation**:
  - `counts('purchases')` in Filament v3 adds `$query->withCount('purchases')`.
  - Adding an explicit `getEloquentQuery(): Builder` with `withCount('purchases')` formalizes this eager-load pattern and avoids accidental N+1 if custom computed columns or subqueries are referenced.
- **Display Enhancement**:
  Add `email` and `address` as optional toggleable columns (`toggleable(isToggledHiddenByDefault: true)`), allowing users to view them in the table without opening the edit screen.

#### B. Validation Constraints & Referential Integrity
- `nit`: Currently has `required()`, `unique(ignoreRecord: true)`, `maxLength(50)`. This is correctly configured.
- `name`: `required()`, `maxLength(255)`.
- `email`: `email()`, `maxLength(255)`.
- **Delete Protection (Critical)**:
  `purchases` references `suppliers` with `restrictOnDelete`. Deleting a supplier with existing purchases will trigger a database crash (500).
  **Fix**: Add a `before()` hook to `DeleteAction` and a custom handler to `DeleteBulkAction` checking `$record->purchases()->exists()` before deletion, halting the action and alerting the user.

---

### 4.2 PriceListResource Audit & Optimization

**File**: `app/Filament/Resources/PriceListResource.php`  
**Model**: `app/Models/PriceList.php`  
**Migration**: `database/migrations/2026_09_05_170835_create_price_lists_and_items_tables.php`

#### A. Table Columns & Query Optimization
- **Current Columns**: `name`, `description`, `is_default`, `is_active`, `customers_count` (via `counts('customers')`), `updated_at`.
- **Missing Column**: `items_count` (number of custom products priced in this list).
  Adding `counts('items')` as `Productos con Precio Diferenciado` provides instant visibility of the list's depth.
- **Eager Loading Optimization**:
  Implement `getEloquentQuery()`:
  ```php
  public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
  {
      return parent::getEloquentQuery()
          ->withCount(['customers', 'items']);
  }
  ```

#### B. Validation Constraints & Invariants
1. **Name Uniqueness**:
   Currently missing `unique(ignoreRecord: true)` on `name`. In POS and Quote dropdowns, users select lists by name; duplicate names create ambiguity.
   **Fix**: Add `->unique(ignoreRecord: true)`.
2. **Single Default List Business Invariant**:
   The business logic requires that **only one price list may be marked `is_default = true`** at any given time (typically the "Detal" retail list).
   Currently, nothing prevents marking multiple lists as `is_default`, causing unpredictable price list resolution for new customers.
   **Fix**: When saving a price list with `is_default = true`, automatically unset `is_default` on all other price lists. This can be handled cleanly via `PriceList::saving` model event or in `CreatePriceList` / `EditPriceList` lifecycle hooks:
   ```php
   // In App\Models\PriceList::booted():
   static::saving(function (PriceList $priceList) {
       if ($priceList->is_default) {
           static::where('id', '!=', $priceList->id ?? 0)
               ->where('is_default', true)
               ->update(['is_default' => false]);
       }
   });
   ```
3. **Default Price List Delete Protection**:
   Deleting the system default price list leaves the ERP in an invalid state.
   **Fix**: Guard `DeleteAction` and `DeleteBulkAction` against deleting any price list where `$record->is_default === true`.

---

## 5. Automated Verification Plan (Pest Tests)

To ensure non-regression and prove all fixes work reliably, the following feature tests should be added to `tests/Feature/MasterDataIntegrityTest.php`:

### Test 1: StocksRelationManager Stock Assignment Integrity
```php
test('assigning product to warehouse creates stock X with exact kardex balance and does not duplicate', function () {
    $product = Product::factory()->create(['cost_price' => 5000, 'sale_price' => 8000]);
    $warehouse = Warehouse::factory()->create(['code' => 'BOD-NEW']);
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user);

    \Pest\Livewire\livewire(\App\Filament\Resources\ProductResource\RelationManagers\StocksRelationManager::class, [
        'ownerRecord' => $product,
        'pageClass' => \App\Filament\Resources\ProductResource\Pages\EditProduct::class,
    ])
    ->callTableAction('create', data: [
        'warehouse_id' => $warehouse->id,
        'current_stock' => 25.00,
        'min_stock' => 5.00,
        'max_stock' => 100.00,
    ])
    ->assertHasNoTableActionErrors();

    // Verify stock is EXACTLY 25.00, NOT 50.00
    $stock = ProductStock::where('product_id', $product->id)
        ->where('warehouse_id', $warehouse->id)
        ->firstOrFail();

    expect((float) $stock->current_stock)->toEqual(25.00);

    // Verify Kardex logged previous: 0, quantity: 25, resulting: 25
    $movement = InventoryMovement::where('product_id', $product->id)
        ->where('warehouse_id', $warehouse->id)
        ->latest('id')
        ->firstOrFail();

    expect((float) $movement->previous_stock)->toEqual(0.00)
        ->and((float) $movement->quantity)->toEqual(25.00)
        ->and((float) $movement->resulting_stock)->toEqual(25.00)
        ->and($movement->type)->toBe('adjustment_in');
});
```

### Test 2: Warehouse Code Uniqueness Validation
```php
test('warehouse form rejects duplicate warehouse code with validation error', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user);

    Warehouse::factory()->create(['code' => 'BOD-DUP']);

    \Pest\Livewire\livewire(\App\Filament\Resources\WarehouseResource\Pages\CreateWarehouse::class)
        ->fillForm([
            'name' => 'Nueva Bodega Duplicada',
            'code' => 'BOD-DUP',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['code' => 'unique']);
});
```

### Test 3: Warehouse Delete Protection with Associated Transactions
```php
test('cannot delete warehouse with associated sales, purchases, or movements', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user);

    $warehouse = Warehouse::factory()->create();
    $customer = Customer::factory()->create();

    // Create a sale in the warehouse
    Sale::factory()->create([
        'warehouse_id' => $warehouse->id,
        'user_id' => $user->id,
        'customer_id' => $customer->id,
    ]);

    \Pest\Livewire\livewire(\App\Filament\Resources\WarehouseResource\Pages\ListWarehouses::class)
        ->callTableAction('delete', $warehouse)
        ->assertNotified('No se puede eliminar la bodega');

    expect(Warehouse::find($warehouse->id))->not->toBeNull();
});
```

### Test 4: Single Default Price List Invariant
```php
test('marking a price list as default unsets default flag on previous default list', function () {
    $defaultList = PriceList::factory()->create(['name' => 'Detal', 'is_default' => true]);
    $newList = PriceList::factory()->create(['name' => 'Mayorista', 'is_default' => false]);

    $newList->update(['is_default' => true]);

    expect($newList->fresh()->is_default)->toBeTrue()
        ->and($defaultList->fresh()->is_default)->toBeFalse();
});
```

---

## 6. Implementation Checklist for Subsequent Turn

- [ ] Modify `app/Filament/Resources/ProductResource/RelationManagers/StocksRelationManager.php`:
  - [ ] Add `minValue(0)` to `current_stock`, `min_stock`, `max_stock`.
  - [ ] Replace `after()` hook in `CreateAction` with atomic `using()` callback.
- [ ] Modify `app/Filament/Resources/WarehouseResource.php`:
  - [ ] Add `unique(ignoreRecord: true)` to `code`.
  - [ ] Add delete protection hooks in `DeleteAction` and `DeleteBulkAction`.
- [ ] Modify `app/Filament/Resources/SupplierResource.php`:
  - [ ] Add `getEloquentQuery()` eager counting purchases.
  - [ ] Add delete protection hook for suppliers with existing purchases.
- [ ] Modify `app/Filament/Resources/PriceListResource.php`:
  - [ ] Add `unique(ignoreRecord: true)` to `name`.
  - [ ] Add `items_count` table column and `getEloquentQuery()` with eager count.
  - [ ] Add delete guard preventing deletion of `is_default` price list.
- [ ] Modify `app/Models/PriceList.php`:
  - [ ] Add `booted()` model event ensuring single-default invariant.
- [ ] Run test suite: `php artisan test --compact`.
