<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Demande de modification de profil</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f6f6f6; margin: 0; padding: 20px;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
        <tr style="background-color: #009a4d; text-align: center;">
            <td style="padding: 24px;">
                <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 800;">PEK - KORI ASSET MANAGEMENT</h1>
                <p style="color: rgba(255,255,255,0.85); margin: 6px 0 0; font-size: 12px; letter-spacing: 1px; text-transform: uppercase;">Alerte Conformité & Back-office</p>
            </td>
        </tr>
        <tr>
            <td style="padding: 30px;">
                <h2 style="color: #1e293b; font-size: 18px; margin-top: 0;">Nouvelle demande de modification reçue</h2>
                <p style="color: #475569; font-size: 14px; line-height: 1.6;">
                    Le client <strong>{{ $changeRequest->user->first_name }} {{ $changeRequest->user->last_name }}</strong> ({{ $changeRequest->user->email }}) a soumis une demande de mise à jour qui nécessite votre validation avant application.
                </p>

                <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin: 20px 0;">
                    <p style="margin: 0 0 10px; font-size: 13px; font-weight: bold; color: #009a4d; text-transform: uppercase;">
                        Type : {{ $changeRequest->request_type === 'bank_account' ? 'Coordonnées Bancaires (Virements)' : 'Nature & Catégorie Réglementaire' }}
                    </p>

                    @if($changeRequest->request_type === 'category_nature')
                        <table width="100%" style="font-size: 13px; color: #334155; border-collapse: collapse;">
                            <tr>
                                <td style="padding: 6px 0; font-weight: bold; width: 45%;">Nature demandée :</td>
                                <td style="padding: 6px 0;">{{ $changeRequest->requested_data['nature_client'] ?? 'Non spécifiée' }}</td>
                            </tr>
                            <tr>
                                <td style="padding: 6px 0; font-weight: bold;">Catégorie demandée :</td>
                                <td style="padding: 6px 0;">{{ $changeRequest->requested_data['categorie_client'] ?? 'Non spécifiée' }}</td>
                            </tr>
                            @if(!empty($changeRequest->current_data))
                            <tr>
                                <td style="padding: 6px 0; color: #94a3b8;">Valeurs actuelles :</td>
                                <td style="padding: 6px 0; color: #94a3b8;">{{ $changeRequest->current_data['nature_client'] ?? '' }} / {{ $changeRequest->current_data['categorie_client'] ?? '' }}</td>
                            </tr>
                            @endif
                        </table>
                    @else
                        <table width="100%" style="font-size: 13px; color: #334155; border-collapse: collapse;">
                            <tr>
                                <td style="padding: 6px 0; font-weight: bold; width: 45%;">Établissement bancaire :</td>
                                <td style="padding: 6px 0;">{{ $changeRequest->requested_data['bank_name'] ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td style="padding: 6px 0; font-weight: bold;">Titulaire du compte :</td>
                                <td style="padding: 6px 0;">{{ $changeRequest->requested_data['account_holder_name'] ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td style="padding: 6px 0; font-weight: bold;">Numéro de compte :</td>
                                <td style="padding: 6px 0;">{{ $changeRequest->requested_data['account_number'] ?? 'N/A' }}</td>
                            </tr>
                            @if(!empty($changeRequest->requested_data['bank_code']))
                            <tr>
                                <td style="padding: 6px 0; font-weight: bold;">Clé / Codes RIB :</td>
                                <td style="padding: 6px 0;">Banque: {{ $changeRequest->requested_data['bank_code'] }} | Guichet: {{ $changeRequest->requested_data['branch_code'] }} | Clé: {{ $changeRequest->requested_data['rib_key'] }}</td>
                            </tr>
                            @endif
                        </table>
                    @endif
                </div>

                <div style="text-align: center; margin: 30px 0 10px;">
                    <a href="{{ url('/admin/profile-change-requests') }}" style="display: inline-block; background-color: #009a4d; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 12px; font-weight: bold; font-size: 14px;">
                        Examiner et Valider dans le Back-office
                    </a>
                </div>
            </td>
        </tr>
        <tr>
            <td style="background-color: #f1f5f9; padding: 16px; text-align: center; font-size: 11px; color: #94a3b8;">
                Ce message automatique est réservé à l'équipe conformité de Kori Asset Management.
            </td>
        </tr>
    </table>
</body>
</html>
