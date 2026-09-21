<?php

namespace App\Services;

use App\Models\ProductVl;
use App\Models\Subscription;
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
     */
    public function getClientValuation(int $userId): array
    {
        // Charger uniquement les souscriptions validées (statut = 'Succès')
        $subscriptions = Subscription::where('user_id', $userId)
            ->where('statut', 'Succès')
            ->with('product')
            ->get();

        $productIds = $subscriptions->pluck('product_id')->unique();

        // Récupérer la dernière VL publiée pour chaque produit
        $latestVls = ProductVl::whereIn('product_id', $productIds)
            ->orderByDesc('date_vl')
            ->get()
            ->groupBy('product_id')
            ->map->first();

        $positions = [];
        $productGrouped = [];
        $valorisation_totale = 0.0;
        $cout_revient_total = 0.0;

        foreach ($subscriptions as $sub) {
            $product = $sub->product;
            $productId = $sub->product_id;

            // 1. VL actuelle : prendre la dernière VL publiée pour ce produit
            //    Fallback -> VL du produit ou VL au moment de l'achat
            $latestVlRecord = $latestVls->get($productId);

            $vl_actuelle = $latestVlRecord
                ? (float) $latestVlRecord->vl
                : ($product ? (float) $product->vl : (float) $sub->prix_unitaire);

            $nb_parts = (float) $sub->nb_parts;
            $cout_revient = (float) $sub->montant_total;
            $valorisation_ligne = $nb_parts * $vl_actuelle;
            $plus_value_ligne = $valorisation_ligne - $cout_revient;
            $rendement_ligne = $cout_revient > 0
                ? ($plus_value_ligne / $cout_revient) * 100
                : 0.0;

            // Détail par souscription / transaction
            $positions[] = [
                'subscription_id' => $sub->id,
                'reference' => $sub->reference_transaction,
                'produit' => $product ? $product->libelle : 'Produit inconnu',
                'product_id' => $productId,
                'vl_achat' => (float) $sub->prix_unitaire,
                'vl_actuelle' => round($vl_actuelle, 4),
                'date_vl' => $latestVlRecord ? $latestVlRecord->date_vl->format('Y-m-d') : null,
                'nb_parts' => round($nb_parts, 4),
                'cout_revient' => round($cout_revient, 2),
                'valorisation' => round($valorisation_ligne, 2),
                'plus_value' => round($plus_value_ligne, 2),
                'rendement_pct' => round($rendement_ligne, 4),
                'date_souscription' => $sub->created_at?->format('Y-m-d'),
            ];

            // Regroupement par produit pour le cumul
            if (! isset($productGrouped[$productId])) {
                $productGrouped[$productId] = [
                    'product_id' => $productId,
                    'produit' => $product ? $product->libelle : 'Produit inconnu',
                    'code_produit' => $product ? $product->name : '',
                    'nb_parts_total' => 0.0,
                    'cout_revient_total' => 0.0,
                    'vl_actuelle' => round($vl_actuelle, 4),
                    'date_vl' => $latestVlRecord ? $latestVlRecord->date_vl->format('Y-m-d') : null,
                    'nb_souscriptions' => 0,
                ];
            }

            $productGrouped[$productId]['nb_parts_total'] += $nb_parts;
            $productGrouped[$productId]['cout_revient_total'] += $cout_revient;
            $productGrouped[$productId]['nb_souscriptions'] += 1;

            $valorisation_totale += $valorisation_ligne;
            $cout_revient_total += $cout_revient;
        }

        // Calculer les synthèses par produit (PMP, valorisation, plus-value, rendement)
        $productPositions = [];
        foreach ($productGrouped as $pid => $data) {
            $nbParts = $data['nb_parts_total'];
            $coutRevient = $data['cout_revient_total'];
            $vlActuelle = $data['vl_actuelle'];
            $pmp = $nbParts > 0 ? $coutRevient / $nbParts : 0.0;
            $valorisation = $nbParts * $vlActuelle;
            $plusValue = $valorisation - $coutRevient;
            $rendementPct = $coutRevient > 0 ? ($plusValue / $coutRevient) * 100 : 0.0;

            $productPositions[] = [
                'product_id' => $pid,
                'produit' => $data['produit'],
                'code_produit' => $data['code_produit'],
                'nb_parts_total' => round($nbParts, 4),
                'pmp' => round($pmp, 4), // Prix Moyen Pondéré d'Achat
                'vl_actuelle' => round($vlActuelle, 4),
                'date_vl' => $data['date_vl'],
                'cout_revient_total' => round($coutRevient, 2),
                'valorisation_actuelle' => round($valorisation, 2),
                'plus_value' => round($plusValue, 2),
                'rendement_pct' => round($rendementPct, 4),
                'nb_souscriptions' => $data['nb_souscriptions'],
            ];
        }

        $plus_value_totale = $valorisation_totale - $cout_revient_total;
        $rendement_global = $cout_revient_total > 0
            ? ($plus_value_totale / $cout_revient_total) * 100
            : 0.0;
        $total_parts = array_sum(array_column($positions, 'nb_parts'));

        return [
            'valorisation_totale' => round($valorisation_totale, 2),
            'cout_revient_total' => round($cout_revient_total, 2),
            'plus_value_totale' => round($plus_value_totale, 2),
            'rendement_global' => round($rendement_global, 4),
            'total_parts' => round($total_parts, 4),
            'nb_positions' => count($positions),
            'nb_produits' => count($productPositions),
            'product_positions' => $productPositions,
            'positions' => $positions,
            'calcule_le' => Carbon::now()->toIso8601String(),
        ];
    }
}
