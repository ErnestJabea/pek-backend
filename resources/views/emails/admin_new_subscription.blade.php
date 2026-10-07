<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Nouvelle Souscription Reçue</title>
</head>
<body style="margin:0;padding:0;background-color:#f6f6f6;font-family:Arial,Helvetica,sans-serif;-webkit-font-smoothing:antialiased;">
<table cellpadding="0" cellspacing="0" role="none" width="100%" style="border-collapse:collapse;background-color:#f6f6f6;padding:0;margin:0;">
    <tbody>
        <tr>
            <td align="center" style="padding:20px 10px;">
                <table cellpadding="0" cellspacing="0" role="none" width="600" style="border-collapse:collapse;background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,0.05);max-width:100%;">
                    <!-- Header -->
                    <tr>
                        <td align="center" style="background-color:#491d00;padding:30px 20px;">
                            <h1 style="color:#ffffff;font-size:26px;font-weight:900;margin:0;letter-spacing:1px;">PEK</h1>
                            <p style="color:#f2e6dc;font-size:11px;margin:5px 0 0;letter-spacing:2px;text-transform:uppercase;">Opérations & Trésorerie</p>
                        </td>
                    </tr>
                    <!-- Badge bar -->
                    <tr>
                        <td style="background-color:#d4af37;height:4px;line-height:4px;font-size:0;">&nbsp;</td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding:35px 30px 25px;">
                            <h2 style="color:#491d00;font-size:20px;font-weight:700;margin-top:0;margin-bottom:15px;">
                                💳 Nouvelle souscription enregistrée
                            </h2>
                            <p style="color:#4a5568;font-size:14px;line-height:1.6;margin-bottom:25px;">
                                Une nouvelle demande de souscription vient d'être initiée par un investisseur :
                            </p>

                            <table cellpadding="8" cellspacing="0" width="100%" style="border-collapse:collapse;margin-bottom:25px;background-color:#faf7f5;border-radius:6px;border:1px solid #ebdcd3;">
                                <tr>
                                    <td width="35%" style="color:#718096;font-size:13px;font-weight:600;border-bottom:1px solid #ebdcd3;">Référence :</td>
                                    <td style="color:#491d00;font-size:13px;font-weight:700;border-bottom:1px solid #ebdcd3;">{{ $subscription->reference_transaction }}</td>
                                </tr>
                                <tr>
                                    <td style="color:#718096;font-size:13px;font-weight:600;border-bottom:1px solid #ebdcd3;">Souscripteur :</td>
                                    <td style="color:#1a202c;font-size:13px;font-weight:700;border-bottom:1px solid #ebdcd3;">
                                        {{ $subscription->user ? "{$subscription->user->first_name} {$subscription->user->last_name}" : 'Client #' . $subscription->user_id }}
                                        <br><span style="color:#718096;font-weight:400;font-size:12px;">({{ $subscription->user?->email }})</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="color:#718096;font-size:13px;font-weight:600;border-bottom:1px solid #ebdcd3;">Fonds souscrit :</td>
                                    <td style="color:#1a202c;font-size:13px;font-weight:700;border-bottom:1px solid #ebdcd3;">{{ $subscription->product?->libelle ?? 'FCP PEK' }}</td>
                                </tr>
                                <tr>
                                    <td style="color:#718096;font-size:13px;font-weight:600;border-bottom:1px solid #ebdcd3;">Montant versé :</td>
                                    <td style="color:#1a202c;font-size:15px;font-weight:800;border-bottom:1px solid #ebdcd3;">
                                        {{ number_format((float) $subscription->montant_total, 0, ',', ' ') }} FCFA
                                    </td>
                                </tr>
                                <tr>
                                    <td style="color:#718096;font-size:13px;font-weight:600;border-bottom:1px solid #ebdcd3;">Mode de paiement :</td>
                                    <td style="color:#1a202c;font-size:13px;border-bottom:1px solid #ebdcd3;text-transform:capitalize;">
                                        {{ str_replace('_', ' ', $subscription->moyen_paiement) }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="color:#718096;font-size:13px;font-weight:600;border-bottom:1px solid #ebdcd3;">Statut actuel :</td>
                                    <td style="color:#1a202c;font-size:13px;border-bottom:1px solid #ebdcd3;">
                                        <span style="display:inline-block;padding:2px 8px;border-radius:4px;font-weight:700;font-size:11px;background-color:{{ $subscription->statut === 'Succès' ? '#c6f6d5;color:#22543d' : '#feebc8;color:#7b341e' }};">
                                            {{ $subscription->statut }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="color:#718096;font-size:13px;font-weight:600;">Date :</td>
                                    <td style="color:#1a202c;font-size:13px;">{{ now()->format('d/m/Y à H:i') }}</td>
                                </tr>
                            </table>

                            <div style="text-align:center;margin:30px 0 20px;">
                                <a href="{{ config('app.url') }}/admin/subscriptions/{{ $subscription->id }}/edit"
                                   style="display:inline-block;background-color:#491d00;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;padding:12px 28px;border-radius:6px;">
                                    Gérer cette Souscription
                                </a>
                            </div>

                            <p style="color:#a0aec0;font-size:12px;line-height:1.4;margin-top:20px;text-align:center;">
                                Vous recevez cet e-mail car votre adresse est configurée pour recevoir les alertes des nouvelles souscriptions PEK.
                            </p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td align="center" style="background-color:#faf7f5;padding:15px;border-top:1px solid #ebdcd3;">
                            <p style="color:#a0aec0;font-size:11px;margin:0;">
                                &copy; {{ date('Y') }} KORI Asset Management — Tous droits réservés.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </tbody>
</table>
</body>
</html>
