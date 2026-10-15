<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Relevé de compte - FCP - {{ $data['client']['nom_complet'] }}</title>
  <style>
    /* ==========================================================================
       KORI ASSET MANAGEMENT · Relevé de compte mensuel FCP
       Conforme à 100% au modèle officiel de relevé
       Compatible DomPDF & impression A4 standard
       ========================================================================== */
    @page {
      size: A4 portrait;
      margin: 12mm 15mm 15mm 15mm;
    }
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    body {
      font-family: "DejaVu Sans", "Helvetica Neue", Arial, sans-serif;
      font-size: 8.5pt;
      line-height: 1.4;
      color: #1A1A1A;
      background: #FFFFFF;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }

    /* Styles pour affichage Web / Écran avec barre d'outils */
    @media screen {
      body {
        background: #F4EFEB;
        padding: 24px 16px 50px 16px;
      }
      .toolbar-container {
        max-width: 820px;
        margin: 0 auto 16px auto;
      }
      .toolbar {
        background: #FFFFFF;
        padding: 12px 20px;
        border-radius: 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 2px 12px rgba(59, 19, 0, 0.08);
        border: 1px solid #E5DCD3;
      }
      .btn-print, .btn-download {
        display: inline-block;
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: bold;
        font-size: 9pt;
        text-decoration: none;
        cursor: pointer;
      }
      .btn-print {
        background: #F3F4F6;
        color: #374151;
        border: 1px solid #D1D5DB;
        margin-right: 8px;
      }
      .btn-download {
        background: #E5A812;
        color: #1A1A1A;
        border: none;
      }
      .sheet-a4 {
        max-width: 820px;
        margin: 0 auto;
        background: #FFFFFF;
        padding: 35px 45px;
        box-shadow: 0 4px 25px rgba(59, 19, 0, 0.09);
        border-radius: 6px;
        border: 1px solid #E5DCD3;
      }
      .fund-page {
        margin-bottom: 40px;
        border-bottom: 2px dashed #E5DCD3;
        padding-bottom: 30px;
      }
      .fund-page:last-child {
        margin-bottom: 0;
        border-bottom: none;
        padding-bottom: 0;
      }
    }

    @media print {
      body {
        background: #FFFFFF;
        padding: 0;
      }
      .toolbar-container {
        display: none !important;
      }
      .sheet-a4 {
        box-shadow: none;
        border: none;
        padding: 0;
      }
      .fund-page {
        page-break-after: always;
      }
      .fund-page:last-child {
        page-break-after: auto;
      }
    }

    /* En-tête */
    .header-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 25px;
    }
    .header-logo {
      width: 45%;
      vertical-align: top;
      text-align: left;
    }
    .header-logo img {
      max-height: 58px;
      width: auto;
    }
    .logo-fallback {
      font-size: 20pt;
      font-weight: 900;
      color: #9C5110;
      letter-spacing: -0.5px;
    }
    .logo-fallback span {
      display: block;
      font-size: 7.5pt;
      font-weight: 700;
      color: #333333;
      letter-spacing: 1px;
      text-transform: uppercase;
    }
    .header-client {
      width: 55%;
      vertical-align: top;
      text-align: right;
      font-size: 8.5pt;
      line-height: 1.35;
      color: #1A1A1A;
    }
    .client-name {
      font-size: 10.5pt;
      font-weight: 900;
      color: #000000;
      margin-bottom: 2px;
      text-transform: uppercase;
    }
    .client-info {
      font-size: 8.5pt;
      color: #333333;
    }

    /* Titres Centrés */
    .title-block {
      text-align: center;
      margin-bottom: 25px;
    }
    .main-title {
      font-size: 15pt;
      font-weight: 900;
      color: #000000;
      letter-spacing: -0.3px;
      margin-bottom: 4px;
    }
    .sub-title {
      font-size: 10pt;
      font-weight: 600;
      color: #333333;
    }

    /* Nom du Fonds */
    .fund-header-banner {
      margin-top: 10px;
      margin-bottom: 15px;
      border-bottom: 2px solid #E5A812;
      padding-bottom: 4px;
    }
    .fund-title {
      font-size: 11pt;
      font-weight: 900;
      color: #C67C00;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    /* Intitulés des sections */
    .section-title {
      font-size: 8pt;
      font-weight: 800;
      color: #555555;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-top: 14px;
      margin-bottom: 5px;
    }

    /* Tableaux de données */
    .data-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 12px;
      font-size: 8pt;
    }
    .data-table th {
      background-color: #E5A812;
      color: #000000;
      font-weight: 800;
      font-size: 7.5pt;
      text-align: center;
      vertical-align: middle;
      padding: 6px 4px;
      border: 1px solid #000000;
      line-height: 1.25;
    }
    .data-table td {
      background-color: #FFFFFF;
      color: #000000;
      font-weight: 500;
      font-size: 8pt;
      padding: 7px 6px;
      border: 1px solid #000000;
      vertical-align: middle;
      text-align: center;
    }
    .data-table td.align-right {
      text-align: right;
    }
    .data-table td.align-left {
      text-align: left;
    }
    .data-table td.text-bold {
      font-weight: 800;
    }
    .data-table td.text-green {
      color: #059669;
      font-weight: 700;
    }
    .data-table td.text-red {
      color: #DC2626;
      font-weight: 700;
    }

    /* Encadré Valorisation Totale (Bas de page) */
    .valorisation-box-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 25px;
      margin-bottom: 30px;
      border: 2px solid #8C4B18;
    }
    .valorisation-box-table td {
      padding: 10px 14px;
      vertical-align: middle;
    }
    .valorisation-box-left {
      width: 60%;
      text-align: left;
      font-size: 9.5pt;
      font-weight: 900;
      color: #000000;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .valorisation-box-right {
      width: 40%;
      text-align: right;
      font-size: 13pt;
      font-weight: 900;
      color: #000000;
    }

    /* Pied de page */
    .statement-footer {
      text-align: center;
      font-size: 7pt;
      color: #444444;
      line-height: 1.4;
      margin-top: 25px;
      padding-top: 12px;
      border-top: 1px solid #E5DCD3;
    }
    .statement-footer a {
      color: #444444;
      text-decoration: none;
    }
  </style>
