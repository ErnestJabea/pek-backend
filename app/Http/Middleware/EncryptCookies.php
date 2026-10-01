<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];

    /**
     * Handle an incoming request with fallback if OpenSSL is unavailable.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle($request, Closure $next)
    {
        if (!extension_loaded('openssl') || !function_exists('openssl_decrypt')) {
            return $next($request);
        }

        try {
            return parent::handle($request, $next);
        } catch (\Throwable $e) {
            return $next($request);
        }
    }
}
