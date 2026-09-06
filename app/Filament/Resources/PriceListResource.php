<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PriceListResource\Pages;
use App\Models\PriceList;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
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
        return auth()->user()?->can('products.manage') ?? false;
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
                                    ->helperText('Se aplicará automáticamente a clientes nuevos sin lista asignada (ej: Detal)'),

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

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Modificada')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
