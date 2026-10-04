<?php

namespace App\Filament\Widgets;

use App\Models\Subscription;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class SubscriptionsStatusChartWidget extends ChartWidget
{
    protected static ?string $heading = '⚡ Statut des Opérations & Transactions';

    protected static ?string $description = 'Taux de succès et opérations en cours de traitement';

    protected static ?int $sort = 6;

    protected static ?string $maxHeight = '340px';

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        $user = auth()->user();
        return $user && app(\App\Services\BackofficeDashboard::class)->canAccess($user);
    }

    protected function getData(): array
    {
        $statusCounts = Subscription::select('statut', DB::raw('count(*) as count'))
            ->groupBy('statut')
            ->pluck('count', 'statut')
            ->all();

        $labels = ['Succès / Validées', 'En attente', 'Échecs', 'À vérifier'];
        $values = [
            $statusCounts['Succès'] ?? 0,
            $statusCounts['En attente'] ?? 0,
            $statusCounts['Échec'] ?? 0,
            $statusCounts['À vérifier'] ?? 0,
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Souscriptions',
                    'data' => $values,
                    'backgroundColor' => [
                        '#009a4d', // Kori green
                        '#f59e0b', // Amber
                        '#ef4444', // Red
                        '#94a3b8', // Slate gray
                    ],
                    'borderWidth' => 2,
                    'borderColor' => '#ffffff',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
