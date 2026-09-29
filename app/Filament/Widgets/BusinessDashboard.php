<?php

namespace App\Filament\Widgets;

use App\Services\BackofficeDashboard;
use Filament\Widgets\Widget;

class BusinessDashboard extends Widget
{
    protected static string $view = 'filament.widgets.business-dashboard';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return app(BackofficeDashboard::class)->canAccess(auth()->user());
    }

    protected function getViewData(): array
    {
        abort_unless(static::canView(), 403);

        return [
            'groups' => app(BackofficeDashboard::class)->snapshot(auth()->user()),
            'updatedAt' => now(config('payments.timezone'))->format('d/m/Y à H:i:s'),
            'timezone' => config('payments.timezone'),
        ];
    }
}
