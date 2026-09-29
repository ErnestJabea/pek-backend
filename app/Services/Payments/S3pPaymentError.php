<?php

namespace App\Services\Payments;

final class S3pPaymentError
{
    /**
     * Libellé court de l'erreur pour les rapports et dashboards.
     */
    public static function label(?string $code): string
    {
        return match ((string) $code) {
            '703201' => 'Délai USSD expiré',
            '703202' => 'Refusé sur le téléphone',
            '703203' => 'Code PIN incorrect',
            '703107' => 'Solde insuffisant',
            '703103' => 'Compte opérateur bloqué',
            '702102' => 'Montant inférieur au minimum',
            '702103' => 'Montant supérieur au plafond',
            default => 'Échec de transaction',
        };
    }

    /**
     * Message clair et rassurant pour l'investisseur.
     */
    public static function message(?string $code, ?string $operator = null): string
    {
        $message = match ((string) $code) {
            '703201' => 'Le délai de confirmation sur votre téléphone a expiré.',
            '703202' => 'La demande de paiement a été refusée ou annulée depuis votre téléphone.',
            '703203' => 'Le code secret saisi auprès de l’opérateur était incorrect. Ne communiquez jamais ce code à PEK.',
            '703107' => 'Le solde de votre compte Mobile Money est insuffisant pour effectuer ce paiement.',
            '703103' => 'Votre portefeuille Mobile Money est bloqué. Contactez votre opérateur.',
            '702102' => 'Le montant est inférieur au minimum accepté par l’opérateur.',
            '702103' => 'Le montant dépasse le plafond unitaire accepté par l’opérateur.',
            default => 'Le prestataire signale un problème pour ce paiement. Si vous constatez un débit, contactez le support avec votre référence PEK.',
        };
        $name = match ($operator) {
            'orange_money' => 'Orange Money',
            'mtn_momo' => 'MTN MoMo',
            default => null,
        };

        return $name ? $name.' : '.$message : $message;
    }

    /**
     * Conseil ou action concrète à suggérer au client.
     */
    public static function advice(?string $code): string
    {
        return match ((string) $code) {
            '703201' => 'Gardez votre écran déverrouillé et confirmez le débit dès réception de l’invite USSD.',
            '703202' => 'Si vous n’avez pas refusé ce paiement, vérifiez son état avant toute nouvelle tentative.',
            '703203' => 'Vérifiez attentivement votre code secret Mobile Money avant de valider la demande.',
            '703107' => 'Veuillez recharger votre compte Mobile Money (montant + frais opérateur) puis réessayer.',
            '703103' => 'Rapprochez-vous d’une agence de votre opérateur pour régulariser votre compte ou utilisez un autre numéro.',
            '702102' => 'Ajustez le montant de votre souscription pour respecter le montant minimum.',
            '702103' => 'Pour ce montant, nous vous recommandons d’opter pour le paiement par virement bancaire.',
            default => 'Consultez le suivi de cette transaction et contactez le support PEK avant de lancer un autre paiement.',
        };
    }

    /**
     * Indique si l'utilisateur peut réessayer immédiatement ou s'il doit d'abord agir.
     */
    public static function canRetry(?string $code): bool
    {
        return in_array($code, ['703201', '703202', '703203', '703107', '702102'], true);
    }

    /**
     * Retourne un payload structuré complet pour l'API.
     */
    public static function toArray(?string $code): array
    {
        return [
            'error_code' => $code,
            'error_label' => self::label($code),
            'message' => self::message($code),
            'action_advice' => self::advice($code),
            'can_retry' => self::canRetry($code),
        ];
    }
}
