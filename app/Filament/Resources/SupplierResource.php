<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * ============================================================================
 * RECURSO FILAMENT: SupplierResource (Gestión de Proveedores)
 * ============================================================================
 * Maneja los proveedores de la ferretería (distribuidores mayoristas, marcas directas).
 * SEGURIDAD: Solo visible para el rol 'admin'. Un cajero no tiene acceso
 * a los contratos, datos ni compras de los proveedores.
 */
class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $modelLabel = 'Proveedor';

    protected static ?string $pluralModelLabel = 'Proveedores';

    protected static ?string $navigationGroup = 'Compras y Proveedores';

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?int $navigationSort = 1;

    /**
     * POLÍTICA DE SEGURIDAD POR ROL:
     * Si no es 'admin', el recurso ni siquiera aparece en el menú lateral
     * y las rutas directas arrojan error 403 Forbidden.
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('suppliers.view') ?? false;
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
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos del Proveedor')
                    ->description('Información legal, tributaria y de contacto')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Razón Social / Nombre Comercial')
                                    ->placeholder('Ej: Distribuciones Ferreteras S.A.S.')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('nit')
                                    ->label('NIT / Identificación Tributaria')
                                    ->placeholder('Ej: 900.123.456-7')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(50),

                                Forms\Components\TextInput::make('contact_person')
                                    ->label('Asesor Comercial / Contacto')
                                    ->placeholder('Ej: Carlos Gómez (Vendedor)')
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('phone')
                                    ->label('Teléfono / WhatsApp Pedidos')
                                    ->tel()
                                    ->maxLength(30),

                                Forms\Components\TextInput::make('email')
                                    ->label('Correo de Pedidos / Facturación')
                                    ->email()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('address')
                                    ->label('Dirección o Sede')
                                    ->maxLength(255),
                            ]),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Proveedor Activo')
                            ->default(true)
                            ->helperText('Desactivar si el proveedor ya no despacha mercancía'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nit')
                    ->label('NIT')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Proveedor')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('contact_person')
                    ->label('Contacto')
                    ->searchable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable(),

                Tables\Columns\TextColumn::make('purchases_count')
                    ->label('Compras Realizadas')
                    ->counts('purchases')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->trueLabel('Solo activos')
                    ->falseLabel('Solo inactivos'),
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
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
