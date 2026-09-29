<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
    protected ?bool $hasDatabaseTransactions = true;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $departmentId = null;
        if (auth()->user()->hasRole('super_admin')) {
            $departmentId = $data['admin_department_id'] ?? null;
        } else {
            $data['role'] = 'client';
            unset($data['roles']);
        }
        unset($data['admin_department_id']);
        $user = new \App\Models\User($data);
        $user->forceFill(['admin_department_id' => $departmentId ?: null])->save();
        return $user;
    }

}
