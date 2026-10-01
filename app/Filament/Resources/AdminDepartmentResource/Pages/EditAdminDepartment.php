<?php

namespace App\Filament\Resources\AdminDepartmentResource\Pages;

use App\Filament\Resources\AdminDepartmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAdminDepartment extends EditRecord
{
    protected static string $resource = AdminDepartmentResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $currentPermissions = $data['permissions'] ?? [];
        $modules = AdminDepartmentResource::getPermissionsByModule();
        $modulePermissions = [];

        foreach ($modules as $key => $module) {
            $matched = [];
            foreach (array_keys($module['permissions']) as $perm) {
                $altPerm = str_replace('::', '_', $perm);
                if (in_array($perm, $currentPermissions, true) || in_array($altPerm, $currentPermissions, true)) {
                    $matched[] = $perm;
                }
            }
            $modulePermissions[$key] = $matched;
        }

        $data['module_permissions'] = $modulePermissions;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $all = [];
        if (! empty($data['module_permissions']) && is_array($data['module_permissions'])) {
            foreach ($data['module_permissions'] as $perms) {
                if (is_array($perms)) {
                    $all = array_merge($all, $perms);
                }
            }
        }

        $data['permissions'] = array_values(array_unique($all));
        unset($data['module_permissions']);

        return $data;
    }
}
