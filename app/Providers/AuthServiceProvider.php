<?php

namespace App\Providers;

use App\Models\OnboardingSession;
use App\Policies\OnboardingSessionPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        OnboardingSession::class => OnboardingSessionPolicy::class,
    ];

    /**
     * Resolve the canonical, sanitized frontend URL.
     */
    public static function resolveFrontendUrl(): string
    {
        $raw = (string) config('app.frontend_url', env('FRONTEND_URL', ''));

        // Si la valeur contient plusieurs URLs séparées par des virgules (ex: CORS copié dans FRONTEND_URL)
        if (str_contains($raw, ',')) {
            $parts = explode(',', $raw);
            $found = null;
            foreach ($parts as $part) {
                $trimmed = trim($part);
                if (str_contains($trimmed, 'pek-v2.koriassetmanagement.com')) {
                    $found = $trimmed;
                    break;
                }
            }
            $raw = $found ?: trim($parts[0]);
        }

        $url = trim($raw);

        // En production ou si localhost/vide, forcer l'URL officielle de production
        if (app()->isProduction()) {
            if (empty($url) || str_contains($url, 'localhost') || str_contains($url, '127.0.0.1')) {
                return 'https://pek-v2.koriassetmanagement.com';
            }
        }

        if (empty($url)) {
            return 'https://pek-v2.koriassetmanagement.com';
        }

        return rtrim($url, '/');
    }

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        ResetPassword::createUrlUsing(function ($user, string $token) {
            $baseUrl = self::resolveFrontendUrl();

            return rtrim($baseUrl, '/')
                .'/password-reset/'.$token
                .'?email='.urlencode($user->getEmailForPasswordReset());
        });

        ResetPassword::toMailUsing(function ($user, string $token) {
            $baseUrl = self::resolveFrontendUrl();
            $url = rtrim($baseUrl, '/')
                .'/password-reset/'.$token
                .'?email='.urlencode($user->getEmailForPasswordReset());

            $locale = app()->getLocale();
            $isEn = ($locale === 'en');
            $name = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
            if (empty($name)) {
                $name = $user->first_name ?: ($isEn ? 'Client' : 'Client');
            }

            $subject = $isEn
                ? 'Password Reset for your PEK Account'
                : 'Réinitialisation de votre mot de passe PEK';

            return (new MailMessage)
                ->subject($subject)
                ->action($isEn ? 'Reset my password' : 'Réinitialiser mon mot de passe', $url)
                ->view('emails.password_reset', [
                    'resetUrl' => $url,
                    'name' => $name,
                    'isEn' => $isEn,
                ]);
        });

        // Implicitly grant "super_admin" role all permissions
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });

        Gate::define('viewLogViewer', function ($user) {
            return $user && $user->hasRole('super_admin');
        });
    }
}
