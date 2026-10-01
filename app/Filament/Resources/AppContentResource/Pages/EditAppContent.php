<?php

namespace App\Filament\Resources\AppContentResource\Pages;

use App\Filament\Resources\AppContentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAppContent extends EditRecord
{
    protected static string $resource = AppContentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}