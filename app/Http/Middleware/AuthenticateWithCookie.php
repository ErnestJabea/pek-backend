<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthenticateWithCookie
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response|RedirectResponse)  $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $authHeader = $request->header('Authorization');
        if ($request->hasCookie('auth_token') && (! $authHeader || str_contains($authHeader, 'cookie_session'))) {
            $token = $request->cookie('auth_token');
            $request->headers->set('Authorization', 'Bearer '.$token);
            $request->attributes->set('authenticated_via_token_cookie', true);
        }

        return $next($request);
    }
}
