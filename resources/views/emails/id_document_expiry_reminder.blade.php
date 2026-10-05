<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rappel réglementaire - Pièce d'identité PEK</title>
</head>

<body style="margin:0;background:#f6f6f6;font-family:Arial,sans-serif;color:#333">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f6f6;padding:24px">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                    style="max-width:600px;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.06)">
                    <!-- Header avec Logo PEK -->
                    <tr>
                        <td style="background:#491d00;color:#fff;padding:32px;text-align:center">
                            <h1 style="margin:0;font-size:26px;font-weight:900;letter-spacing:2px">PEK</h1>
                            <p style="margin:6px 0 0;font-size:11px;letter-spacing:3px;text-transform:uppercase;opacity:0.8">
                                Plan d'Épargne Kori · Kori Asset Management
                            </p>
                        </td>
                    </tr>

                    <!-- Bannière Statut -->
                    <tr>
                        <td style="background:{{ $isExpired ? '#fef2f2' : '#fffbeb' }};border-bottom:1px solid {{ $isExpired ? '#fecaca' : '#fde68a' }};padding:16px 32px;text-align:center">
                            @if($isExpired)
                                <span style="display:inline-block;background:#fee2e2;color:#991b1b;padding:6px 18px;border-radius:9999px;font-weight:bold;font-size:13px;border:1px solid #fca5a5">
                                    ⚠️ Pièce expirée le {{ $expirationDateFormatted }}
                                </span>
                            @else
                                <span style="display:inline-block;background:#fef3c7;color:#92400e;padding:6px 18px;border-radius:9999px;font-weight:bold;font-size:13px;border:1px solid #fde68a">
                                    ⏳ Expire le {{ $expirationDateFormatted }} ({{ $daysRemaining }} jours restants)
                                </span>
                            @endif
                        </td>
                    </tr>

                    <!-- Corps du message -->
                    <tr>
                        <td style="padding:36px 32px;line-height:1.7">
                            <p style="margin:0 0 16px;font-size:16px">
                                Bonjour <strong>{{ $user->first_name }} {{ $user->last_name }}</strong>,
                            </p>

                            <p style="margin:0 0 16px;font-size:15px;color:#334155">
                                @if($isExpired)
                                    Nous constatons que votre pièce d'identification (<strong>{{ $pieceType }}</strong>) est <strong>arrivée à expiration le {{ $expirationDateFormatted }}</strong>.
                                @else
                                    Nous vous informons que votre pièce d'identification (<strong>{{ $pieceType }}</strong>) <strong>arrive à expiration dans {{ $daysRemaining }} jours (le {{ $expirationDateFormatted }})</strong>.
                                @endif
                            </p>

                            <!-- Notice Réglementaire Encadrée -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0">
                                <tr>
                                    <td style="background:#f8fafc;border-left:4px solid #491d00;border-radius:6px;padding:16px 20px">
                                        <p style="margin:0;font-size:13px;color:#334155;line-height:1.6">
                                            Conformément aux exigences réglementaires de la COSUMAF relatives à la tenue des comptes et à la lutte contre le blanchiment des capitaux (LAB/FT), la validité de votre pièce d'identification est indispensable pour la gestion de votre compte d'investissement.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 24px;font-size:14px;color:#475569">
                                Afin de garantir la continuité de vos opérations (souscriptions, rachats et édition de vos attestations fiscales), nous vous invitons à renseigner votre nouveau document dès maintenant sur votre espace client.
                            </p>

                            <!-- Bouton CTA -->
                            <div style="text-align:center;margin:32px 0">
                                <a href="{{ $renewUrl }}"
                                    style="background-color:#491d00;color:#ffffff;padding:14px 36px;text-decoration:none;font-weight:bold;border-radius:8px;display:inline-block;font-size:14px;letter-spacing:0.5px">
                                    Mettre à jour ma pièce d'identité
                                </a>
                            </div>

                            <p style="margin-top:24px;color:#64748b;font-size:12px;line-height:1.5;background:#f8fafc;padding:12px 16px;border-radius:6px">
                                <strong>Pièces acceptées :</strong> Carte Nationale d'Identité (CNI) en cours de validité, Passeport biométrique, ou Titre de séjour / Carte de Résident.
                            </p>

                            <p style="margin-top:24px;color:#94a3b8;font-size:11px;border-top:1px solid #eee;padding-top:16px;text-align:center">
                                Si vous avez déjà transmis votre nouvelle pièce récemment, veuillez ne pas tenir compte de ce rappel. Notre équipe de conformité procède actuellement à son contrôle.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer Officiel -->
                    <tr>
                        <td style="background:#f9f9f9;padding:20px 32px;text-align:center;border-top:1px solid #eee">
                            <p style="margin:0;font-size:11px;color:#999">
                                PEK — Plan d'Épargne Kori · Kori Asset Management · Douala, Cameroun
                            </p>
                            <p style="margin:6px 0 0;font-size:11px;color:#bbb">
                                By <a href="https://ejabbing.com" style="color:#bbb;text-decoration:none">E-jabbing</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
