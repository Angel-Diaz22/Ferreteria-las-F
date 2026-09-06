<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'Usuario';

    protected static ?string $pluralModelLabel = 'Usuarios y Permisos';

    protected static ?string $navigationLabel = 'Usuarios y Permisos';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('users.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('users.manage') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('users.manage') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('users.manage') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Ficha del Usuario')
                    ->columnSpanFull()
                    ->tabs([
                        // PESTAÑA 1: DATOS GENERALES Y CREDENCIALES
                        Forms\Components\Tabs\Tab::make('Datos de la Cuenta')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Forms\Components\Section::make('Información del Perfil')
                                    ->description('Datos personales y credenciales de acceso al sistema')
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('name')
                                                    ->label('Nombre Completo')
                                                    ->placeholder('Ej: Don Fernando Gómez')
                                                    ->required()
                                                    ->maxLength(255),

                                                Forms\Components\TextInput::make('email')
                                                    ->label('Correo Electrónico')
                                                    ->placeholder('usuario@ferreteria.com')
                                                    ->email()
                                                    ->required()
                                                    ->unique(ignoreRecord: true)
                                                    ->maxLength(255),

                                                Forms\Components\TextInput::make('password')
                                                    ->label('Contraseña de Acceso')
                                                    ->password()
                                                    ->revealable()
                                                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                                                    ->dehydrated(fn ($state) => filled($state))
                                                    ->required(fn (string $operation): bool => $operation === 'create')
                                                    ->helperText(fn (string $operation): string => $operation === 'edit'
                                                        ? 'Deja este campo vacío para conservar la contraseña actual.'
                                                        : 'Mínimo 8 caracteres.'),

                                                Forms\Components\Select::make('roles')
                                                    ->label('Rol Principal del Sistema')
                                                    ->relationship('roles', 'name')
                                                    ->preload()
                                                    ->native(false)
                                                    ->helperText('El Administrador tiene acceso total. Otros roles heredan permisos base y pueden personalizarse en la pestaña siguiente.'),
                                            ]),

                                        Forms\Components\Toggle::make('is_active')
                                            ->label('Usuario Activo en el Sistema')
                                            ->default(true)
                                            ->helperText('Al desactivar al usuario, se bloqueará su inicio de sesión de inmediato sin alterar los registros históricos que haya emitido.'),
                                    ]),
                            ]),

                        // PESTAÑA 2: MATRIZ DE PERMISOS POR MÓDULO
                        Forms\Components\Tabs\Tab::make('Permisos y Visibilidad')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Forms\Components\Placeholder::make('permissions_help')
                                    ->label('Control Granular de Módulos')
                                    ->content('Marca las casillas correspondientes a lo que deseas que este usuario pueda ver y utilizar en la aplicación. Si el usuario cuenta con el rol Administrador, tendrá acceso total de forma automática.'),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        // 1. VENTAS Y FACTURACIÓN
                                        Forms\Components\Section::make('Punto de Venta y Clientes')
                                            ->icon('heroicon-o-shopping-cart')
                                            ->collapsible()
                                            ->schema([
                                                Forms\Components\CheckboxList::make('sales_permissions')
                                                    ->hiddenLabel()
                                                    ->options([
                                                        'pos.access' => 'Terminal POS (Caja de mostrador y facturación rápida)',
                                                        'sales.view' => 'Historial de Ventas (Ver listado de facturas y recibos)',
                                                        'sales.cancel' => 'Anular Facturas (Permiso especial de anulación y reintegro)',
                                                        'customers.view' => 'Directorio de Clientes (Consultar créditos y habeas data)',
                                                        'quotes.view' => 'Cotizaciones (Crear presupuestos y convertirlos a venta)',
                                                    ])
                                                    ->bulkToggleable(),
                                            ]),

                                        // 2. CATÁLOGO E INVENTARIO
                                        Forms\Components\Section::make('Catálogo e Inventario')
                                            ->icon('heroicon-o-archive-box')
                                            ->collapsible()
                                            ->schema([
                                                Forms\Components\CheckboxList::make('inventory_permissions')
                                                    ->hiddenLabel()
                                                    ->options([
                                                        'products.view' => 'Ver Catálogo de Productos (Lista de artículos y stock disponible)',
                                                        'products.manage' => 'Crear / Modificar Productos (Editar datos técnicos y precios)',
                                                        'products.view_cost' => 'Ver Costo y Margen de Ganancia (Dato confidencial)',
                                                        'categories.view' => 'Categorías de Productos (Familias de artículos)',
                                                        'brands.view' => 'Marcas y Fabricantes (Stanley, DeWalt, Pavco, etc.)',
                                                        'price_lists.view' => 'Listas de Precios (Detal, Mayorista, Constructor)',
                                                        'inventory.view' => 'Kardex de Movimientos (Auditoría física de entradas y salidas)',
                                                        'warehouses.view' => 'Bodegas y Almacenes (Administrar almacenes físicos)',
                                                    ])
                                                    ->bulkToggleable(),
                                            ]),

                                        // 3. COMPRAS Y PROVEEDORES
                                        Forms\Components\Section::make('Compras y Proveedores')
                                            ->icon('heroicon-o-truck')
                                            ->collapsible()
                                            ->schema([
                                                Forms\Components\CheckboxList::make('purchases_permissions')
                                                    ->hiddenLabel()
                                                    ->options([
                                                        'suppliers.view' => 'Directorio de Proveedores (Contactos comerciales y NIT)',
                                                        'purchases.view' => 'Compras a Proveedores (Registrar facturas de mercancía)',
                                                    ])
                                                    ->bulkToggleable(),
                                            ]),

                                        // 4. INFORMES Y ESTADÍSTICAS
                                        Forms\Components\Section::make('Informes y Estadísticas')
                                            ->icon('heroicon-o-chart-bar')
                                            ->collapsible()
                                            ->schema([
                                                Forms\Components\CheckboxList::make('reports_permissions')
                                                    ->hiddenLabel()
                                                    ->options([
                                                        'reports.view' => 'Informes y Estadísticas (Gráficas de ventas y reportes PDF)',
                                                    ])
                                                    ->bulkToggleable(),
                                            ]),

                                        // 5. CONFIGURACIÓN DEL SISTEMA
                                        Forms\Components\Section::make('Configuración del Sistema')
                                            ->icon('heroicon-o-cog-6-tooth')
                                            ->collapsible()
                                            ->columnSpanFull()
                                            ->schema([
                                                Forms\Components\CheckboxList::make('system_permissions')
                                                    ->hiddenLabel()
                                                    ->options([
                                                        'users.manage' => 'Gestión de Usuarios y Permisos (Crear cuentas y configurar accesos)',
                                                    ])
                                                    ->bulkToggleable(),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre Completo')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Rol Asignado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'danger',
                        'cashier' => 'success',
                        'auditor' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'admin' => 'Administrador',
                        'cashier' => 'Cajero',
                        'auditor' => 'Auditor',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('Permisos Específicos')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Estado')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha Registro')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->label('Filtrar por Rol'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Estado del Usuario')
                    ->trueLabel('Solo Activos')
                    ->falseLabel('Solo Inactivos'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (User $record, Tables\Actions\DeleteAction $action) {
                        if ($record->id === auth()->id()) {
                            Notification::make()
                                ->danger()
                                ->title('Operación no permitida')
                                ->body('No puedes eliminar tu propio usuario en sesión activa.')
                                ->send();

                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
