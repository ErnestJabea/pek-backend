<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Bulletin de souscription - {{ $data['fcp_nom'] }}</title>
  <style>
    /* ==========================================================================
       KORI ASSET MANAGEMENT · Bulletin officiel de souscription FCP
       Conception 100% compatible DomPDF & impression A4 standard
       ========================================================================== */
    @page {
      size: A4 portrait;
      margin: 10mm 12mm 10mm 12mm;
    }
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    body {
      font-family: "DejaVu Sans", "Helvetica Neue", Arial, sans-serif;
      font-size: 8.5pt;
      line-height: 1.35;
      color: #2A1406;
      background: #F4EFEB;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }

    /* Rendu Ecran avec conteneur A4 et marges soignees */
    @media screen {
      body {
        background: #F4EFEB;
        padding: 24px 16px 50px 16px;
      }
      .barre-outils-container {
        max-width: 820px;
        margin: 0 auto 16px auto;
      }
      .barre-outils {
        background: #FFFFFF;
        padding: 12px 20px;
        border-radius: 12px;
        display: table;
        width: 100%;
        box-shadow: 0 2px 12px rgba(59, 19, 0, 0.08);
        border: 1px solid #E5DCD3;
      }
      .feuille-a4 {
        max-width: 820px;
        margin: 0 auto;
        background: #FFFFFF;
        padding: 38px 46px;
        box-shadow: 0 4px 25px rgba(59, 19, 0, 0.09);
        border-radius: 8px;
        border: 1px solid #E5DCD3;
      }
    }

    .barre-gauche {
      display: table-cell;
      vertical-align: middle;
      text-align: left;
    }
    .barre-droite {
      display: table-cell;
      vertical-align: middle;
      text-align: right;
    }
    .btn-action {
      display: inline-block;
      padding: 9px 18px;
      font-size: 9pt;
      font-weight: bold;
      text-decoration: none;
      border-radius: 8px;
      cursor: pointer;
      vertical-align: middle;
      line-height: 1.2;
    }
    .btn-retour {
      background: #F8F5F0;
      color: #3B1300;
      border: 1.5px solid #D9CBB8;
    }
    .btn-retour:hover {
      background: #EFE9DF;
    }
    .btn-telecharger {
      background: #E8B008;
      color: #3B1300;
      border: none;
      margin-right: 10px;
    }
    .btn-telecharger:hover {
      background: #D9A000;
    }
    .btn-imprimer {
      background: #3B1300;
      color: #FFFFFF;
      border: none;
    }
    .btn-imprimer:hover {
      background: #521C02;
    }

    /* Rendu Impression & PDF : exclure totalement les barres d'outils */
    @media print {
      body {
        background: #FFFFFF !important;
        margin: 0 !important;
        padding: 0 !important;
      }
      .barre-outils-container, .barre-outils, .no-print {
        display: none !important;
      }
      .feuille-a4 {
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
        box-shadow: none !important;
      }
    }

    /* En-tete officiel */
    .table-header {
      width: 100%;
      border-collapse: collapse;
      margin-top: 4px;
      margin-bottom: 12px;
    }
    .header-logo {
      width: 36%;
      vertical-align: middle;
      text-align: left;
      padding-right: 14px;
    }
    .header-logo img, .header-logo img.logo-kam {
      max-height: 58px;
      max-width: 180px;
      height: auto;
      width: auto;
      display: block;
    }
    .header-title-box {
      width: 64%;
      vertical-align: middle;
      text-align: center;
    }
    .titre-bulletin {
      background: #3B1300;
      color: #FFFFFF;
      font-size: 13pt;
      font-weight: bold;
      letter-spacing: 0.6px;
      padding: 6px 18px;
      display: inline-block;
      border-radius: 3px;
      white-space: nowrap;
    }
    .fcp-nom {
      font-size: 11pt;
      font-weight: bold;
      color: #3B1300;
      margin-top: 4px;
      letter-spacing: 0.3px;
    }
    .fcp-agrement {
      font-size: 8pt;
      color: #5E3208;
      margin-top: 2px;
      font-weight: 500;
    }

    /* Titres de section */
    .section-titre {
      background: #FBF6EC;
      border-left: 3.5px solid #8E5E0A;
      color: #3B1300;
      font-weight: bold;
      font-size: 9.5pt;
      padding: 3px 8px;
      margin: 8px 0 5px 0;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }
    /* Tableaux de champs */
    .table-champs {
      width: 100%;
      border-collapse: collapse;
      font-size: 8.5pt;
      margin-bottom: 2px;
    }
    .table-champs td {
      padding: 3px 0;
      vertical-align: middle;
    }
    .table-champs .lbl {
      color: #2A1406;
      white-space: nowrap;
      width: 175px;
      padding-right: 8px;
    }
    .table-champs .val-ligne {
      border-bottom: 1px solid #8E5E0A;
      font-weight: bold;
      color: #3B1300;
      padding: 2px 4px;
    }
    /* Cases a cocher compatibles DomPDF */
    .box-check {
      display: inline-block;
      width: 12px;
      height: 12px;
      border: 1px solid #3B1300;
      background: #FFFFFF;
      text-align: center;
      line-height: 10px;
      font-size: 8pt;
      font-weight: bold;
      color: #3B1300;
      vertical-align: middle;
      margin-right: 4px;
      font-family: Arial, sans-serif;
    }
    .box-check.checked {
      background: #FBF6EC;
      color: #3B1300;
    }
    /* Tableau d'operation financiere */
    .table-financiere {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
      margin: 6px 0 7px 0;
      text-align: center;
      font-size: 8pt;
    }
    .table-financiere th, .table-financiere td {
      border: 1px solid #3B1300;
      padding: 4px 2px;
      vertical-align: middle;
    }
    .table-financiere th {
      background: #FBF6EC;
      color: #3B1300;
      font-weight: bold;
      font-size: 8pt;
      line-height: 1.15;
    }
    .table-financiere td {
      font-weight: bold;
      color: #3B1300;
      font-size: 8.5pt;
      height: 24px;
    }
    .table-financiere td.total {
      background: #FFF9E6;
      color: #3B1300;
    }
    /* Signatures */
    .table-signatures {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }
    .table-signatures td.signature-cadre {
      width: 48%;
      border: 1px solid #8E5E0A;
      background: transparent;
      padding: 6px 10px;
      vertical-align: top;
      border-radius: 3px;
    }
    .signature-titre {
      font-weight: bold;
      font-size: 8pt;
      color: #3B1300;
      margin-bottom: 4px;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }
    .signature-zone {
      height: 52px;
      text-align: center;
      vertical-align: middle;
    }
    .signature-zone img {
      max-height: 48px;
      max-width: 170px;
    }
    .signature-nom {
      font-size: 7.5pt;
      color: #5E3208;
      text-align: center;
      margin-top: 3px;
      font-weight: bold;
    }
    .badge-kam-visa {
      display: inline-block;
      border: 1.5px solid #8E5E0A;
      border-radius: 4px;
      padding: 3px 8px;
      color: #3B1300;
      font-size: 6.8pt;
      line-height: 1.25;
      text-align: center;
      background: #FFFFFF;
    }
    .badge-sig-elec {
      display: inline-block;
      padding: 4px 8px;
      background: transparent;
      border: 1px dashed #93c5fd;
      border-radius: 4px;
      font-size: 6.8pt;
      color: #1e3a8a;
      line-height: 1.25;
      text-align: center;
    }
    /* Pied de page */
    .pied-page {
      margin-top: 12px;
      padding: 5px 8px;
      background: #3B1300;
      color: #FBF6EC;
      text-align: center;
      border-top: 2px solid #E8B008;
      font-size: 6.8pt;
      line-height: 1.35;
    }
  </style>
</head>
<body>
  @if(empty($is_pdf))
  <!-- Barre d'outils ecran (strictement exclue de l'impression et du PDF) -->
  <div class="barre-outils-container no-print">
    <div class="barre-outils">
      <div class="barre-gauche">
        <a href="javascript:history.back()" class="btn-action btn-retour">← Retour</a>
      </div>
      <div class="barre-droite">
        @php
          $subId = $data['subscription']->id ?? (is_object($data['subscription']) ? $data['subscription']->id : null) ?? request()->route('subscription');
        @endphp
        @if(!empty($subId))
          <a href="{{ route('subscriptions.bulletin.download', $subId) }}" class="btn-action btn-telecharger" download>
            Télécharger le PDF
          </a>
        @endif
        <button class="btn-action btn-imprimer" type="button" onclick="window.print()">
          Imprimer / Enregistrer en PDF
        </button>
      </div>
    </div>
  </div>
  @endif

  <!-- Feuille A4 officielle du Bulletin (avec marges definies) -->
  <div class="feuille-a4">
    <!-- En-tête officiel KORI ASSET MANAGEMENT -->
    <table class="table-header">
      <tr>
        <td class="header-logo">
          @if (!empty($data['logo_base64']))
            <img src="{{ $data['logo_base64'] }}" alt="KORI Asset Management" class="logo-kam">
          @elseif (file_exists(public_path('logo-kam.png')))
            <img src="{{ asset('logo-kam.png') }}" alt="KORI Asset Management" class="logo-kam">
          @else
            <div style="font-weight: bold; font-size: 16pt; color: #3B1300; line-height: 1;">KORI</div>
            <div style="font-size: 7.5pt; color: #8E5E0A; letter-spacing: 0.5px;">Asset Management</div>
          @endif
        </td>
        <td class="header-title-box">
          <div class="titre-bulletin">BULLETIN DE SOUSCRIPTION</div>
          <div class="fcp-nom">{{ $data['fcp_nom'] }}</div>
          <div class="fcp-agrement">{{ $data['fcp_agrement'] }}</div>
        </td>
      </tr>
    </table>
  <!-- ==================== I - IDENTIFICATION DU CLIENT ==================== -->
  <div class="section-titre">I- IDENTIFICATION DU CLIENT</div>
  <table class="table-champs">
    <tr>
      <td class="lbl">Nom ou Raison sociale&nbsp;:</td>
      <td class="val-ligne" colspan="3">{{ $data['nom_complet'] }}</td>
    </tr>
    <tr>
      <td class="lbl">Adresse&nbsp;:</td>
      <td class="val-ligne" colspan="3">{{ $data['adresse'] }}</td>
    </tr>
    <tr>
      <td class="lbl">Téléphone&nbsp;:</td>
      <td class="val-ligne" style="width: 32%;">{{ $data['telephone'] }}</td>
      <td class="lbl" style="width: 60px; text-align: right; padding-right: 6px;">E-mail&nbsp;:</td>
      <td class="val-ligne">{{ $data['email'] }}</td>
    </tr>
    <tr>
      <td class="lbl">Nature du client&nbsp;:</td>
      <td colspan="3" style="padding: 4px 0;">
        <span style="margin-right: 30px;">
          <span class="box-check {{ $data['is_personne_morale'] ? 'checked' : '' }}">{{ $data['is_personne_morale'] ? 'X' : '' }}</span>
          Personne Morale
        </span>
        <span>
          <span class="box-check {{ $data['is_personne_physique'] ? 'checked' : '' }}">{{ $data['is_personne_physique'] ? 'X' : '' }}</span>
          <strong>Personne Physique</strong>
        </span>
      </td>
    </tr>
    <tr>
      <td class="lbl">Catégorie du client*&nbsp;:</td>
      <td class="val-ligne" colspan="3">{{ !empty($data['categorie_client']) ? $data['categorie_client'] : 'Particulier' }}</td>
    </tr>
    <tr>
      <td class="lbl">Pièce d’identité&nbsp;:</td>
      <td colspan="3" style="padding: 4px 0;">
        <span style="margin-right: 18px;">
          <span class="box-check {{ $data['is_cni'] ? 'checked' : '' }}">{{ $data['is_cni'] ? 'X' : '' }}</span> CNI
        </span>
        <span style="margin-right: 18px;">
          <span class="box-check {{ $data['is_passeport'] ? 'checked' : '' }}">{{ $data['is_passeport'] ? 'X' : '' }}</span> Passeport
        </span>
        <span style="margin-right: 18px;">
          <span class="box-check {{ $data['is_rccm'] ? 'checked' : '' }}">{{ $data['is_rccm'] ? 'X' : '' }}</span> RCCM
        </span>
        <span>
          <span class="box-check {{ $data['is_autre_piece'] ? 'checked' : '' }}">{{ $data['is_autre_piece'] ? 'X' : '' }}</span> Autres (Préciser)&nbsp;:
        </span>
        <span style="border-bottom: 1px solid #8E5E0A; display: inline-block; min-width: 90px; padding: 0 4px; font-weight: bold; color: #3B1300;">
          {{ $data['precision_autre_piece'] ?: '—' }}
        </span>
      </td>
    </tr>
    <tr>
      <td class="lbl">Numéro pièce identité&nbsp;:</td>
      <td class="val-ligne" colspan="3">{{ $data['num_piece'] }}</td>
    </tr>
    <tr>
      <td class="lbl">Compte bancaire N°&nbsp;:</td>
      <td class="val-ligne" colspan="3">{{ $data['compte_bancaire'] }}</td>
    </tr>
  </table>
  <!-- ==================== II - TYPE D'OPÉRATION ==================== -->
  <div class="section-titre">II- TYPE D’OPÉRATION</div>
  <div style="font-size: 7.8pt; color: #5E3208; margin-bottom: 5px; line-height: 1.3;">
    Demande dans les conditions fixées par la réglementation et la documentation du <strong>{{ $data['fcp_nom'] }}</strong>, notamment le règlement de gestion et document d’information, l’exécution de la transaction suivante&nbsp;:
  </div>
  <table style="width: 100%; border-collapse: collapse; margin-bottom: 5px; font-size: 8pt;">
    <tr>
      <td style="width: 38%;">
        <span class="box-check checked">X</span>
        <strong>Souscription ponctuelle</strong>
      </td>
      <td style="width: 62%; text-align: left;">
        <span>Souscription mensuelle&nbsp;:</span>
        <span style="margin-left: 10px;"><span class="box-check">&nbsp;</span> 05</span>
        <span style="margin-left: 10px;"><span class="box-check">&nbsp;</span> 15</span>
        <span style="margin-left: 10px;"><span class="box-check">&nbsp;</span> 25</span>
      </td>
    </tr>
  </table>
  <!-- Tableau financier officiel -->
  <table class="table-financiere">
    <thead>
      <tr>
        <th style="width: 16%;">Date de<br>valeur</th>
        <th style="width: 15%;">Nombre<br>de part</th>
        <th style="width: 16%;">Valeur<br>liquidative</th>
        <th style="width: 18%;">Taux de souscription<br>appliqué</th>
        <th style="width: 17%;">Frais d'entrée TTC<br><small style="font-weight:normal; font-size: 6.5pt;">(En FCFA)</small></th>
        <th style="width: 18%;">Montant à payer<br><small style="font-weight:normal; font-size: 6.5pt;">(En FCFA)</small></th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>{{ $data['date_valeur'] }}</td>
        <td>{{ $data['nb_parts'] }}</td>
        <td>{{ $data['valeur_liquidative'] }}</td>
        <td>{{ $data['taux_souscription'] }}</td>
        <td>{{ $data['montant_frais'] }}</td>
        <td class="total">{{ $data['montant_total'] }}</td>
      </tr>
    </tbody>
  </table>
  <table class="table-champs" style="margin-top: 4px;">
    <tr>
      <td class="lbl" style="width: 195px;">Montant à payer (en toutes lettres)&nbsp;:</td>
      <td class="val-ligne">{{ ucfirst($data['montant_en_lettres']) }} francs CFA</td>
    </tr>
  </table>
  <table class="table-champs" style="margin-top: 4px;">
    <tr>
      <td class="lbl" style="width: 140px;">Moyen de paiement*&nbsp;:</td>
      <td colspan="3" style="padding: 3px 0;">
        <span style="margin-right: 14px;">
          <span class="box-check {{ $data['is_cheque'] ? 'checked' : '' }}">{{ $data['is_cheque'] ? 'X' : '' }}</span> Chèque
        </span>
        <span style="margin-right: 14px;">
          <span class="box-check {{ $data['is_virement'] ? 'checked' : '' }}">{{ $data['is_virement'] ? 'X' : '' }}</span> Virement bancaire
        </span>
        <span style="margin-right: 14px;">
          <span class="box-check {{ $data['is_mobile'] ? 'checked' : '' }}">{{ $data['is_mobile'] ? 'X' : '' }}</span> <strong>Paiement mobile</strong>
        </span>
        <span style="margin-right: 14px;">
          <span class="box-check {{ $data['is_apport_titres'] ? 'checked' : '' }}">{{ $data['is_apport_titres'] ? 'X' : '' }}</span> Apport de titres
        </span>
        <span>
          <span class="box-check {{ $data['is_autre_paiement'] ? 'checked' : '' }}">{{ $data['is_autre_paiement'] ? 'X' : '' }}</span> Autres
        </span>
      </td>
    </tr>
  </table>
  <!-- ==================== SIGNATURES ==================== -->
  <table class="table-signatures">
    <tr>
      <td class="signature-cadre">
        <div class="signature-titre">Signature &amp; cachet client&nbsp;:</div>
        <div class="signature-zone">
          @if (!empty($data['signature_client']))
            <img src="{{ $data['signature_client'] }}" alt="Signature Client">
          @else
            <div class="badge-sig-elec">
              <strong>SIGNATURE ÉLECTRONIQUE CERTIFIÉE</strong><br>
              {{ $data['nom_complet'] }}<br>
              Réf : {{ $data['reference_transaction'] }} · Date : {{ $data['date_valeur'] }}
            </div>
          @endif
        </div>
        <div class="signature-nom">{{ $data['nom_complet'] }}</div>
      </td>
      <td style="width: 4%;"></td>
      <td class="signature-cadre">
        <div class="signature-titre">Visa KORI Asset Management&nbsp;:</div>
        <div class="signature-zone"></div>
      </td>
    </tr>
  </table>
  <!-- Renvoi -->
  <div style="font-size: 6.5pt; color: #5E3208; margin-top: 8px; text-align: center;">
    * Liste catégorie du client et Référence bancaire / compte titres {{ $data['fcp_nom'] }} disponibles dans la documentation officielle.
  </div>
  <!-- Pied de page officiel -->
  <div class="pied-page">
    <strong style="color: #E8B008;">KORI ASSET MANAGEMENT S.A.</strong> · Société de Gestion d'OPCVM au capital de 300 000 000 FCFA<br>
    Agrément COSUMAF N° COSUMAF-SGP-02/2021 · Siège social : Douala, Cameroun<br>
    Tél : +237 233 42 00 00 · E-mail : contact@koriassetmanagement.com · Site web : www.koriassetmanagement.com
  </div>
  </div> <!-- fin .feuille-a4 -->
</body>
</html>
