<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Joyeux Anniversaire !</title>
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

                    <!-- Bannière Anniversaire -->
                    <tr>
                        <td style="background:#fff8ed;border-bottom:1px solid #fed7aa;padding:16px 32px;text-align:center">
                            <span style="display:inline-block;background:#fef3c7;color:#92400e;padding:6px 18px;border-radius:9999px;font-weight:bold;font-size:14px;border:1px solid #fde68a">
                                🎂 Joyeux Anniversaire !
                            </span>
                        </td>
                    </tr>

                    <!-- Corps du message -->
                    <tr>
                        <td style="padding:36px 32px;line-height:1.7">
                            <p style="margin:0 0 16px;font-size:16px">
                                Cher(e) <strong>{{ $user->first_name }} {{ $user->last_name }}</strong>,
                            </p>

                            <p style="margin:0 0 16px;font-size:15px;color:#334155">
                                En ce jour si spécial, toute l'équipe de <strong>KORI Asset Management</strong> tient à vous souhaiter un très heureux anniversaire !
                            </p>

                            <!-- Message personnalisé encadré -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0">
                                <tr>
                                    <td style="background:#fdfcfb;border-left:4px solid #491d00;border-radius:6px;padding:20px 24px;border-top:1px solid #f3ebe1;border-right:1px solid #f3ebe1;border-bottom:1px solid #f3ebe1">
                                        <p style="margin:0;font-size:14px;line-height:1.6;color:#491d00;font-style:italic">
                                            « {{ $customMessage }} »
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 24px;font-size:14px;color:#475569">
                                Nous profitons de cette occasion pour vous remercier chaleureusement de votre confiance au sein du <strong>Portefeuille Épargne Kori (PEK)</strong>. C'est un privilège de vous accompagner chaque jour dans la concrétisation de vos projets patrimoniaux et d'investissement.
                            </p>

                            <!-- Bouton CTA -->
                            <div style="text-align:center;margin:32px 0">
                                <a href="{{ $frontendUrl }}"
                                    style="background-color:#491d00;color:#ffffff;padding:14px 36px;text-decoration:none;font-weight:bold;border-radius:8px;display:inline-block;font-size:14px;letter-spacing:0.5px">
                                    Accéder à mon espace PEK
                                </a>
                            </div>

                            <p style="margin-top:32px;color:#94a3b8;font-size:12px;border-top:1px solid #eee;padding-top:16px;text-align:center">
                                KORI Asset Management — Société de Gestion d'Actifs agréée par la COSUMAF
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
