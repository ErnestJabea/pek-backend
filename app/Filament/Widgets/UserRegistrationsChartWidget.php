<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class UserRegistrationsChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Croissance des Inscriptions Clients';

    protected static ?string $description = 'Nouveaux comptes clients créés au fil du temps';

    protected static ?int $sort = 6;

    protected static ?string $maxHeight = '340px';

    protected int|string|array $columnSpan = 1;

    public ?string $filter = '30d';

    public static function canView(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasRole('super_admin') || $user->can('view_any_user') || in_array($user->adminDepartment?->name, ['Support client', 'Activité commune'], true);
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

        $baseQuery = fn () => User::where('role', 'client')
            ->whereNull('admin_department_id')
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'super_admin'));

        if ($activeFilter === '7d' || $activeFilter === '30d') {
            $days = $activeFilter === '7d' ? 7 : 30;
            $startDate = Carbon::now()->subDays($days - 1)->startOfDay();

            $rawCounts = $baseQuery()
                ->where('created_at', '>=', $startDate)
                ->select(\Illuminate\Support\Facades\DB::raw('DATE(created_at) as date_str'), \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->groupBy('date_str')
                ->pluck('count', 'date_str')
                ->all();

            for ($i = $days - 1; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);
                $dayStr = $date->format('Y-m-d');
                $labels[] = $date->format('d/m');
                $values[] = (int) ($rawCounts[$dayStr] ?? 0);
            }
        } elseif ($activeFilter === '90d') {
            $startDate = Carbon::now()->subWeeks(11)->startOfWeek();
            $rawUsers = $baseQuery()
                ->where('created_at', '>=', $startDate)
                ->select('created_at')
                ->get();

            for ($i = 11; $i >= 0; $i--) {
                $start = Carbon::now()->subWeeks($i)->startOfWeek();
                $end = Carbon::now()->subWeeks($i)->endOfWeek();
                $labels[] = 'Sem. ' . $start->format('W');
                $cnt = $rawUsers->filter(fn ($u) => $u->created_at >= $start && $u->created_at <= $end)->count();
                $values[] = (int) $cnt;
            }
        } else { // 12 derniers mois
            $startDate = Carbon::now()->subMonths(11)->startOfMonth();
            $rawCounts = $baseQuery()
                ->where('created_at', '>=', $startDate)
                ->select(\Illuminate\Support\Facades\DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month_str'), \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->groupBy('month_str')
                ->pluck('count', 'month_str')
                ->all();

            for ($i = 11; $i >= 0; $i--) {
                $month = Carbon::now()->subMonths($i);
                $monthKey = $month->format('Y-m');
                $frMonths = [1 => 'Janv', 2 => 'FÃ©vr', 3 => 'Mars', 4 => 'Avr', 5 => 'Mai', 6 => 'Juin', 7 => 'Juil', 8 => 'AoÃ»t', 9 => 'Sept', 10 => 'Oct', 11 => 'Nov', 12 => 'DÃ©c'];
                $labels[] = ($frMonths[(int) $month->format('n')] ?? $month->format('M')) . ' ' . $month->format('Y');
                $values[] = (int) ($rawCounts[$monthKey] ?? 0);
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Nouveaux clients',
                    'data' => $values,
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.12)',
                    'fill' => 'start',
                    'tension' => 0.35,
                    'pointBackgroundColor' => '#6366f1',
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
