# Estrategia de Implementación: M1 - Parte 3 (CustomerResource, UserResource y Límites de Formularios)

**Autor:** `teamwork_preview_explorer_m1_3`  
**Fecha:** 2026-09-06  
**Objetivo:** Diseñar la estrategia quirúrgica y exacta de implementación para:
1. `CustomerResource`: Eliminación de consultas N+1 en tabla y endurecimiento de autorización (canCreate, canEdit, canDelete).
2. `UserResource`: Blindaje de seguridad en `DeleteBulkAction` contra auto-eliminación de la cuenta en sesión activa y optimización N+1.
3. Límites numéricos y validaciones en formularios en todos los recursos de M1 (`minValue(0)`, `maxValue(100)`).

---

## 1. Resumen Ejecutivo de Hallazgos

| Componente | Vulnerabilidad / Deficiencia Observada | Impacto | Estrategia de Solución Diseñada |
|---|---|---|---|
| **CustomerResource** (Consultas N+1) | Columnas `priceList.name` y `has_consented` disparan consultas fila por fila. Para 10 clientes se disparan 20 consultas adicionales. `Customer::getHasConsentedAttribute()` ejecuta `consentLogs()->exists()`. | Degradación de latencia O(N) en listados de clientes. | 1. Implementar `CustomerResource::getEloquentQuery()` con `with(['priceList'])` y `withExists('consentLogs as has_consented')`.<br>2. Ajustar `Customer::getHasConsentedAttribute()` para consultar primero `$this->attributes['has_consented']`. |
| **CustomerResource** (Autorización) | Carece de `canCreate()`, `canEdit()`, `canDelete()` y `canDeleteAny()`. Solo define `canViewAny()`. Cajeros (`cashier`) con permiso `customers.view` pueden crear, editar y borrar clientes, incluso vía `DeleteBulkAction`. | Brecha de seguridad que permite a cajeros mutar y eliminar clientes maestros y cupos de crédito. | 1. Implementar `canCreate()`, `canEdit()`, `canDelete()`, `canDeleteAny()` exigiendo rol `admin`.<br>2. Condicionar `canDelete` para prohibir la eliminación de clientes con deuda activa (`current_debt > 0`).<br>3. Restringir `DeleteBulkAction` a administradores y filtrar registros con deuda. |
| **UserResource** (Auto-eliminación en lote) | `DeleteAction` individual previene auto-borrado, pero `DeleteBulkAction` en `UserResource.php:280-283` carece de guardas. Un admin que seleccione todos los registros se elimina a sí mismo. | Pérdida irreversible de la sesión activa y potencial bloqueo total del sistema por falta de administradores. | 1. Aplicar `$table->checkIfRecordIsSelectableUsing(fn (User $u) => $u->id !== auth()->id())` para deshabilitar checkbox de selección.<br>2. Personalizar `DeleteBulkAction::action()` para excluir explícitamente `auth()->id()`.<br>3. Agregar `getEloquentQuery()` con `with(['roles'])` para eliminar N+1 en columna `roles.name`. |
| **Límites de Formularios (M1)** | `credit_limit` (Customer), `cost_price`, `sale_price`, `tax_rate`, `priceListItems.price` (Product), y `current_stock`, `min_stock`, `max_stock` (StocksRelationManager) carecen de `minValue(0)` y rangos de IVA `[0, 100]`. | Permite precios, costos, cupos o existencias negativas y porcentajes de IVA absurdos (ej. -19% o 500%), alterando cálculos financieros y Kardex. | Agregar `->minValue(0)` en todos los campos monetarios y de stock; añadir `->minValue(0)->maxValue(100)->step(0.01)` en tasas de IVA. |

---

## 2. CustomerResource: Análisis Detallado y Diseño de Corrección

### 2.1. Eliminación de Consultas N+1

