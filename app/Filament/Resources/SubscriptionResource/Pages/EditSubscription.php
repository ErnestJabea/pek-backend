<?php

namespace App\Filament\Resources\SubscriptionResource\Pages;

use App\Filament\Actions\BankSubscriptionActions;
use App\Filament\Resources\SubscriptionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditSubscription extends EditRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        $refresh = function () {
            $this->getRecord()->refresh();
            $this->refreshFormData(['statut', 'nb_parts', 'prix_unitaire', 'value_date', 'valuation_status']);
        };

        return [
            BankSubscriptionActions::confirm(Action::class)->after($refresh),
            BankSubscriptionActions::value(Action::class)->after($refresh),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $montantTotal = (float) ($data['montant_total'] ?? 0);
        $investmentAmount = (float) ($data['investment_amount'] ?? 0);
        $subscriptionFee = (float) ($data['subscription_fee'] ?? 0);

        if ($investmentAmount > 0 && $subscriptionFee > 0 && $montantTotal > 0) {
            $taux = 0.01;
            if (abs($investmentAmount - round($montantTotal / (1 + $taux))) <= 1) {
                $data['mode_calcul_frais'] = 'en_sus';
                $data['taux_frais'] = '1';
            } elseif (abs($subscriptionFee - round($montantTotal * $taux)) <= 1) {
                $data['mode_calcul_frais'] = 'deduit';
                $data['taux_frais'] = '1';
            } else {
                $data['mode_calcul_frais'] = 'manuel';
            }
        } elseif ($montantTotal > 0) {
            // Si les montants nets/frais n'étaient pas renseignés en base
            $fee = round($montantTotal * 0.01);
            $data['mode_calcul_frais'] = 'deduit';
            $data['taux_frais'] = '1';
            $data['subscription_fee'] = (string) ((int) $fee);
            $data['investment_amount'] = (string) ((int) max(0, $montantTotal - $fee));
        } else {
            $data['mode_calcul_frais'] = 'deduit';
            $data['taux_frais'] = '1';
        }

        if (empty($data['value_date'])) {
            $data['value_date'] = !empty($data['created_at']) ? substr($data['created_at'], 0, 10) : now()->toDateString();
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        abort_unless(
            auth()->user()?->hasRole('super_admin') || auth()->user()?->can('update_subscription'),
            403,
            'Vous ne disposez pas des droits RBAC nécessaires (update_subscription) pour modifier une souscription.'
        );

        // Nettoyage des champs virtuels non persistés directement
        unset($data['taux_frais']);

        return $data;
    }

    protected function afterSave(): void
    {
        $subscription = $this->getRecord();

        if ($subscription->statut === 'Succès') {
            try {
                app(\App\Services\PortfolioService::class)->getClientValuation($subscription->user_id);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Erreur actualisation portefeuille après édition souscription', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
