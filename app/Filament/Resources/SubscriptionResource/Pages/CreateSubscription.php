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

        // 1. Détermination serveur de la VL officielle <= date de valeur
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

        // 2. Calcul strict du montant de placement net et des frais d'entrée
        if ($montantTotal > 0) {
            $taux = max(0.0, $tauxFrais) / 100.0;
            $montantPlacement = round($montantTotal / (1.0 + $taux));
            $fraisEntree = round($montantTotal - $montantPlacement);

            $data['investment_amount'] = (int) $montantPlacement;
            $data['subscription_fee'] = (int) $fraisEntree;
        } else {
            $montantPlacement = 0;
            $data['investment_amount'] = 0;
            $data['subscription_fee'] = 0;
        }

        // 3. Calcul strict du nombre de parts (sur le placement net !)
        $baseCalcul = $montantPlacement > 0 ? $montantPlacement : $montantTotal;
        if ($vl > 0 && $baseCalcul > 0) {
            $data['nb_parts'] = round($baseCalcul / $vl, 4);
        } else {
            $data['nb_parts'] = 0;
        }

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
