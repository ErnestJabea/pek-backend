<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth-register', fn (Request $request) => [
            Limit::perMinute(5)->by($request->ip()),
            Limit::perHour(3)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
        ]);
        RateLimiter::for('auth-login', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip()),
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
        ]);
        RateLimiter::for('auth-otp', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip()),
            Limit::perMinute(5)->by((string) ($request->input('challenge_id') ?: $request->input('email'))),
        ]);
        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinute(5)->by($request->ip()),
            Limit::perHour(3)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
        ]);
        RateLimiter::for('identity-verification', fn (Request $request) => [
            Limit::perHour(5)->by((string) $request->user()?->id),
            Limit::perHour(20)->by($request->ip()),
        ]);
        RateLimiter::for('payment-return', fn (Request $request) => [
            Limit::perMinute(60)->by($request->ip()),
            Limit::perMinute(40)->by(hash('sha256', (string) $request->input('session_id')).'|'.$request->ip()),
        ]);
        RateLimiter::for('provider-webhooks', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('payment-initiation', function (Request $request) {
            $input = $request->input('payment_phone');
            $phone = is_string($input) ? preg_replace('/[\s()+-]/', '', $input) : (string) $request->user()?->id;
            if (str_starts_with($phone, '00237')) $phone = substr($phone, 2);
            if (strlen($phone) === 9 && str_starts_with($phone, '6')) $phone = '237'.$phone;
            return [
                Limit::perMinute(5)->by('user:'.$request->user()?->id),
                Limit::perHour(10)->by('wallet:'.hash('sha256', $phone)),
            ];
        });
        RateLimiter::for('proof-upload', fn (Request $request) => Limit::perHour(20)->by((string) $request->user()?->id));
    }
}
