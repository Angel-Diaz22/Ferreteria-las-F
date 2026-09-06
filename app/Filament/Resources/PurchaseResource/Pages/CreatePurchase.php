<?php

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\PurchaseResource;
use App\Services\KardexService;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchase extends CreateRecord
{
    protected static string $resource = PurchaseResource::class;

    /**
     * GANCHO POSTERIOR A LA CREACIÓN:
     * Cuando la compra se guarda exitosamente, si su estado es 'completed',
     * llamamos inmediatamente a KardexService para actualizar existencias y costos.
     */
    protected function afterCreate(): void
    {
        if ($this->record->status === 'completed') {
            KardexService::processPurchase($this->record);
        }
    }
}
