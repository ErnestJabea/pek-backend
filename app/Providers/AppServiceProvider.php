<?php

namespace App\Providers;

use App\Services\IdentityVerification\IdenfyIdentityVerificationProvider;
use App\Services\IdentityVerification\IdentityVerificationProvider;
use App\Services\Payments\EnkapPaymentGateway;
use App\Services\Payments\LocalPaymentGateway;
use App\Services\Payments\PaymentCheckoutGateway;
use App\Services\Payments\StripeCheckoutGateway;
use Filament\Facades\Filament;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentCheckoutGateway::class, StripeCheckoutGateway::class);
        $this->app->bind(LocalPaymentGateway::class, EnkapPaymentGateway::class);

        $this->app->bind(IdentityVerificationProvider::class, function () {
            return match (config('identity_verification.provider')) {
                'idenfy' => new IdenfyIdentityVerificationProvider,
                default => throw new \RuntimeException('Fournisseur de vérification d’identité non pris en charge.'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force the Admin panel to boot early to ensure all Livewire components
        // (widgets, pages, resources) are registered. This bypasses the ComponentNotFoundException
        // in this specific local MAMP environment where middleware execution order is failing.
        app()->booted(function () {
            Filament::getPanel('admin')->boot();
        });
    }
}
