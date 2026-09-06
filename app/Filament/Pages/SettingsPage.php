<?php

namespace App\Filament\Pages;

use App\Models\SystemSetting;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;

class SettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?string $navigationLabel = 'Ajustes del Sistema';

    protected static ?string $title = 'Configuración General y Tributaria';

    protected static ?int $navigationSort = 99;

    protected static string $view = 'filament.pages.settings-page';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'iva_enabled' => SystemSetting::isIvaEnabled(),
            'iva_percentage' => SystemSetting::getIvaRate(),
            'company_name' => SystemSetting::get('company_name', 'FERRETERIA LAS F'),
            'company_nit' => SystemSetting::get('company_nit', '7719126'),
            'company_regime' => SystemSetting::get('company_regime', 'NO RESPONSABLE DE IVA'),
            'company_address' => SystemSetting::get('company_address', 'CALLE 21 N. 56-74'),
            'company_phone' => SystemSetting::get('company_phone', '3142103651 Y 3053198658'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Régimen Tributario e Impuesto al Valor Agregado (IVA)')
                    ->description('Administre el comportamiento del IVA en ventas de mostrador y pedidos.')
                    ->icon('heroicon-o-receipt-percent')
                    ->schema([
                        Placeholder::make('tax_notice')
                            ->label('Estado Legal Actual:')
                            ->content(new HtmlString('
                                <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-900 flex items-start gap-2.5">
                                    <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                                    </svg>
                                    <div>
                                        <strong class="font-bold">Ferretería Las F está registrada como NO RESPONSABLE DE IVA.</strong><br>
                                        Por norma tributaria, las facturas y tirillas de mostrador no deben incluir sobrecosto de IVA. Mantenga esta opción desactivada para que el sistema cobre exactamente el precio de los artículos. Si en el futuro la empresa cambia de régimen tributario ante la DIAN, active el interruptor y el sistema comenzará a calcular y desglosar el IVA de forma automática.
                                    </div>
                                </div>
                            ')),

                        Toggle::make('iva_enabled')
                            ->label('Activar Cálculo y Cobro de IVA en Ventas (POS)')
                            ->helperText('Si está desactivado: no se calcula ni se muestra ningún valor de IVA en el terminal, pedidos ni recibos.')
                            ->default(false)
                            ->live(),

                        TextInput::make('iva_percentage')
                            ->label('Tasa de IVA Legal (%)')
                            ->numeric()
                            ->default(19.00)
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%')
                            ->helperText('Porcentaje estándar aplicable en caso de tener el IVA activado (ej. 19% en Colombia).')
                            ->visible(fn (Get $get) => (bool) $get('iva_enabled'))
                            ->required(fn (Get $get) => (bool) $get('iva_enabled')),
                    ]),

                Section::make('Información Institucional de la Empresa')
                    ->description('Datos comerciales que figuran en las tirillas de pago, reportes y cotizaciones.')
                    ->icon('heroicon-o-building-office')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('company_name')
                                    ->label('Razón Social / Nombre Comercial')
                                    ->required()
                                    ->maxLength(100),

                                TextInput::make('company_nit')
                                    ->label('NIT / Identificación Tributaria')
                                    ->required()
                                    ->maxLength(50),

                                TextInput::make('company_regime')
                                    ->label('Régimen Tributario DIAN')
                                    ->required()
                                    ->maxLength(100),

                                TextInput::make('company_address')
                                    ->label('Dirección del Establecimiento')
                                    ->required()
                                    ->maxLength(150),

                                TextInput::make('company_phone')
                                    ->label('Teléfonos de Contacto')
                                    ->required()
                                    ->maxLength(100),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        SystemSetting::set('iva_enabled', (bool) ($state['iva_enabled'] ?? false), 'boolean', 'tax');
        SystemSetting::set('iva_percentage', (float) ($state['iva_percentage'] ?? 19.00), 'float', 'tax');
        SystemSetting::set('company_name', (string) ($state['company_name'] ?? 'FERRETERIA LAS F'), 'string', 'company');
        SystemSetting::set('company_nit', (string) ($state['company_nit'] ?? '7719126'), 'string', 'company');
        SystemSetting::set('company_regime', (string) ($state['company_regime'] ?? 'NO RESPONSABLE DE IVA'), 'string', 'company');
        SystemSetting::set('company_address', (string) ($state['company_address'] ?? 'CALLE 21 N. 56-74'), 'string', 'company');
        SystemSetting::set('company_phone', (string) ($state['company_phone'] ?? '3142103651 Y 3053198658'), 'string', 'company');

        Notification::make()
            ->title('Configuración guardada exitosamente')
            ->body('Los parámetros del sistema y régimen tributario se han actualizado correctamente.')
            ->success()
            ->send();
    }
}
