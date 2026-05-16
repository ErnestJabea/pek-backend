<?php

namespace App\Filament\Resources\BankDetailResource\Pages;

use App\Filament\Resources\BankDetailResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBankDetails extends ListRecords
{
    protected static string $resource = BankDetailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