</head>
<body>

@php
  // Préparation du logo en Base64 pour DomPDF
  $logoBase64 = null;
  $logoCandidates = [
    public_path('logo-kam.png'),
    public_path('logo-kori.png'),
    public_path('logo.png'),
  ];
  foreach ($logoCandidates as $cand) {
    if (file_exists($cand)) {
      $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($cand));
      break;
    }
  }
@endphp

@if (empty($is_pdf))
  <div class="toolbar-container">
    <div class="toolbar">
      <div style="font-weight: bold; color: #482010; font-size: 10pt;">
        Aperçu du Relevé de Compte FCP &bull; {{ $data['period']['label'] }}
      </div>
      <div>
        <a href="javascript:window.print()" class="btn-print">🖨️ Imprimer</a>
        <a href="{{ request()->fullUrlWithQuery(['download' => 'pdf']) }}" class="btn-download">📥 Télécharger le PDF officiel</a>
      </div>
    </div>
  </div>
@endif

<div class="sheet-a4">
  @forelse ($data['fund_statements'] as $fundStatement)
    <div class="fund-page">
      <!-- 1. En-tête : Logo à gauche & Coordonnées Client à droite -->
      <table class="header-table">
        <tr>
          <td class="header-logo">
            @if ($logoBase64)
              <img src="{{ $logoBase64 }}" alt="Kori Asset Management">
            @else
              <div class="logo-fallback">
                KORI
                <span>Asset Management</span>
              </div>
            @endif
          </td>
          <td class="header-client">
            <div class="client-name">{{ $data['client']['nom_complet'] }}</div>
            <div class="client-info">{{ $data['client']['email'] }}</div>
            <div class="client-info">{{ $data['client']['telephone'] }}</div>
            <div class="client-info">{{ $data['client']['adresse'] }}</div>
          </td>
        </tr>
      </table>

      <!-- 2. Titre Centré -->
      <div class="title-block">
        <div class="main-title">Relevé de compte mensuel - FCP</div>
        <div class="sub-title">{{ $data['period']['label'] }}</div>
      </div>

      <!-- 3. Nom du Fonds -->
      <div class="fund-header-banner">
        <span class="fund-title">{{ $fundStatement['fcp_nom'] }}</span>
      </div>

      <!-- 4. SECTION I. DÉTAIL SYNTHÉTIQUE -->
      <div class="section-title">I. DÉTAIL SYNTHÉTIQUE</div>
      <table class="data-table">
        <thead>
          <tr>
            <th style="width: 17%;">Date de Valorisation</th>
            <th style="width: 17%;">VL à date (XAF)</th>
            <th style="width: 17%;">Capital Total Investi</th>
            <th style="width: 16%;">Nombre de Parts Cumulé</th>
            <th style="width: 17%;">Valorisation Portefeuille</th>
            <th style="width: 16%;">Plus Value / Moins Value</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>{{ $fundStatement['synthetique']['date_valorisation'] }}</td>
            <td class="align-right text-bold">{{ $fundStatement['synthetique']['vl_a_date_display'] }}</td>
            <td class="align-right text-bold">{{ $fundStatement['synthetique']['capital_total_investi_display'] }}</td>
            <td class="align-right text-bold">{{ $fundStatement['synthetique']['nb_parts_cumule_display'] }}</td>
            <td class="align-right text-bold">{{ $fundStatement['synthetique']['valorisation_portefeuille_display'] }}</td>
            <td class="align-right text-bold {{ $fundStatement['synthetique']['plus_value'] >= 0 ? 'text-green' : 'text-red' }}">
              {{ $fundStatement['synthetique']['plus_value_display'] }}
            </td>
          </tr>
        </tbody>
      </table>

      <!-- 5. SECTION II. DÉTAILS DES TRANSACTIONS (PARTS) -->
      <div class="section-title">II. DÉTAILS DES TRANSACTIONS (PARTS)</div>
      <table class="data-table">
        <thead>
          <tr>
            <th style="width: 18%;">Désignation</th>
            <th style="width: 15%;">Nb Parts (Mois précédent)</th>
            <th style="width: 15%;">Montant brut investi</th>
            <th style="width: 14%;">Frais de souscription</th>
            <th style="width: 12%;">Nb Parts (Mois)</th>
            <th style="width: 12%;">Nb Parts (Rachetées)</th>
            <th style="width: 14%;">Nombre de Parts Actuel</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td class="align-left">{{ $fundStatement['mouvements']['designation'] }}</td>
            <td class="align-right">{{ $fundStatement['mouvements']['nb_parts_precedent_display'] }}</td>
            <td class="align-right">{{ $fundStatement['mouvements']['montant_brut_investi_display'] }}</td>
            <td class="align-right">{{ $fundStatement['mouvements']['frais_souscription_display'] }}</td>
            <td class="align-right text-green">{{ $fundStatement['mouvements']['nb_parts_mois_display'] }}</td>
            <td class="align-right text-red">{{ $fundStatement['mouvements']['nb_parts_rachetees_display'] }}</td>
            <td class="align-right text-bold">{{ $fundStatement['mouvements']['nb_parts_actuel_display'] }}</td>
          </tr>
        </tbody>
      </table>

      <!-- 6. SECTION III. REGULARISATION -->
      <div class="section-title">III. REGULARISATION</div>
      <table class="data-table">
        <thead>
          <tr>
            <th style="width: 25%;">Total encaissé</th>
            <th style="width: 25%;">Total des frais de souscription</th>
            <th style="width: 25%;">Montant total des souscriptions</th>
            <th style="width: 25%;">Montant regularisé</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td class="align-right text-bold">{{ $fundStatement['regularisation']['total_encaisse_display'] }}</td>
            <td class="align-right">{{ $fundStatement['regularisation']['total_frais_display'] }}</td>
            <td class="align-right text-bold text-green">{{ $fundStatement['regularisation']['montant_total_souscriptions_display'] }}</td>
            <td class="align-right">{{ $fundStatement['regularisation']['montant_regularise_display'] }}</td>
          </tr>
        </tbody>
      </table>

      <!-- 7. Encadré Valorisation Totale (Bas de page) -->
      <table class="valorisation-box-table">
        <tr>
          <td class="valorisation-box-left">
            VALORISATION AU {{ $fundStatement['valorisation_finale']['date_label'] }}
          </td>
          <td class="valorisation-box-right">
            {{ $fundStatement['valorisation_finale']['montant_display'] }}
          </td>
        </tr>
      </table>

      <!-- 8. Pied de page officiel -->
      <div class="statement-footer">
        Kori Asset Management - Rue 1.131 DIKOUME BELL, BP: 1245 BALI-DOUALA<br>
        info@koriassetmanagement.com | www.koriassetmanagement.com
      </div>
    </div>
  @empty
    <div style="padding: 40px; text-align: center; color: #666;">
      Aucune transaction enregistrée pour ce client sur la période sélectionnée.
    </div>
  @endforelse
</div>

</body>
</html>
