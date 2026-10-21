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
        if (app()->runningInConsole()) {
            @ini_set('max_execution_time', '0');
            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }
        } else {
            @ini_set('max_execution_time', '300');
            if (function_exists('set_time_limit')) {
                @set_time_limit(300);
            }
        }
        // Force the Admin panel to boot early to ensure all Livewire components
        // (widgets, pages, resources) are registered. This bypasses the ComponentNotFoundException
        // in this specific local MAMP environment where middleware execution order is failing.
        app()->booted(function () {
            Filament::getPanel('admin')->boot();
        });

                // Configure all Filament table columns to be toggleable by default across the backoffice
        \Filament\Tables\Columns\Column::configureUsing(function (\Filament\Tables\Columns\Column $column): void {
            $column->toggleable();
        });

        \Filament\Tables\Table::configureUsing(function (\Filament\Tables\Table $table): void {
            $table->columnToggleFormColumns(2);
        });

        \Illuminate\Support\Facades\View::composer('emails.*', function ($view) {
            if (! isset($view->getData()['frontendUrl'])) {
                $frontendUrl = \App\Providers\AuthServiceProvider::resolveFrontendUrl();
                $view->with('frontendUrl', $frontendUrl);
            }
        });

        $this->configureDynamicMailSettings();
    }

    /**
     * Configure dynamically mail sender and reply-to from SystemSetting.
     */
    protected function configureDynamicMailSettings(): void
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('system_settings')) {
                $fromAddress = \App\Models\SystemSetting::get('mail_from_address');
                $fromName = \App\Models\SystemSetting::get('mail_from_name', config('mail.from.name', 'PEK - KORI Asset Management'));
                if (! empty($fromAddress) && filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
                    config(['mail.from.address' => $fromAddress, 'mail.from.name' => $fromName]);
                    \Illuminate\Support\Facades\Mail::alwaysFrom($fromAddress, $fromName);
                }

                $replyToAddress = \App\Models\SystemSetting::get('mail_reply_to_email', config('mail.reply_to.address', 'contact@koriassetmanagement.com'));
                $replyToName = \App\Models\SystemSetting::get('mail_reply_to_name', config('mail.reply_to.name', 'Support Client PEK - KORI Asset Management'));
                if (! empty($replyToAddress) && filter_var($replyToAddress, FILTER_VALIDATE_EMAIL)) {
                    config(['mail.reply_to.address' => $replyToAddress, 'mail.reply_to.name' => $replyToName]);
                    \Illuminate\Support\Facades\Mail::alwaysReplyTo($replyToAddress, $replyToName);
                }
            }
        } catch (\Throwable $e) {
            // Ignore during early bootstrap, migrations, or database connection failures
        }
    }
}
