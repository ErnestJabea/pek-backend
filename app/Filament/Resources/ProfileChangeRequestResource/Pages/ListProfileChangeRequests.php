<?php

namespace App\Filament\Resources\ProfileChangeRequestResource\Pages;

use App\Filament\Resources\ProfileChangeRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListProfileChangeRequests extends ListRecords
{
    protected static string $resource = ProfileChangeRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