#### Diagnóstico Técnico
En `app/Filament/Resources/CustomerResource.php`:
- Línea 237: `Tables\Columns\TextColumn::make('priceList.name')`
- Línea 263: `Tables\Columns\IconColumn::make('has_consented')`

En `app/Models/Customer.php:94-97`:
```php
public function getHasConsentedAttribute(): bool
{
    return $this->consentLogs()->exists();
}
```

Al renderizar la tabla de clientes sin `getEloquentQuery()`, Filament ejecuta:
1. `SELECT * FROM customers LIMIT 25 OFFSET 0`
2. Para cada cliente: `SELECT * FROM price_lists WHERE id = ? LIMIT 1` (si tiene lista asignada).
3. Para cada cliente: `SELECT EXISTS(SELECT * FROM consent_logs WHERE subject_id = ? AND subject_type = 'customer') AS exists`.

**Evidencia Dinámica (Tinker):**
Al invocar `Customer::withExists('consentLogs as has_consented')->first()`, si el accesor `getHasConsentedAttribute()` no verifica `$this->attributes['has_consented']`, Laravel ignora el campo subconsultado en el `SELECT` y vuelve a disparar la consulta SQL `SELECT EXISTS...`.

#### Estrategia de Solución
Se deben aplicar dos cambios coordinados:

1. **En `app/Filament/Resources/CustomerResource.php`:**
Definir el método `getEloquentQuery()` para precargar la relación y agregar la subconsulta booleana:
```php
use Illuminate\Database\Eloquent\Builder;

public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->with(['priceList'])
        ->withExists('consentLogs as has_consented');
}
```

2. **En `app/Models/Customer.php`:**
Ajustar el accesor para aprovechar el valor precargado cuando esté disponible:
```php
public function getHasConsentedAttribute(): bool
{
    if (array_key_exists('has_consented', $this->attributes)) {
        return (bool) $this->attributes['has_consented'];
    }

    if (array_key_exists('consent_logs_exists', $this->attributes)) {
        return (bool) $this->attributes['consent_logs_exists'];
    }

    return $this->consentLogs()->exists();
}
```

**Resultado:** Se reduce de `1 + 2N` consultas a exactamente `2` consultas fijas (1 para clientes con subconsulta `exists`, 1 para las listas de precios relacionadas via `with`), eliminando por completo el problema N+1.

---

### 2.2. Endurecimiento de Autorización y Seguridad

#### Diagnóstico Técnico
`CustomerResource.php:46-49` define únicamente:
```php
public static function canViewAny(): bool
{
    return auth()->user()?->can('customers.view') ?? false;
}
```
En `database/seeders/PermissionSeeder.php`, el rol `cashier` tiene asignado:
```php
$cashierRole->syncPermissions([
    'pos.access',
    'sales.view',
    'customers.view',
    'quotes.view',
    'products.view',
]);
```
Como no existe `CustomerPolicy` ni los métodos `canCreate()`, `canEdit()`, `canDelete()` en el recurso, Filament asume que cualquier usuario autenticado que tenga permiso `canViewAny()` tiene autorización para mutar o eliminar registros.
Por lo tanto, un cajero que navegue a `/admin/customers` puede:
- Modificar nombres, teléfonos, cupos o condiciones de clientes existentes.
- Borrar clientes en lote mediante `DeleteBulkAction`.
- Borrar clientes individuales con saldos pendientes de cobro (`current_debt > 0`).

*Nota de Flujo POS:* Los cajeros NO necesitan crear clientes mediante el recurso Filament, ya que en el terminal POS (`app/Filament/Pages/PosTerminal.php:861-915`) disponen del modal rápido `saveQuickCustomer()`, el cual interactúa directamente con `Customer::create()` para registrar clientes al momento de despachar.

#### Estrategia de Solución
En `app/Filament/Resources/CustomerResource.php`:

