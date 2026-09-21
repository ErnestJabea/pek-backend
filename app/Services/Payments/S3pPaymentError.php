<?php

namespace App\Services\Payments;

final class S3pPaymentError
{
    public static function message(?string $code): string
    {
        return match ($code) {
            '703201' => 'Le délai de confirmation sur le téléphone a expiré.',
            '703202' => 'La demande de paiement a été refusée sur le téléphone.',
            '703203' => 'Le code saisi auprès de l’opérateur était incorrect. Ne communiquez jamais ce code à PEK.',
            '703107' => 'Le solde du portefeuille Mobile Money est insuffisant.',
            '703103' => 'Le portefeuille est bloqué. Contactez votre opérateur.',
            '702102' => 'Le montant est inférieur au minimum accepté par l’opérateur.',
            '702103' => 'Le montant dépasse le plafond accepté par l’opérateur.',
            default => 'Échec confirmé par le prestataire. Contactez le support avec la référence PEK.',
        };
    }
}
