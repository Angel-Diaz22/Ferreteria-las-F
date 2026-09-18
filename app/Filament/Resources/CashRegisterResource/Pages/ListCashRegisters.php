<?php

namespace App\Filament\Resources\CashRegisterResource\Pages;

use App\Filament\Resources\CashRegisterResource;
use App\Models\CashRegister;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCashRegisters extends ListRecords
{
    protected static string $resource = CashRegisterResource::class;

    protected function getHeaderActions(): array
    {
        $count = CashRegister::count();
        $limitReached = $count >= 10;

        return [
            Actions\CreateAction::make()
                ->label('Nueva Caja Registradora')
                ->disabled($limitReached)
                ->tooltip($limitReached ? 'Límite máximo de 10 cajas registradoras alcanzado.' : null),
        ];
    }
}
