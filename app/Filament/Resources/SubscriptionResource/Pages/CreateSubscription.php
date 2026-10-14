<?php

namespace App\Filament\Resources\SubscriptionResource\Pages;

use App\Filament\Resources\SubscriptionResource;
use App\Models\Product;
use App\Models\ProductVl;
use Filament\Resources\Pages\CreateRecord;

class CreateSubscription extends CreateRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $productId = $data['product_id'] ?? 1;
        $valueDate = $data['value_date'] ?? now()->toDateString();
        $montantTotal = (float) ($data['montant_total'] ?? 0);
        $tauxFrais = isset($data['taux_frais']) ? (float) $data['taux_frais'] : 1.0;

        $modeCalcul = $data['mode_calcul_frais'] ?? 'en_sus';

        // 1. Détermination de la VL (manuelle ou auto-détectée)
        if ($modeCalcul === 'manuel' && isset($data['prix_unitaire']) && (float) $data['prix_unitaire'] > 0) {
            $vl = (float) $data['prix_unitaire'];
            $data['nav_date'] = $valueDate;
        } else {
            $nearestVl = ProductVl::where('product_id', $productId)
                ->where('date_vl', '<=', $valueDate)
                ->orderByDesc('date_vl')
                ->first();

            if ($nearestVl) {
                $vl = (float) $nearestVl->vl;
                $data['prix_unitaire'] = $vl;
                $data['nav_date'] = $nearestVl->date_vl?->toDateString() ?: (string) $nearestVl->date_vl;
            } else {
                $earliestVl = ProductVl::where('product_id', $productId)
                    ->orderBy('date_vl', 'asc')
                    ->first();
                $product = Product::find($productId);
                $vl = $earliestVl ? (float) $earliestVl->vl : (float) ($product?->vl ?? 10000.0);
                $data['prix_unitaire'] = $vl;
                $data['nav_date'] = $earliestVl?->date_vl?->toDateString() ?? $valueDate;
            }
        }

        // 2. Calcul strict du montant de placement net et des frais d'entrée selon le mode
        $hasFormNet = isset($data['investment_amount']) && (float) $data['investment_amount'] > 0;
        $hasFormFee = isset($data['subscription_fee']) && (float) $data['subscription_fee'] >= 0;

        if ($hasFormNet && $hasFormFee && $modeCalcul === 'manuel') {
            // Mode manuel : préserver la saisie explicite de l'opérateur
            $montantPlacement = (float) $data['investment_amount'];
            $fraisEntree = (float) $data['subscription_fee'];
        } elseif ($modeCalcul === 'deduit' && $montantTotal > 0) {
            // Mode Frais déduits : frais prélevés sur l'enveloppe totale (ex: 50 000 versés => 49 500 net + 500 frais)
            $taux = max(0.0, $tauxFrais) / 100.0;
            $fraisEntree = round($montantTotal * $taux);
            $montantPlacement = max(0.0, $montantTotal - $fraisEntree);
        } elseif ($montantTotal > 0) {
            // Mode Frais en sus (défaut) : le client verse le capital désiré + 1% (ex: 50 500 versés => 50 000 net + 500 frais)
            $taux = max(0.0, $tauxFrais) / 100.0;
            $montantPlacement = round($montantTotal / (1.0 + $taux));
            $fraisEntree = round($montantTotal - $montantPlacement);
        } else {
            $montantPlacement = 0;
            $fraisEntree = 0;
        }

        $data['investment_amount'] = (int) $montantPlacement;
        $data['subscription_fee'] = (int) $fraisEntree;

        // 3. Calcul strict du nombre de parts (sur le placement net !)
        $baseCalcul = $montantPlacement > 0 ? $montantPlacement : $montantTotal;
        if ($vl > 0 && $baseCalcul > 0) {
            $data['nb_parts'] = round($baseCalcul / $vl, 4);
        } else {
            $data['nb_parts'] = 0;
        }

        // Nettoyage des champs virtuels de calcul non présents en BD
        unset($data['mode_calcul_frais']);
        unset($data['taux_frais']);

        // 4. Garantir la référence transaction
        if (empty($data['reference_transaction'])) {
            $data['reference_transaction'] = 'HIST-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        }

        // 5. Si l'opérateur valide directement en "Succès"
        if (($data['statut'] ?? '') === 'Succès') {
            $data['valuation_status'] = 'valued';
            $data['funds_received_at'] = $data['funds_received_at'] ?? now();
            $data['payment_confirmed_at'] = $data['payment_confirmed_at'] ?? now();
            $data['manager_reviewed_at'] = now();
            $data['manager_reviewed_by_user_id'] = auth()->id();
            $data['compliance_reviewed_at'] = now();
            $data['compliance_reviewed_by_user_id'] = auth()->id();
            $data['accounting_reviewed_at'] = now();
            $data['accounting_reviewed_by_user_id'] = auth()->id();
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
