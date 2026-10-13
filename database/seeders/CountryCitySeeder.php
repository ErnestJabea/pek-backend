<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Country;
use App\Models\City;

class CountryCitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countriesPath = base_path('../fcp-mobile/src/data/countries.js');
        $citiesPath = base_path('../fcp-mobile/src/data/cities.js');

        $countriesData = [];
        if (file_exists($countriesPath)) {
            $content = file_get_contents($countriesPath);
            // Extraire le tableau JSON de "export const countries = [...]"
            if (preg_match('/export\s+const\s+countries\s*=\s*(\[.*?\]);/s', $content, $matches)) {
                $countriesData = json_decode($matches[1], true) ?? [];
            }
        }

        // Compléments de villes pour les principaux pays
        $manualCities = [
            'CM' => [
                'Douala', 'Yaoundé', 'Garoua', 'Bamenda', 'Maroua', 'Bafoussam', 'Ngaoundéré', 'Bertoua', 
                'Edéa', 'Loum', 'Kumba', 'Nkongsamba', 'Mbouda', 'Dschang', 'Foumban', 'Ebolowa', 'Guider', 
                'Meiganga', 'Yagoua', 'Kousséri', 'Kribi', 'Limbe', 'Limbé', 'Buea', 'Bafia', 'Sangmélima', 
                'Bangangté', 'Tibati', 'Bafut', 'Bali', 'Wum', 'Fontem', 'Melong', 'Manjo', 'Penja', 'Mbanga', 
                'Obala', 'Mbalmayo', 'Mfou', 'Akonolinga', 'Nanga Eboko', 'Monatélé', 'Batouri', 'Abong Mbang', 
                'Yokadouma', 'Bétaré-Oya', 'Garoua-Boulaï', 'Moloundou', 'Belabo', 'Ngaoundal', 'Bankim', 
                'Banyo', 'Tignère', 'Poli', 'Tcholliré', 'Rey-Bouba', 'Figuil', 'Pitoa', 'Lagdo', 'Kaélé', 
                'Mora', 'Mokolo', 'Meri', 'Bogo', 'Maga', 'Makary', 'Waza', 'Fotokol', 'Goulfey', 'Moundou', 
                'Eseka', 'Dizangué', 'Mouanko', 'Yabassi', 'Tiko', 'Muyuka', 'Tombel', 'Mamfe', 'Mundemba', 
                'Kumbo', 'Ndu', 'Nkambe', 'Mbengwi', 'Batibo', 'Fundong', 'Bandjoun', 'Baham', 'Batié', 
                'Bamendjou', 'Bazou', 'Tonga', 'Foumbot', 'Malantouen', 'Koutaba', 'Kouoptamo', 'Zoétélé', 
                'Meyomessala', 'Djoum', 'Campo', 'Lolodorf', 'Ambam', 'Kyé-Ossi'
            ],
            'TD' => [
                "N'Djamena", 'Moundou', 'Sarh', 'Abéché', 'Kélo', 'Koumra', 'Pala', 'Am Timan', 'Bongor', 'Mongo',
                'Doba', 'Ati', 'Laï', 'Mao', 'Faya-Largeau', 'Bitkine', 'Oum Hadjer', 'Goz Beïda'
            ],
            'CF' => [
                'Bangui', 'Bimbo', 'Bégoua', 'Carnot', 'Berbérati', 'Bambari', 'Bria', 'Bouar', 'Bossangoa', 'Nola'
            ],
            'CG' => [
                'Brazzaville', 'Pointe-Noire', 'Dolisie', 'Nkayi', 'Kindamba', 'Impfondo', 'Ouésso', 'Madingou', 'Owando'
            ],
            'GA' => [
                'Libreville', 'Port-Gentil', 'Franceville', 'Oyem', 'Moanda', 'Mouila', 'Lambaréné', 'Tchibanga', 'Koulamoutou', 'Makokou'
            ],
            'GQ' => [
                'Malabo', 'Bata', 'Ebebiyín', 'Aconibe', 'Añisoc', 'Luba', 'Evinayong', 'Mongomo'
            ],
            'CI' => [
                'Abidjan', 'Bouaké', 'Daloa', 'Yamoussoukro', 'San-Pédro', 'Korhogo', 'Man', 'Divo', 'Gagnoa', 'Abengourou', 'Anyama', 'Soubré'
            ],
            'SN' => [
                'Dakar', 'Touba', 'Thiès', 'Kaolack', "M'bour", 'Saint-Louis', 'Rufisque', 'Ziguinchor', 'Diourbel', 'Tambacounda'
            ],
            'FR' => [
                'Paris', 'Marseille', 'Lyon', 'Toulouse', 'Nice', 'Nantes', 'Montpellier', 'Strasbourg', 'Bordeaux', 'Lille', 'Rennes', 'Toulon', 'Reims', 'Saint-Étienne', 'Le Havre'
            ],
            'BE' => [
                'Bruxelles', 'Anvers', 'Gand', 'Charleroi', 'Liège', 'Bruges', 'Namur', 'Louvain', 'Mons', 'Alost'
            ],
            'CA' => [
                'Montréal', 'Toronto', 'Vancouver', 'Ottawa', 'Calgary', 'Edmonton', 'Québec', 'Winnipeg', 'Hamilton', 'Laval'
            ],
            'US' => [
                'New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Philadelphia', 'San Antonio', 'San Diego', 'Dallas', 'Austin', 'Washington', 'Atlanta', 'Miami', 'Boston'
            ]
        ];

        // Ordre de priorité des pays (CEMAC en tête)
        $priorityCodes = ['CM', 'GA', 'CG', 'TD', 'CF', 'GQ', 'CI', 'SN', 'FR', 'BE', 'CA', 'US'];

        DB::transaction(function () use ($countriesData, $manualCities, $priorityCodes) {
            foreach ($countriesData as $c) {
                $code = strtoupper($c['code'] ?? '');
                if (!$code) continue;

                $sortOrder = in_array($code, $priorityCodes) ? (array_search($code, $priorityCodes) + 1) : 100;

                $currency = match ($code) {
                    'CM', 'GA', 'CG', 'TD', 'CF', 'GQ' => 'XAF',
                    'CI', 'SN' => 'XOF',
                    'FR', 'BE' => 'EUR',
                    'CA' => 'CAD',
                    'US' => 'USD',
                    default => 'USD'
                };

                $country = Country::updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => $c['name'],
                        'english_name' => $c['english'] ?? $c['name'],
                        'dial_code' => $c['dial_code'] ?? null,
                        'currency' => $currency,
                        'is_active' => true,
                        'sort_order' => $sortOrder,
                    ]
                );

                // Insérer les villes si présentes
                if (isset($manualCities[$code])) {
                    foreach ($manualCities[$code] as $cityName) {
                        City::firstOrCreate(
                            [
                                'country_id' => $country->id,
                                'name' => trim($cityName),
                            ],
                            [
                                'is_active' => true,
                                'sort_order' => 0,
                            ]
                        );
                    }
                }
            }
        });
    }
}
