<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuoteResource\Pages;
use App\Models\Customer;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\Quote;
use App\Models\SystemSetting;
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
 * RECURSO FILAMENT: QuoteResource (Cotizaciones Comerciales en Ferretería)
 * ============================================================================
 * ¿CÓMO FUNCIONA ESTE MÓDULO?
 * 1. Permite cotizar pedidos a clientes de mostrador, mayoristas y constructoras.
 * 2. Asigna automáticamente el precio unitario según la lista de precios asignada
 *    al cliente (ej. si el cliente tiene tarifa Constructor, consulta `price_list_items`).
 * 3. Calcula subtotales, IVA estimado (19% en Colombia) y Total en tiempo real
 *    mediante la reactividad de Livewire y Filament.
 * 4. Genera un documento PDF formal con membrete de la empresa y validez de la oferta.
 */
class QuoteResource extends Resource
{
    protected static ?string $model = Quote::class;

    protected static ?string $modelLabel = 'Cotización';

    protected static ?string $pluralModelLabel = 'Cotizaciones';

    protected static ?string $navigationGroup = 'Ventas y Clientes';

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('quotes.view') ?? false;
    }

    /**
     * FORMULARIO: Estructura visual de captura para la cotización
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // -------------------------------------------------------------
                // SECCIÓN 1: ENCABEZADO DE LA COTIZACIÓN
                // -------------------------------------------------------------
                Forms\Components\Section::make('Información General de la Cotización')
                    ->description('Datos del cliente, consecutivo comercial y vigencia de la oferta')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('quote_number')
                            ->label('N° Cotización')
                            ->default(fn () => 'COT-'.str_pad((Quote::max('id') ?? 0) + 1, 5, '0', STR_PAD_LEFT))
                            ->required()
                            ->readOnly()
                            ->unique(ignoreRecord: true)
                            ->helperText('Consecutivo automático'),

                        Forms\Components\Select::make('customer_id')
                            ->label('Cliente')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->helperText('Si el cliente tiene Lista Mayorista o Constructor, los precios se calcularán según su tarifa.')
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                // Al cambiar de cliente, recalcular los precios de los productos en el repetidor
                                self::recalculateAllItemPrices($get, $set);
                            }),

                        Forms\Components\DatePicker::make('valid_until')
                            ->label('Válida Hasta')
                            ->default(now()->addDays(15))
                            ->required()
                            ->helperText('Oferta comercial garantizada por 15 días'),

                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'pending' => 'Pendiente / En Estudio',
                                'accepted' => 'Aceptada (Aprobada por el cliente)',
                                'rejected' => 'Rechazada',
                                'expired' => 'Vencida / Expirada',
                            ])
                            ->default('pending')
                            ->required(),

                        Forms\Components\Hidden::make('user_id')
                            ->default(fn () => auth()->id()),
                    ]),

                // -------------------------------------------------------------
                // SECCIÓN 2: DETALLE DE ARTÍCULOS COTIZADOS (REPEATER)
                // -------------------------------------------------------------
                Forms\Components\Section::make('Productos Cotizados')
                    ->description('Selecciona los artículos, cantidades y verifica el precio asignado')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship('items')
                            ->label('Artículos de la Oferta')
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Producto')
                                    ->options(fn () => Product::where('is_active', true)
                                        ->withSum('stocks as aggregated_stock', 'current_stock')
                                        ->limit(100)
                                        ->get()
                                        ->mapWithKeys(
                                            fn (Product $p) => [$p->id => "{$p->name} [{$p->sku}] - Stock: ".((float) ($p->aggregated_stock ?? 0))]
                                        )
                                        ->all()
                                    )
                                    ->getSearchResultsUsing(function (string $search): array {
                                        $likeOp = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

                                        return Product::where('is_active', true)
                                            ->where(fn ($q) => $q->where('name', $likeOp, "%{$search}%")->orWhere('sku', $likeOp, "%{$search}%"))
                                            ->withSum('stocks as aggregated_stock', 'current_stock')
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(
                                                fn (Product $p) => [$p->id => "{$p->name} [{$p->sku}] - Stock: ".((float) ($p->aggregated_stock ?? 0))]
                                            )
                                            ->all();
                                    })
                                    ->getOptionLabelUsing(function ($value): ?string {
                                        $p = Product::withSum('stocks as aggregated_stock', 'current_stock')->find($value);

                                        return $p ? "{$p->name} [{$p->sku}] - Stock: ".((float) ($p->aggregated_stock ?? 0)) : null;
                                    })
                                    ->searchable()
                                    ->required()
                                    ->columnSpan(2)
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, ?int $state) {
                                        if (! $state) {
                                            return;
                                        }

                                        $product = Product::find($state);
                                        if (! $product) {
                                            return;
                                        }

                                        // 1. Obtener precio base
                                        $unitPrice = (float) $product->sale_price;

                                        // 2. Revisar si el cliente seleccionado tiene lista de precios personalizada
                                        $customerId = $get('../../customer_id');
                                        if ($customerId) {
                                            $customer = Customer::find($customerId);
                                            if ($customer && $customer->price_list_id) {
                                                $customPrice = PriceListItem::where('price_list_id', $customer->price_list_id)
                                                    ->where('product_id', $product->id)
                                                    ->value('price');

                                                if ($customPrice !== null) {
                                                    $unitPrice = (float) $customPrice;
                                                }
                                            }
                                        }

                                        $set('unit_price', $unitPrice);
                                        $set('tax_rate', SystemSetting::isIvaEnabled() ? SystemSetting::getIvaRate() : 0.0);

                                        $qty = (float) ($get('quantity') ?: 1);
                                        $set('subtotal', round($qty * $unitPrice, 2));

                                        self::recalculateTotals($get, $set, true);
                                    }),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        $qty = (float) ($get('quantity') ?? 0);
                                        $price = (float) ($get('unit_price') ?? 0);
                                        $set('subtotal', round($qty * $price, 2));
                                        self::recalculateTotals($get, $set, true);
                                    }),

                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Precio Unit.')
                                    ->numeric()
                                    ->prefix('$')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        $qty = (float) ($get('quantity') ?? 0);
                                        $price = (float) ($get('unit_price') ?? 0);
                                        $set('subtotal', round($qty * $price, 2));
                                        self::recalculateTotals($get, $set, true);
                                    }),

                                Forms\Components\TextInput::make('tax_rate')
                                    ->label('IVA %')
                                    ->numeric()
                                    ->suffix('%')
                                    ->default(fn () => SystemSetting::isIvaEnabled() ? SystemSetting::getIvaRate() : 0.0)
                                    ->visible(fn () => SystemSetting::isIvaEnabled())
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        self::recalculateTotals($get, $set, true);
                                    }),

                                Forms\Components\TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->prefix('$')
                                    ->disabled()
                                    ->dehydrated()
                                    ->required(),
                            ])
                            ->columns(6)
                            ->defaultItems(1)
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                self::recalculateTotals($get, $set, false);
                            }),
                    ]),

                // -------------------------------------------------------------
                // SECCIÓN 3: LIQUIDACIÓN TRIBUTARIA Y TOTALES
                // -------------------------------------------------------------
                Forms\Components\Section::make('Liquidación Económica')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('subtotal')
                            ->label(fn () => SystemSetting::isIvaEnabled() ? 'Subtotal (Antes de IVA)' : 'Subtotal')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->readOnly(),

                        Forms\Components\TextInput::make('tax_amount')
                            ->label('IVA Total Estimado')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->visible(fn () => SystemSetting::isIvaEnabled())
                            ->readOnly(),

                        Forms\Components\TextInput::make('total')
                            ->label('TOTAL COTIZADO')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->readOnly()
                            ->extraInputAttributes(['class' => 'font-bold text-lg text-primary-600']),

                        Forms\Components\Textarea::make('notes')
                            ->label('Condiciones Comerciales y Observaciones')
                            ->placeholder('Ej: Tiempo de entrega estimado: 48 horas. Precios válidos para pago en efectivo o transferencia.')
                            ->columnSpanFull()
                            ->rows(3),
                    ]),
            ]);
    }

    /**
     * MÉTODO table(): Vista en tabla del listado de cotizaciones
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('quote_number')
                    ->label('N° Cotización')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Cliente Mostrador'),

                Tables\Columns\TextColumn::make('valid_until')
                    ->label('Válida Hasta')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'accepted',
                        'danger' => 'rejected',
                        'gray' => 'expired',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pendiente',
                        'accepted' => 'Aceptada',
                        'rejected' => 'Rechazada',
                        'expired' => 'Vencida',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->money('COP', locale: 'es_CO')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tax_amount')
                    ->label('IVA')
                    ->money('COP', locale: 'es_CO')
                    ->visible(fn () => SystemSetting::isIvaEnabled())
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('COP', locale: 'es_CO')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Asesor')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'accepted' => 'Aceptada',
                        'rejected' => 'Rechazada',
                        'expired' => 'Vencida',
                    ]),
            ])
            ->actions([
                // Botón para generar y descargar el PDF de la cotización
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn (Quote $record): string => route('quotes.pdf', $record))
                    ->openUrlInNewTab(),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * RECALCULAR TOTALES:
     * Suma los subtotales e impuestos de todos los artículos de la cotización.
     */
    public static function recalculateTotals(Get $get, Set $set, bool $isInsideItem = false): void
    {
        $items = $isInsideItem ? ($get('../../items') ?? []) : ($get('items') ?? []);
        $subtotal = 0.0;
        $taxAmount = 0.0;

        foreach ($items as $item) {
            $qty = (float) ($item['quantity'] ?? 0);
            $price = (float) ($item['unit_price'] ?? 0);
            $taxRate = (float) ($item['tax_rate'] ?? 19.00);

            $lineSubtotal = round($qty * $price, 2);
            $lineTax = round($lineSubtotal * ($taxRate / 100), 2);

            $subtotal += $lineSubtotal;
            $taxAmount += $lineTax;
        }

        $total = round($subtotal + $taxAmount, 2);

        if ($isInsideItem) {
            $set('../../subtotal', round($subtotal, 2));
            $set('../../tax_amount', round($taxAmount, 2));
            $set('../../total', $total);
        } else {
            $set('subtotal', round($subtotal, 2));
            $set('tax_amount', round($taxAmount, 2));
            $set('total', $total);
        }
    }

    /**
     * RECALCULAR PRECIOS DE ITEMS CUANDO CAMBIA EL CLIENTE:
     * Si el usuario selecciona un cliente que tiene tarifa especial (ej. Mayorista),
     * actualiza los precios unitarios de los productos ya agregados en la cotización.
     */
    public static function recalculateAllItemPrices(Get $get, Set $set): void
    {
        $customerId = $get('customer_id');
        $items = $get('items') ?? [];

        if (empty($items)) {
            return;
        }

        $priceListId = null;
        if ($customerId) {
            $priceListId = Customer::find($customerId)?->price_list_id;
        }

        $updatedItems = [];
        $subtotal = 0.0;
        $taxAmount = 0.0;

        $isIvaEnabled = SystemSetting::isIvaEnabled();
        $defaultTaxRate = $isIvaEnabled ? SystemSetting::getIvaRate() : 0.0;

        foreach ($items as $key => $item) {
            $productId = $item['product_id'] ?? null;
            if (! $productId) {
                $updatedItems[$key] = $item;

                continue;
            }

            $product = Product::find($productId);
            if (! $product) {
                $updatedItems[$key] = $item;

                continue;
            }

            $unitPrice = (float) $product->sale_price;

            if ($priceListId) {
                $customPrice = PriceListItem::where('price_list_id', $priceListId)
                    ->where('product_id', $product->id)
                    ->value('price');
                if ($customPrice !== null) {
                    $unitPrice = (float) $customPrice;
                }
            }

            $qty = (float) ($item['quantity'] ?? 1);
            $taxRate = $isIvaEnabled ? (float) ($item['tax_rate'] ?? $defaultTaxRate) : 0.0;
            $lineSubtotal = round($qty * $unitPrice, 2);
            $lineTax = $isIvaEnabled ? round($lineSubtotal * ($taxRate / 100), 2) : 0.0;

            $item['unit_price'] = $unitPrice;
            $item['tax_rate'] = $taxRate;
            $item['subtotal'] = $lineSubtotal;
            $updatedItems[$key] = $item;

            $subtotal += $lineSubtotal;
            $taxAmount += $lineTax;
        }

        $set('items', $updatedItems);
        $set('subtotal', round($subtotal, 2));
        $set('tax_amount', round($taxAmount, 2));
        $set('total', round($subtotal + $taxAmount, 2));
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
            'index' => Pages\ListQuotes::route('/'),
            'create' => Pages\CreateQuote::route('/create'),
            'edit' => Pages\EditQuote::route('/{record}/edit'),
        ];
    }
}
