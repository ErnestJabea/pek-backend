<?php

namespace App\Jobs;

use App\Mail\OnboardingMail;
use App\Models\OnboardingSession;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GenerateOnboardingDocumentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [60, 300];

    public function __construct(private readonly string $sessionId) {}

    public function handle(): void
    {
        $session = OnboardingSession::with('user')->findOrFail($this->sessionId);
        $user = $session->user;
        $payload = $session->getSubmittedPayload();

        if ($payload === []) {
            throw new RuntimeException('Le snapshot KYC soumis est absent.');
        }

        $disk = Storage::disk('kyc_private');
        if (! $session->signature_path
            || ! str_starts_with($session->signature_path, 'signatures/')
            || ! $disk->exists($session->signature_path)) {
            throw new RuntimeException('La signature privée du dossier est absente.');
        }
        $signatureBytes = $disk->get($session->signature_path);
        $signatureMime = @getimagesizefromstring($signatureBytes)['mime'] ?? null;
        if (! in_array($signatureMime, ['image/jpeg', 'image/png'], true)) {
            throw new RuntimeException('Le fichier de signature privé est invalide.');
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
    }
}
