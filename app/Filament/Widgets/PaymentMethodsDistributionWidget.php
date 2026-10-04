<?php

namespace App\Filament\Widgets;

use App\Models\Subscription;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class PaymentMethodsDistributionWidget extends ChartWidget
{
    protected static ?string $heading = '💳 Répartition par Moyen de Paiement';

    protected static ?string $description = 'Volumes collectés selon le canal d\'encaissement';

    protected static ?int $sort = 3;

    protected static ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasRole('super_admin') || $user->can('confirm_bank_payment') || $user->can('view_any_subscription') || in_array($user->adminDepartment?->name, ['Comptabilité / Trésorerie', 'Gestion des fonds'], true);
    }

    protected function getData(): array
    {
        $rows = Subscription::where('statut', 'Succès')
            ->select('moyen_paiement', DB::raw('SUM(montant_total) as total'))
            ->groupBy('moyen_paiement')
            ->pluck('total', 'moyen_paiement')
            ->all();

        $labelMap = [
            'bank_transfer' => 'Virement bancaire',
            'virement' => 'Virement bancaire',
            'orange_money' => 'Orange Money',
            'mtn_momo' => 'MTN Mobile Money',
            'mobile_money' => 'Mobile Money (e-nkap)',
            'cash_deposit' => 'Dépôt d\'espèces / Bordereau',
            'cheque' => 'Chèque',
            'apport_titres' => 'Apport de titres',
            'stripe' => 'Stripe (Carte)',
            'card' => 'Carte bancaire',
            'manuel' => 'Demande manuelle',
        ];

        $aggregated = [];
        foreach ($rows as $method => $amount) {
            $label = $labelMap[$method] ?? ucfirst(str_replace('_', ' ', $method ?: 'Autre'));
            $aggregated[$label] = ($aggregated[$label] ?? 0) + (float) $amount;
        }

        if (empty($aggregated)) {
            $aggregated = ['Aucun encaissement' => 0];
        }

        $colors = [
            '#0ea5e9', // Sky blue (Virement)
            '#f97316', // Orange (Orange Money)
            '#eab308', // Yellow (MTN)
            '#10b981', // Emerald (Cash)
            '#8b5cf6', // Purple (Cheque)
            '#ec4899', // Pink (Apport)
            '#6366f1', // Indigo (Carte)
            '#64748b', // Slate (Autre)
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Volume (FCFA)',
                    'data' => array_values($aggregated),
                    'backgroundColor' => array_slice($colors, 0, count($aggregated)),
                    'borderWidth' => 2,
                    'borderColor' => '#ffffff',
                ],
            ],
            'labels' => array_keys($aggregated),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
