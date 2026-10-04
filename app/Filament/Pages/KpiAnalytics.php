<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\KycFunnelChartWidget;
use App\Filament\Widgets\PaymentMethodsDistributionWidget;
use App\Filament\Widgets\ProductVlEvolutionWidget;
use App\Filament\Widgets\SubscriptionsCollectChartWidget;
use App\Filament\Widgets\SubscriptionsStatusChartWidget;
use App\Filament\Widgets\UserRegistrationsChartWidget;
use App\Services\BackofficeDashboard;
use Filament\Pages\Dashboard as BaseDashboard;

class KpiAnalytics extends BaseDashboard
{
    protected static string $routePath = '/kpi-analytics';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'Tableaux de bord';

    protected static ?string $navigationLabel = 'Graphiques des KPI';

    protected static ?string $title = 'Évolution des KPI & Graphiques Analytiques';

    protected static ?int $navigationSort = 2;

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
            SubscriptionsCollectChartWidget::class,
            PaymentMethodsDistributionWidget::class,
            ProductVlEvolutionWidget::class,
            KycFunnelChartWidget::class,
            UserRegistrationsChartWidget::class,
            SubscriptionsStatusChartWidget::class,
        ];
    }
}
