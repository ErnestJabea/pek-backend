<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mise à jour de votre profil</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f6f6f6; margin: 0; padding: 20px;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
        <tr style="background-color: {{ $changeRequest->status === 'approved' ? '#009a4d' : '#e11d48' }}; text-align: center;">
            <td style="padding: 24px;">
                <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 800;">PEK - PLAN D'EPARGNE KORI</h1>
                <p style="color: rgba(255,255,255,0.85); margin: 6px 0 0; font-size: 12px; letter-spacing: 1px; text-transform: uppercase;">
                    {{ $changeRequest->status === 'approved' ? 'Mise à jour validée' : 'Information concernant votre demande' }}
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding: 30px;">
                <h2 style="color: #1e293b; font-size: 18px; margin-top: 0;">Bonjour {{ $changeRequest->user->first_name }},</h2>
                
                @if($changeRequest->status === 'approved')
                    <p style="color: #475569; font-size: 14px; line-height: 1.6;">
                        Votre demande de mise à jour relative à vos <strong>{{ $changeRequest->request_type === 'bank_account' ? 'coordonnées bancaires' : 'informations de catégorie client' }}</strong> a été examinée et <strong>validée avec succès</strong> par l'équipe de conformité Kori Asset Management.
                    </p>
                    <p style="color: #475569; font-size: 14px; line-height: 1.6;">
                        Vos données sont désormais actives sur votre espace et prêtes pour vos opérations et virements.
                    </p>
                @else
                    <p style="color: #475569; font-size: 14px; line-height: 1.6;">
                        Votre demande de mise à jour n'a pas pu être validée par l'équipe de conformité pour la raison suivante :
                    </p>
                    <div style="background-color: #fff1f2; border-left: 4px solid #e11d48; padding: 14px 16px; border-radius: 8px; margin: 20px 0;">
                        <p style="margin: 0; color: #9f1239; font-size: 13px; font-weight: bold;">
                            {{ $changeRequest->rejection_reason ?? 'Informations incomplètes ou non conformes aux justificatifs.' }}
                        </p>
                    </div>
                    <p style="color: #475569; font-size: 14px; line-height: 1.6;">
                        Vous pouvez vous reconnecter à votre application pour soumettre à nouveau vos informations corrigées.
                    </p>
                @endif

                <div style="text-align: center; margin: 30px 0 10px;">
                    <a href="{{ config('app.frontend_url', 'http://127.0.0.1:5173') }}/profile" style="display: inline-block; background-color: #009a4d; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 12px; font-weight: bold; font-size: 14px;">
                        Accéder à mon Profil PEK
                    </a>
                </div>
            </td>
        </tr>
        <tr>
            <td style="background-color: #f1f5f9; padding: 16px; text-align: center; font-size: 11px; color: #94a3b8;">
                Pour toute question, contactez notre équipe conformité à support@koriassetmanagement.com
            </td>
        </tr>
    </table>
</body>
</html>
