<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    protected ?bool $hasDatabaseTransactions = true;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (auth()->user()->hasRole('super_admin')) {
            if (array_key_exists('admin_department_id', $data)) {
                $this->record->forceFill(['admin_department_id' => $data['admin_department_id'] ?: null]);
            }
        } else {
            unset($data['role'], $data['roles']);
        }
        unset($data['admin_department_id']);
        return $data;
    }

}
