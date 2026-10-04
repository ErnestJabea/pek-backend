<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BusinessDashboard;
use App\Filament\Widgets\KycFunnelChartWidget;
use App\Filament\Widgets\PaymentMethodsDistributionWidget;
use App\Filament\Widgets\ProductVlEvolutionWidget;
use App\Filament\Widgets\SubscriptionsCollectChartWidget;
use App\Filament\Widgets\SubscriptionsStatusChartWidget;
use App\Filament\Widgets\UserRegistrationsChartWidget;
use App\Services\BackofficeDashboard;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected static ?string $title = 'Tableau de bord';

    public static function canAccess(): bool
    {
        return app(BackofficeDashboard::class)->canAccess(auth()->user());
    }

    public function getColumns(): int | string | array
    {
        return 2;
    }

    public function getWidgets(): array
    {
        return [
            BusinessDashboard::class,
            SubscriptionsCollectChartWidget::class,
            PaymentMethodsDistributionWidget::class,
            ProductVlEvolutionWidget::class,
            KycFunnelChartWidget::class,
            UserRegistrationsChartWidget::class,
            SubscriptionsStatusChartWidget::class,
        ];
    }
}
