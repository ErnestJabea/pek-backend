<?php

namespace App\Filament\Widgets;

use App\Models\OnboardingSession;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class KycFunnelChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Entonnoir & Dossiers KYC';

    protected static ?string $description = 'Statut de traitement et conformité des dossiers clients';

    protected static ?int $sort = 5;

    protected static ?string $maxHeight = '340px';

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasRole('super_admin') || $user->can('view_any_onboarding_session') || in_array($user->adminDepartment?->name, ['Conformité / KYC', 'Support client'], true);
    }

    protected function getData(): array
    {
        $statusCounts = OnboardingSession::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $labels = [
            'Validés / Conformes',
            "En attente d'examen",
            'En cours de saisie',
            'Rejetés / À corriger',
        ];

        $validated = ($statusCounts['validated'] ?? 0) + ($statusCounts['completed'] ?? 0);
        $pending = ($statusCounts['submitted'] ?? 0) + ($statusCounts['pending_review'] ?? 0);
        $draft = ($statusCounts['draft'] ?? 0) + ($statusCounts['in_progress'] ?? 0) + ($statusCounts['revision_requested'] ?? 0);
        $rejected = $statusCounts['rejected'] ?? 0;

        return [
            'datasets' => [
                [
                    'label' => 'Nombre de dossiers',
                    'data' => [$validated, $pending, $draft, $rejected],
                    'backgroundColor' => [
                        '#22c55e', // Green
                        '#f59e0b', // Amber
                        '#3b82f6', // Blue
                        '#ef4444', // Red
                    ],
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
