<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequirePermanentPassword
{
    public function handle(Request $request, Closure $next)
    {
        $allowed = ['user', 'logout', 'reset-temp-password'];
        $endpoint = preg_replace('#^api/(v1/)?#', '', $request->path());
        if ($request->user()?->has_temp_password && ! in_array($endpoint, $allowed, true)) {
            return response()->json([
                'message' => 'Veuillez remplacer votre mot de passe temporaire avant de continuer.',
                'code' => 'PASSWORD_CHANGE_REQUIRED',
            ], 403);
        }

        return $next($request);
    }
}
