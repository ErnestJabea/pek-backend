<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BusinessDashboard;
use App\Services\BackofficeDashboard;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected static ?string $title = 'Tableau de bord';

    public static function canAccess(): bool
    {
        return app(BackofficeDashboard::class)->canAccess(auth()->user());
    }

    public function getWidgets(): array
    {
        return [BusinessDashboard::class];
    }
}
