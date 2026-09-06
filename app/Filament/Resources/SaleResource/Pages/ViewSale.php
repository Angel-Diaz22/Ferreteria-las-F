<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use App\Models\Sale;
use App\Services\PosService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * ============================================================================
 * PÁGINA: ViewSale (Consulta Detallada de Comprobante)
 * ============================================================================
 * Permite examinar la información completa de una venta emitida.
 * Desde esta vista, el usuario puede reimprimir el ticket térmico (80mm),
 * generar el PDF oficial o anular la venta si tiene privilegios de administrador.
 */
class ViewSale extends ViewRecord
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Sale $sale */
        $sale = $this->getRecord();
        $canCancel = auth()->user()?->can('sales.cancel') ?? false;

        return [
            // Botón para imprimir ticket térmico
            Actions\Action::make('print_ticket')
                ->label('Imprimir Ticket')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(route('sales.receipt', $sale))
                ->openUrlInNewTab(),

            // Botón para descargar PDF
            Actions\Action::make('download_pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(route('sales.pdf', $sale))
                ->openUrlInNewTab(),

            // Acción para anular venta (requiere permiso sales.cancel)
            Actions\Action::make('void_sale')
                ->label('Anular Venta')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => $sale->status !== 'cancelled' && $canCancel)
                ->requiresConfirmation()
                ->modalHeading("Anular Venta #{$sale->invoice_number}")
                ->modalDescription('Esta acción devolverá los artículos al inventario físico en el Kardex y descontará el saldo al cliente si fue a crédito.')
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('Motivo de Anulación')
                        ->placeholder('Ingrese la causa justificada de la anulación...')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    /** @var Sale $record */
                    $record = $this->getRecord();

                    PosService::voidSale($record, $data['reason'], auth()->id());

                    Notification::make()
                        ->title('Venta Anulada')
                        ->body('Se reintegraron los productos al inventario satisfactoriamente.')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'notes']);
                }),
        ];
    }
}
