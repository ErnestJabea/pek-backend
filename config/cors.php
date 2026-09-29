<?php

// Exact origins also protect cookie-authenticated writes. No /login or trailing slash.
$configured = trim((string) env('CORS_ALLOWED_ORIGINS', ''));
$origins = $configured !== '' ? explode(',', $configured) : [
    'https://pek-v2.koriassetmanagement.com',
    'https://pek-v2.e-jabbing.com',
    'https://pek-v2.ejabbing.com',
    'https://pek-api-v2.ejabbing.com',
    'https://pek-mobile.ejabbing.com',
    'https://pek.ejabbing.com',
    'https://pek.koriassetmanagement.com',
    'https://pek-mobile.koriassetmanagement.com',
    'https://pek-backoffice.koriassetmanagement.com',
];
$origins[] = env('FRONTEND_URL');
$local = in_array(env('APP_ENV', 'production'), ['local', 'testing'], true);
$origins = array_values(array_unique(array_filter(array_map(
    static fn ($origin) => rtrim(trim((string) $origin), '/'), $origins
), static function (string $origin) use ($local): bool {
    $parts = parse_url($origin);

    return $parts !== false && isset($parts['host'])
        && in_array($parts['scheme'] ?? '', $local ? ['http', 'https'] : ['https'], true)
        && ! isset($parts['user']) && ! isset($parts['pass'])
        && ! isset($parts['path']) && ! isset($parts['query']) && ! isset($parts['fragment']);
})));

return [
    'paths' => ['api/*', 'api', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => $origins,
    // Development conveniences never authorize arbitrary production subdomains.
    'allowed_origins_patterns' => $local ? [
        '#^http://(localhost|127\.0\.0\.1)(:[0-9]+)?$#',
        '#^http://(192\.168\.\d+\.\d+|10\.\d+\.\d+\.\d+|172\.(1[6-9]|2\d|3[01])\.\d+\.\d+)(:[0-9]+)?$#',
    ] : [],
    'allowed_headers' => ['Accept', 'Accept-Language', 'Authorization', 'Content-Type', 'Idempotency-Key',
        'X-Requested-With', 'X-CSRF-TOKEN', 'X-XSRF-TOKEN', 'Cache-Control', 'Pragma'],
    'exposed_headers' => ['Retry-After', 'Content-Disposition'],
    'max_age' => 600,
    'supports_credentials' => true,
];
