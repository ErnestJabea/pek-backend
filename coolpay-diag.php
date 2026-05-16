<?php

$coolpayPublicKey = '118a4852-7df8-46d9-834b-23b4ef25aaab';
$fields = [
    'transaction_amount' => 100,
    'transaction_currency' => 'XAF',
    'transaction_reason' => "Test Diag PEK",
    'app_transaction_ref' => 'DIAG-' . time(),
    'customer_phone_number' => '699009900',
    'customer_name' => "Test Diag",
    'customer_email' => 'test@diag.com',
    'customer_lang' => 'fr'
];

echo "--- Debut Diagnostic CoolPay ---\n";
echo "URL : https://my-coolpay.com/api/{$coolpayPublicKey}/payin\n";

$curl = curl_init();
curl_setopt_array($curl, array(
  CURLOPT_URL => "https://my-coolpay.com/api/{$coolpayPublicKey}/payin",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS => json_encode($fields),
  CURLOPT_SSL_VERIFYPEER => false,
  CURLOPT_HTTPHEADER => array(
    'Content-Type: application/json',
    'Accept: application/json',
    'Expect:'
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($err) {
    echo "❌ Erreur CURL : " . $err . "\n";
} else {
    echo "✅ Code HTTP : " . $httpCode . "\n";
    echo "📝 Reponse : " . $response . "\n";
}
echo "--- Fin Diagnostic ---\n";
