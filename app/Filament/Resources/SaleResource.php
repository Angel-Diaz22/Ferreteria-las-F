<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleResource\Pages;
use App\Models\Sale;
use App\Services\PosService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * ============================================================================
 * RECURSO FILAMENT: SaleResource (Historial y Auditoría de Ventas)
 * ============================================================================
 * ¿CÓMO FUNCIONA ESTE MÓDULO?
 * 1. Muestra el historial completo de ventas y remisiones internas emitidas (REM-XXXXXX).
 * 2. Es un módulo de SOLO LECTURA para la creación/edición manual:
 *    - Las ventas NUNCA se crean con un formulario convencional; se procesan en la
 *      Terminal POS (Livewire) para asegurar bloqueo pesimista y rebaja atómica de stock.
 *    - Las ventas NUNCA se editan (inmutabilidad de comprobantes para prevenir fraudes).
 *    - Las ventas NUNCA se eliminan con DELETE físico (destruiría la trazabilidad del Kardex).
 * 3. Permite a los cajeros y administradores reimprimir tickets térmicos (80mm) y descargar PDFs.
 * 4. Permite a usuarios con rol 'admin' anular ventas justificadas, lo cual dispara
 *    automáticamente el reintegro de existencias físicas en Kardex y el ajuste de deuda.
 * 5. Principio de Seguridad: Los cajeros tienen oculta la información de costos y márgenes.
 */
