<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        $permissions = array_merge(
            $this->data['sales_permissions'] ?? [],
            $this->data['inventory_permissions'] ?? [],
            $this->data['purchases_permissions'] ?? [],
            $this->data['reports_permissions'] ?? [],
            $this->data['system_permissions'] ?? []
        );

        $this->record->syncPermissions($permissions);
    }
}
