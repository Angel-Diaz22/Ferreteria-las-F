<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarehouseResource\Pages;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationGroup = 'Catálogo e Inventario';

    protected static ?string $navigationLabel = 'Bodegas';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('warehouses.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('products.manage') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('products.manage') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('products.manage') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('code')
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\TextInput::make('address')
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->maxLength(255),
                Forms\Components\Toggle::make('is_active')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('address')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Warehouse $record, Tables\Actions\DeleteAction $action) {
                        $hasActiveStock = $record->stocks()->where('current_stock', '>', 0)->exists();
                        $hasHistory = $record->sales()->exists() || $record->purchases()->exists() || $record->inventoryMovements()->exists() || $record->cashRegisters()->exists();

                        if ($hasActiveStock || $hasHistory) {
                            Notification::make()
                                ->title('No se puede eliminar la bodega')
                                ->body($hasActiveStock ? 'La bodega tiene existencias activas mayores a cero. Reasigne o descargue el inventario antes de eliminarla.' : 'La bodega tiene registros históricos asociados (ventas, compras, movimientos o cajas). Desactívela en su lugar.')
                                ->danger()
                                ->send();

                            $action->halt();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (Collection $records) {
                            $deletable = $records->filter(function (Warehouse $w) {
                                return ! ($w->stocks()->where('current_stock', '>', 0)->exists()
                                    || $w->sales()->exists()
                                    || $w->purchases()->exists()
                                    || $w->inventoryMovements()->exists()
                                    || $w->cashRegisters()->exists());
                            });
                            $blocked = $records->diff($deletable);

                            $deletable->each->delete();

                            if ($blocked->isNotEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Eliminación parcial de bodegas')
                                    ->body("Se eliminaron {$deletable->count()} bodega(s). Se omitieron {$blocked->count()} bodega(s) con existencias activas o histórico.")
                                    ->send();
                            } else {
                                Notification::make()
                                    ->success()
                                    ->title('Bodegas eliminadas')
                                    ->body("{$deletable->count()} bodega(s) eliminadas correctamente.")
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWarehouses::route('/'),
            'create' => Pages\CreateWarehouse::route('/create'),
            'edit' => Pages\EditWarehouse::route('/{record}/edit'),
        ];
    }
}
