<?php
namespace App\Filament\Resources\AdminDepartmentResource\Pages;
class EditAdminDepartment extends \Filament\Resources\Pages\EditRecord
{
    protected static string $resource = \App\Filament\Resources\AdminDepartmentResource::class;
    protected ?bool $hasDatabaseTransactions = true;
}
