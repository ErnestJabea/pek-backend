<?php

namespace Database\Seeders;

use App\Models\BankDetail;
use Illuminate\Database\Seeder;

class BankDetailSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        BankDetail::create([
            'bank_name' => 'Banque Atlantique Bénin',
            'iban' => 'BJ66 0400 1200 0000 1234 5678',
            'rib' => '12345678901',
            'swift' => 'BATLBJCC',
            'is_active' => true,
            'om_instructions' => "1. Composez le #144# puis validez.\n2. Choisissez l'option 1 : Transfert d'argent.\n3. Entrez le numéro marchand PEK : 154789.\n4. Saisissez le montant exact de votre souscription.\n5. Confirmez la transaction en saisissant votre code secret Orange Money.",
            'momo_instructions' => "1. Composez le *122# (ou le code dédié de votre pays) puis validez.\n2. Sélectionnez l'option 2 : Paiement Marchand / Factures.\n3. Entrez le code marchand PEK : 654321.\n4. Saisissez le montant exact de votre souscription.\n5. Confirmez le paiement en saisissant votre code PIN MTN MoMo.",
            'bank_instructions' => "Pour régler par Virement Bancaire :\n1. Connectez-vous à votre espace client bancaire sur internet ou sur mobile.\n2. Effectuez un virement à destination du compte bancaire PEK aux coordonnées ci-dessus (IBAN/RIB).\n3. IMPORTANT : Mentionnez impérativement la Référence Transaction PEK fournie dans le libellé/motif de votre virement pour que votre souscription soit validée.",
        ]);
    }
}
