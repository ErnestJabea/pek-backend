<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Alerte SLA Onboarding PEK</title>
</head>
<body style="font-family:Arial,sans-serif;background:#f6f6f6;padding:20px;color:#333">
    <table align="center" width="600" style="background:#fff;border-radius:12px;padding:30px;box-shadow:0 2px 8px rgba(0,0,0,0.08)">
        <tr>
            <td style="background:#491d00;color:#fff;padding:20px;border-radius:8px;text-align:center">
                <h2 style="margin:0">⚠ Alerte Dépassement SLA — Onboarding</h2>
            </td>
        </tr>
        <tr>
            <td style="padding:20px 0;line-height:1.6">
                <p>Bonjour,</p>
                <p>Attention : <strong>{{ $overdueSessions->count() }} dossier(s) d'onboarding</strong> en attente dépassent le délai de validation configuré de <strong>{{ $slaHours }} heures</strong>.</p>
                
                <table width="100%" border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;border-color:#eee;margin:20px 0;font-size:13px">
                    <tr style="background:#fafafa">
                        <th>Référence</th>
                        <th>Client</th>
                        <th>Soumis le</th>
                        <th>Attente</th>
                    </tr>
                    @foreach($overdueSessions as $session)
                        <tr>
                            <td><strong>{{ $session->reference }}</strong></td>
                            <td>{{ $session->user?->first_name }} {{ $session->user?->last_name }} ({{ $session->user?->email }})</td>
                            <td>{{ $session->updated_at?->format('d/m/Y H:i') }}</td>
                            <td style="color:#d97706;font-weight:bold">{{ round($session->updated_at?->diffInHours(now())) }}h</td>
                        </tr>
                    @endforeach
                </table>

                <p style="margin-top:20px">Veuillez vous connecter au Backoffice PEK pour traiter ces dossiers dans les meilleurs délais.</p>
            </td>
        </tr>
    </table>
</body>
</html>
