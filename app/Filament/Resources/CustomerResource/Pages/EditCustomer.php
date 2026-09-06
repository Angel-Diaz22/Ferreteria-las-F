<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * HOOK mutateFormDataBeforeFill():
     * Se ejecuta al cargar el formulario de edición.
     * Como el checkbox 'authorize_data_processing' no es columna física de `customers`,
     * aquí le inyectamos true si ya existe al menos un registro en `consent_logs`.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['authorize_data_processing'] = $this->record->has_consented;

        return $data;
    }

    /**
     * HOOK afterSave():
     * Si el cliente no tenía consentimiento previo y el usuario marca la casilla,
     * creamos la evidencia digital en `consent_logs`.
     */
    protected function afterSave(): void
    {
        if (($this->data['authorize_data_processing'] ?? false) && ! $this->record->has_consented) {
            $this->record->consentLogs()->create([
                'subject_type' => 'customer',
                'policy_version' => 'v1.0',
                'consented_at' => now(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'channel' => 'pos_terminal',
                'notes' => 'Autorización Habeas Data (Ley 1581) otorgada en actualización de ficha.',
            ]);
        }
    }
}
