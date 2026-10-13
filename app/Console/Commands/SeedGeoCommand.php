<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Database\Seeders\CountryCitySeeder;
use App\Models\Country;
use App\Models\City;

class SeedGeoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'geo:seed {--force : Forcer l\'exécution sans confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Alimente le référentiel des pays et des villes en base de données';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Début de l\'initialisation des pays et villes...');

        $seeder = new CountryCitySeeder();
        $seeder->run();

        $countriesCount = Country::count();
        $citiesCount = City::count();

        $this->info("Opération terminée avec succès :");
        $this->line(" - Pays enregistrés : <info>{$countriesCount}</info>");
        $this->line(" - Villes enregistrées : <info>{$citiesCount}</info>");

        return Command::SUCCESS;
    }
}
