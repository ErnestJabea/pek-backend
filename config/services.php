<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'checkout_ttl_minutes' => env('STRIPE_CHECKOUT_TTL_MINUTES', 30),
        'checkout_allowed_hosts' => array_values(array_filter(array_map(
            fn (string $host): string => strtolower(trim($host)),
            explode(',', env('STRIPE_CHECKOUT_ALLOWED_HOSTS', 'checkout.stripe.com'))
        ))),
    ],

    'maviance' => [
        'enabled' => env('MAVIANCE_ENABLED', true),
        'client_id' => env('ENKAP_CLIENT_ID', env('MAVIANCE_PUBLIC_KEY')),
        'client_secret' => env('ENKAP_CLIENT_SECRET', env('MAVIANCE_PRIVATE_KEY')),
        'base_url' => env('ENKAP_BASE_URL', 'https://api.enkap-staging.maviance.info'),
        'evisa_url' => env('ENKAP_EVISA_URL', 'https://api-evisa.enkap-staging.maviance.info'),
        'public_key' => env('ENKAP_CLIENT_ID', env('MAVIANCE_PUBLIC_KEY')),
        'private_key' => env('ENKAP_CLIENT_SECRET', env('MAVIANCE_PRIVATE_KEY')),
        'test_amount' => env('MAVIANCE_TEST_AMOUNT', 100),
        'checkout_ttl_minutes' => env('ENKAP_CHECKOUT_TTL_MINUTES', 30),
        'checkout_allowed_hosts' => array_values(array_filter(array_map(
            fn (string $host): string => strtolower(trim($host)),
            explode(',', env('ENKAP_CHECKOUT_ALLOWED_HOSTS', 'payment.enkap.cm,payment.enkap-staging.maviance.info,api-evisa.enkap-staging.maviance.info'))
        ))),
    ],

    'idenfy' => [
        'base_url' => env('IDENFY_BASE_URL', 'https://ivs.idenfy.com'),
        'api_key' => env('IDENFY_API_KEY'),
        'api_secret' => env('IDENFY_API_SECRET'),
        'webhook_signing_secret' => env('IDENFY_WEBHOOK_SIGNING_SECRET'),
        'callback_url' => env('IDENFY_CALLBACK_URL') ?: rtrim(env('APP_URL', ''), '/').'/api/identity-verification/idenfy/webhook',
        'country' => env('IDENFY_COUNTRY', 'CM'),
        'documents' => array_values(array_filter(array_map('trim', explode(',', env('IDENFY_DOCUMENTS', 'PASSPORT,ID_CARD,RESIDENCE_PERMIT'))))),
        'allowed_redirect_hosts' => array_values(array_filter(array_map(
            fn (string $host): string => strtolower(trim($host)),
            explode(',', env('IDENFY_ALLOWED_REDIRECT_HOSTS', 'ivs.idenfy.com,ui.idenfy.com'))
        ))),
    ],

];
