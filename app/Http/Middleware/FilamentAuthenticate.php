<?php

namespace App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as BaseAuthenticate;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Model;

class FilamentAuthenticate extends BaseAuthenticate
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  array<string>  $guards
     */
    protected function authenticate($request, array $guards): void
    {
        $guard = Filament::auth();

        if (! $guard->check()) {
            $this->unauthenticated($request, $guards);

            return;
        }

        $this->auth->shouldUse(Filament::getAuthGuard());

        /** @var Model $user */
        $user = $guard->user();

        $panel = Filament::getCurrentPanel();

        $canAccess = $user instanceof FilamentUser ?
            $user->canAccessPanel($panel) :
            (config('app.env') === 'local');

        if (! $canAccess) {
            // L'utilisateur connecté dans la session n'a pas les droits Backoffice (ex: session client).
            // On le déconnecte du guard d'administration et on le redirige vers le login avec un message explicite
            // au lieu de le bloquer sur un écran d'erreur 403 figé.
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            session()->flash('notification', [
                'title' => 'Accès refusé',
                'body' => 'Le compte actuellement connecté n’a pas accès au panneau d’administration. Veuillez vous connecter avec un compte administrateur.',
                'status' => 'danger',
            ]);

            redirect()->guest(Filament::getLoginUrl())->send();
            exit;
        }
    }
}
