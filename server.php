<?php

/**
 * Laravel - A PHP Framework For Web Artisans
 *
 * This file allows us to emulate Apache's "mod_rewrite" functionality from the
 * built-in PHP web server. This provides a convenient way to test a Laravel
 * application without having installed a "real" web server software here.
 */
if (!function_exists('openssl_cipher_iv_length')) {
    function openssl_cipher_iv_length(string $cipher): int|false {
        $c = strtolower($cipher);
        if (str_contains($c, 'gcm') || str_contains($c, 'ccm')) {
            return 12;
        }
        return 16;
    }
}
if (!function_exists('openssl_decrypt')) {
    function openssl_decrypt(string $data, string $cipher_algo, string $passphrase, int $options = 0, string $iv = "", ?string $tag = null, string $aad = ""): string|false {
        return false;
    }
}
if (!function_exists('openssl_encrypt')) {
    function openssl_encrypt(string $data, string $cipher_algo, string $passphrase, int $options = 0, string $iv = "", ?string &$tag = null, string $aad = "", int $tag_length = 16): string|false {
        return false;
    }
}

file_put_contents(__DIR__ . '/storage/logs/active_server_info.log', json_encode([
    'PHP_BINARY' => PHP_BINARY,
    'PHP_VERSION' => PHP_VERSION,
    'Loaded_INI' => php_ini_loaded_file(),
    'extension_dir' => ini_get('extension_dir'),
    'openssl_loaded' => extension_loaded('openssl'),
    'extensions' => get_loaded_extensions(),
], JSON_PRETTY_PRINT));

$publicPath = __DIR__.'/public';

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

$filePath = $publicPath . $uri;

if ($uri !== '/' && file_exists($filePath) && is_file($filePath)) {
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'css'   => 'text/css; charset=UTF-8',
        'js'    => 'application/javascript; charset=UTF-8',
        'mjs'   => 'application/javascript; charset=UTF-8',
        'json'  => 'application/json; charset=UTF-8',
        'svg'   => 'image/svg+xml',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'webp'  => 'image/webp',
        'ico'   => 'image/x-icon',
        'woff2' => 'font/woff2',
        'woff'  => 'font/woff',
        'ttf'   => 'font/ttf',
        'eot'   => 'application/vnd.ms-fontobject',
        'map'   => 'application/json',
    ];

    if (isset($mimeTypes[$extension])) {
        header('Content-Type: ' . $mimeTypes[$extension]);
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: public, max-age=3600');
        readfile($filePath);
        exit;
    }

    return false;
}

require_once $publicPath.'/index.php';
