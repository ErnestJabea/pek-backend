<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Nouvelle Inscription Client</title>
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
                            <p style="color:#f2e6dc;font-size:11px;margin:5px 0 0;letter-spacing:2px;text-transform:uppercase;">Plan d'Épargne KORI — Administration</p>
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
                                ✨ Nouvelle inscription client enregistrée
                            </h2>
                            <p style="color:#4a5568;font-size:14px;line-height:1.6;margin-bottom:25px;">
                                Un nouvel investisseur vient de finaliser son inscription sur la plateforme mobile PEK. Voici les informations de son profil :
                            </p>

                            <table cellpadding="8" cellspacing="0" width="100%" style="border-collapse:collapse;margin-bottom:25px;background-color:#faf7f5;border-radius:6px;border:1px solid #ebdcd3;">
                                <tr>
                                    <td width="35%" style="color:#718096;font-size:13px;font-weight:600;border-bottom:1px solid #ebdcd3;">Nom & Prénom :</td>
                                    <td style="color:#1a202c;font-size:13px;font-weight:700;border-bottom:1px solid #ebdcd3;">{{ $client->first_name }} {{ $client->last_name }}</td>
                                </tr>
                                <tr>
                                    <td style="color:#718096;font-size:13px;font-weight:600;border-bottom:1px solid #ebdcd3;">Adresse Email :</td>
                                    <td style="color:#1a202c;font-size:13px;border-bottom:1px solid #ebdcd3;">{{ $client->email }}</td>
                                </tr>
                                <tr>
                                    <td style="color:#718096;font-size:13px;font-weight:600;border-bottom:1px solid #ebdcd3;">Téléphone :</td>
                                    <td style="color:#1a202c;font-size:13px;border-bottom:1px solid #ebdcd3;">{{ $client->phone ?? 'Non renseigné' }}</td>
                                </tr>
                                <tr>
                                    <td style="color:#718096;font-size:13px;font-weight:600;border-bottom:1px solid #ebdcd3;">Pays / Ville :</td>
                                    <td style="color:#1a202c;font-size:13px;border-bottom:1px solid #ebdcd3;">{{ $client->country ?? '-' }} / {{ $client->city ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td style="color:#718096;font-size:13px;font-weight:600;border-bottom:1px solid #ebdcd3;">Pièce d'identité :</td>
                                    <td style="color:#1a202c;font-size:13px;border-bottom:1px solid #ebdcd3;">{{ $client->type_piece ?? 'CNI' }} (N° {{ $client->num_piece ?? '-' }})</td>
                                </tr>
                                <tr>
                                    <td style="color:#718096;font-size:13px;font-weight:600;">Date d'inscription :</td>
                                    <td style="color:#1a202c;font-size:13px;">{{ now()->format('d/m/Y à H:i') }}</td>
                                </tr>
                            </table>

                            <div style="text-align:center;margin:30px 0 20px;">
                                <a href="{{ config('app.url') }}/admin/clients"
                                   style="display:inline-block;background-color:#491d00;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;padding:12px 28px;border-radius:6px;">
                                    Accéder à la liste des Clients
                                </a>
                            </div>

                            <p style="color:#a0aec0;font-size:12px;line-height:1.4;margin-top:20px;text-align:center;">
                                Vous recevez cet e-mail car votre adresse est configurée pour recevoir les alertes des nouvelles inscriptions PEK.
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
