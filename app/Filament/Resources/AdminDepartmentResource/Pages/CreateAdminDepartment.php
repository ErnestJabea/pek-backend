<?php

namespace App\Filament\Resources\AdminDepartmentResource\Pages;

use App\Filament\Resources\AdminDepartmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdminDepartment extends CreateRecord
{
    protected static string $resource = AdminDepartmentResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function mutateFormDataBeforeCreate(array $data): array
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