1. **Incorporar métodos estáticos de autorización por rol:**
```php
use Illuminate\Database\Eloquent\Model;

public static function canCreate(): bool
{
    return auth()->user()?->hasRole('admin') ?? false;
}

public static function canEdit(Model $record): bool
{
    return auth()->user()?->hasRole('admin') ?? false;
}

public static function canDelete(Model $record): bool
{
    if (! (auth()->user()?->hasRole('admin') ?? false)) {
        return false;
    }

    // Regla de negocio: No permitir eliminar clientes con deudas activas pendientes
    return (float) $record->current_debt <= 0;
}

public static function canDeleteAny(): bool
{
    return auth()->user()?->hasRole('admin') ?? false;
}
```

2. **Reforzar `DeleteBulkAction` en `CustomerResource::table()`:**
```php
Tables\Actions\DeleteBulkAction::make()
    ->visible(fn (): bool => auth()->user()?->hasRole('admin') ?? false)
    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
        $deletable = $records->filter(fn (Customer $customer) => (float) $customer->current_debt <= 0);
        $withDebt = $records->filter(fn (Customer $customer) => (float) $customer->current_debt > 0);

        $deletable->each->delete();

        if ($withDebt->isNotEmpty()) {
            \Filament\Notifications\Notification::make()
                ->warning()
                ->title('Eliminación parcial de clientes')
                ->body("Se eliminaron {$deletable->count()} cliente(s). Se protegieron {$withDebt->count()} cliente(s) con saldo de deuda pendiente.")
                ->send();
        } else {
            \Filament\Notifications\Notification::make()
                ->success()
                ->title('Clientes eliminados')
                ->body("{$deletable->count()} cliente(s) eliminados correctamente.")
                ->send();
        }
    }),
```

---

## 3. UserResource: Blindaje de Auto-eliminación y Optimización N+1

### 3.1. Diagnóstico de Falla en `DeleteBulkAction`
En `app/Filament/Resources/UserResource.php:266-277` y en `app/Filament/Resources/UserResource/Pages/EditUser.php:58-69`, la acción individual `DeleteAction` cuenta con:
```php
if ($record->id === auth()->id()) {
    Notification::make()->danger()->title('Operación no permitida')->body('No puedes eliminar tu propio usuario en sesión activa.')->send();
    $action->cancel();
}
```
Sin embargo, en la tabla de usuarios (`UserResource.php:280-283`):
```php
->bulkActions([
    Tables\Actions\BulkActionGroup::make([
        Tables\Actions\DeleteBulkAction::make(),
    ]),
]);
```
No existe ninguna salvaguarda contra la eliminación masiva. Si un administrador selecciona todas las casillas de la tabla y ejecuta la acción masiva, el sistema eliminará al propio administrador logueado, destruyendo el acceso administrativo.

### 3.2. Diagnóstico de Consulta N+1
En `UserResource.php:217`:
`Tables\Columns\TextColumn::make('roles.name')`
`UserResource` no define `getEloquentQuery()`, lo que causa que al listar usuarios se cargue la relación `roles` de manera individual por fila.

### 3.3. Estrategia de Solución (Defensa en Profundidad)

1. **Impedir Selección en UI:**
En `UserResource::table()`, encadenar `$table->checkIfRecordIsSelectableUsing(...)`:
```php
->checkIfRecordIsSelectableUsing(
    fn (User $record): bool => $record->id !== auth()->id(),
)
```
Esto deshabilita la casilla de selección en la fila del usuario actualmente autenticado.

2. **Interceptar y Excluir en la Ejecución de `DeleteBulkAction`:**
```php
Tables\Actions\DeleteBulkAction::make()
    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
        $currentUserId = auth()->id();
        $toDelete = $records->reject(fn (User $user) => $user->id === $currentUserId);

        $toDelete->each->delete();

        if ($records->contains('id', $currentUserId)) {
            \Filament\Notifications\Notification::make()
                ->warning()
                ->title('Aviso de seguridad')
                ->body('Tu propio usuario fue omitido del borrado masivo para proteger tu sesión activa.')
                ->send();
        } else {
            \Filament\Notifications\Notification::make()
                ->success()
                ->title('Usuarios eliminados')
                ->body("{$toDelete->count()} usuario(s) eliminados correctamente.")
                ->send();
        }
    }),
```

