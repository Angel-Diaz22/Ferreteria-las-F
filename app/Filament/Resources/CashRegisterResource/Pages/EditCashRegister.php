<?php

namespace App\Filament\Resources\CashRegisterResource\Pages;

use App\Filament\Resources\CashRegisterResource;
use App\Models\CashRegister;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCashRegister extends EditRecord
{
    protected static string $resource = CashRegisterResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function (CashRegister $record, Actions\DeleteAction $action) {
                    if ($record->shifts()->exists()) {
                        Notification::make()
                            ->title('No se puede eliminar la caja')
                            ->body('Esta caja cuenta con turnos o registros contables asociados. Desactívela en su lugar para mantener la trazabilidad.')
                            ->danger()
                            ->send();

                        $action->halt();
                    }
                }),
        ];
    }
}
