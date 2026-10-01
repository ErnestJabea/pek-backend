<?php

namespace App\Filament\Resources\AppContentResource\Pages;

use App\Filament\Resources\AppContentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAppContents extends ListRecords
{
    protected static string $resource = AppContentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}