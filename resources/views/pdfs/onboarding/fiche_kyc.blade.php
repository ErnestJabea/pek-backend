<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Fiche KYC - Onboarding PEK</title>
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
            padding: 15px 30px;
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
            margin-top: 8px;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        table.data-table td {
            padding: 4px 8px;
            border-bottom: 1px solid #edf2f7;
            vertical-align: top;
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

        /* ─── SIGNATURE ───────────────────────────────────────────────────────── */
        .signature-section {
            margin-top: 15px;
            page-break-inside: avoid;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }

        .signature-declaration {
            font-size: 8.5pt;
            color: #4a5568;
            line-height: 1.5;
        }

        .signature-label {
            font-size: 9pt;
            font-weight: bold;
            color: #242424;
            display: block;
            margin-bottom: 5px;
        }

        .signature-box {
            height: 80px;
            width: 230px;
            text-align: center;
            vertical-align: middle;
            display: inline-block;
        }

        .signature-box img {
            max-height: 75px;
            max-width: 220px;
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
        }
    </style>
</head>

<body>

    {{-- ─── EN-TÊTE ──────────────────────────────────────────────────────────── --}}
    <div class="header">
        <table class="header-inner">
            <tr>
                <td style="vertical-align: middle; width: 60%;">
                    @if (!empty($logo))
                        <img src="{{ $logo }}" alt="Kori Asset Management" style="height: 55px; width: auto;">
                    @else
                        <span class="header-logo-text">PEK</span>
                        <div class="header-subtitle">KORI ASSET MANAGEMENT</div>
                    @endif
                </td>
                <td class="header-meta">
                    <strong>FICHE KYC</strong><br>
                    Réf : <strong>{{ $session->reference }}</strong><br>
                    Date : {{ now()->format('d/m/Y H:i') }}<br>
                    @if ($session->status === 'completed')
                        <span style="color: #491D00; font-weight: bold;">Dossier Complété</span>
                    @else
                        <span style="color: #d97706; font-weight: bold;">En Cours</span>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- ─── CORPS DU DOCUMENT ─────────────────────────────────────────────────── --}}
    <div class="content-area">

        <h1>Fiche de Connaissance Client (KYC)</h1>

        {{-- Section 1 : Informations Personnelles --}}
        <div class="section-title">1. Informations Personnelles</div>
        <table class="data-table">
            <tr>
                <td class="label">Civilité</td>
                <td class="value">{{ $payload['civ'] ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Nom</td>
                <td class="value">{{ strtoupper($payload['nom'] ?? '') ?: '—' }}</td>
            </tr>
            <tr>
                <td class="label">Prénom(s)</td>
                <td class="value">{{ $payload['prenom'] ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Nationalité</td>
                <td class="value">{{ $payload['nat'] ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Date de Naissance</td>
                <td class="value">{{ $payload['dob'] ? date('d/m/Y', strtotime($payload['dob'])) : '—' }}</td>
            </tr>
            <tr>
                <td class="label">Lieu de Naissance</td>
                <td class="value">{{ $payload['lieu_naiss'] ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Adresse de Résidence</td>
                <td class="value">{{ $payload['adresse'] ?? '—' }}</td>
            </tr>
        </table>

        {{-- Section 2 : Coordonnées de Contact --}}
        <div class="section-title">2. Coordonnées de Contact</div>
        <table class="data-table">
            <tr>
                <td class="label">Numéro de Téléphone</td>
                <td class="value">{{ $payload['tel'] ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Adresse E-mail</td>
                <td class="value">{{ $payload['email'] ?? '—' }}</td>
            </tr>
        </table>

        {{-- Section 3 : Informations Professionnelles --}}
        <div class="section-title">3. Informations Professionnelles</div>
        <table class="data-table">
            <tr>
                <td class="label">Profession</td>
                <td class="value">{{ $payload['profession'] ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Nom de l'Employeur</td>
                <td class="value">{{ $payload['employeur'] ?? '—' }}</td>
            </tr>
        </table>

        {{-- Section 4 : Pièce d'Identité --}}
        <div class="section-title">4. Pièce d'Identité Officielle</div>
        <table class="data-table">
            <tr>
                <td class="label">Type de pièce</td>
                <td class="value">{{ $payload['piece'] ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Numéro de pièce</td>
                <td class="value">{{ $payload['num_piece'] ?? '—' }}</td>
            </tr>
        </table>

        {{-- Zone de signature --}}
        <div class="signature-section">
            <table class="signature-table">
                <tr>
                    <td style="width: 100%; text-align: right; vertical-align: top;">
                        <div style="display: inline-block; text-align: right;">
                            <span class="signature-label"
                                style="display: block; text-align: right; margin-bottom: 5px;">Signature du Client
                                :</span>
                            <div class="signature-box">
                                @if (!empty($signature))
                                    <img src="{{ $signature }}" alt="Signature Client">
                                @else
                                    <span
                                        style="color: #a0aec0; font-size: 8.5pt; line-height: 80px; display: block; text-align: center;">
                                        Aucune signature
                                    </span>
                                @endif
                            </div>
                            <div
                                style="font-style: italic; font-size: 9.5pt; color: #2d3748; text-align: right; margin-top: 5px;">
                                Lu et approuvé
                            </div>
                            <div style="margin-top: 5px; font-size: 9.5pt; color: #2d3748; text-align: right;">
                                Fait à {{ $client_city ?? ($session ? $session->getClientCity() : 'Douala') }}, le <strong>{{ now()->format('d/m/Y') }}</strong>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

    </div>{{-- /content-area --}}

    {{-- ─── PIED DE PAGE ─────────────────────────────────────────────────────── --}}
    <div class="footer">
        <strong>Kori Asset Management S.A.</strong> — Agrément COSUMAF-SGP-01/2023 — Siège social : Douala,
        Cameroun<br>
        FCP KORI SERENITE · Document généré le {{ now()->format('d/m/Y à H:i') }}
    </div>

</body>

</html>
