<?php

namespace App\Filament\Resources\CountryResource\Pages;

use App\Filament\Resources\CountryResource;
use App\Models\Country;
use App\Models\City;
use Database\Seeders\CountryCitySeeder;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListCountries extends ListRecords
{
    protected static string $resource = CountryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('seedGeo')
                ->label('Initialiser Pays & Villes par défaut')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Alimenter le référentiel des Pays et Villes')
                ->modalDescription('Cette action va initialiser les pays mondiaux (zone CEMAC prioritaire) ainsi que les principales villes.')
                ->action(function () {
                    $seeder = new CountryCitySeeder();
                    $seeder->run();

                    Notification::make()
                        ->title('Référentiel alimenté avec succès')
                        ->body(Country::count() . ' pays et ' . City::count() . ' villes sont maintenant enregistrés.')
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
