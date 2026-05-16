<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Product::create([
            'libelle' => 'FCP Kori Sérénité',
            'description' => 'Idéal pour une épargne de précaution avec un risque maîtrisé. Investissement prudent et stable.',
            'vl' => 11372.18,
            'seuil_minimum' => 100000,
        ]);

       /*  Product::create([
            'libelle' => 'FCP Croissance',
            'description' => 'Optimisez vos rendements sur le long terme via une allocation diversifiée. Performance dynamique.',
            'vl' => 980.2500,
            'seuil_minimum' => 100000,
        ]);

        Product::create([
            'libelle' => 'FCP Horizon 2030',
            'description' => 'Un fonds dynamique pour préparer vos projets d\'avenir avec un horizon de placement long.',
            'vl' => 1560.1000,
            'seuil_minimum' => 250000,
        ]); */
    }
}
