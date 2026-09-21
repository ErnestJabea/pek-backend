<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'api', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',
        'http://localhost:5174',
        'http://localhost:5175',
        'http://localhost:5176',
        'http://localhost:5177',
        'http://localhost:3000',
        'http://localhost:4173',
        'http://localhost:8000',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:5174',
        'http://127.0.0.1:5175',
        'http://127.0.0.1:5176',
        'http://127.0.0.1:5177',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:4173',
        'http://127.0.0.1:8000',
        'http://192.168.30.130:5173',
        'http://192.168.30.130:8000',
        'http://10.0.0.6:5173',
        'http://10.0.0.6:8000',
        'https://pek-api-v2.ejabbing.com',
        'https://pek-v2.ejabbing.com',
        'https://pek-mobile.ejabbing.com',
        'https://pek.ejabbing.com',
        'https://ejabbing.com',
        'https://pek.koriassetmanagement.com',
        'https://pek-mobile.koriassetmanagement.com',
        'https://pek-backoffice.koriassetmanagement.com',
        'https://koriassetmanagement.com',
        env('FRONTEND_URL', 'https://pek.koriassetmanagement.com'),
    ],

    'allowed_origins_patterns' => [
        '#^https?://([a-zA-Z0-9-]+\.)*ejabbing\.com(:[0-9]+)?$#',
        '#^https?://([a-zA-Z0-9-]+\.)*koriassetmanagement\.com(:[0-9]+)?$#',
        '#^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$#',
        '#^https?://192\.168\.\d+\.\d+(:\d+)?$#',
        '#^https?://10\.\d+\.\d+\.\d+(:\d+)?$#',
        '#^https?://172\.(1[6-9]|2\d|3[0-1])\.\d+\.\d+(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['*'],

    'max_age' => 86400,

    'supports_credentials' => true,

];
