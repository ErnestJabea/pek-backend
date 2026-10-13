<?php

namespace App\Filament\Resources\CityResource\Pages;

use App\Filament\Resources\CityResource;
use App\Models\Country;
use App\Models\City;
use Database\Seeders\CountryCitySeeder;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListCities extends ListRecords
{
    protected static string $resource = CityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('seedGeo')
                ->label('Initialiser Villes & Pays par défaut')
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
                        ->body(City::count() . ' villes et ' . Country::count() . ' pays sont maintenant enregistrés.')
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
