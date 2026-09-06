<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
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
 * RECURSO FILAMENT: CustomerResource (CRUD de Clientes)
 * ============================================================================
 * ¿QUÉ ES UN "RESOURCE" EN FILAMENT?
 * En desarrollo tradicional, para crear un CRUD (Create, Read, Update, Delete)
 * tendrías que escribir:
 * 1. Una ruta web (web.php).
 * 2. Un controlador (CustomerController.php) con 7 métodos (index, create, store, edit...).
 * 3. Al menos 3 o 4 plantillas HTML/Blade con formularios y tablas.
 * 4. JavaScript para modales, alertas y búsquedas en tiempo real.
 *
 * Filament automatiza todo eso: con esta única clase define:
 * - El formulario de creación y edición (método form()).
 * - La tabla interactiva con filtros, buscador y ordenamiento (método table()).
 * - La conexión directa con el modelo Eloquent `Customer`.
 */
class CustomerResource extends Resource
{
    // Modelo Eloquent que este recurso manipula:
    protected static ?string $model = Customer::class;

    // Etiquetas en la interfaz y menú:
    protected static ?string $modelLabel = 'Cliente';

    protected static ?string $pluralModelLabel = 'Clientes';

    protected static ?string $navigationGroup = 'Ventas y Clientes';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('customers.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        if (! (auth()->user()?->hasRole('admin') ?? false)) {
            return false;
        }

