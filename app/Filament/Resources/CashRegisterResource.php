<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CashRegisterResource\Pages;
use App\Models\CashRegister;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class CashRegisterResource extends Resource
{
    protected static ?string $model = CashRegister::class;

    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?string $navigationLabel = 'Cajas Registradoras';

    protected static ?string $modelLabel = 'Caja Registradora';

    protected static ?string $pluralModelLabel = 'Cajas Registradoras';

    protected static ?int $navigationSort = 10;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return (auth()->user()?->hasRole('admin') ?? false) && CashRegister::count() < 10;
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
                Section::make('Configuración del Puesto / Caja')
                    ->description('Defina el rol operativo, ubicación y comportamiento de la caja en el terminal POS.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nombre de la Caja')
                                    ->placeholder('Ej: Caja 4 - Pasillo Tornillería')
                                    ->required()
                                    ->maxLength(100),

                                Forms\Components\Select::make('type')
                                    ->label('Tipo y Rol Operativo')
                                    ->options(CashRegister::getTypeOptions())
                                    ->default(CashRegister::TYPE_COUNTER)
                                    ->required()
                                    ->native(false)
                                    ->helperText('Mostrador: Pedidos sin cobro directo. Recaudadora: Cobra y valida pagos. Híbrida: Cotiza y cobra directo.'),

                                Forms\Components\Select::make('warehouse_id')
                                    ->label('Bodega Asociada (Opcional)')
                                    ->relationship('warehouse', 'name')
                                    ->nullable()
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Las cajas pueden operar de forma independiente a las bodegas.'),

                                Forms\Components\TextInput::make('display_order')
                                    ->label('Orden de Visualización')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->helperText('Orden en que se presenta este puesto en la lista del POS.'),

                                Forms\Components\Toggle::make('is_main')
                                    ->label('Caja Principal del Administrador')
                                    ->helperText('El Administrador entra automáticamente a esta caja al abrir el POS. Solo una caja puede ser principal.')
                                    ->default(false),

                                Forms\Components\Toggle::make('is_active')
                                    ->label('Caja Activa para el POS')
                                    ->helperText('Si se desactiva, los cajeros no podrán seleccionar este puesto.')
                                    ->default(true),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->label('Ubicación Física o Notas')
                            ->placeholder('Ej: Computador #2 frente a la báscula de clavos')
                            ->rows(2)
                            ->nullable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('display_order', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('display_order')
                    ->label('Orden')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre del Puesto')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo de Caja')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        CashRegister::TYPE_COUNTER => 'Mostrador (Atención)',
                        CashRegister::TYPE_CASHIER => 'Recaudadora (Cobro)',
                        CashRegister::TYPE_HYBRID => 'Híbrida (Venta Directa)',
                        default => 'Mostrador (Atención)',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        CashRegister::TYPE_COUNTER => 'info',
                        CashRegister::TYPE_CASHIER => 'success',
                        CashRegister::TYPE_HYBRID => 'warning',
                        default => 'info',
                    }),

                Tables\Columns\IconColumn::make('is_main')
                    ->label('Principal')
                    ->boolean()
                    ->trueIcon('heroicon-s-star')
                    ->falseIcon(null)
                    ->trueColor('warning')
                    ->alignCenter()
                    ->tooltip(fn (CashRegister $record) => $record->is_main ? 'Caja Principal de Administración' : null),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Activa'),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Bodega')
                    ->placeholder('Independiente')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registrada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Filtrar por Tipo')
                    ->options(CashRegister::getTypeOptions()),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->placeholder('Todas')
                    ->trueLabel('Solo Activas')
                    ->falseLabel('Solo Inactivas'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (CashRegister $record, Tables\Actions\DeleteAction $action) {
                        if ($record->shifts()->exists()) {
                            Notification::make()
                                ->title('No se puede eliminar la caja')
                                ->body('Esta caja cuenta con turnos o registros contables asociados. Desactívela en su lugar para mantener la trazabilidad.')
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
                            $deletable = $records->filter(fn (CashRegister $r) => ! $r->shifts()->exists());
                            $blocked = $records->diff($deletable);

                            $deletable->each->delete();

                            if ($blocked->isNotEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Eliminación parcial de cajas')
                                    ->body("Se eliminaron {$deletable->count()} caja(s). Se omitieron {$blocked->count()} caja(s) con historial de turnos.")
                                    ->send();
                            } else {
                                Notification::make()
                                    ->success()
                                    ->title('Cajas eliminadas')
                                    ->body("{$deletable->count()} caja(s) eliminadas correctamente.")
                                    ->send();
                            }
                        }),
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
            'index' => Pages\ListCashRegisters::route('/'),
            'create' => Pages\CreateCashRegister::route('/create'),
            'edit' => Pages\EditCashRegister::route('/{record}/edit'),
        ];
    }
}
