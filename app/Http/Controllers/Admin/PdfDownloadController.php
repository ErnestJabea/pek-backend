<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OnboardingSession;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PdfDownloadController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Télécharger un PDF d'onboarding depuis le backoffice.
     *
     * @param  OnboardingSession  $session  Résolu automatiquement par le model binding Laravel
     * @param  string  $type  Le type de document : kyc | risk | labft
     */
    public function download(OnboardingSession $session, string $type)
    {
        $this->authorize('view', $session);
        // Charger la relation user si pas encore chargée
        $session->loadMissing('user');

        $allowedTypes = ['kyc', 'risk', 'labft'];
        if (! in_array($type, $allowedTypes)) {
            abort(404);
        }

        $fileLabel = match ($type) {
            'kyc' => 'fiche_kyc_',
            'risk' => 'profil_investisseur_',
            'labft' => 'questionnaire_labft_',
        };

        $lastName = $session->user->last_name ?? 'client';
        $firstName = $session->user->first_name ?? '';
        $filename = $fileLabel.$lastName.'_'.$firstName.'.pdf';

        try {
            $pdfContent = $this->getPdfContent($session, $type);
        } catch (\Exception $e) {
            abort(500, 'Erreur lors de la génération du PDF : '.$e->getMessage());
        }

        return response()->streamDownload(
            fn () => print ($pdfContent),
            $filename,
            $this->privateDownloadHeaders('application/pdf')
        );
    }

    /**
     * Télécharger une pièce justificative téléversée.
     */
    public function downloadDocument(OnboardingSession $session, string $type)
    {
        $this->authorize('view', $session);
        $session->loadMissing('user');

        $allowedTypes = ['piece_identite', 'justificatif_domicile', 'photo', 'origine_fonds'];
        if (! in_array($type, $allowedTypes)) {
            abort(404);
        }

        $columnName = 'doc_'.$type;
        $storagePath = $session->$columnName;

        $disk = Storage::disk('kyc_private');
        if (! $storagePath || ! $disk->exists($storagePath)) {
            abort(404);
        }

        $ext = pathinfo($storagePath, PATHINFO_EXTENSION);
        $lastName = $session->user->last_name ?? 'client';
        $firstName = $session->user->first_name ?? '';
        $filename = $type.'_'.$lastName.'_'.$firstName.'.'.$ext;

        return $disk->download($storagePath, $filename, $this->privateDownloadHeaders());
    }

    /**
     * Télécharger le dossier complet (ZIP) de l'onboarding.
     */
    public function downloadZip(OnboardingSession $session)
    {
        $this->authorize('view', $session);
        $session->loadMissing('user');

        $lastName = $session->user->last_name ?? 'client';
        $firstName = $session->user->first_name ?? '';
        $clientNameClean = str_replace(' ', '_', trim($lastName.'_'.$firstName));
        $zipFilename = 'dossier_onboarding_'.$clientNameClean.'.zip';

        // Créer un dossier temporaire si nécessaire
        $tempDir = storage_path('app/temp');
        if (! file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $zipPath = $tempDir.'/onboarding_'.$session->id.'_'.time().'.zip';

        $zip = new \ZipArchive;
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Impossible de créer le fichier ZIP');
        }

        // 1. Ajouter les 3 PDFs générés
        $pdfTypes = [
            'kyc' => '1_fiche_kyc.pdf',
            'risk' => '2_profil_investisseur.pdf',
            'labft' => '3_questionnaire_labft.pdf',
        ];
        foreach ($pdfTypes as $type => $name) {
            try {
                $pdfContent = $this->getPdfContent($session, $type);
                $zip->addFromString($name, $pdfContent);
            } catch (\Exception $e) {
                // En cas d'erreur sur un document, on ne bloque pas le zip complet
            }
        }

        // 2. Ajouter la signature si disponible
        $disk = Storage::disk('kyc_private');
        if ($session->signature_path && $disk->exists($session->signature_path)) {
            $ext = pathinfo($session->signature_path, PATHINFO_EXTENSION);
            if (! $ext) {
                $ext = 'png';
            }
            $zip->addFromString('signature.'.$ext, $disk->get($session->signature_path));
        }

        // 3. Ajouter les documents administratifs s'ils existent
        $adminDocs = [
            'piece_identite' => 'doc_piece_identite',
            'justificatif_domicile' => 'doc_justificatif_domicile',
            'photo' => 'doc_photo',
            'origine_fonds' => 'doc_origine_fonds',
        ];

        foreach ($adminDocs as $label => $column) {
            $storagePath = $session->$column;
            if ($storagePath && $disk->exists($storagePath)) {
                $ext = pathinfo($storagePath, PATHINFO_EXTENSION);
                $zip->addFromString($label.'.'.$ext, $disk->get($storagePath));
            }
        }

        $zip->close();

        if (! file_exists($zipPath)) {
            abort(500, 'Fichier ZIP introuvable après création');
        }

        return response()->download($zipPath, $zipFilename, $this->privateDownloadHeaders('application/zip'))
            ->deleteFileAfterSend(true);
    }

    /**
     * Obtenir le contenu brut d'un PDF d'onboarding.
     */
    private function getPdfContent(OnboardingSession $session, string $type): string
    {
        $storagePath = 'generated/'.$type.'_'.$session->id.'.pdf';
        $disk = Storage::disk('kyc_private');

        if ($disk->exists($storagePath)) {
            return $disk->get($storagePath);
        }

        $viewName = match ($type) {
            'kyc' => 'pdfs.onboarding.fiche_kyc',
            'risk' => 'pdfs.onboarding.profil_investisseur',
            'labft' => 'pdfs.onboarding.questionnaire_labft',
        };

        $signatureBase64 = '';
        if ($session->signature_path && $disk->exists($session->signature_path)) {
            $imgData = $disk->get($session->signature_path);
            $mime = 'image/png';
            if (str_ends_with($session->signature_path, '.jpg') || str_ends_with($session->signature_path, '.jpeg')) {
                $mime = 'image/jpeg';
            }
            $signatureBase64 = 'data:'.$mime.';base64,'.base64_encode($imgData);
        }

        $logoBase64 = '';
        $logoPath = public_path('logo-kori.png');
        if (file_exists($logoPath)) {
            $logoBase64 = 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath));
        }

        $pdfData = [
            'session' => $session,
            'payload' => $session->getSubmittedPayload() ?: $session->payload,
            'user' => $session->user,
            'signature' => $signatureBase64,
            'logo' => $logoBase64,
        ];

        $pdf = Pdf::loadView($viewName, $pdfData)
            ->setPaper('a4', 'portrait');

        return $pdf->output();
    }

    private function privateDownloadHeaders(?string $contentType = null): array
    {
        return array_filter([
            'Content-Type' => $contentType,
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
