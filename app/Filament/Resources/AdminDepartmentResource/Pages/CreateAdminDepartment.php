<?php
namespace App\Filament\Resources\AdminDepartmentResource\Pages;
class CreateAdminDepartment extends \Filament\Resources\Pages\CreateRecord
{
    protected static string $resource = \App\Filament\Resources\AdminDepartmentResource::class;
    protected ?bool $hasDatabaseTransactions = true;
}
