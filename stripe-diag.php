<?php

require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$key = new \Stripe\StripeClient(env('STRIPE_SECRET'));
\Stripe\Stripe::setApiKey($key);

try {
    echo "--- Début du Diagnostic Stripe ---\n";
    echo "Clé utilisée : " . substr($key, 0, 10) . "..." . substr($key, -5) . "\n";
    
    // 1. Test de connexion
    $account = \Stripe\Account::retrieve();
    echo "✅ Connexion réussie : Compte " . $account->email . " (" . $account->id . ")\n";

    // 2. Tentative de création d'un virement de test
    echo "⏳ Tentative de création d'un virement (customer_balance)...\n";
    
    $customer = \Stripe\Customer::create([
        'email' => 'test-diagnostic@pek.com',
        'name' => 'Diagnostic Test',
    ]);

    $intent = \Stripe\PaymentIntent::create([
        'amount' => 1000,
        'currency' => 'eur',
        'customer' => $customer->id,
        'payment_method_types' => ['customer_balance'],
        'payment_method_options' => [
            'customer_balance' => [
                'funding_type' => 'bank_transfer',
                'bank_transfer' => [
                    'type' => 'eu_bank_transfer',
                    'eu_bank_transfer' => [
                        'country' => 'FR',
                    ],
                ],
            ],
        ],
    ]);

    // CONFIRM the intent to get instructions
    $intent->confirm();

    echo "✅ PaymentIntent créé et CONFIRMÉ !\n";
    echo "ID : " . $intent->id . "\n";
    
    if (isset($intent->next_action->display_bank_transfer_instructions)) {
        echo "✅ Instructions de virement générées !\n";
        $iban = $intent->next_action->display_bank_transfer_instructions->financial_addresses[0]->iban->iban;
        echo "IBAN DE TEST : " . $iban . "\n";
    } else {
        echo "⚠️ Attention : L'intention est confirmée mais AUCUNE instruction n'est présente.\n";
    }

} catch (\Exception $e) {
    echo "❌ ERREUR STRIPE : " . $e->getMessage() . "\n";
    echo "Code d'erreur : " . $e->getCode() . "\n";
}

echo "--- Fin du Diagnostic ---\n";
