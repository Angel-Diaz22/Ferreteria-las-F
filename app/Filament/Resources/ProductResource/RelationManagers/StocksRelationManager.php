<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\ProductStock;
use App\Models\Warehouse;
use App\Services\KardexService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

/**
 * ============================================================================
 * RELATION MANAGER: Stocks (Inventario por Bodega y Kardex)
 * ============================================================================
 * ¿QUÉ PROBLEMA RESUELVE ESTA CLASE?
 * 1. Controla que un producto solo pueda tener UNA ficha de stock por bodega
 *    (evita el error SQLSTATE 23505 duplicate key violation).
 * 2. Filtra el selector para mostrar únicamente las bodegas donde el producto
 *    aún no ha sido asignado.
 * 3. Incorpora la acción "Ajustar Stock" con registro automático en el Kardex
 *    para entradas (conteo, inventario inicial) y salidas (merma, daño).
 */
class StocksRelationManager extends RelationManager
{
    protected static string $relationship = 'stocks';

    protected static ?string $title = 'Inventario por Bodega / Sucursal';

    protected static ?string $modelLabel = 'Stock en Bodega';

    protected static ?string $pluralModelLabel = 'Stocks en Bodegas';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('warehouse_id')
                    ->label('Bodega / Sucursal')
                    ->relationship('warehouse', 'name', modifyQueryUsing: function ($query) {
                        // Obtenemos los IDs de las bodegas que ya están asignadas a este producto
                        $assignedWarehouses = ProductStock::where('product_id', $this->getOwnerRecord()->id)
                            ->pluck('warehouse_id')
                            ->toArray();

                        // Filtramos para que SOLO aparezcan las bodegas no asignadas
                        return $query->where('is_active', true)
                            ->whereNotIn('id', $assignedWarehouses);
                    })
                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                    ->required()
                    ->helperText('Solo aparecen bodegas donde este producto aún no está registrado.'),

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
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('warehouse.name')
            ->columns([
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Bodega')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Stock Actual')
                    ->numeric(decimalPlaces: 2)
                    ->badge()
                    ->color(function (ProductStock $record): string {
                        if ($record->current_stock <= 0) {
                            return 'danger'; // Agotado
                        }
                        if ($record->current_stock <= $record->min_stock) {
                            return 'warning'; // En punto de reorden
                        }

                        return 'success'; // Existencia saludable
                    }),

                Tables\Columns\TextColumn::make('min_stock')
                    ->label('Stock Mínimo')
                    ->numeric(decimalPlaces: 2),

                Tables\Columns\TextColumn::make('max_stock')
                    ->label('Capacidad Máxima')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('Sin límite'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Asignar a Otra Bodega')
                    ->modalHeading('Asignar Producto a Nueva Bodega')
                    ->modalDescription('Crea la ficha de inventario para una bodega donde el producto aún no tiene presencia.')
                    ->visible(function (): bool {
                        // Solo mostramos el botón si todavía existen bodegas activas sin asignar
                        $totalActive = Warehouse::where('is_active', true)->count();
                        $alreadyAssigned = $this->getOwnerRecord()->stocks()->count();

                        return $alreadyAssigned < $totalActive;
                    })
                    ->using(function (array $data, RelationManager $livewire): ProductStock {
                        return DB::transaction(function () use ($data, $livewire): ProductStock {
                            $initialStock = (float) ($data['current_stock'] ?? 0);
                            $product = $livewire->getOwnerRecord();

                            /** @var ProductStock $record */
                            $record = $product->stocks()->create([
                                'warehouse_id' => $data['warehouse_id'],
                                'current_stock' => 0.00,
                                'min_stock' => $data['min_stock'] ?? 5.00,
                                'max_stock' => $data['max_stock'] ?? null,
                            ]);

                            if ($initialStock > 0) {
                                KardexService::registerAdjustment(
                                    product: $product,
                                    warehouseId: (int) $data['warehouse_id'],
                                    quantity: $initialStock,
                                    type: 'adjustment_in',
                                    notes: 'Inventario inicial al asignar producto a la bodega '.($record->warehouse?->name ?? ''),
                                    userId: auth()->id()
                                );

                                $record->refresh();
                            }

                            return $record;
                        });
                    }),
            ])
            ->actions([
                // ACCIÓN RÁPIDA: Ajustar existencias con registro en Kardex
                Tables\Actions\Action::make('adjust_stock')
                    ->label('Ajustar Stock')
                    ->icon('heroicon-o-arrows-up-down')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('type')
                            ->label('Tipo de Ajuste')
                            ->options([
                                'adjustment_in' => '🟢 Entrada de Stock (Suma inventario)',
                                'adjustment_out' => '🔴 Salida de Stock (Resta inventario / Merma)',
                            ])
                            ->default('adjustment_in')
                            ->required(),

                        Forms\Components\TextInput::make('quantity')
                            ->label('Cantidad a Ajustar')
                            ->numeric()
                            ->minValue(0.01)
                            ->required()
                            ->helperText('Cantidad exacta que vas a sumar o restar de esta bodega.'),

                        Forms\Components\TextInput::make('notes')
                            ->label('Motivo del Ajuste')
                            ->placeholder('Ej: Conteo físico en estantería, mercancía recibida sin factura, rotura')
                            ->required(),
                    ])
                    ->action(function (ProductStock $record, array $data): void {
                        KardexService::registerAdjustment(
                            product: $record->product,
                            warehouseId: $record->warehouse_id,
                            quantity: (float) $data['quantity'],
                            type: $data['type'],
                            notes: $data['notes'],
                            userId: auth()->id()
                        );

                        Notification::make()
                            ->title('Stock actualizado con éxito')
                            ->body('El movimiento fue registrado en el Kardex de inventario.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make()
                    ->label('Editar Umbrales')
                    ->modalHeading('Editar Parámetros de Bodega')
                    ->after(function (ProductStock $record, array $data) {
                        // Si el usuario modificó manualmente el stock en el modal de edición:
                        // Nota: el cambio ya fue guardado por Filament en $record->current_stock
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('Desvincular'),
            ]);
    }
}
