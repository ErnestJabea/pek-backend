<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'type',
        'read_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getTitleAttribute($value): string
    {
        if (app()->getLocale() === 'en') {
            return self::translateTitleToEn((string) $value);
        }

        return (string) $value;
    }

    public function getBodyAttribute($value): string
    {
        if (app()->getLocale() === 'en') {
            return self::translateBodyToEn((string) $value);
        }

        return (string) $value;
    }

    public static function translateTitleToEn(string $title): string
    {
        $map = [
            'Demande de mise à jour transmise' => 'Update request submitted',
            'Coordonnées bancaires en cours de validation' => 'Bank details under review',
            'Mise à jour validée ✅' => 'Update approved ✅',
            'Mise à jour validée' => 'Update approved',
            'Mise à jour non validée ⚠️' => 'Update not approved ⚠️',
            'Mise à jour non validée' => 'Update not approved',
            'Souscription Validée ✅' => 'Subscription Confirmed ✅',
            'Souscription Validée' => 'Subscription Confirmed',
            'Paiement non abouti ⚠️' => 'Payment Unsuccessful ⚠️',
            'Paiement non abouti' => 'Payment Unsuccessful',
            'Dossier KYC soumis' => 'KYC application submitted',
            'Dossier KYC validé' => 'KYC application approved',
            'Dossier KYC rejeté' => 'KYC application rejected',
            'Demande de modification client' => 'Client change request',
        ];

        return $map[$title] ?? $title;
    }

    public static function translateBodyToEn(string $body): string
    {
        // 1. Demande de modification (Personne Physique - Salarié secteur privé...)
        if (preg_match('/^Votre demande de modification \((.+?)\) a bien été transmise à l\'équipe Kori et est en attente de validation\./i', $body, $matches)) {
            $inner = self::translateInnerTermsToEn($matches[1]);
            return "Your update request ({$inner}) has been successfully submitted to the Kori team and is pending validation.";
        }

        // 2. Coordonnées bancaires en attente
        if (preg_match('/^Vos coordonnées bancaires \((.+?)\) ont été envoyées pour vérification par l\'équipe Kori\./i', $body, $matches)) {
            return "Your bank details ({$matches[1]}) have been submitted for verification by the Kori team.";
        }

        // 3. Demande relative à vos coordonnées bancaires / catégorie client validée
        if (preg_match('/^Votre demande relative à vos (.+?) a été validée avec succès par l\'équipe Kori\./i', $body, $matches)) {
            $inner = self::translateInnerTermsToEn($matches[1]);
            return "Your request regarding your {$inner} has been successfully approved by the Kori team.";
        }

        // 4. Demande de modification non retenue
        if (preg_match('/^Votre demande de modification \((.+?)\) n\'a pas été retenue\s*:\s*(.+)$/i', $body, $matches)) {
            $inner = self::translateInnerTermsToEn($matches[1]);
            return "Your change request ({$inner}) was not approved: {$matches[2]}";
        }

        // 5. Souscription validée
        if (preg_match('/^Votre souscription (.+?) pour (.+?) a été validée\. Vos parts sont créditées\./i', $body, $matches)) {
            return "Your subscription {$matches[1]} for {$matches[2]} has been confirmed. Your shares have been credited.";
        }

        // 6. Paiement souscription non abouti
        if (preg_match('/^Votre paiement pour la souscription (.+?) \((.+?)\) a échoué\./i', $body, $matches)) {
            return "Your payment for subscription {$matches[1]} ({$matches[2]}) has failed.";
        }

        // 7. Demande admin
        if (preg_match('/^Le client (.+?) a soumis une mise à jour \((.+?)\) à valider\./i', $body, $matches)) {
            $inner = self::translateInnerTermsToEn($matches[2]);
            return "Client {$matches[1]} submitted an update ({$inner}) to be reviewed.";
        }

        return $body;
    }

    public static function translateInnerTermsToEn(string $text): string
    {
        $replace = [
            'Personne Physique' => 'Individual',
            'Personne Morale' => 'Legal Entity',
            'Salarié secteur privé' => 'Private sector employee',
            'Salarié secteur public' => 'Public sector employee',
            'Profession libérale' => 'Self-employed / Professional',
            'Commerçant / Entrepreneur' => 'Merchant / Entrepreneur',
            'Retraité' => 'Retired',
            'Étudiant' => 'Student',
            'Sans emploi' => 'Unemployed',
            'Particulier' => 'Individual',
            'Entreprise' => 'Company',
            'Institutionnel' => 'Institutional',
            'coordonnées bancaires' => 'bank details',
            'catégorie client' => 'client category',
            'nature & catégorie client' => 'nature & client category',
        ];

        return strtr($text, $replace);
    }
}
