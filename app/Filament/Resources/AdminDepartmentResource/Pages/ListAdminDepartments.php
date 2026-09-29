<?php
namespace App\Filament\Resources\AdminDepartmentResource\Pages;
class ListAdminDepartments extends \Filament\Resources\Pages\ListRecords
{
    protected static string $resource = \App\Filament\Resources\AdminDepartmentResource::class;
    protected function getHeaderActions(): array { return [\Filament\Actions\CreateAction::make()]; }
}