3. **Eager Loading de Roles en `getEloquentQuery()`:**
```php
use Illuminate\Database\Eloquent\Builder;

public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()->with(['roles']);
}
```

---

## 4. Auditoría Integral de Límites Numéricos y Validaciones (M1)

### 4.1. Inventario M1 Exhaustivo y Correcciones Requeridas

#### A. `CustomerResource` (`app/Filament/Resources/CustomerResource.php`)
- **Campo:** `credit_limit` (Línea 128)
- **Estado actual:** `->numeric()->prefix('$')->default(0)` (Sin límite inferior)
- **Corrección:** Añadir `->minValue(0)`

```php
// BEFORE:
Forms\Components\TextInput::make('credit_limit')
    ->label('Cupo de Crédito Autorizado')
    ->numeric()
    ->prefix('$')
    ->default(0)
    ->disabled(fn (): bool => ! (auth()->user()?->hasRole('admin') ?? false))

// AFTER:
Forms\Components\TextInput::make('credit_limit')
    ->label('Cupo de Crédito Autorizado')
    ->numeric()
    ->minValue(0)
    ->prefix('$')
    ->default(0)
    ->disabled(fn (): bool => ! (auth()->user()?->hasRole('admin') ?? false))
```

#### B. `ProductResource` (`app/Filament/Resources/ProductResource.php`)
- **Campo:** `cost_price` (Línea 168)
  - **Estado actual:** `->numeric()->prefix('$')->required()->default(0)`
  - **Corrección:** Añadir `->minValue(0)`
- **Campo:** `sale_price` (Línea 177)
  - **Estado actual:** `->numeric()->prefix('$')->required()->default(0)`
  - **Corrección:** Añadir `->minValue(0)`
- **Campo:** `tax_rate` (Línea 185)
  - **Estado actual:** `->numeric()->suffix('%')->default(19)->required()`
  - **Corrección:** Añadir `->minValue(0)->maxValue(100)->step(0.01)`
- **Campo:** `priceListItems` Repeater `price` (Línea 244)
  - **Estado actual:** `->numeric()->prefix('$')->required()`
  - **Corrección:** Añadir `->minValue(0)`

```php
// AFTER (ProductResource):
Forms\Components\TextInput::make('cost_price')
    ->label('Precio de Costo (Compra)')
    ->numeric()
    ->minValue(0)
    ->prefix('$')
    ->required()
    ->default(0)
    ->live(onBlur: true)
    ->visible(fn (): bool => auth()->user()?->can('products.view_cost') ?? false),

Forms\Components\TextInput::make('sale_price')
    ->label('Precio de Venta (Detal / General)')
    ->numeric()
    ->minValue(0)
    ->prefix('$')
    ->required()
    ->default(0)
    ->live(onBlur: true),

Forms\Components\TextInput::make('tax_rate')
    ->label('IVA (%)')
    ->numeric()
    ->minValue(0)
    ->maxValue(100)
    ->step(0.01)
    ->suffix('%')
    ->default(19)
    ->required(),

// En el Repeater priceListItems:
Forms\Components\TextInput::make('price')
    ->label('Precio para esta Lista')
    ->numeric()
    ->minValue(0)
    ->prefix('$')
    ->required(),
```

#### C. `StocksRelationManager` (`app/Filament/Resources/ProductResource/RelationManagers/StocksRelationManager.php`)
- **Campo:** `current_stock` (Línea 57)
  - **Estado actual:** `->numeric()->default(0)`
  - **Corrección:** Añadir `->minValue(0)`
- **Campo:** `min_stock` (Línea 66)
  - **Estado actual:** `->numeric()->default(5)`
  - **Corrección:** Añadir `->minValue(0)`
