<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTrustedOriginForCookieAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->attributes->get('authenticated_via_token_cookie') || $request->isMethodSafe()) {
            return $next($request);
        }

        $origin = rtrim((string) $request->header('Origin'), '/');
        $allowedOrigins = collect(config('cors.allowed_origins', []))
            ->filter()
            ->map(fn (string $allowed): string => rtrim($allowed, '/'));

        $isAllowed = $origin !== '' && $allowedOrigins->containsStrict($origin);

        if (! $isAllowed && $origin !== '') {
            $allowedPatterns = config('cors.allowed_origins_patterns', []);
            foreach ($allowedPatterns as $pattern) {
                if (@preg_match($pattern, $origin)) {
                    $isAllowed = true;
                    break;
                }
            }
        }

        if (! $isAllowed && $origin !== '' && (app()->environment('local') || config('app.debug'))) {
            if (preg_match('#^https?://(192\.168|10\.|172\.(1[6-9]|2[0-9]|3[01]))\.\d+\.\d+(:\d+)?$#', $origin)) {
                $isAllowed = true;
            }
        }

        abort_unless($isAllowed, 403, 'Origine de requête non autorisée.');

        return $next($request);
    }
}
