<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Product;
use App\Models\ProductVl;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;

class ClientStatementService
{
    /**
     * Génère l'ensemble des données structurées pour le Relevé de Compte FCP (écran et PDF).
     */
    public function getStatementData(User|Client $client, ?string $startDateStr = null, ?string $endDateStr = null, ?int $productId = null): array
    {
        Carbon::setLocale('fr');

        // 1. Définition de la période
        $endDate = $endDateStr ? Carbon::parse($endDateStr)->endOfDay() : now()->endOfDay();
        $startDate = $startDateStr ? Carbon::parse($startDateStr)->startOfDay() : $endDate->copy()->startOfMonth()->startOfDay();

        // Label du mois / de la période
        $isFullMonth = $startDate->day === 1 && $startDate->format('Y-m') === $endDate->format('Y-m') && $endDate->isSameDay($endDate->copy()->endOfMonth());
        $labelPeriode = $isFullMonth 
            ? ucfirst($startDate->translatedFormat('F Y'))
            : 'Période du ' . $startDate->format('d/m/Y') . ' au ' . $endDate->format('d/m/Y');

        // 2. Fiche signalétique du client
        $onboarding = $client->onboardingSession;
        $payload = $onboarding ? $onboarding->getSubmittedPayload() : [];

        $nom = $payload['nom'] ?? $client->last_name ?? '';
        $prenom = $payload['prenom'] ?? $client->first_name ?? '';
        $nomComplet = $payload['nom_complet'] ?? trim("{$prenom} {$nom}");
        if (empty($nomComplet)) {
            $nomComplet = trim("{$nom} {$prenom}");
        }
        if (empty($nomComplet)) {
            $nomComplet = $client->name ?? 'Client KORI';
        }

        $email = $payload['email'] ?? $client->email ?? '';
        $telephone = $payload['tel'] ?? $client->phone ?? '';
        $adresse = $payload['adresse'] ?? $client->city ?? '';
        $ville = $payload['ville'] ?? $client->city ?? 'Yaoundé';
        $pays = $payload['pays_residence'] ?? $client->country ?? 'Cameroun';
        $adresseComplete = trim("{$adresse}, {$ville}");
        if (empty(trim($adresseComplete, ', '))) {
            $adresseComplete = $pays;
        }

        // Date de 1ère souscription historique
        $firstSub = Subscription::where('user_id', $client->id)
            ->where('statut', 'Succès')
            ->orderBy('value_date')
            ->orderBy('created_at')
            ->first();

        $firstSubDate = $firstSub ? ($firstSub->value_date ?? $firstSub->created_at) : null;
        $ancienneteText = $firstSubDate ? Carbon::parse($firstSubDate)->diffForHumans(now(), [
            'parts' => 2,
            'syntax' => Carbon::DIFF_RELATIVE_TO_NOW,
        ]) : 'Nouveau client';

        // 3. Identification des fonds concernés
        $productsQuery = Product::query()->where('is_active', true);
        if ($productId) {
            $productsQuery->where('id', $productId);
        } else {
            // Trouver tous les fonds où le client a des souscriptions validées à la date de fin
            $clientProductIds = Subscription::where('user_id', $client->id)
                ->where('statut', 'Succès')
                ->where(function ($q) use ($endDate) {
                    $q->whereDate('value_date', '<=', $endDate->toDateString())
                      ->orWhereNull('value_date');
                })
                ->pluck('product_id')
                ->unique()
                ->toArray();

            if (empty($clientProductIds)) {
                // S'il n'en a pas encore, prendre le produit par défaut ou tous
                $clientProductIds = Product::where('is_active', true)->pluck('id')->take(1)->toArray();
            }
            $productsQuery->whereIn('id', $clientProductIds);
        }

        $products = $productsQuery->get();

        $fundStatements = [];
        $totalConsolideValorisation = 0;
        $totalConsolideInvesti = 0;
        $totalConsolidePlusValue = 0;

        foreach ($products as $product) {
            // A. Souscriptions antérieures à la période (< startDate)
            $subsBefore = Subscription::where('user_id', $client->id)
                ->where('product_id', $product->id)
                ->where('statut', 'Succès')
                ->whereDate('value_date', '<', $startDate->toDateString())
                ->get();

            $nbPartsPrecedent = (float) $subsBefore->sum('nb_parts');

            // B. Mouvements sur la période ([startDate, endDate])
            $subsPeriod = Subscription::where('user_id', $client->id)
                ->where('product_id', $product->id)
                ->where('statut', 'Succès')
                ->whereBetween('value_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->orderBy('value_date')
                ->get();

            $montantBrutInvestiPeriode = (float) $subsPeriod->sum('montant_total');
            $fraisSouscriptionPeriode = (float) $subsPeriod->sum('subscription_fee');
            $montantNetInvestiPeriode = (float) $subsPeriod->sum('investment_amount');
            if ($montantNetInvestiPeriode <= 0 && $montantBrutInvestiPeriode > 0) {
                $montantNetInvestiPeriode = $montantBrutInvestiPeriode - $fraisSouscriptionPeriode;
            }

            $nbPartsMois = (float) $subsPeriod->sum('nb_parts');
            $nbPartsRachetees = 0.0; // Pas de module de rachat pour l'instant

            // C. Situation cumulée à la date de fin (<= endDate)
            $subsUpToEnd = Subscription::where('user_id', $client->id)
                ->where('product_id', $product->id)
                ->where('statut', 'Succès')
                ->whereDate('value_date', '<=', $endDate->toDateString())
                ->get();

            $nombrePartsCumule = $nbPartsPrecedent + $nbPartsMois;
            if ($nombrePartsCumule <= 0 && $subsUpToEnd->isNotEmpty()) {
                $nombrePartsCumule = (float) $subsUpToEnd->sum('nb_parts');
            }

            // Récupérer la dernière VL officielle à date (<= endDate)
            $latestVlRecord = ProductVl::where('product_id', $product->id)
                ->whereDate('date_vl', '<=', $endDate->toDateString())
                ->orderByDesc('date_vl')
                ->first();

            $vlADate = $latestVlRecord 
                ? (float) $latestVlRecord->vl 
                : (float) ($product->vl ?: 10000.0);

            $dateVlEffectuee = $latestVlRecord 
                ? $latestVlRecord->date_vl->format('d/m/Y') 
                : $endDate->format('d/m/Y');

            // Calculs cumulés à date
            $capitalTotalInvesti = (int) round($subsUpToEnd->sum(function ($sub) {
                return (float) ($sub->investment_amount ?: $sub->montant_net ?: ($sub->montant_total - (float) ($sub->subscription_fee ?: 0)));
            }));

            $totalEncaisseCumule = (int) round($subsUpToEnd->sum('montant_total'));
            $totalFraisCumule = (int) round($subsUpToEnd->sum('subscription_fee'));
            $montantTotalSouscriptionsCumule = $capitalTotalInvesti;
            $montantRegularise = $totalFraisCumule;

            $valorisationPortefeuille = (int) round($nombrePartsCumule * $vlADate);
            $plusValue = (int) round($valorisationPortefeuille - $capitalTotalInvesti);

            $totalConsolideValorisation += $valorisationPortefeuille;
            $totalConsolideInvesti += $capitalTotalInvesti;
            $totalConsolidePlusValue += $plusValue;

            $fundStatements[] = [
                'product' => $product,
                'fcp_nom' => mb_strtoupper($product->libelle ?: 'FCP KORI SERENITE', 'UTF-8'),
                'synthetique' => [
                    'date_valorisation' => $endDate->format('d/m/Y'),
                    'date_vl_effective' => $dateVlEffectuee,
                    'vl_a_date' => $vlADate,
                    'vl_a_date_display' => number_format($vlADate, 2, ',', ' '),
                    'capital_total_investi' => $capitalTotalInvesti,
                    'capital_total_investi_display' => number_format($capitalTotalInvesti, 0, ',', ' '),
                    'nb_parts_cumule' => $nombrePartsCumule,
                    'nb_parts_cumule_display' => number_format($nombrePartsCumule, 2, ',', ' '),
                    'valorisation_portefeuille' => $valorisationPortefeuille,
                    'valorisation_portefeuille_display' => number_format($valorisationPortefeuille, 0, ',', ' '),
                    'plus_value' => $plusValue,
                    'plus_value_display' => ($plusValue >= 0 ? '' : '-') . number_format(abs($plusValue), 0, ',', ' '),
                ],
                'mouvements' => [
                    'designation' => 'Mouvements du Mois',
                    'nb_parts_precedent' => $nbPartsPrecedent,
                    'nb_parts_precedent_display' => number_format($nbPartsPrecedent, 2, ',', ' '),
                    'montant_brut_investi' => $montantBrutInvestiPeriode > 0 ? (int) round($montantBrutInvestiPeriode) : null,
                    'montant_brut_investi_display' => $montantBrutInvestiPeriode > 0 ? number_format((int) round($montantBrutInvestiPeriode), 0, ',', ' ') : '-',
                    'frais_souscription' => $fraisSouscriptionPeriode > 0 ? (int) round($fraisSouscriptionPeriode) : null,
                    'frais_souscription_display' => $fraisSouscriptionPeriode > 0 ? number_format((int) round($fraisSouscriptionPeriode), 0, ',', ' ') : '-',
                    'nb_parts_mois' => $nbPartsMois,
                    'nb_parts_mois_display' => ($nbPartsMois >= 0 ? '+' : '') . number_format($nbPartsMois, 2, ',', ' '),
                    'nb_parts_rachetees' => $nbPartsRachetees,
                    'nb_parts_rachetees_display' => number_format($nbPartsRachetees, 2, ',', ' '),
                    'nb_parts_actuel' => $nombrePartsCumule,
                    'nb_parts_actuel_display' => number_format($nombrePartsCumule, 2, ',', ' '),
                    'details_transactions' => $subsPeriod->map(function ($sub) {
                        return [
                            'reference' => $sub->reference_transaction,
                            'date' => $sub->value_date ? Carbon::parse($sub->value_date)->format('d/m/Y') : $sub->created_at->format('d/m/Y'),
                            'montant_total' => (int) round($sub->montant_total),
                            'frais' => (int) round($sub->subscription_fee ?: 0),
                            'montant_net' => (int) round($sub->investment_amount ?: ($sub->montant_total - (float) ($sub->subscription_fee ?: 0))),
                            'vl' => (float) $sub->prix_unitaire,
                            'nb_parts' => (float) $sub->nb_parts,
                        ];
                    })->toArray(),
                ],
                'regularisation' => [
                    'total_encaisse' => $totalEncaisseCumule,
                    'total_encaisse_display' => number_format($totalEncaisseCumule, 0, ',', ' '),
                    'total_frais' => $totalFraisCumule,
                    'total_frais_display' => number_format($totalFraisCumule, 0, ',', ' '),
                    'montant_total_souscriptions' => $montantTotalSouscriptionsCumule,
                    'montant_total_souscriptions_display' => number_format($montantTotalSouscriptionsCumule, 0, ',', ' '),
                    'montant_regularise' => $montantRegularise,
                    'montant_regularise_display' => number_format($montantRegularise, 0, ',', ' '),
                ],
                'valorisation_finale' => [
                    'date_label' => $endDate->format('d/m/Y'),
                    'montant' => $valorisationPortefeuille,
                    'montant_display' => number_format($valorisationPortefeuille, 0, ',', ' '),
                ],
            ];
        }

        return [
            'client' => [
                'id' => $client->id,
                'nom_complet' => $nomComplet,
                'email' => $email,
                'telephone' => $telephone,
                'adresse' => $adresseComplete,
                'first_subscription_date' => $firstSubDate ? Carbon::parse($firstSubDate)->format('d/m/Y') : null,
                'seniority' => $ancienneteText,
            ],
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'start_date_display' => $startDate->format('d/m/Y'),
                'end_date' => $endDate->format('Y-m-d'),
                'end_date_display' => $endDate->format('d/m/Y'),
                'label' => $labelPeriode,
            ],
            'fund_statements' => $fundStatements,
            'consolide' => [
                'total_valorisation' => $totalConsolideValorisation,
                'total_valorisation_display' => number_format($totalConsolideValorisation, 0, ',', ' '),
                'total_investi' => $totalConsolideInvesti,
                'total_investi_display' => number_format($totalConsolideInvesti, 0, ',', ' '),
                'total_plus_value' => $totalConsolidePlusValue,
                'total_plus_value_display' => ($totalConsolidePlusValue >= 0 ? '+' : '-') . number_format(abs($totalConsolidePlusValue), 0, ',', ' '),
                'nb_fonds' => count($fundStatements),
            ],
        ];
    }
}
