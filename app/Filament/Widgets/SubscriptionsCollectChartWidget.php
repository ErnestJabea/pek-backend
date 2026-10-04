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

        if ($activeFilter === '7d' || $activeFilter === '30d') {
            $days = $activeFilter === '7d' ? 7 : 30;
            $startDate = Carbon::now()->subDays($days - 1)->startOfDay();

            $rawTotals = Subscription::where('statut', 'Succès')
                ->where('created_at', '>=', $startDate)
                ->select(\Illuminate\Support\Facades\DB::raw('DATE(created_at) as date_str'), \Illuminate\Support\Facades\DB::raw('SUM(montant_total) as total'))
                ->groupBy('date_str')
                ->pluck('total', 'date_str')
                ->all();

            for ($i = $days - 1; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);
                $dayStr = $date->format('Y-m-d');
                $labels[] = $date->format('d/m');
                $values[] = (float) ($rawTotals[$dayStr] ?? 0);
            }
        } elseif ($activeFilter === '90d') {
            $startDate = Carbon::now()->subWeeks(11)->startOfWeek();
            $rawRecords = Subscription::where('statut', 'Succès')
                ->where('created_at', '>=', $startDate)
                ->select('created_at', 'montant_total')
                ->get();

            for ($i = 11; $i >= 0; $i--) {
                $start = Carbon::now()->subWeeks($i)->startOfWeek();
                $end = Carbon::now()->subWeeks($i)->endOfWeek();
                $labels[] = 'Sem. ' . $start->format('W');
                $sum = $rawRecords->filter(fn ($r) => $r->created_at >= $start && $r->created_at <= $end)->sum('montant_total');
                $values[] = (float) $sum;
            }
        } else { // 12 derniers mois
            $startDate = Carbon::now()->subMonths(11)->startOfMonth();
            $rawTotals = Subscription::where('statut', 'Succès')
                ->where('created_at', '>=', $startDate)
                ->select(\Illuminate\Support\Facades\DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month_str'), \Illuminate\Support\Facades\DB::raw('SUM(montant_total) as total'))
                ->groupBy('month_str')
                ->pluck('total', 'month_str')
                ->all();

            for ($i = 11; $i >= 0; $i--) {
                $month = Carbon::now()->subMonths($i);
                $monthKey = $month->format('Y-m');
                $frMonths = [1 => 'Janv', 2 => 'Févr', 3 => 'Mars', 4 => 'Avr', 5 => 'Mai', 6 => 'Juin', 7 => 'Juil', 8 => 'Août', 9 => 'Sept', 10 => 'Oct', 11 => 'Nov', 12 => 'Déc'];
                $labels[] = ($frMonths[(int) $month->format('n')] ?? $month->format('M')) . ' ' . $month->format('Y');
                $values[] = (float) ($rawTotals[$monthKey] ?? 0);
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
