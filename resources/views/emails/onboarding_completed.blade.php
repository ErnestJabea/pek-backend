<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Suivi de votre dossier PEK</title>
</head>

<body style="margin:0;background:#f6f6f6;font-family:Arial,sans-serif;color:#333">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f6f6;padding:24px">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                    style="max-width:600px;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.06)">
                    <!-- Header -->
                    <tr>
                        <td style="background:#491d00;color:#fff;padding:32px;text-align:center">
                            <h1 style="margin:0;font-size:26px;font-weight:900;letter-spacing:2px">PEK</h1>
                            <p
                                style="margin:6px 0 0;font-size:11px;letter-spacing:3px;text-transform:uppercase;opacity:0.8">
                                Plan d'Épargne Kori</p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:36px 32px;line-height:1.7">
                            @if ($type === 'client')
                                <p style="margin:0 0 16px">Bonjour <strong>{{ $recipientFirstName }}</strong>,</p>

                                <p style="margin:0 0 16px">Votre dossier réglementaire a bien été soumis et est
                                    désormais examiné par notre équipe de conformité.</p>

                                <p style="margin:0 0 16px">Pour protéger vos données, aucune pièce d'identité ni aucun
                                    document KYC n'est joint à cet e-mail. Tous les documents sont accessibles
                                    uniquement depuis l'interface sécurisée.</p>

                                {{-- Affichage des documents manquants --}}
                                @if (!empty($missingDocs))
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                                        style="margin:20px 0">
                                        <tr>
                                            <td
                                                style="background:#fff8ed;border-left:4px solid #d97706;border-radius:6px;padding:16px 20px">
                                                <p
                                                    style="margin:0 0 10px;font-size:13px;font-weight:bold;color:#92400e;text-transform:uppercase;letter-spacing:0.05em">
                                                    Documents à fournir
                                                </p>
                                                <p style="margin:0 0 10px;font-size:13px;color:#78350f">
                                                    Pour accélérer le traitement de votre dossier, veuillez compléter
                                                    les pièces suivantes depuis votre application :
                                                </p>
                                                <ul style="margin:0;padding-left:18px;color:#92400e;font-size:13px">
                                                    @foreach ($missingDocs as $doc)
                                                        <li style="margin-bottom:4px">{{ $doc }}</li>
                                                    @endforeach
                                                </ul>
                                                <p style="margin:14px 0 0;font-size:12px;color:#78350f">
                                                    Connectez-vous à l'application PEK et accédez à votre dossier
                                                    d'onboarding pour les ajouter.
                                                </p>
                                            </td>
                                        </tr>
                                    </table>
                                @else
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                                        style="margin:20px 0">
                                        <tr>
                                            <td
                                                style="background:#f0fdf4;border-left:4px solid #16a34a;border-radius:6px;padding:16px 20px">
                                                <p style="margin:0;font-size:13px;font-weight:bold;color:#14532d">
                                                    Dossier complet
                                                </p>
                                                <p style="margin:6px 0 0;font-size:13px;color:#166534">
                                                    Tous vos documents ont été fournis. Notre équipe procède à leur
                                                    vérification.
                                                </p>
                                            </td>
                                        </tr>
                                    </table>
                                @endif

                                <p style="margin:16px 0 0;font-size:13px;color:#666">
                                    Vous serez notifié par e-mail dès que votre dossier sera examiné. En cas de
                                    question, contactez notre équipe de conformité.
                                </p>
                            @else
                                <p style="margin:0 0 12px">Un dossier réglementaire est prêt à être examiné dans le
                                    back-office sécurisé.</p>
                                <p style="margin:0 0 6px"><strong>Référence :</strong> {{ $reference }}</p>
                                <p style="margin:0 0 6px"><strong>Niveau de vigilance :</strong> {{ $riskLevel }}</p>

                                @if (!empty($missingDocs))
                                    <p style="margin:16px 0 6px;font-weight:bold;color:#92400e">⚠ Documents manquants
                                        dans ce dossier :</p>
                                    <ul style="margin:0 0 12px;padding-left:18px;color:#92400e;font-size:13px">
                                        @foreach ($missingDocs as $doc)
                                            <li style="margin-bottom:4px">{{ $doc }}</li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p style="margin:12px 0;color:#166534;font-weight:bold">✓ Dossier fourni complet
                                        (tous les documents présents).</p>
                                @endif

                                <p style="margin:0">Les documents doivent être consultés uniquement depuis le
                                    back-office authentifié. Ils ne sont pas transmis par e-mail.</p>
                            @endif

                            <p
                                style="margin-top:32px;color:#999;font-size:11px;border-top:1px solid #eee;padding-top:16px">
                                Ce message ne contient volontairement aucune donnée KYC détaillée. Pour toute question,
                                utilisez l'application PEK.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background:#f9f9f9;padding:20px 32px;text-align:center;border-top:1px solid #eee">
                            <p style="margin:0;font-size:11px;color:#999">PEK — Plan d'Épargne Kori · Kori Asset
                                Management · Douala, Cameroun</p>
                            <p style="margin:6px 0 0;font-size:11px;color:#bbb">By <a href="https://ejabbing.com"
                                    style="color:#bbb">E-jabbing</a></p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