        return (float) $record->current_debt <= 0;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['priceList'])
            ->withExists('consentLogs as has_consented');
    }

    /**
     * MÉTODO form(): Construye el formulario visual de alta y edición.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // -------------------------------------------------------------
                // SECCIÓN 1: DATOS DE IDENTIFICACIÓN Y CONTACTO
                // -------------------------------------------------------------
                Forms\Components\Section::make('Información Básica')
                    ->description('Datos de identificación y contacto para facturación')
                    ->columns(2)
                    ->schema([
                        // Selector de lista de precios: Relación BelongsTo con PriceList
                        Forms\Components\Select::make('price_list_id')
                            ->label('Lista de Precios Asignada')
                            ->relationship('priceList', 'name')
                            ->helperText('Define si el cliente tiene tarifa Detal, Mayorista o Constructor en cotizaciones y ventas')
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Forms\Components\TextInput::make('name')
                            ->label('Nombre Completo o Razón Social')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ej: Construcciones del Norte S.A.S.'),

                        Forms\Components\Select::make('document_type')
                            ->label('Tipo de Documento')
                            ->options([
                                'CC' => 'Cédula de Ciudadanía (CC)',
                                'NIT' => 'Número de Identificación Tributaria (NIT)',
                                'CE' => 'Cédula de Extranjería (CE)',
                                'RUT' => 'RUT',
                            ])
                            ->default('CC')
                            ->required(),

                        Forms\Components\TextInput::make('document')
                            ->label('Número de Documento / NIT')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->placeholder('Ej: 900123456-1'),

                        Forms\Components\TextInput::make('phone')
                            ->label('Teléfono / WhatsApp')
                            ->tel()
                            ->maxLength(30)
                            ->placeholder('Ej: 3001234567'),

                        Forms\Components\TextInput::make('email')
                            ->label('Correo Electrónico')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('cliente@ejemplo.com'),

                        Forms\Components\TextInput::make('address')
                            ->label('Dirección de Entrega / Despacho')
                            ->maxLength(255)
                            ->placeholder('Ej: Calle 45 # 12-30 Bodega 4'),

                        Forms\Components\TextInput::make('city')
                            ->label('Ciudad / Municipio')
                            ->default('Local')
                            ->maxLength(100),
                    ]),

                // -------------------------------------------------------------
                // SECCIÓN 2: CARTERA Y CUPOS FINANCIEROS
                // -------------------------------------------------------------
                Forms\Components\Section::make('Condiciones de Crédito y Cartera')
                    ->description('Límites financieros para ventas fiadas o a crédito en la ferretería')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('credit_limit')
                            ->label('Cupo de Crédito Autorizado')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('$')
                            ->default(0)
                            ->disabled(fn (): bool => ! (auth()->user()?->hasRole('admin') ?? false))
                            ->helperText('Monto total máximo que la ferretería le permite fiar (Solo editable por el Administrador)'),

                        Forms\Components\TextInput::make('current_debt')
                            ->label('Deuda Actual')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->disabled() // La deuda NO se edita a mano; se altera con ventas y recibos de caja
                            ->helperText('Saldo acumulado pendiente de pago (calculado automáticamente)'),

                        // Indicador informativo en tiempo real del cupo disponible:
                        Forms\Components\Placeholder::make('available_credit_display')
                            ->label('Cupo Disponible Restante')
                            ->content(function (?Customer $record): string {
                                if (! $record) {
                                    return '$0 (Se calculará al crear)';
                                }
                                $available = $record->available_credit;
                                $formatted = '$'.number_format($available, 0, ',', '.');

                                if ($record->credit_limit > 0 && $available <= 0) {
                                    return "⚠️ {$formatted} (CUPO AGOTADO)";
                                }

                                return "✅ {$formatted}";
                            })
                            ->helperText('Diferencia entre cupo autorizado y deuda actual'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Cliente Activo')
                            ->default(true)
                            ->columnSpanFull()
                            ->helperText('Desactiva para bloquear nuevas compras a este cliente por mora o decisión comercial'),
                    ]),

                // -------------------------------------------------------------
                // SECCIÓN 3: PROTECCIÓN DE DATOS (HABEAS DATA - LEY 1581 DE 2012)
                // -------------------------------------------------------------
                Forms\Components\Section::make('Protección de Datos Personales (Habeas Data)')
                    ->description('Cumplimiento legal colombiano para recolección y custodia de datos de clientes (Ley 1581 de 2012)')
                    ->columns(1)
                    ->schema([
                        /**
                         * ¿CÓMO FUNCIONA dehydrated(false)?
                         * Este campo NO existe como columna directa en la tabla `customers`.
                         * Al poner dehydrated(false), Filament no intenta guardarlo en `customers`.
                         * En su lugar, cuando el usuario guarda, la página CreateCustomer o EditCustomer
                         * intercepta el valor e inserta un registro inmutable en la tabla `consent_logs`
                         * con la IP, timestamp y versión legal del contrato.
                         */
                        Forms\Components\Checkbox::make('authorize_data_processing')
                            ->label('El cliente autoriza expresamente el tratamiento de sus datos personales')
                            ->helperText('Autorización informada para fines de facturación, cobranza, avisos de despacho y comerciales según la política de privacidad de la ferretería.')
                            ->dehydrated(false)
                            ->default(fn (?Customer $record): bool => $record?->has_consented ?? false),

                        Forms\Components\Placeholder::make('consent_audit_info')
                            ->label('Estado de Auditoría Legal')
                            ->content(function (?Customer $record): string {
                                if (! $record) {
                                    return 'El registro de consentimiento se creará al guardar el cliente.';
                                }

                                $latestConsent = $record->latestConsentLog;
                                if (! $latestConsent) {
                                    return '⚠️ Sin consentimiento digital registrado aún.';
                                }

                                return sprintf(
                                    '🛡️ Autorizado el %s (Versión: %s | Canal: %s | IP: %s)',
                                    $latestConsent->consented_at?->format('d/m/Y H:i') ?? 'N/A',
                                    $latestConsent->policy_version,
                                    $latestConsent->channel,
                                    $latestConsent->ip_address ?? 'Local'
                                );
                            })
                            ->visible(fn (?Customer $record): bool => $record !== null),
                    ]),
            ]);
    }

    /**
     * MÉTODO table(): Define la tabla interactiva de clientes.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('document')
                    ->label('Documento')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre / Razón Social')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable(),

                Tables\Columns\TextColumn::make('priceList.name')
                    ->label('Lista Asignada')
                    ->badge()
                    ->color('info')
                    ->placeholder('General / Detal'),

                Tables\Columns\TextColumn::make('credit_limit')
                    ->label('Cupo Total')
                    ->money('COP', locale: 'es_CO')
                    ->sortable(),

                Tables\Columns\TextColumn::make('current_debt')
                    ->label('Deuda')
                    ->money('COP', locale: 'es_CO')
                    ->sortable()
                    ->color(fn (Customer $record): string => $record->current_debt > 0 ? 'danger' : 'gray'),

                // Columna calculada: Cupo disponible
                Tables\Columns\TextColumn::make('available_credit')
                    ->label('Cupo Disponible')
                    ->money('COP', locale: 'es_CO')
                    ->sortable(false)
                    ->color(fn (Customer $record): string => ($record->credit_limit > 0 && $record->available_credit <= 0) ? 'danger' : 'success')
                    ->weight('bold'),

                // Columna de Habeas Data: Escudo verde si autorizó, advertencia si no
                Tables\Columns\IconColumn::make('has_consented')
                    ->label('Habeas Data')
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-shield-exclamation')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->tooltip(fn (Customer $record): string => $record->has_consented ? 'Autorización Ley 1581 registrada' : 'Sin autorización registrada'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->placeholder('Todos los clientes')
                    ->trueLabel('Solo activos')
                    ->falseLabel('Solo inactivos'),

                Tables\Filters\Filter::make('with_debt')
                    ->label('Clientes con Deuda')
                    ->query(fn ($query) => $query->where('current_debt', '>', 0)),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Customer $record, Tables\Actions\DeleteAction $action) {
                        if ((float) $record->current_debt > 0) {
                            Notification::make()
                                ->title('No se puede eliminar el cliente')
                                ->body('El cliente tiene una deuda activa pendiente. Cancele el saldo antes de eliminarlo.')
                                ->danger()
                                ->send();

                            $action->halt();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->hasRole('admin') ?? false)
                        ->action(function (Collection $records) {
                            $deletable = $records->filter(fn (Customer $customer) => (float) $customer->current_debt <= 0);
                            $withDebt = $records->filter(fn (Customer $customer) => (float) $customer->current_debt > 0);

                            $deletable->each->delete();

                            if ($withDebt->isNotEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Eliminación parcial de clientes')
                                    ->body("Se eliminaron {$deletable->count()} cliente(s). Se protegieron {$withDebt->count()} cliente(s) con saldo de deuda pendiente.")
                                    ->send();
                            } else {
                                Notification::make()
                                    ->success()
                                    ->title('Clientes eliminados')
                                    ->body("{$deletable->count()} cliente(s) eliminados correctamente.")
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // Aquí se conectarán en fases posteriores los historiales de ventas o cotizaciones
        ];
    }

    /**
     * Define las rutas internas del panel para este recurso:
     * /admin/customers          -> Listar clientes
     * /admin/customers/create   -> Formulario nuevo cliente
     * /admin/customers/{id}/edit -> Formulario editar cliente
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
