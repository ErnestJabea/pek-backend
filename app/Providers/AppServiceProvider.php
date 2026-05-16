<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
            \Filament\Facades\Filament::getPanel('admin')->boot();
        });
    }
}
