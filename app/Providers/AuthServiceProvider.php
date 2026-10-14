<?php

namespace App\Providers;

use App\Models\OnboardingSession;
// use Illuminate\Support\Facades\Gate;
use App\Policies\OnboardingSessionPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
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
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        ResetPassword::createUrlUsing(function ($user, string $token) {
            return rtrim((string) config('app.frontend_url'), '/')
                .'/password-reset/'.$token
                .'?email='.urlencode($user->getEmailForPasswordReset());
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