- **Campo:** `max_stock` (Línea 72)
  - **Estado actual:** `->numeric()->nullable()`
  - **Corrección:** Añadir `->minValue(0)`
- **Modal `adjust_stock` -> `quantity`** (Línea 155):
  - Ya cuenta con `->minValue(0.01)` (Validado como correcto).

```php
// AFTER (StocksRelationManager):
Forms\Components\TextInput::make('current_stock')
    ->label('Stock Actual Disponible')
    ->numeric()
    ->minValue(0)
    ->default(0)
    ->disabled(fn (string $operation): bool => $operation === 'edit')
    ->dehydrated(fn (string $operation): bool => $operation !== 'edit')
    ->required(),

Forms\Components\TextInput::make('min_stock')
    ->label('Stock Mínimo de Alerta')
    ->numeric()
    ->minValue(0)
    ->default(5),

Forms\Components\TextInput::make('max_stock')
    ->label('Capacidad Máxima')
    ->numeric()
    ->minValue(0)
    ->nullable(),
```

#### D. Verificación de Otros Recursos de M1
- `WarehouseResource`: No posee campos numéricos (campos: `name`, `code`, `address`, `phone`, `is_active`).
- `PriceListResource`: No posee campos numéricos en su formulario principal (los precios por lista se configuran en el repeater de `ProductResource`).
- `SupplierResource`: No posee campos numéricos (campos: `name`, `nit`, `contact_person`, `phone`, `email`, `address`, `is_active`).
- `CategoryResource`: No posee campos numéricos (`name`, `slug`, `description`, `is_active`).
- `BrandResource`: No posee campos numéricos (`name`, `slug`, `description`, `is_active`).
- `UserResource`: No posee campos numéricos (`name`, `email`, `password`, `roles`, `is_active`, permisos).
- `SettingsPage`: Ya cuenta con `minValue(0)`, `maxValue(100)`, `step(0.01)` en `iva_percentage` (Líneas 87-89).

---

## 5. Blueprint Exacto de Implementación (Snippets Antes / Después)

### Archivo 1: `app/Filament/Resources/CustomerResource.php`

#### Modificación 1A: Importaciones y Métodos de Autorización y Query
**Línea objetivo:** después de la línea 45.

```php
<<<<
    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('customers.view') ?? false;
    }
====
    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('customers.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        if (! (auth()->user()?->hasRole('admin') ?? false)) {
            return false;
        }

        return (float) $record->current_debt <= 0;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with(['priceList'])
            ->withExists('consentLogs as has_consented');
    }
>>>>
```

#### Modificación 1B: Límite Numérico en `credit_limit`
**Línea objetivo:** 128-135.

```php
<<<<
                        Forms\Components\TextInput::make('credit_limit')
                            ->label('Cupo de Crédito Autorizado')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->disabled(fn (): bool => ! (auth()->user()?->hasRole('admin') ?? false))
                            ->helperText('Monto total máximo que la ferretería le permite fiar (Solo editable por el Administrador)'),
====
                        Forms\Components\TextInput::make('credit_limit')
                            ->label('Cupo de Crédito Autorizado')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('$')
                            ->default(0)
                            ->disabled(fn (): bool => ! (auth()->user()?->hasRole('admin') ?? false))
                            ->helperText('Monto total máximo que la ferretería le permite fiar (Solo editable por el Administrador)'),
>>>>
```

#### Modificación 1C: Protección en `DeleteBulkAction`
**Línea objetivo:** 290-294.

```php
<<<<
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
====
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->hasRole('admin') ?? false)
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $deletable = $records->filter(fn (Customer $customer) => (float) $customer->current_debt <= 0);
                            $withDebt = $records->filter(fn (Customer $customer) => (float) $customer->current_debt > 0);

                            $deletable->each->delete();

                            if ($withDebt->isNotEmpty()) {
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('Eliminación parcial de clientes')
                                    ->body("Se eliminaron {$deletable->count()} cliente(s). Se protegieron {$withDebt->count()} cliente(s) con saldo de deuda pendiente.")
                                    ->send();
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->success()
                                    ->title('Clientes eliminados')
                                    ->body("{$deletable->count()} cliente(s) eliminados correctamente.")
                                    ->send();
                            }
                        }),
                ]),
            ]);
>>>>
```

