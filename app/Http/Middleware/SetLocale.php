<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = 'fr';

        if ($request->hasHeader('Accept-Language')) {
            $headerLang = strtolower(substr($request->header('Accept-Language'), 0, 2));
            if (in_array($headerLang, ['en', 'fr'], true)) {
                $locale = $headerLang;
            }
        } elseif ($request->user() && in_array($request->user()->locale, ['en', 'fr'], true)) {
            $locale = $request->user()->locale;
        } elseif (session()->has('locale')) {
            $locale = session()->get('locale');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
