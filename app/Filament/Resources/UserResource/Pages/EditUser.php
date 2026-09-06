<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $userPermissions = $this->record->permissions->pluck('name')->toArray();

        $data['sales_permissions'] = array_values(array_intersect($userPermissions, [
            'pos.access', 'sales.view', 'sales.cancel', 'customers.view', 'quotes.view',
        ]));
        $data['inventory_permissions'] = array_values(array_intersect($userPermissions, [
            'products.view', 'products.manage', 'products.view_cost', 'categories.view', 'brands.view', 'price_lists.view', 'inventory.view', 'warehouses.view',
        ]));
        $data['purchases_permissions'] = array_values(array_intersect($userPermissions, [
            'suppliers.view', 'purchases.view',
        ]));
        $data['reports_permissions'] = array_values(array_intersect($userPermissions, [
            'reports.view',
        ]));
        $data['system_permissions'] = array_values(array_intersect($userPermissions, [
            'users.manage',
        ]));

        return $data;
    }

    protected function afterSave(): void
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

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function (Actions\DeleteAction $action) {
                    if ($this->record->id === auth()->id()) {
                        Notification::make()
                            ->danger()
                            ->title('Operación no permitida')
                            ->body('No puedes eliminar tu propio usuario en sesión activa.')
                            ->send();

                        $action->cancel();
                    }
                }),
        ];
    }
}