---

### Archivo 2: `app/Models/Customer.php`

#### Modificación 2A: Soporte para Atributo Precargado `has_consented`
**Línea objetivo:** 94-97.

```php
<<<<
    public function getHasConsentedAttribute(): bool
    {
        return $this->consentLogs()->exists();
    }
====
    public function getHasConsentedAttribute(): bool
    {
        if (array_key_exists('has_consented', $this->attributes)) {
            return (bool) $this->attributes['has_consented'];
        }

        if (array_key_exists('consent_logs_exists', $this->attributes)) {
            return (bool) $this->attributes['consent_logs_exists'];
        }

        return $this->consentLogs()->exists();
    }
>>>>
```

---

### Archivo 3: `app/Filament/Resources/UserResource.php`

#### Modificación 3A: Eager Loading de Roles en `getEloquentQuery`
**Línea objetivo:** después de `canDelete` (Línea 50).

```php
<<<<
    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('users.manage') ?? false;
    }
====
    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('users.manage') ?? false;
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['roles']);
    }
>>>>
```

#### Modificación 3B: Blindaje de Auto-eliminación en Tabla y `DeleteBulkAction`
**Línea objetivo:** 279-284.

```php
<<<<
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
====
            ->checkIfRecordIsSelectableUsing(
                fn (User $record): bool => $record->id !== auth()->id(),
            )
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $currentUserId = auth()->id();
                            $toDelete = $records->reject(fn (User $user) => $user->id === $currentUserId);

                            $toDelete->each->delete();

                            if ($records->contains('id', $currentUserId)) {
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('Aviso de seguridad')
                                    ->body('Tu propio usuario fue omitido del borrado masivo para proteger tu sesión activa.')
                                    ->send();
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->success()
                                    ->title('Usuarios eliminados')
                                    ->body("{$toDelete->count()} usuario(s) eliminados correctamente.")
                                    ->send();
                            }
                        }),
                ]),
            ]);
>>>>
```

---

### Archivo 4: `app/Filament/Resources/ProductResource.php`

#### Modificación 4A: Límites en Precios y Rango de IVA
**Líneas objetivo:** 168-191.

```php
<<<<
                                        Forms\Components\TextInput::make('cost_price')
                                            ->label('Precio de Costo (Compra)')
                                            ->numeric()
                                            ->prefix('$')
                                            ->required()
                                            ->default(0)
                                            ->live(onBlur: true)
                                            ->visible(fn (): bool => auth()->user()?->can('products.view_cost') ?? false),

                                        Forms\Components\TextInput::make('sale_price')
                                            ->label('Precio de Venta (Detal / General)')
                                            ->numeric()
                                            ->prefix('$')
                                            ->required()
                                            ->default(0)
                                            ->live(onBlur: true),

                                        Forms\Components\TextInput::make('tax_rate')
                                            ->label('IVA (%)')
                                            ->numeric()
                                            ->suffix('%')
                                            ->default(19)
                                            ->required(),
====
                                        Forms\Components\TextInput::make('cost_price')
                                            ->label('Precio de Costo (Compra)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->prefix('$')
                                            ->required()
                                            ->default(0)
                                            ->live(onBlur: true)
                                            ->visible(fn (): bool => auth()->user()?->can('products.view_cost') ?? false),

                                        Forms\Components\TextInput::make('sale_price')
                                            ->label('Precio de Venta (Detal / General)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->prefix('$')
                                            ->required()
                                            ->default(0)
                                            ->live(onBlur: true),

                                        Forms\Components\TextInput::make('tax_rate')
                                            ->label('IVA (%)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->step(0.01)
                                            ->suffix('%')
                                            ->default(19)
                                            ->required(),
>>>>
```

