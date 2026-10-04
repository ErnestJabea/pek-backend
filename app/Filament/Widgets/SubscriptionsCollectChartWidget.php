<?php

namespace App\Filament\Widgets;

use App\Models\Subscription;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class SubscriptionsCollectChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Évolution de la Collecte';

    protected static ?string $description = 'Montant total des souscriptions confirmées en FCFA';

    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '340px';

    protected int|string|array $columnSpan = 1;

    public ?string $filter = '30d';

    public static function canView(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasRole('super_admin') || $user->can('view_any_subscription') || in_array($user->adminDepartment?->name, ['Gestion des fonds', 'Comptabilité / Trésorerie'], true);
    }

    protected function getFilters(): ?array
    {
        return [
            '7d' => '7 derniers jours',
            '30d' => '30 derniers jours',
            '90d' => '3 derniers mois',
            'year' => '12 derniers mois',
        ];
    }

    protected function getData(): array
    {
        $activeFilter = $this->filter ?? '30d';
        $labels = [];
        $values = [];

        if ($activeFilter === '7d') {
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);
                $dayStr = $date->format('Y-m-d');
                $labels[] = $date->format('d/m');
                $values[] = (float) Subscription::where('statut', 'Succès')
                    ->whereDate('created_at', $dayStr)
                    ->sum('montant_total');
            }
        } elseif ($activeFilter === '30d') {
            for ($i = 29; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);
                $dayStr = $date->format('Y-m-d');
                $labels[] = $date->format('d/m');
                $values[] = (float) Subscription::where('statut', 'Succès')
                    ->whereDate('created_at', $dayStr)
                    ->sum('montant_total');
            }
        } elseif ($activeFilter === '90d') {
            for ($i = 11; $i >= 0; $i--) {
                $start = Carbon::now()->subWeeks($i)->startOfWeek();
                $end = Carbon::now()->subWeeks($i)->endOfWeek();
                $labels[] = 'Sem. ' . $start->format('W');
                $values[] = (float) Subscription::where('statut', 'Succès')
                    ->whereBetween('created_at', [$start, $end])
                    ->sum('montant_total');
            }
        } else { // 12 derniers mois
            for ($i = 11; $i >= 0; $i--) {
                $month = Carbon::now()->subMonths($i);
                $frMonths = [1 => 'Janv', 2 => 'Févr', 3 => 'Mars', 4 => 'Avr', 5 => 'Mai', 6 => 'Juin', 7 => 'Juil', 8 => 'Août', 9 => 'Sept', 10 => 'Oct', 11 => 'Nov', 12 => 'Déc'];
                $labels[] = ($frMonths[(int) $month->format('n')] ?? $month->format('M')) . ' ' . $month->format('Y');
                $values[] = (float) Subscription::where('statut', 'Succès')
                    ->whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month)
                    ->sum('montant_total');
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Collecte confirmée (FCFA)',
                    'data' => $values,
                    'borderColor' => '#009a4d',
                    'backgroundColor' => 'rgba(0, 154, 77, 0.12)',
                    'fill' => 'start',
                    'tension' => 0.35,
                    'pointBackgroundColor' => '#009a4d',
                    'pointBorderColor' => '#ffffff',
                    'pointHoverRadius' => 6,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
