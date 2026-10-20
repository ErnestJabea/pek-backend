<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SyncUserTablePreferences
{
    /**
     * Handle an incoming request.
     * Synchronizes user-specific table preferences (e.g. column toggles)
     * between the user's database record and the current session.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Pre-load: if user is authenticated, ensure their database table preferences are present in session
        if (auth()->check()) {
            $user = auth()->user();
            $dbPrefs = $user->table_preferences ?? [];

            if (is_array($dbPrefs) && ! empty($dbPrefs)) {
                foreach ($dbPrefs as $tableKey => $toggledCols) {
                    $sessionKey = "tables.{$tableKey}_toggled_columns";
                    if (! session()->has($sessionKey)) {
                        session()->put($sessionKey, $toggledCols);
                    }
                }
            }
        }

        $response = $next($request);

        // 2. Post-sync: after request execution, save any modified table column toggles back to DB for this specific user
        if (auth()->check()) {
            $user = auth()->user();
            $dbPrefs = $user->table_preferences ?? [];
            $updatedPrefs = is_array($dbPrefs) ? $dbPrefs : [];
            $hasChanges = false;

            $tablesSession = session()->get('tables', []);
            if (is_array($tablesSession)) {
                foreach ($tablesSession as $subKey => $value) {
                    if (is_string($subKey) && str_ends_with($subKey, '_toggled_columns') && is_array($value)) {
                        $tableKey = substr($subKey, 0, -16);
                        if (! isset($updatedPrefs[$tableKey]) || $updatedPrefs[$tableKey] !== $value) {
                            $updatedPrefs[$tableKey] = $value;
                            $hasChanges = true;
                        }
                    }
                }
            }

            if ($hasChanges) {
                $user->table_preferences = $updatedPrefs;
                $user->saveQuietly();
            }
        }

        return $response;
    }
}
