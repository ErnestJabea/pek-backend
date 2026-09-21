<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Alerte SLA Souscriptions PEK</title>
</head>
<body style="font-family:Arial,sans-serif;background:#f6f6f6;padding:20px;color:#333">
    <table align="center" width="600" style="background:#fff;border-radius:12px;padding:30px;box-shadow:0 2px 8px rgba(0,0,0,0.08)">
        <tr>
            <td style="background:#491d00;color:#fff;padding:20px;border-radius:8px;text-align:center">
                <h2 style="margin:0">⚠ Alerte Dépassement SLA — Souscriptions</h2>
            </td>
        </tr>
        <tr>
            <td style="padding:20px 0;line-height:1.6">
                <p>Bonjour,</p>
                <p>Attention : <strong>{{ $overdueSubscriptions->count() }} souscription(s) payée(s)</strong> nécessitent encore un contrôle interne (Conformité ou Rapprochement Comptable) et dépassent le délai SLA de <strong>{{ $slaHours }} heures</strong>.</p>
                
                <table width="100%" border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;border-color:#eee;margin:20px 0;font-size:13px">
                    <tr style="background:#fafafa">
                        <th>Réf. Trans.</th>
                        <th>Client</th>
                        <th>Montant</th>
                        <th>Paiement Le</th>
                        <th>Conformité</th>
                        <th>Comptabilité</th>
                    </tr>
                    @foreach($overdueSubscriptions as $sub)
                        <tr>
                            <td><strong>{{ $sub->reference_transaction }}</strong></td>
                            <td>{{ $sub->user?->first_name }} {{ $sub->user?->last_name }}</td>
                            <td>{{ number_format((float)$sub->montant_total, 0, ',', ' ') }} XAF</td>
                            <td>{{ $sub->created_at?->format('d/m/Y H:i') }}</td>
                            <td>{!! $sub->compliance_reviewed_at ? '<span style="color:green">OK</span>' : '<span style="color:red">En attente</span>' !!}</td>
                            <td>{!! $sub->accounting_reviewed_at ? '<span style="color:green">OK</span>' : '<span style="color:red">En attente</span>' !!}</td>
                        </tr>
                    @endforeach
                </table>

                <p style="margin-top:20px">Veuillez vous connecter au Backoffice PEK pour finaliser les contrôles internes requis.</p>
            </td>
        </tr>
    </table>
</body>
</html>