class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static ?string $modelLabel = 'Comprobante de Venta';

    protected static ?string $pluralModelLabel = 'Historial de Ventas';

    protected static ?string $navigationLabel = 'Historial de Ventas';

    protected static ?string $navigationGroup = 'Ventas y Clientes';

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('sales.view') ?? false;
    }

    /**
     * Deshabilitamos la creación directa por formulario.
     * Todas las ventas deben nacer en la Terminal POS para garantizar la coherencia de inventario.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Deshabilitamos la edición de ventas (inmutabilidad contable).
     */
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /**
     * Deshabilitamos el borrado físico de ventas (auditoría obligatoria).
     */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /**
     * FORMULARIO: Vista detallada de sólo lectura al hacer clic en "Ver".
     */
    public static function form(Form $form): Form
    {
        $isAdmin = auth()->user()?->hasRole('admin') ?? false;

        return $form
            ->schema([
                // -------------------------------------------------------------
                // SECCIÓN 1: ENCABEZADO DEL COMPROBANTE
                // -------------------------------------------------------------
                Forms\Components\Section::make('Información del Comprobante')
                    ->description('Datos de radicación, responsable y estado del comprobante')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('invoice_number')
                            ->label('N° Comprobante')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('created_at')
                            ->label('Fecha y Hora')
                            ->disabled(),

                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'completed' => 'Completada',
                                'cancelled' => 'Anulada',
                            ])
                            ->disabled(),

                        Forms\Components\Select::make('user_id')
                            ->label('Cajero / Responsable')
                            ->relationship('user', 'name')
                            ->disabled(),

                        Forms\Components\Select::make('warehouse_id')
                            ->label('Bodega Despachadora')
                            ->relationship('warehouse', 'name')
                            ->disabled(),

                        Forms\Components\Select::make('payment_method')
                            ->label('Método de Pago')
                            ->options([
                                'cash' => 'Efectivo',
                                'card' => 'Tarjeta Débito/Crédito',
                                'credit' => 'Crédito de Cartera',
                                'transfer' => 'Transferencia Bancaria',
                            ])
                            ->disabled(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notas y Observaciones')
                            ->columnSpanFull()
                            ->disabled(),
                    ]),

                // -------------------------------------------------------------
                // SECCIÓN 2: CLIENTE Y LIQUIDACIÓN MONETARIA
                // -------------------------------------------------------------
                Forms\Components\Section::make('Liquidación y Pagos')
                    ->description('Detalle de importes, descuentos y vueltas entregadas')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label('Cliente')
                            ->relationship('customer', 'name')
                            ->placeholder('Cliente Mostrador')
                            ->disabled(),

                        Forms\Components\TextInput::make('subtotal')
                            ->label('Subtotal')
                            ->prefix('$')
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('tax_amount')
                            ->label('IVA Total')
                            ->prefix('$')
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('discount_amount')
                            ->label('Descuentos')
                            ->prefix('$')
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('total')
                            ->label('Total Venta')
                            ->prefix('$')
                            ->numeric()
                            ->extraAttributes(['class' => 'text-lg font-bold text-emerald-600'])
                            ->disabled(),

                        Forms\Components\TextInput::make('paid_amount')
                            ->label('Monto Pagado')
                            ->prefix('$')
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('change_amount')
                            ->label('Vueltas / Cambio')
                            ->prefix('$')
                            ->numeric()
                            ->disabled(),
                    ]),

                // -------------------------------------------------------------
                // SECCIÓN 3: ARTÍCULOS VENDIDOS (REPEATER DE SOLO LECTURA)
                // -------------------------------------------------------------
                Forms\Components\Section::make('Artículos Vendidos')
                    ->description('Lista de productos entregados en esta transacción')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship('items')
                            ->label('Productos')
                            ->disabled()
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columns(5)
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Producto')
                                    ->relationship('product', 'name')
                                    ->columnSpan(2)
                                    ->disabled(),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->disabled(),

                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Precio Unitario')
                                    ->prefix('$')
                                    ->numeric()
                                    ->disabled(),

                                Forms\Components\TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->prefix('$')
                                    ->numeric()
                                    ->disabled(),

                                // Costo unitario: ÚNICAMENTE visible para administradores
                                Forms\Components\TextInput::make('unit_cost')
                                    ->label('Costo Unitario')
                                    ->prefix('$')
                                    ->numeric()
                                    ->visible($isAdmin)
                                    ->disabled(),
                            ]),
                    ]),
            ]);
    }

    /**
     * TABLA: Listado de ventas con búsqueda, filtros y acciones de tickets.
     */
    public static function table(Table $table): Table
    {
        $isAdmin = auth()->user()?->hasRole('admin') ?? false;

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('N° Comprobante')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->default('Cliente Mostrador')
                    ->searchable(),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Bodega')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Pago')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cash' => 'Efectivo',
                        'card' => 'Tarjeta',
                        'credit' => 'Crédito',
                        'transfer' => 'Transferencia',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'cash' => 'success',
                        'card' => 'info',
                        'credit' => 'warning',
                        'transfer' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total (COP)')
                    ->money('COP', divideBy: 1)
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Cajero')
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'completed' => 'Completada',
                        'cancelled' => 'Anulada',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'completed' => 'Completadas',
                        'cancelled' => 'Anuladas',
                    ]),

                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Método de Pago')
                    ->options([
                        'cash' => 'Efectivo',
                        'card' => 'Tarjeta',
                        'credit' => 'Crédito',
                        'transfer' => 'Transferencia',
                    ]),

                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Bodega')
                    ->relationship('warehouse', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Ver'),

                // ACCIÓN 1: Imprimir Ticket Térmico en ventana emergente
                Tables\Actions\Action::make('receipt')
                    ->label('Ticket')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->url(fn (Sale $record) => route('sales.receipt', $record))
                    ->openUrlInNewTab(),

                // ACCIÓN 2: Descargar Comprobante en formato PDF
                Tables\Actions\Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (Sale $record) => route('sales.pdf', $record))
                    ->openUrlInNewTab(),

                // ACCIÓN 3: Anular Venta (Requiere permiso sales.cancel, requiere justificación)
                Tables\Actions\Action::make('void')
                    ->label('Anular')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Sale $record): bool => $record->status !== 'cancelled' && (auth()->user()?->can('sales.cancel') ?? false))
                    ->requiresConfirmation()
                    ->modalHeading('Anulación de Comprobante de Venta')
                    ->modalDescription('Esta acción reintegrará las existencias físicas al Kardex y descontará la deuda si la venta fue a crédito. Ingrese el motivo.')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Motivo de Anulación')
                            ->placeholder('Ej: Error en los artículos cobrados, devolución total...')
                            ->required(),
                    ])
                    ->action(function (Sale $record, array $data): void {
                        PosService::voidSale($record, $data['reason'], auth()->id());

                        Notification::make()
                            ->title('Comprobante Anulado')
                            ->body("La venta {$record->invoice_number} fue anulada y su stock reincorporado al Kardex.")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                // No permitimos borrado masivo de ventas para salvaguardar la integridad fiscal
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
            'index' => Pages\ListSales::route('/'),
            'view' => Pages\ViewSale::route('/{record}'),
        ];
    }
}
