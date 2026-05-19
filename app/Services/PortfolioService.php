<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\ProductVl;
use Carbon\Carbon;

class PortfolioService
{
    /**
     * Calcule la valorisation FCP en temps réel pour un client donné.
     *
     * Formule :
     *   Valorisation = Σ (nb_parts × VL_actuelle_du_produit)
     *   Plus-value   = Valorisation - Coût_de_revient
     *   Rendement    = (Plus-value / Coût_de_revient) × 100
     *
     * @param  int $userId
     * @return array
     */
    public function getClientValuation(int $userId): array
    {
        // Charger uniquement les souscriptions validées (statut = 'Succès')
        $subscriptions = Subscription::where('user_id', $userId)
            ->where('statut', 'Succès')
            ->with('product')
            ->get();

        $positions          = [];
        $valorisation_totale = 0.0;
        $cout_revient_total  = 0.0;

        foreach ($subscriptions as $sub) {
            $product = $sub->product;

            // 1. VL actuelle : prendre la dernière VL publiée pour ce produit
            //    Fallback → VL d'achat si aucune nouvelle VL en base
            $latestVlRecord = ProductVl::where('product_id', $sub->product_id)
                ->orderBy('date_vl', 'desc')
                ->first();

            $vl_actuelle = $latestVlRecord
                ? (float) $latestVlRecord->vl
                : (float) $sub->prix_unitaire; // VL au moment de l'achat

            // 2. Nombre de parts souscrites
            $nb_parts = (float) $sub->nb_parts;

            // 3. Coût de revient = montant total payé lors de la souscription
            $cout_revient = (float) $sub->montant_total;

            // 4. Valorisation de cette ligne
            $valorisation_ligne = $nb_parts * $vl_actuelle;

            // 5. Plus-value latente sur cette ligne
            $plus_value_ligne = $valorisation_ligne - $cout_revient;

            // 6. Rendement de cette ligne (%)
            $rendement_ligne = $cout_revient > 0
                ? ($plus_value_ligne / $cout_revient) * 100
                : 0.0;

            $positions[] = [
                'subscription_id'      => $sub->id,
                'reference'            => $sub->reference_transaction,
                'produit'              => $product ? $product->libelle : 'Produit inconnu',
                'product_id'           => $sub->product_id,
                'vl_achat'             => (float) $sub->prix_unitaire,
                'vl_actuelle'          => round($vl_actuelle, 4),
                'date_vl'              => $latestVlRecord ? $latestVlRecord->date_vl->format('Y-m-d') : null,
                'nb_parts'             => round($nb_parts, 4),
                'cout_revient'         => round($cout_revient, 2),
                'valorisation'         => round($valorisation_ligne, 2),
                'plus_value'           => round($plus_value_ligne, 2),
                'rendement_pct'        => round($rendement_ligne, 4),
                'date_souscription'    => $sub->created_at?->format('Y-m-d'),
            ];

            $valorisation_totale += $valorisation_ligne;
            $cout_revient_total  += $cout_revient;
        }

        $plus_value_totale = $valorisation_totale - $cout_revient_total;
        $rendement_global   = $cout_revient_total > 0
            ? ($plus_value_totale / $cout_revient_total) * 100
            : 0.0;

        return [
            'valorisation_totale' => round($valorisation_totale, 2),
            'cout_revient_total'  => round($cout_revient_total, 2),
            'plus_value_totale'   => round($plus_value_totale, 2),
            'rendement_global'    => round($rendement_global, 4),
            'nb_positions'        => count($positions),
            'positions'           => $positions,
            'calcule_le'          => Carbon::now()->toIso8601String(),
        ];
    }
}
