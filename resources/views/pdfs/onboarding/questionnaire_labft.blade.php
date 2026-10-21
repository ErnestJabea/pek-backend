<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Questionnaire LAB-FT - Onboarding PEK</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #2d3748;
            font-size: 8.5pt;
            line-height: 1.25;
            margin: 15px;
        }

        .header {
            background-color: #ffffff;
            border-bottom: 3px solid #491D00;
            padding-bottom: 14px;
            margin-bottom: 20px;
        }

        .header table {
            width: 100%;
        }

        .logo-text {
            font-size: 24px;
            font-weight: bold;
            color: #242424;
        }

        .doc-meta {
            text-align: right;
            font-size: 9pt;
            color: #718096;
        }

        h1 {
            color: #242424;
            font-size: 12.5pt;
            text-transform: uppercase;
            text-align: center;
            margin-bottom: 3px;
            letter-spacing: 1px;
        }

        .subtitle {
            text-align: center;
            font-size: 8pt;
            color: #718096;
            margin-bottom: 10px;
            font-style: italic;
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
            margin-bottom: 8px;
        }

        table.data-table td {
            padding: 4px 8px;
            border-bottom: 1px solid #edf2f7;
            vertical-align: top;
            font-size: 8.5pt;
        }

        table.data-table td.label {
            font-weight: bold;
            color: #4a5568;
            width: 40%;
        }

        table.data-table td.value {
            color: #2d3748;
        }

        .alert-box {
            background-color: #fffaf0;
            border: 1px solid #feebc8;
            border-radius: 6px;
            padding: 6px 10px;
            margin-bottom: 10px;
            color: #c05621;
            font-size: 8.5pt;
        }

        .signature-section {
            margin-top: 12px;
            page-break-inside: avoid;
        }

        .signature-table {
            width: 100%;
            margin-top: 15px;
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

    <div class="header">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="vertical-align: middle; width: 60%;">
                    @if (!empty($logo))
                        <img src="{{ $logo }}" alt="Kori Asset Management" style="height: 55px; width: auto;">
                    @else
                        <span
                            style="font-size: 22px; font-weight: bold; color: #491D00; letter-spacing: 2px;">PEK</span><br>
                        <span
                            style="font-size: 8.5pt; color: #4a5568; font-weight: 600; letter-spacing: 1px; text-transform: uppercase;">KORI
                            ASSET MANAGEMENT</span>
                    @endif
                </td>
                <td style="text-align: right; font-size: 8.5pt; color: #491D00; vertical-align: top;">
                    <strong style="color: #242424; font-size: 9pt;">QUESTIONNAIRE LAB-FT</strong><br>
                    Réf : {{ str_replace('KYC-', 'LABFT-', $session->reference) }}<br>
                    Nom : {{ strtoupper($user->last_name) }} {{ $user->first_name }}
                </td>
            </tr>
        </table>
    </div>

    <h1>Questionnaire LAB/FT</h1>
    <div class="subtitle">Lutte contre le Blanchiment de Capitaux et le Financement du Terrorisme </div>

    @if ($session->risk_level === 'HIGH')
        <div class="alert-box">
            <strong>EXAMEN RENFORCÉ REQUIS :</strong> Des indicateurs de vigilance réglementaire (PPE ou exposition
            géographique/sectorielle) ont été cochés. Ce dossier doit faire l'objet d'une validation manuelle par
            l'équipe conformité.
        </div>
    @endif

    <div class="section-title">1. Situation de Famille & Résidence</div>
    <table class="data-table">
        <tr>
            <td class="label">Situation Matrimoniale</td>
            <td class="value">{{ $payload['situation_mat'] ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">Pays de Résidence Fiscale</td>
            <td class="value">{{ $payload['pays_residence'] ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">Date d'Expiration de la Pièce</td>
            <td class="value">
                {{ $payload['expiration_piece'] ? date('d/m/Y', strtotime($payload['expiration_piece'])) : '' }}</td>
        </tr>
        @if (!empty($payload['agent_kam']))
            <tr>
                <td class="label">Agent Kori Asset Management associé</td>
                <td class="value">{{ $payload['agent_kam'] }}</td>
            </tr>
        @endif
    </table>

    <div class="section-title">2. Profil Économique & Origine des Fonds</div>
    <table class="data-table">
        <tr>
            <td class="label">Secteur d'Activité Professionnel</td>
            <td class="value">{{ $payload['secteur'] ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">Tranche d'estimation des Revenus Annuels</td>
            <td class="value">
                @if (($payload['revenus_annuels'] ?? '') === 'moins_5m')
                    Moins de 5 000 000 FCFA
                @elseif(($payload['revenus_annuels'] ?? '') === '5m_15m' || ($payload['revenus_annuels'] ?? '') === '5_15m')
                    Entre 5 000 000 et 15 000 000 FCFA
                @elseif(($payload['revenus_annuels'] ?? '') === 'plus_15m')
                    Plus de 15 000 000 FCFA
                @else
                    {{ $payload['revenus_annuels'] ?? '' }}
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">Sources des Revenus Déclarées</td>
            <td class="value">
                @php
                    $sources = [];
                    if ($payload['src_salaire'] ?? false) {
                        $sources[] = 'Salaire';
                    }
                    if ($payload['src_pro_liberal'] ?? false) {
                        $sources[] = 'Profession Libérale / Honoraires';
                    }
                    if ($payload['src_foncier'] ?? false) {
                        $sources[] = 'Revenus Fonciers';
                    }
                    if ($payload['src_dividendes'] ?? false) {
                        $sources[] = 'Dividendes / Plus-values';
                    }
                    if ($payload['src_heritage'] ?? false) {
                        $sources[] = 'Héritage';
                    }
                    if ($payload['src_autre_check'] ?? false) {
                        $sources[] = 'Autre : ' . ($payload['src_autre'] ?? '');
                    }
                @endphp
                {{ implode(', ', $sources) }}
            </td>
        </tr>
        <tr>
            <td class="label">Origine des fonds à investir sur PEK</td>
            <td class="value">{{ $payload['origine_fonds'] ?? '' }}</td>
        </tr>
    </table>



    <div class="section-title">3. Indicateurs de Vigilance LAB/FT</div>
    <table class="data-table">
        <tr>
            <td class="label">Êtes-vous une Personne Politiquement Exposée (PPE) ?</td>
            <td class="value">
                <strong>{{ $payload['ppe'] ?? 'Non' }}</strong>
                @if (($payload['ppe'] ?? 'Non') === 'Oui')
                    <br><span style="font-size: 8.5pt; color: #718096;">Détails de la fonction :
                        {{ $payload['ppe_detail'] ?? '' }}</span>
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">Compte bancaire situé dans un pays à haut risque ou sous sanctions ?</td>
            <td class="value"><strong>{{ $payload['pays_risque'] ?? 'Non' }}</strong></td>
        </tr>
        <tr>
            <td class="label">Origine des fonds provenant d'un secteur d'activité sensible ?</td>
            <td class="value"><strong>{{ $payload['secteur_sensible'] ?? 'Non' }}</strong></td>
        </tr>
        <tr>
            <td class="label">Condamnation passée pour blanchiment ou financement du terrorisme ?</td>
            <td class="value"><strong>{{ $payload['condamnation'] ?? 'Non' }}</strong></td>
        </tr>
    </table>

    <div class="section-title">4. Déclarations de Consentement</div>
    <table class="data-table">
        <tr>
            <td style="width: 5%; font-weight: bold; text-align: center;">[X]</td>
            <td style="width: 95%;">Je déclare avoir pris connaissance des règlements généraux et conditions du Fonds
                FCP KORI SÉRÉNITÉ.</td>
        </tr>
        <tr>
            <td style="width: 5%; font-weight: bold; text-align: center;">[X]</td>
            <td style="width: 95%;">J'autorise Kori Asset Management à effectuer le traitement informatique de mes
                données personnelles et financières dans le strict respect de la réglementation en vigueur.</td>
        </tr>
    </table>

    <div class="signature-section">
        <table class="signature-table">
            <tr>
                <td style="width: 100%; text-align: right; vertical-align: top;">
                    <div style="display: inline-block; text-align: right;">
                        <span
                            style="font-size: 8.5pt; font-weight: bold; color: #242424; display: block; text-align: right; margin-bottom: 5px;">Signature
                            du Client :</span>
                        <div class="signature-box">
                            @if ($signature)
                                <img src="{{ $signature }}" alt="Signature Client">
                            @else
                                <span
                                    style="color: #a0aec0; font-size: 9pt; line-height: 80px; display: block; text-align: center;">Pas
                                    de signature</span>
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

    <div class="footer">
        <strong>Kori Asset Management S.A.</strong> — Agrément COSUMAF-SGP-01/2023 — Siège social : Douala,
        Cameroun<br>
        FCP KORI SERENITE · Document généré le {{ now()->format('d/m/Y à H:i') }}
    </div>

</body>

</html>
