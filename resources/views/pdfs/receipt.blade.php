<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Reçu de Souscription - PEK</title>
    <style>
        @page {
            margin: 0;
            size: A4 portrait;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #242424;
            font-size: 9.5pt;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }

        /* ─── EN-TÊTE ─────────────────────────────────────────────────────────── */
        .header {
            background-color: #ffffff;
            padding: 20px 30px 16px 30px;
            border-bottom: 3px solid #491D00;
        }

        .header-inner {
            width: 100%;
            border-collapse: collapse;
        }

        .header-logo img {
            height: 55px;
            width: auto;
        }

        .header-logo-text {
            font-size: 22px;
            font-weight: bold;
            color: #491D00;
            letter-spacing: 2px;
        }

        .header-subtitle {
            font-size: 8.5pt;
            color: #242424;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .header-meta {
            text-align: right;
            font-size: 8.5pt;
            color: #242424;
            vertical-align: top;
        }

        .header-meta strong {
            color: #242424;
            font-size: 9pt;
        }

        /* ─── CONTENU ─────────────────────────────────────────────────────────── */
        .content-area {
            padding: 20px 30px;
        }

        h1 {
            color: #242424;
            font-size: 13pt;
            text-transform: uppercase;
            text-align: center;
            margin: 0 0 15px 0;
            letter-spacing: 1px;
            padding-bottom: 8px;
        }

        .section-title {
            background-color: #491d00;
            border-left: 4px solid #ebb009;
            padding: 8px 10px;
            font-weight: bold;
            color: #ebb009;
            font-size: 9pt;
            margin-top: 15px;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        table.data-table td, table.data-table th {
            padding: 6px 10px;
            border-bottom: 1px solid #edf2f7;
            vertical-align: middle;
        }

        table.data-table tr:last-child td {
            border-bottom: none;
        }

        table.data-table td.label {
            font-weight: bold;
            color: #242424;
            width: 38%;
            font-size: 9.5pt;
            background-color: #fafafa;
        }

        table.data-table td.value {
            color: #242424;
            font-size: 10pt;
            font-weight: 500;
        }

        .total-row {
            background-color: #491d00 !important;
            color: #ffffff !important;
        }

        .total-row td {
            font-weight: bold;
            font-size: 11pt;
            color: #ffffff !important;
            border-bottom: none !important;
        }

        .total-price {
            color: #ebb009 !important;
            font-weight: 900 !important;
            font-size: 12pt !important;
        }

        /* ─── STATUT BADGES ───────────────────────────────────────────────────── */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-weight: bold;
            font-size: 8.5pt;
            text-transform: uppercase;
        }

        .status-success {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        /* ─── PIED DE PAGE ────────────────────────────────────────────────────── */
        .footer {
            background-color: #491d00;
            padding: 8px;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8pt;
            color: #fff;
            line-height: 1.4;
        }
    </style>
</head>

<body>

    @php
        $logoSrc = $logo ?? '';
        if (empty($logoSrc)) {
            $logoPath = public_path('logo-kori.png');
            if (file_exists($logoPath)) {
                $logoSrc = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
            }
        }
        $netAmount = $subscription->montant_net;
        $feesAmount = $subscription->frais_gestion;
        if ($feesAmount < 0) {
            $feesAmount = 0;
        }
    @endphp

    {{-- ─── EN-TÊTE ──────────────────────────────────────────────────────────── --}}
    <div class="header">
        <table class="header-inner">
            <tr>
                <td style="vertical-align: middle; width: 60%;">
                    @if (!empty($logoSrc))
                        <img src="{{ $logoSrc }}" alt="Kori Asset Management" style="height: 55px; width: auto;">
                    @else
                        <span class="header-logo-text">PEK</span>
                        <div class="header-subtitle">KORI ASSET MANAGEMENT</div>
                    @endif
                </td>
                <td class="header-meta">
                    <strong>REÇU DE SOUSCRIPTION</strong><br>
                    Réf : <strong>{{ $subscription->reference_transaction }}</strong><br>
                    Date de demande : {{ $subscription->created_at->format('d/m/Y H:i') }}<br>
                    @if($subscription->value_date)
                        Date de valeur (réception des fonds) : {{ $subscription->value_date->format('d/m/Y') }}<br>
                    @endif
                    @if($subscription->statut === 'Succès')
                        <span style="color: #065f46; font-weight: bold;">Reçu Officiel</span>
                    @else
                        <span style="color: #d97706; font-weight: bold;">Reçu Provisoire</span>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- ─── CORPS DU DOCUMENT ─────────────────────────────────────────────────── --}}
    <div class="content-area">

        @if($subscription->statut === 'Succès')
            <h1>Reçu de Souscription Officiel</h1>
        @else
            <h1>Reçu de Souscription Provisoire</h1>
        @endif

        {{-- Section 1 : Informations du Client --}}
        <div class="section-title">1. Informations du Client</div>
        <table class="data-table">
            <tr>
                <td class="label">Nom Complet</td>
                <td class="value">{{ $subscription->user->first_name }} {{ $subscription->user->last_name }}</td>
            </tr>
            <tr>
                <td class="label">Adresse E-mail</td>
                <td class="value">{{ $subscription->user->email }}</td>
            </tr>
        </table>

        {{-- Section 2 : Détails du Placement --}}
        <div class="section-title">2. Détails du Placement</div>
        <table class="data-table">
            <tr>
                <td class="label">Fonds de placement</td>
                <td class="value" style="font-weight: bold;">{{ $subscription->product->libelle }}</td>
            </tr>
            <tr>
                <td class="label">Nombre de parts</td>
                <td class="value">{{ $subscription->nb_parts }}</td>
            </tr>
            <tr>
                <td class="label">Prix unitaire (VL)</td>
                <td class="value">{{ number_format($subscription->prix_unitaire, 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr>
                <td class="label">Montant Hors Frais</td>
                <td class="value">{{ number_format($netAmount, 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr>
                <td class="label">Frais de souscription (1%)</td>
                <td class="value">{{ number_format($feesAmount, 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr class="total-row">
                <td style="background-color: #491d00;">MONTANT TOTAL</td>
                <td class="total-price" style="background-color: #491d00; text-align: right;">
                    {{ number_format($subscription->montant_total, 0, ',', ' ') }} FCFA
                </td>
            </tr>
        </table>

        {{-- Section 3 : Règlement & Statut --}}
        <div class="section-title">3. Règlement & Statut</div>
        <table class="data-table">
            <tr>
                <td class="label">Référence de Transaction</td>
                <td class="value" style="font-family: monospace; font-size: 10.5pt; font-weight: bold;">{{ $subscription->reference_transaction }}</td>
            </tr>
            <tr>
                <td class="label">Moyen de paiement</td>
                <td class="value">{{ strtoupper(str_replace('_', ' ', $subscription->moyen_paiement)) }}</td>
            </tr>
            <tr>
                <td class="label">Statut de la transaction</td>
                <td class="value">
                    @if($subscription->statut === 'Succès')
                        <span class="status-badge status-success">CONFIRMÉ / SUCCÈS</span>
                    @else
                        <span class="status-badge status-pending">EN ATTENTE DE VALIDATION</span>
                    @endif
                </td>
            </tr>
        </table>

    </div>

    {{-- ─── PIED DE PAGE ─────────────────────────────────────────────────────── --}}
    <div class="footer">
        <strong>Kori Asset Management S.A.</strong> — Agrément COSUMAF-SGP-01/2023 — Siège social : Douala, Cameroun<br>
        @if($subscription->statut === 'Succès')
            Ce document est un reçu officiel généré automatiquement par la plateforme PEK.
        @else
            Ce document est un reçu provisoire généré automatiquement par la plateforme PEK.
        @endif
        <br>© 2026 Kori Asset Management - Tous droits réservés.
    </div>

</body>

</html>
