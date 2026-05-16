<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reçu de Souscription PEK</title>
    <style>
        body { font-family: sans-serif; color: #333; line-height: 1.5; }
        .header { text-align: center; border-bottom: 2px solid #0f172b; padding-bottom: 20px; margin-bottom: 30px; }
        .logo { font-size: 24px; font-weight: bold; color: #0f172b; }
        .title { font-size: 18px; color: #666; margin-top: 5px; }
        .details { margin-bottom: 30px; }
        .details-row { display: flex; justify-content: space-between; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .label { font-weight: bold; color: #555; }
        .footer { margin-top: 50px; text-align: center; font-size: 10px; color: #999; border-top: 1px solid #eee; pt: 10px; }
        .status { display: inline-block; padding: 5px 15px; border-radius: 20px; font-weight: bold; font-size: 12px; }
        .status-pending { background-color: #fef3c7; color: #92400e; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">PEK - Plan d'Épargne Kori</div>
        <div class="title">REÇU DE SOUSCRIPTION PROVISOIRE</div>
    </div>

    <div class="details">
        <p><strong>Client :</strong> {{ $subscription->user->first_name }} {{ $subscription->user->last_name }}</p>
        <p><strong>Email :</strong> {{ $subscription->user->email }}</p>
        <p><strong>Date :</strong> {{ $subscription->created_at->format('d/m/Y H:i') }}</p>
    </div>

    <div style="background-color: #f8fafc; padding: 20px; border-radius: 10px;">
        <table style="width: 100%; border-collapse: collapse;">
            <tr style="border-bottom: 1px solid #cbd5e1;">
                <th style="text-align: left; padding: 10px;">Description</th>
                <th style="text-align: right; padding: 10px;">Détails</th>
            </tr>
            <tr>
                <td style="padding: 10px;">Fonds de placement</td>
                <td style="text-align: right; padding: 10px; font-weight: bold;">{{ $subscription->product->libelle }}</td>
            </tr>
            <tr>
                <td style="padding: 10px;">Nombre de parts</td>
                <td style="text-align: right; padding: 10px;">{{ $subscription->nb_parts }}</td>
            </tr>
            <tr>
                <td style="padding: 10px;">Prix unitaire (VL)</td>
                <td style="text-align: right; padding: 10px;">{{ number_format($subscription->prix_unitaire, 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr style="border-top: 2px solid #0f172b;">
                <td style="padding: 10px; font-weight: bold; font-size: 16px;">MONTANT TOTAL</td>
                <td style="text-align: right; padding: 10px; font-weight: bold; font-size: 16px; color: #0f172b;">{{ number_format($subscription->montant_total, 0, ',', ' ') }} FCFA</td>
            </tr>
        </table>
    </div>

    <div style="margin-top: 30px;">
        <p><strong>Référence de transaction :</strong> {{ $subscription->reference_transaction }}</p>
        <p><strong>Moyen de paiement :</strong> {{ strtoupper(str_replace('_', ' ', $subscription->moyen_paiement)) }}</p>
        <p><strong>Statut :</strong> <span class="status status-pending">EN ATTENTE DE VALIDATION</span></p>
    </div>

    <div class="footer">
        Ce document est un reçu provisoire généré automatiquement par la plateforme PEK.<br>
        © 2026 Kori Asset Management - Tous droits réservés.
    </div>
</body>
</html>
