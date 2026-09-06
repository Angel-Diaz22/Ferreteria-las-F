<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    /**
     * HOOK afterCreate(): Se ejecuta justo después de que Eloquent guarda
     * el nuevo cliente en la tabla `customers`.
     *
     * ¿POR QUÉ AQUÍ?
     * La casilla `authorize_data_processing` fue marcada como ->dehydrated(false)
     * en el formulario para evitar que Laravel intentara guardarla en una columna
     * que no existe en `customers`.
     *
     * En este gancho tomamos ese valor y creamos un registro legal inmutable
     * en la tabla `consent_logs` con la IP del usuario, navegador y timestamp.
     */
    protected function afterCreate(): void
    {
        if ($this->data['authorize_data_processing'] ?? false) {
            $this->record->consentLogs()->create([
                'subject_type' => 'customer',
                'policy_version' => 'v1.0',
                'consented_at' => now(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'channel' => 'pos_terminal',
                'notes' => 'Autorización Habeas Data (Ley 1581) otorgada en el registro del cliente.',
            ]);
        }
    }
}