#### Modificación 4B: Límite en Precios Diferenciados (Repeater `priceListItems`)
**Línea objetivo:** 244-249.

```php
<<<<
                                        Forms\Components\TextInput::make('price')
                                            ->label('Precio para esta Lista')
                                            ->numeric()
                                            ->prefix('$')
                                            ->required(),
====
                                        Forms\Components\TextInput::make('price')
                                            ->label('Precio para esta Lista')
                                            ->numeric()
                                            ->minValue(0)
                                            ->prefix('$')
                                            ->required(),
>>>>
```

---

### Archivo 5: `app/Filament/Resources/ProductResource/RelationManagers/StocksRelationManager.php`

#### Modificación 5A: Límites en Campos de Existencia y Umbrales
**Líneas objetivo:** 57-76.

```php
<<<<
                Forms\Components\TextInput::make('current_stock')
                    ->label('Stock Actual Disponible')
                    ->numeric()
                    ->default(0)
                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                    ->dehydrated(fn (string $operation): bool => $operation !== 'edit')
                    ->required()
                    ->helperText('Unidades físicas disponibles en esta bodega. En registros existentes, use el botón "Ajustar Stock" en la tabla para auditar en Kardex.'),

                Forms\Components\TextInput::make('min_stock')
                    ->label('Stock Mínimo de Alerta')
                    ->numeric()
                    ->default(5)
                    ->helperText('Dispara alertas de reposición si baja de esta cantidad'),

                Forms\Components\TextInput::make('max_stock')
                    ->label('Capacidad Máxima')
                    ->numeric()
                    ->nullable()
                    ->helperText('Capacidad física máxima sugerida en estantería'),
====
                Forms\Components\TextInput::make('current_stock')
                    ->label('Stock Actual Disponible')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                    ->dehydrated(fn (string $operation): bool => $operation !== 'edit')
                    ->required()
                    ->helperText('Unidades físicas disponibles en esta bodega. En registros existentes, use el botón "Ajustar Stock" en la tabla para auditar en Kardex.'),

                Forms\Components\TextInput::make('min_stock')
                    ->label('Stock Mínimo de Alerta')
                    ->numeric()
                    ->minValue(0)
                    ->default(5)
                    ->helperText('Dispara alertas de reposición si baja de esta cantidad'),

                Forms\Components\TextInput::make('max_stock')
                    ->label('Capacidad Máxima')
                    ->numeric()
                    ->minValue(0)
                    ->nullable()
                    ->helperText('Capacidad física máxima sugerida en estantería'),
>>>>
```

---

## 6. Método de Verificación y Pruebas Independientes

### 6.1. Suite de Pruebas Automatizadas
Para verificar de forma determinista la solución planteada, se ejecutarán los siguientes comandos:

1. **Pruebas E2E Tier 1 Master Data:**
```bash
"$HOME/Library/Application Support/Herd/bin/php" vendor/bin/pest tests/Feature/E2E/Tier1/MasterDataFeatureTest.php
```
*Cobertura:* F01 (consultas eager loading sin N+1), F04 (autorizaciones de cliente), F05 (eliminación segura de usuario), F06 (límites numéricos de precios, stock e IVA).

2. **Pruebas de Autorizaciones y Permisos:**
```bash
"$HOME/Library/Application Support/Herd/bin/php" vendor/bin/pest tests/Feature/UserPermissionAndSettingsTest.php tests/Feature/QuoteAndCustomerTest.php
```

3. **Verificación de Reducción N+1 en Tinker:**
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
*Criterio de Aceptación:* El total de consultas para 10 clientes debe ser exactamente 2 (o `<= 3`), nunca `1 + 2N`.

4. **Verificación de Estilo con Laravel Pint:**
```bash
"$HOME/Library/Application Support/Herd/bin/php" vendor/bin/pint --dirty --format agent
```
