<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PriceListResource\Pages;
use App\Models\PriceList;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * ============================================================================
 * RECURSO FILAMENT: PriceListResource (Listas de Precios Diferenciadas)
 * ============================================================================
 * En una ferretería los precios varían según el cliente:
 * - Detal: Precio de mostrador al cliente general.
 * - Mayorista: Descuento por volumen para subdistribuidores o compras grandes.
 * - Constructor: Precios preferenciales para maestros de obra y contratistas.
 */
class PriceListResource extends Resource
{
    protected static ?string $model = PriceList::class;

    protected static ?string $modelLabel = 'Lista de Precios';

    protected static ?string $pluralModelLabel = 'Listas de Precios';

    protected static ?string $navigationGroup = 'Ventas y Clientes';

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('price_lists.view') ?? false;
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
        if (! (auth()->user()?->can('products.manage') ?? false)) {
            return false;
        }

        return ! $record->is_default;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->can('products.manage') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount(['customers', 'items']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Definición de la Lista')
                    ->description('Tarifas segmentadas para ventas en el Punto de Venta')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre de la Lista')
                                    ->placeholder('Ej: Constructor / Maestro de Obra')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('description')
                                    ->label('Descripción')
                                    ->placeholder('Ej: Tarifa con 10% de descuento en fijaciones y cemento')
                                    ->maxLength(255),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Toggle::make('is_default')
                                    ->label('Lista por Defecto')
                                    ->helperText('Se aplicará automáticamente a clientes nuevos sin lista asignada (ej: Detal)')
                                    ->dehydrateStateUsing(function ($state, ?PriceList $record) {
                                        if ($state) {
                                            PriceList::where('id', '!=', $record?->id ?? 0)
                                                ->where('is_default', true)
                                                ->update(['is_default' => false]);
                                        }

                                        return (bool) $state;
                                    }),

                                Forms\Components\Toggle::make('is_active')
                                    ->label('Lista Activa')
                                    ->default(true),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->searchable()
                    ->limit(50),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Por Defecto')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),

                Tables\Columns\TextColumn::make('customers_count')
                    ->label('Clientes Asignados')
                    ->counts('customers')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Productos con Tarifa')
                    ->counts('items')
                    ->badge()
                    ->color('success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Modificada')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (PriceList $record, Tables\Actions\DeleteAction $action) {
                        if ($record->is_default) {
                            Notification::make()
                                ->title('No se puede eliminar la lista por defecto')
                                ->body('Esta lista de precios está configurada como la lista predeterminada del sistema.')
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
                            $deletable = $records->filter(fn (PriceList $pl) => ! $pl->is_default);
                            $withDefault = $records->filter(fn (PriceList $pl) => $pl->is_default);

                            $deletable->each->delete();

                            if ($withDefault->isNotEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Eliminación parcial de listas')
                                    ->body('Se omitió la lista por defecto del sistema.')
                                    ->send();
                            } else {
                                Notification::make()
                                    ->success()
                                    ->title('Listas eliminadas')
                                    ->body("{$deletable->count()} lista(s) eliminadas correctamente.")
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPriceLists::route('/'),
            'create' => Pages\CreatePriceList::route('/create'),
            'edit' => Pages\EditPriceList::route('/{record}/edit'),
        ];
    }
}
