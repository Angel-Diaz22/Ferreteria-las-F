<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * ============================================================================
 * RECURSO FILAMENT: CategoryResource (Categorías de Productos)
 * ============================================================================
 * Permite organizar el inventario ferretero (ej: Plomería, Eléctricos,
 * Tornillería, Pinturas, Herramientas Eléctricas).
 */
class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $modelLabel = 'Categoría';

    protected static ?string $pluralModelLabel = 'Categorías';

    protected static ?string $navigationGroup = 'Catálogo e Inventario';

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('categories.view') ?? false;
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

    /**
     * FORMULARIO: Construye los campos para crear/editar categorías.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalle de la Categoría')
                    ->description('Organización temática del inventario')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                /**
                                 * REACTIVIDAD CON LIVEWIRE:
                                 * live(onBlur: true) detecta cuando el usuario termina de escribir
                                 * el nombre y ejecuta afterStateUpdated para autocompletar el slug.
                                 */
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre de la Categoría')
                                    ->placeholder('Ej: Plomería y Grifería')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, ?string $state) {
                                        $set('slug', Str::slug($state ?? ''));
                                    }),

                                Forms\Components\TextInput::make('slug')
                                    ->label('Identificador URL (Slug)')
                                    ->helperText('Generado automáticamente para búsquedas y URLs')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->label('Descripción (Opcional)')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Categoría Activa')
                            ->default(true)
                            ->helperText('Si se desactiva, sus productos no aparecerán en el Punto de Venta'),
                    ]),
            ]);
    }

    /**
     * TABLA: Muestra la lista de categorías con contador de productos en vivo.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->badge()
                    ->color('gray'),

                /**
                 * counts('products'): Ejecuta automáticamente un SQL eficiente:
                 * SELECT categories.*, COUNT(products.id) as products_count
                 * evitando problemas de rendimiento (N+1 query).
                 */
                Tables\Columns\TextColumn::make('products_count')
                    ->label('Total Productos')
                    ->counts('products')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Última Actualización')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->placeholder('Todas las categorías')
                    ->trueLabel('Solo activas')
                    ->falseLabel('Solo inactivas'),
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
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
