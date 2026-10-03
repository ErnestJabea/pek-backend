<?php

namespace App\Jobs;

use App\Mail\OnboardingMail;
use App\Models\OnboardingSession;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GenerateOnboardingDocumentsJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly string $sessionId) {}

    public function handle(): void
    {
        try {
            $session = OnboardingSession::with('user')->findOrFail($this->sessionId);
            $user = $session->user;
            $payload = $session->getSubmittedPayload();

            if ($payload === []) {
                Log::warning('Le snapshot KYC soumis est absent.', ['session_id' => $this->sessionId]);
                return;
            }

            $disk = Storage::disk('kyc_private');
            if (! $session->signature_path
                || ! str_starts_with($session->signature_path, 'signatures/')
                || ! $disk->exists($session->signature_path)) {
                Log::warning('La signature privée du dossier est absente.', ['session_id' => $this->sessionId]);
                return;
            }
            $signatureBytes = $disk->get($session->signature_path);
            $signatureMime = @getimagesizefromstring($signatureBytes)['mime'] ?? null;
            if (! in_array($signatureMime, ['image/jpeg', 'image/png'], true)) {
                Log::warning('Le fichier de signature privé est invalide.', ['session_id' => $this->sessionId]);
                return;
            }

            $logoBase64 = '';
            $logoPath = public_path('logo-kori.png');
            if (is_file($logoPath)) {
                $logoBase64 = 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath));
            }

            $pdfData = [
                'session' => $session,
                'payload' => $payload,
                'user' => $user,
                'signature' => 'data:'.$signatureMime.';base64,'.base64_encode($signatureBytes),
                'logo' => $logoBase64,
            ];

            $documents = [
                'kyc' => Pdf::loadView('pdfs.onboarding.fiche_kyc', $pdfData)->setPaper('a4', 'portrait')->output(),
                'risk' => Pdf::loadView('pdfs.onboarding.profil_investisseur', $pdfData)->setPaper('a4', 'portrait')->output(),
                'labft' => Pdf::loadView('pdfs.onboarding.questionnaire_labft', $pdfData)->setPaper('a4', 'portrait')->output(),
            ];

            foreach ($documents as $type => $content) {
                $disk->put('generated/'.$type.'_'.$session->id.'.pdf', $content);
            }

            // Sensitive PDFs remain in private storage. Emails only notify; they never carry KYC attachments.
            Mail::to($user->email)->send(new OnboardingMail($session, 'client'));

            if ($complianceEmail = config('mail.compliance_address')) {
                Mail::to($complianceEmail)->send(new OnboardingMail($session, 'compliance'));
            }
        } catch (Throwable $e) {
            Log::error('Erreur lors de la génération/envoi des documents onboarding : ' . $e->getMessage(), [
                'session_id' => $this->sessionId,
                'exception' => $e::class,
            ]);
        }
    }
}