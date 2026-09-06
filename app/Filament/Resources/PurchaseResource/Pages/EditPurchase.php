<?php

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Filament\Resources\PurchaseResource;
use App\Services\KardexService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPurchase extends EditRecord
{
    protected static string $resource = PurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->hidden(fn (): bool => $this->record->status === 'completed'),
        ];
    }

    /**
     * Si la compra estaba 'pending' y ahora pasa a 'completed', procesa el Kardex.
     */
    protected function afterSave(): void
    {
        if ($this->record->status === 'completed' && $this->record->wasChanged('status')) {
            KardexService::processPurchase($this->record);
        }
    }
}
