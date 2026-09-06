<?php

namespace App\Filament\Resources;

use App\Filament\Imports\ProductImporter;
use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * ============================================================================
 * RECURSO FILAMENT: ProductResource (Catálogo Maestro de Productos)
 * ============================================================================
 * Es el corazón del catálogo de la ferretería:
 * - Clasificación por Categoría y Marca.
 * - Identificación por SKU interno y Código de Barras USB.
 * - Costos, Precios al Detal y cálculo reactivo en vivo del Margen de Ganancia.
 * - SEGURIDAD Y ROLES: El precio de costo y la ganancia SOLO son visibles
 *   para usuarios con permiso products.view_cost (o admin).
 * - Listas de precios diferenciadas (Mayorista, Constructor).
 * - Subida de imágenes con compresión automática a formato WebP.
 * - Carga masiva de inventario desde archivos Excel / CSV.
 */
class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $modelLabel = 'Producto';

    protected static ?string $pluralModelLabel = 'Productos';

    protected static ?string $navigationGroup = 'Catálogo e Inventario';

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('products.view') ?? false;
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['category', 'brand'])
            ->withSum('stocks as total_stock', 'current_stock');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Ficha del Producto')
                    ->tabs([
                        // PESTAÑA 1: INFORMACIÓN GENERAL
                        Forms\Components\Tabs\Tab::make('General')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Nombre del Producto')
                                            ->placeholder('Ej: Taladro Percutor 1/2 Pulgada 650W')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(2),

                                        Forms\Components\Select::make('unit')
                                            ->label('Unidad de Venta')
                                            ->options([
                                                'UND' => 'Unidad (UND)',
                                                'MTR' => 'Metro (MTR)',
                                                'KG' => 'Kilogramo (KG)',
                                                'BOL' => 'Bolsa (BOL)',
                                                'GLN' => 'Galón (GLN)',
                                                'PQ' => 'Paquete (PQ)',
                                                'CJA' => 'Caja (CJA)',
                                            ])
                                            ->default('UND')
                                            ->required(),

                                        Forms\Components\TextInput::make('sku')
                                            ->label('SKU (Código Interno)')
                                            ->placeholder('Ej: TAL-PER-650W')
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(100),

                                        Forms\Components\TextInput::make('barcode')
                                            ->label('Código de Barras (Escáner USB)')
                                            ->placeholder('Lectura con pistola de barras o EAN')
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(100),

                                        Forms\Components\Select::make('category_id')
                                            ->label('Categoría')
                                            ->relationship('category', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->createOptionForm([
                                                Forms\Components\TextInput::make('name')
                                                    ->label('Nombre de Categoría')
                                                    ->required(),
                                            ]),

                                        Forms\Components\Select::make('brand_id')
                                            ->label('Marca')
                                            ->relationship('brand', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->createOptionForm([
                                                Forms\Components\TextInput::make('name')
                                                    ->label('Nombre de Marca')
                                                    ->required(),
                                            ]),

                                        Forms\Components\Toggle::make('is_active')
                                            ->label('Producto Activo para Venta')
                                            ->default(true)
                                            ->columnSpan(2),
                                    ]),

                                Forms\Components\Textarea::make('description')
                                    ->label('Descripción / Ficha Técnica')
                                    ->rows(2)
                                    ->columnSpanFull(),

                                Forms\Components\FileUpload::make('image')
                                    ->label('Foto del Producto')
                                    ->image()
                                    ->disk('public')
                                    ->directory('products')
                                    ->visibility('public')
                                    ->imageEditor()
                                    ->helperText('Formato admitido: JPG, PNG, WEBP. El sistema lo convertirá y comprimirá automáticamente a WebP para máxima velocidad.')
                                    ->columnSpanFull(),
                            ]),

                        // PESTAÑA 2: COSTOS, PRECIOS Y MARGEN
                        Forms\Components\Tabs\Tab::make('Precios y Margen')
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        /**
                                         * CONTROL DE ROLES EN CAMPO COST_PRICE:
                                         * ->visible(fn () => auth()->user()?->hasRole('admin'))
                                         * Si el usuario logueado es un cajero, este campo ni siquiera
                                         * se envía en el HTML por seguridad.
                                         */
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
                                    ]),

                                /**
                                 * CONTROL DE ROLES EN MARGEN CALCULADO:
                                 * Solo visible para quien tiene el permiso 'products.view_cost' (o admin).
                                 */
                                Forms\Components\Placeholder::make('margen_calculado')
                                    ->label('Análisis de Rentabilidad en Tiempo Real')
                                    ->visible(fn (): bool => auth()->user()?->can('products.view_cost') ?? false)
                                    ->content(function (Get $get): HtmlString {
                                        $cost = (float) ($get('cost_price') ?? 0);
                                        $sale = (float) ($get('sale_price') ?? 0);

                                        if ($cost <= 0) {
                                            return new HtmlString('<span class="text-gray-500">Ingresa el precio de costo para calcular el margen de ganancia.</span>');
                                        }

                                        $margen = round((($sale - $cost) / $cost) * 100, 2);
                                        $gananciaNeta = $sale - $cost;

                                        if ($sale < $cost) {
                                            return new HtmlString(
                                                '<div class="p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 font-medium">'.
                                                '⚠️ <strong>¡ALERTA DE PÉRDIDA!</strong> El precio de venta está por debajo del costo. '.
                                                'Pérdida por unidad: <strong>-$'.number_format(abs($gananciaNeta), 2).' COP</strong> ('.$margen.'%)'.
                                                '</div>'
                                            );
                                        }

                                        return new HtmlString(
                                            '<div class="p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-emerald-800">'.
                                            'Ganancia estimada: <strong>+$'.number_format($gananciaNeta, 2).' COP</strong> por unidad. '.
                                            'Margen de ganancia bruto: <strong class="text-base text-emerald-600">'.$margen.'%</strong>'.
                                            '</div>'
                                        );
                                    }),
                            ]),

                        // PESTAÑA 3: PRECIOS DIFERENCIADOS
                        Forms\Components\Tabs\Tab::make('Listas de Precios')
                            ->icon('heroicon-o-rectangle-stack')
                            ->schema([
                                Forms\Components\Repeater::make('priceListItems')
                                    ->relationship('priceListItems')
                                    ->label('Tarifas Especiales por Segmento de Cliente')
                                    ->itemLabel(fn (array $state): ?string => 'Precio Especial')
                                    ->schema([
                                        Forms\Components\Select::make('price_list_id')
                                            ->label('Lista de Precios')
                                            ->relationship('priceList', 'name')
                                            ->required()
                                            ->distinct(),

                                        Forms\Components\TextInput::make('price')
                                            ->label('Precio para esta Lista')
                                            ->numeric()
                                            ->minValue(0)
                                            ->prefix('$')
                                            ->required(),
                                    ])
                                    ->columns(2)
                                    ->defaultItems(0)
                                    ->addActionLabel('Agregar Precio por Lista (Mayorista / Constructor)'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Foto')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl('/images/no-image.png'),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('SKU copiado'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Producto')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->wrap(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoría')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('brand.name')
                    ->label('Marca')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                // Solo quien tiene permiso products.view_cost ve el costo de compra en la tabla
                Tables\Columns\TextColumn::make('cost_price')
                    ->label('Costo')
                    ->money('COP', locale: 'es_CO')
                    ->sortable()
                    ->visible(fn (): bool => auth()->user()?->can('products.view_cost') ?? false),

                Tables\Columns\TextColumn::make('sale_price')
                    ->label('Precio Venta')
                    ->money('COP', locale: 'es_CO')
                    ->sortable(),

                // Solo quien tiene permiso products.view_cost ve el margen de ganancia en la tabla
                Tables\Columns\TextColumn::make('profit_margin')
                    ->label('Margen')
                    ->state(fn (Product $record): string => $record->profit_margin.'%')
                    ->badge()
                    ->color(function (Product $record): string {
                        if ($record->profit_margin >= 25) {
                            return 'success';
                        }
                        if ($record->profit_margin >= 10) {
                            return 'warning';
                        }

                        return 'danger';
                    })
                    ->sortable()
                    ->visible(fn (): bool => auth()->user()?->can('products.view_cost') ?? false),

                // Stock total consolidado (todos los roles pueden verlo)
                Tables\Columns\TextColumn::make('total_stock')
                    ->label('Stock Total')
                    ->sortable()
                    ->state(fn (Product $record): string => $record->total_stock.' '.$record->unit)
                    ->badge()
                    ->color(function (Product $record): string {
                        if ($record->total_stock <= 0) {
                            return 'danger';
                        }
                        if ($record->total_stock <= 5) {
                            return 'warning';
                        }

                        return 'success';
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Categoría')
                    ->relationship('category', 'name'),

                Tables\Filters\SelectFilter::make('brand_id')
                    ->label('Marca')
                    ->relationship('brand', 'name'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->placeholder('Todos los productos')
                    ->trueLabel('Solo activos')
                    ->falseLabel('Solo inactivos'),
            ])
            ->headerActions([
                // Importación masiva de catálogo
                Tables\Actions\ImportAction::make()
                    ->label('Importar Excel / CSV')
                    ->importer(ProductImporter::class)
                    ->color('primary')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->visible(fn (): bool => auth()->user()?->can('products.manage') ?? false),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()?->can('products.manage') ?? false),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->can('products.manage') ?? false),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // Control de stock físico asignado por bodega
            RelationManagers\StocksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
