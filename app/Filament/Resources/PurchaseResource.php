<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseResource\Pages;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

/**
 * ============================================================================
 * RECURSO FILAMENT: PurchaseResource (Ingreso de Compras y Facturas de Proveedores)
 * ============================================================================
 * Permite registrar las facturas de mercancía comprada a proveedores.
 * Cuando la compra se marca como 'completed' (Recibida):
 * 1. Incrementa automáticamente el stock en la bodega de destino.
 * 2. Recalcula el costo promedio ponderado de cada producto.
 * 3. Registra el movimiento inmutable en el Kardex.
 *
 * SEGURIDAD: Restringido exclusivamente al rol 'admin' (Dueño/Administrador).
 */
class PurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;

    protected static ?string $modelLabel = 'Compra de Mercancía';

    protected static ?string $pluralModelLabel = 'Compras de Mercancía';

    protected static ?string $navigationGroup = 'Compras y Proveedores';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('purchases.view') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Cabecera de Factura')
                    ->description('Datos generales de la compra y bodega de recepción')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Select::make('supplier_id')
                            ->label('Proveedor')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Razón Social')
                                    ->required(),
                                Forms\Components\TextInput::make('nit')
                                    ->label('NIT')
                                    ->required(),
                            ]),

                        Forms\Components\Select::make('warehouse_id')
                            ->label('Bodega de Destino')
                            ->relationship('warehouse', 'name')
                            ->required()
                            ->default(fn () => Warehouse::where('is_active', true)->first()?->id)
                            ->helperText('Bodega donde ingresarán físicamente los productos'),

                        Forms\Components\DatePicker::make('purchase_date')
                            ->label('Fecha de Compra')
                            ->default(now())
                            ->required(),

                        Forms\Components\TextInput::make('invoice_number')
                            ->label('N° Factura Proveedor')
                            ->placeholder('Ej: FE-9842')
                            ->maxLength(100),

                        Forms\Components\Select::make('status')
                            ->label('Estado de la Compra')
                            ->options([
                                'completed' => 'Recibida (Ingresa a stock y recalcula costos)',
                                'pending' => 'En Trámite (Sin ingresar a inventario)',
                                'cancelled' => 'Cancelada / Anulada',
                            ])
                            ->default('completed')
                            ->required()
                            ->helperText('Al seleccionar "Recibida", el sistema actualizará el Kardex'),

                        Forms\Components\Hidden::make('user_id')
                            ->default(fn () => auth()->id()),
                    ]),

                Forms\Components\Section::make('Detalle de Productos Comprados')
                    ->description('Artículos, cantidades y costos unitarios de compra')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship('items')
                            ->label('Productos en la Factura')
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Producto')
                                    ->options(fn () => Product::limit(100)->get()->mapWithKeys(fn (Product $p) => [$p->id => "{$p->name} [{$p->sku}]"])->all())
                                    ->getSearchResultsUsing(function (string $search): array {
                                        $likeOp = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

                                        return Product::where(fn ($q) => $q->where('name', $likeOp, "%{$search}%")->orWhere('sku', $likeOp, "%{$search}%"))
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(fn (Product $p) => [$p->id => "{$p->name} [{$p->sku}]"])
                                            ->all();
                                    })
                                    ->getOptionLabelUsing(fn ($value): ?string => ($p = Product::find($value)) ? "{$p->name} [{$p->sku}]" : null)
                                    ->searchable()
                                    ->required()
                                    ->columnSpan(2)
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, ?int $state) {
                                        if ($state && $product = Product::find($state)) {
                                            $set('unit_cost', $product->cost_price);
                                        }
                                    }),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        $qty = (float) ($get('quantity') ?? 0);
                                        $cost = (float) ($get('unit_cost') ?? 0);
                                        $set('subtotal', round($qty * $cost, 2));
                                    }),

                                Forms\Components\TextInput::make('unit_cost')
                                    ->label('Costo Unitario Factura')
                                    ->numeric()
                                    ->prefix('$')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        $qty = (float) ($get('quantity') ?? 0);
                                        $cost = (float) ($get('unit_cost') ?? 0);
                                        $set('subtotal', round($qty * $cost, 2));
                                    }),

                                Forms\Components\TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->prefix('$')
                                    ->disabled()
                                    ->dehydrated()
                                    ->required(),
                            ])
                            ->columns(5)
                            ->defaultItems(1)
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                // Sumar todos los subtotales del repetidor para el total de la compra
                                $items = $get('items') ?? [];
                                $total = 0.0;
                                foreach ($items as $item) {
                                    $qty = (float) ($item['quantity'] ?? 0);
                                    $cost = (float) ($item['unit_cost'] ?? 0);
                                    $total += ($qty * $cost);
                                }
                                $set('subtotal', $total);
                                $set('total', $total);
                            }),
                    ]),

                Forms\Components\Section::make('Liquidación Total')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('subtotal')
                            ->label('Subtotal Compra')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->readOnly(),

                        Forms\Components\TextInput::make('tax_amount')
                            ->label('Impuestos (IVA)')
                            ->numeric()
                            ->prefix('$')
                            ->default(0),

                        Forms\Components\TextInput::make('total')
                            ->label('Total a Pagar')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->required(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Observaciones de Entrega')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('purchase_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('N° Factura')
                    ->searchable()
                    ->placeholder('Sin Factura')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Proveedor')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Bodega Ingreso')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total Compra')
                    ->money('COP', locale: 'es_CO')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'completed' => 'Recibida',
                        'pending' => 'Pendiente',
                        'cancelled' => 'Cancelada',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Registrada Por')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Proveedor')
                    ->relationship('supplier', 'name'),

                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Bodega')
                    ->relationship('warehouse', 'name'),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'completed' => 'Recibidas',
                        'pending' => 'Pendientes',
                        'cancelled' => 'Canceladas',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchases::route('/'),
            'create' => Pages\CreatePurchase::route('/create'),
            'edit' => Pages\EditPurchase::route('/{record}/edit'),
        ];
    }
}
