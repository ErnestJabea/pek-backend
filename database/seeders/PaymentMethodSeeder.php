<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PaymentMethod;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $methods = [
            [
                'code' => 'card',
                'name' => 'Carte Bancaire (Stripe)',
                'name_en' => 'Credit Card (Stripe)',
                'description' => 'Paiement immédiat sur la page sécurisée Stripe.',
                'description_en' => 'Instant payment on Stripe secure checkout.',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'code' => 'orange_money',
                'name' => 'Orange Money Cameroun',
                'name_en' => 'Orange Money Cameroon',
                'description' => 'Confirmation du paiement sur votre téléphone (Orange Money).',
                'description_en' => 'Payment confirmation on your phone (Orange Money).',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'code' => 'mtn_momo',
                'name' => 'MTN Mobile Money',
                'name_en' => 'MTN Mobile Money',
                'description' => 'Confirmation du paiement sur votre téléphone (MTN MoMo).',
                'description_en' => 'Payment confirmation on your phone (MTN MoMo).',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'code' => 'bank_transfer',
                'name' => 'Virement Bancaire',
                'name_en' => 'Bank Transfer',
                'description' => 'Validation après réception et rapprochement effectif des fonds.',
                'description_en' => 'Validation after receipt and reconciliation of funds.',
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($methods as $data) {
            PaymentMethod::updateOrCreate(
                ['code' => $data['code']],
                $data
            );
        }
    }
}
