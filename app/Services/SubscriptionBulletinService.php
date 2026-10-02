<?php

namespace App\Services;

use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class SubscriptionBulletinService
{
    /**
     * Prépare toutes les données consolidées pour le Bulletin de Souscription FCP.
     */
    public function getBulletinData(Subscription $subscription): array
    {
        $subscription->loadMissing(['user.onboardingSession', 'product']);
        $user = $subscription->user;
        $session = $user?->onboardingSession;
        $product = $subscription->product;
        $payload = $session ? $session->getSubmittedPayload() : [];

        // 1. Identification du Client
        $nom = $payload['nom'] ?? $user?->last_name ?? '';
        $prenom = $payload['prenom'] ?? $user?->first_name ?? '';
        $nomComplet = $payload['nom_complet'] ?? trim("{$nom} {$prenom}");
        if (empty($nomComplet)) {
            $nomComplet = $user?->name ?? 'Client KORI';
        }

        $adresse = $payload['adresse'] ?? $user?->city ?? 'Douala, Cameroun';
        $telephone = $subscription->payment_phone ?? $payload['tel'] ?? $user?->phone ?? '';
        $email = $payload['email'] ?? $user?->email ?? '';

        $natureClient = strtolower($payload['nature_client'] ?? 'personne_physique');
        $isPersonneMorale = str_contains($natureClient, 'morale') || ! empty($payload['rccm']);
        $isPersonnePhysique = ! $isPersonneMorale;

        $categorieClient = $user?->categorie_client
            ?? $payload['categorie_client']
            ?? 'Particulier';

        // Type de pièce
        $pieceRaw = strtolower($payload['piece'] ?? $payload['type_piece'] ?? 'cni');
        $isCni = str_contains($pieceRaw, 'cni') || str_contains($pieceRaw, 'identite');
        $isPasseport = str_contains($pieceRaw, 'pass');
        $isRccm = str_contains($pieceRaw, 'rccm');
        $isAutrePiece = ! $isCni && ! $isPasseport && ! $isRccm;
        $precisionAutrePiece = $isAutrePiece ? ($payload['piece'] ?? '') : '';

        $numPiece = $payload['num_piece'] ?? $payload['id_number'] ?? '—';
        $compteBancaire = $payload['compte_bancaire'] ?? $payload['rib'] ?? $user?->rib ?? '—';

        // 2. Caractéristiques Financières de l'Opération
        $dateValeurRaw = $subscription->value_date
            ?? $subscription->funds_received_at
            ?? $subscription->created_at;
        $dateValeur = $dateValeurRaw ? Carbon::parse($dateValeurRaw)->format('d/m/Y') : now()->format('d/m/Y');

        $nbParts = (float) $subscription->nb_parts;
        $prixUnitaire = (float) ($subscription->prix_unitaire ?? $product?->vl ?? 10000.00);
        $montantTotal = (float) $subscription->montant_total;

        // Calcul des frais (par défaut 1.00% selon barème)
        $tauxSouscription = '1,00 %';
        $montantFrais = $subscription->frais_gestion
            ?? max(0, $montantTotal - round($montantTotal / 1.01));

        $montantEnLettres = self::numberToFrenchWords((int) round($montantTotal));

        // Moyen de paiement
        $moyenPaiementRaw = strtolower($subscription->moyen_paiement ?? '');
        $isMobile = in_array($moyenPaiementRaw, ['mobile_money', 'orange_money', 'mtn_momo'])
            || ! empty($subscription->mobile_provider);
        $isVirement = in_array($moyenPaiementRaw, ['bank_transfer', 'virement']);
        $isCheque = str_contains($moyenPaiementRaw, 'cheque');
        $isApportTitres = str_contains($moyenPaiementRaw, 'titre');
        $isAutrePaiement = ! $isMobile && ! $isVirement && ! $isCheque && ! $isApportTitres;

        // 3. Signature du Client
        $signatureBase64 = null;
        if ($session && $session->signature_path) {
            $disk = Storage::disk('kyc_private');
            if ($disk->exists($session->signature_path)) {
                $signatureBytes = $disk->get($session->signature_path);
                $signatureMime = @getimagesizefromstring($signatureBytes)['mime'] ?? 'image/png';
                $signatureBase64 = 'data:'.$signatureMime.';base64,'.base64_encode($signatureBytes);
            }
        }

        // 4. Logo KORI en base64 (pour rendu PDF sans problème réseau)
        $logoBase64 = null;
        $logoPath = public_path('logo-kori.png');
        if (! is_file($logoPath)) {
            $logoPath = public_path('logo.png');
        }
        if (is_file($logoPath)) {
            $logoBase64 = 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath));
        }

        return [
            'subscription' => $subscription,
            'user' => $user,
            'product' => $product,
            'fcp_nom' => strtoupper($product?->libelle ?? 'FCP KORI SÉRÉNITÉ'),
            'fcp_agrement' => $product?->agrement_numero ?? 'Agrément N° COSUMAF-FCP-02/2025',
            'nom_complet' => $nomComplet,
            'adresse' => $adresse,
            'telephone' => $telephone,
            'email' => $email,
            'is_personne_morale' => $isPersonneMorale,
            'is_personne_physique' => $isPersonnePhysique,
            'categorie_client' => $categorieClient,
            'is_cni' => $isCni,
            'is_passeport' => $isPasseport,
            'is_rccm' => $isRccm,
            'is_autre_piece' => $isAutrePiece,
            'precision_autre_piece' => $precisionAutrePiece,
            'num_piece' => $numPiece,
            'compte_bancaire' => $compteBancaire,
            'date_valeur' => $dateValeur,
            'nb_parts' => number_format($nbParts, 4, ',', ' '),
            'valeur_liquidative' => number_format($prixUnitaire, 2, ',', ' '),
            'taux_souscription' => $tauxSouscription,
            'montant_frais' => number_format($montantFrais, 0, ',', ' '),
            'montant_total' => number_format($montantTotal, 0, ',', ' '),
            'montant_en_lettres' => $montantEnLettres,
            'is_mobile' => $isMobile,
            'is_virement' => $isVirement,
            'is_cheque' => $isCheque,
            'is_apport_titres' => $isApportTitres,
            'is_autre_paiement' => $isAutrePaiement,
            'signature_client' => $signatureBase64,
            'logo_base64' => $logoBase64,
            'reference_transaction' => $subscription->reference_transaction,
            'date_impression' => now()->format('d/m/Y'),
        ];
    }

    /**
     * Convertit un nombre entier en toutes lettres en français (CFA).
     */
    public static function numberToFrenchWords(int $number): string
    {
        if ($number === 0) {
            return 'zéro';
        }

        $units = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf'];
        $teens = ['dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
        $tens = ['', 'dix', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante-dix', 'quatre-vingts', 'quatre-vingt-dix'];

        if ($number < 10) {
            return $units[$number];
        }

        if ($number < 20) {
            return $teens[$number - 10];
        }

        if ($number < 70) {
            $ten = intdiv($number, 10);
            $unit = $number % 10;
            if ($unit === 1 && $ten !== 8) {
                return $tens[$ten].' et un';
            }

            return $tens[$ten].($unit ? '-'.$units[$unit] : '');
        }

        if ($number < 80) {
            $unit = $number % 10;
            if ($unit === 1) {
                return 'soixante et onze';
            }

            return 'soixante-'.$teens[$unit];
        }

        if ($number < 90) {
            $unit = $number % 10;
            if ($unit === 0) {
                return 'quatre-vingts';
            }

            return 'quatre-vingt-'.$units[$unit];
        }

        if ($number < 100) {
            $unit = $number % 10;

            return 'quatre-vingt-'.$teens[$unit];
        }

        if ($number < 1000) {
            $hundred = intdiv($number, 100);
            $rest = $number % 100;
            $prefix = ($hundred === 1) ? 'cent' : ($units[$hundred].' cents');
            if ($rest) {
                $prefix = ($hundred === 1) ? 'cent' : ($units[$hundred].' cent');

                return $prefix.' '.self::numberToFrenchWords($rest);
            }

            return $prefix;
        }

        if ($number < 1000000) {
            $thousand = intdiv($number, 1000);
            $rest = $number % 1000;
            $thousandStr = self::numberToFrenchWords($thousand);
            // "cents" perd son "s" s'il est suivi d'un autre adjectif numéral (comme "mille")
            if (str_ends_with($thousandStr, 'cents')) {
                $thousandStr = substr($thousandStr, 0, -1);
            }
            $prefix = ($thousand === 1) ? 'mille' : ($thousandStr.' mille');
            if ($rest) {
                return $prefix.' '.self::numberToFrenchWords($rest);
            }

            return $prefix;
        }

        if ($number < 1000000000) {
            $million = intdiv($number, 1000000);
            $rest = $number % 1000000;
            $prefix = ($million === 1) ? 'un million' : (self::numberToFrenchWords($million).' millions');
            if ($rest) {
                return $prefix.' '.self::numberToFrenchWords($rest);
            }

            return $prefix;
        }

        return (string) $number;
    }
}
