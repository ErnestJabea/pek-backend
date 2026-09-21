<?php

return [
    'fee_basis_points' => 100,
    'max_investment' => 100000000,
    'timezone' => 'Africa/Douala',
    'proof_max_kb' => 10240,
    'scanner_binary' => env('PAYMENT_PROOF_SCANNER'),
    's3p' => [
        'enabled' => (bool) env('S3P_ENABLED', false),
        'simulation' => (bool) env('MOBILE_MONEY_SIMULATION', false),
        'base_url' => env('S3P_BASE_URL', 'https://s3p.smobilpay.staging.maviance.info'),
        'allowed_hosts' => array_filter(explode(',', env('S3P_ALLOWED_HOSTS', 's3p.smobilpay.staging.maviance.info'))),
        'public_key' => env('S3P_PUBLIC_KEY'),
        'secret_key' => env('S3P_SECRET_KEY'),
        'webhook_secret' => env('S3P_WEBHOOK_SECRET'),
        'api_version' => env('S3P_API_VERSION', '3.0.0'),
        'ca_bundle' => env('S3P_CA_BUNDLE'),
        'timestamp_timezone' => env('S3P_TIMESTAMP_TIMEZONE'),
        // Enable only after Maviance confirms verifytx.timestamp is the receipt time for this service.
        'verified_timestamp_is_receipt' => (bool) env('S3P_VERIFY_TIMESTAMP_IS_RECEIPT', false),
        'merchants' => [
            'orange_money' => env('S3P_ORANGE_MERCHANT'),
            'mtn_momo' => env('S3P_MTN_MERCHANT'),
        ],
        'wallet_formats' => [
            'orange_money' => env('S3P_ORANGE_WALLET_FORMAT', 'international'),
            'mtn_momo' => env('S3P_MTN_WALLET_FORMAT', 'international'),
        ],
        'services' => [
            'orange_money' => env('S3P_ORANGE_SERVICE_ID'),
            'mtn_momo' => env('S3P_MTN_SERVICE_ID'),
        ],
    ],
];
