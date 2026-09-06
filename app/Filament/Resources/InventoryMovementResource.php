<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryMovementResource\Pages;
use App\Models\InventoryMovement;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * ============================================================================
 * RECURSO FILAMENT: InventoryMovementResource (Visor del Kardex de Inventario)
 * ============================================================================
 * Provee una auditoría inmutable e histórica de cada unidad que entra o sale
 * del inventario de la ferretería.
 *
 * REGLA DE INTEGRIDAD: Los movimientos del Kardex son de SOLO LECTURA.
 * No se pueden crear, editar ni eliminar manualmente desde la interfaz;
 * solo se generan mediante transacciones (compras, ventas, devoluciones o ajustes).
 *
 * SEGURIDAD: Solo visible para el rol 'admin' (Dueño).
 */
class InventoryMovementResource extends Resource
{
    protected static ?string $model = InventoryMovement::class;

    protected static ?string $modelLabel = 'Movimiento de Kardex';

    protected static ?string $pluralModelLabel = 'Kardex de Inventario';

    protected static ?string $navigationGroup = 'Catálogo e Inventario';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('inventory.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return false; // El Kardex no se puede inventar a mano
    }

    public static function form(Form $form): Form
    {
        return $form;
    }

    public static function table(Table $table): Table
    {
        return $table
            // Agrupamos por producto para que el Kardex no sea una lista desordenada
            ->defaultGroup('product.name')
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha y Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto')
                    ->description(fn (InventoryMovement $record): string => "SKU: {$record->product->sku}")
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->wrap(),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Bodega')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo Operación')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'purchase' => 'success',
                        'sale' => 'info',
                        'sale_return' => 'purple',
                        'adjustment_in' => 'warning',
                        'adjustment_out' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'purchase' => 'Entrada por Compra',
                        'sale' => 'Salida por Venta',
                        'sale_return' => 'Devolución de Venta',
                        'adjustment_in' => 'Ajuste Entrada (+)',
                        'adjustment_out' => 'Ajuste Salida (-)',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->state(fn (InventoryMovement $record): string => match ($record->type) {
                        'purchase', 'sale_return', 'adjustment_in' => "+{$record->quantity} {$record->product->unit}",
                        'sale', 'adjustment_out' => "-{$record->quantity} {$record->product->unit}",
                        default => "{$record->quantity} {$record->product->unit}",
                    })
                    ->badge()
                    ->color(fn (InventoryMovement $record): string => in_array($record->type, ['purchase', 'sale_return', 'adjustment_in']) ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Costo de Operación')
                    ->money('COP', locale: 'es_CO')
                    ->visible(fn (): bool => auth()->user()?->can('products.view_cost') ?? false),

                Tables\Columns\TextColumn::make('product.cost_price')
                    ->label('Costo Promedio (CPP)')
                    ->money('COP', locale: 'es_CO')
                    ->weight('bold')
                    ->visible(fn (): bool => auth()->user()?->can('products.view_cost') ?? false),

                Tables\Columns\TextColumn::make('product.sale_price')
                    ->label('Precio Venta')
                    ->money('COP', locale: 'es_CO')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('margin')
                    ->label('Margen Ganancia')
                    ->state(fn (InventoryMovement $record): string => number_format($record->product->profit_margin, 1).'%')
                    ->badge()
                    ->color(fn (InventoryMovement $record): string => match (true) {
                        $record->product->profit_margin >= 30 => 'success',
                        $record->product->profit_margin >= 15 => 'warning',
                        default => 'danger',
                    })
                    ->visible(fn (): bool => auth()->user()?->can('products.view_cost') ?? false),

                Tables\Columns\TextColumn::make('previous_stock')
                    ->label('Stock Previo')
                    ->numeric(decimalPlaces: 2)
                    ->color('gray'),

                Tables\Columns\TextColumn::make('resulting_stock')
                    ->label('Stock Resultante')
                    ->numeric(decimalPlaces: 2)
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
                    ->placeholder('Sistema')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Referencia / Factura')
                    ->searchable()
                    ->wrap(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('product_id')
                    ->label('Producto')
                    ->relationship('product', 'name')
                    ->searchable(),

                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Bodega')
                    ->relationship('warehouse', 'name'),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo de Movimiento')
                    ->options([
                        'purchase' => 'Entradas por Compra',
                        'sale' => 'Salidas por Venta',
                        'sale_return' => 'Devoluciones',
                        'adjustment_in' => 'Ajustes (+)',
                        'adjustment_out' => 'Ajustes (-)',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryMovements::route('/'),
        ];
    }
}
