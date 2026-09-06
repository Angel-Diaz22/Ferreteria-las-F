<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Pages\PosTerminal;
use App\Filament\Resources\SaleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

/**
 * ============================================================================
 * PÁGINA: ListSales (Listado del Historial de Ventas)
 * ============================================================================
 * Muestra el listado cronológico de comprobantes emitidos.
 * En lugar de un botón tradicional de "Crear Venta" (que abriría un formulario vacío),
 * proporciona un acceso directo a la Terminal POS en vivo.
 */
class ListSales extends ListRecords
{
    protected static string $resource = SaleResource::class;

    public function getTitle(): string
    {
        return 'Historial de Ventas';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('open_pos')
                ->label('Ir a Terminal POS')
                ->icon('heroicon-o-computer-desktop')
                ->color('success')
                ->url(PosTerminal::getUrl()),
        ];
    }
}
