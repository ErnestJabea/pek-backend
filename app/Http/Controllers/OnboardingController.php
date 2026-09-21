<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKYCRequest;
use App\Http\Requests\StoreLABFTRequest;
use App\Jobs\GenerateOnboardingDocumentsJob;
use App\Models\IdentityVerification;
use App\Models\Notification;
use App\Models\OnboardingEvent;
use App\Models\OnboardingSession;
use App\Services\ProfilRiskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class OnboardingController extends Controller
{
    private const EDITABLE_STEPS = ['kyc', 'identity', 'risk', 'labft', 'signature'];

    public function status(Request $request): JsonResponse
    {
        $session = $request->user()->onboardingSession()->firstOrCreate([], [
            'current_step' => 'kyc',
            'status' => 'in_progress',
            'payload' => [],
        ]);

        $verification = $session->latestIdentityVerification()->first();

        return $this->privateResponse([
            'session' => $session,
            'identity_verification' => $verification ? [
                'status' => $verification->status,
                'final' => $verification->is_final,
                'verified' => $verification->isApprovedFor($session->payload),
                'matches_current_data' => hash_equals(
                    $verification->identity_data_hash,
                    IdentityVerification::fingerprint($session->payload)
                ),
            ] : null,
            'identity_verification_required' => (bool) config('identity_verification.required'),
        ]);
    }

    public function saveProgress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'step' => ['required', Rule::in(self::EDITABLE_STEPS)],
            'payload' => ['required', 'array:'.implode(',', $this->allowedPayloadFields())],
            ...$this->draftRules(),
        ]);

        try {
            $session = DB::transaction(function () use ($request, $validated) {
                $session = $request->user()->onboardingSession()->lockForUpdate()->first();
                if (! $session) {
                    $session = $request->user()->onboardingSession()->create([
                        'current_step' => 'kyc',
                        'status' => 'in_progress',
                        'payload' => [],
                    ]);
                }

                if (in_array($session->status, ['completed', 'validated'], true)) {
                    abort(409, 'Ce dossier a été soumis et est désormais verrouillé.');
                }

                if ($session->status === 'rejected') {
                    OnboardingEvent::create([
                        'onboarding_session_id' => $session->id,
                        'actor_user_id' => $request->user()->id,
                        'event_type' => 'correction_started',
                        'from_status' => 'rejected',
                        'to_status' => 'in_progress',
                        'reason' => $session->rejection_reason,
                    ]);
                    $session->status = 'in_progress';
                    $session->revision++;
                }

                $session->current_step = $validated['step'];
                $session->payload = array_replace($session->payload, $validated['payload']);
                $this->syncSupportingDocuments($session, $session->payload);
                $session->save();

                return $session->fresh();
            });

            return $this->privateResponse([
                'message' => 'Progression enregistrée avec succès.',
                'session' => $session,
            ]);
        } catch (HttpException $exception) {
            return $this->privateResponse(['message' => $exception->getMessage()], $exception->getStatusCode());
        }
    }

    public function finalize(Request $request, ProfilRiskService $riskService): JsonResponse
    {
        $request->validate([
            'signature' => [
                'required',
                'string',
                'max:700000',
                'regex:/^data:image\/(jpeg|png);base64,[A-Za-z0-9+\/=\r\n]+$/',
            ],
        ]);
        [$signatureBytes, $signatureExtension] = $this->decodeSignature(
            $request->string('signature')->toString()
        );

        try {
            $session = DB::transaction(function () use ($request, $riskService, $signatureBytes, $signatureExtension) {
                $session = $request->user()->onboardingSession()->lockForUpdate()->first();
                if (! $session) {
                    abort(404, 'Aucune session d’onboarding trouvée.');
                }

                if (in_array($session->status, ['completed', 'validated'], true)) {
                    abort(409, 'Ce dossier est déjà finalisé et verrouillé.');
                }

                $payload = $session->payload;
                $this->validateFinalPayload($payload);

                if (config('identity_verification.required')) {
                    $identity = $session->latestIdentityVerification()->lockForUpdate()->first();
                    if (! $identity || ! $identity->isApprovedFor($payload)) {
                        abort(422, 'La vérification stricte de votre identité doit être approuvée avant la signature.');
                    }
                }

                $score = $riskService->calculateScore($payload);
                $payload['risk_score'] = $score;
                $payload['risk_profile'] = $riskService->getProfileName($score);

                $riskLevel = collect(['ppe', 'pays_risque', 'secteur_sensible', 'condamnation'])
                    ->contains(fn ($field) => ($payload[$field] ?? 'Non') === 'Oui')
                    ? 'HIGH'
                    : 'LOW';

                $fromStatus = $session->status;
                $session->payload = $payload;
                $this->syncSupportingDocuments($session, $payload);
                $session->setSubmittedPayload($payload);
                $session->risk_level = $riskLevel;
                $session->status = 'completed';
                $session->current_step = 'completed';
                $session->submitted_at = now();
                $session->rejected_at = null;
                $session->signature_path = 'signatures/sig_'.$session->id.'.'.$signatureExtension;
                Storage::disk('kyc_private')->put($session->signature_path, $signatureBytes);
                $session->save();

                OnboardingEvent::create([
                    'onboarding_session_id' => $session->id,
                    'actor_user_id' => $request->user()->id,
                    'event_type' => 'submitted',
                    'from_status' => $fromStatus,
                    'to_status' => 'completed',
                ]);

                Notification::create([
                    'user_id' => $session->user_id,
                    'title' => 'Dossier KYC soumis',
                    'body' => 'Votre dossier a été transmis à l’équipe de conformité pour examen.',
                    'type' => 'success',
                ]);

                GenerateOnboardingDocumentsJob::dispatch($session->id)
                    ->afterCommit();

                return $session->fresh();
            });

            return $this->privateResponse([
                'message' => 'Onboarding soumis avec succès.',
                'session' => $session,
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (HttpException $exception) {
            return $this->privateResponse(['message' => $exception->getMessage()], $exception->getStatusCode());
        } catch (Throwable $exception) {
            Log::error('Onboarding finalization failed: '.$exception->getMessage()."\n".$exception->getTraceAsString(), [
                'user_id' => $request->user()?->id,
                'exception' => $exception::class,
            ]);

            return $this->privateResponse([
                'message' => 'Une erreur interne est survenue lors de la finalisation.',
            ], 500);
        }
    }

    private function validateFinalPayload(array $payload): void
    {
        $validators = [
            Validator::make($payload, (new StoreKYCRequest)->rules()),
            Validator::make($payload, (new StoreLABFTRequest)->rules()),
            Validator::make($payload, $this->riskRules()),
        ];

        foreach ($validators as $validator) {
            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
        }
    }

    /**
     * @return array{string, string}
     */
    private function decodeSignature(string $dataUri): array
    {
        if (! preg_match('/^data:image\/(jpeg|png);base64,([A-Za-z0-9+\/=\r\n]+)$/', $dataUri, $matches)) {
            throw ValidationException::withMessages(['signature' => 'Format de signature invalide.']);
        }

        $bytes = base64_decode($matches[2], true);
        if ($bytes === false || strlen($bytes) > 512000) {
            throw ValidationException::withMessages(['signature' => 'Signature invalide ou trop volumineuse.']);
        }

        $mime = @getimagesizefromstring($bytes)['mime'] ?? null;
        if (! in_array($mime, ['image/jpeg', 'image/png'], true)) {
            throw ValidationException::withMessages(['signature' => 'Le contenu de la signature n’est pas une image autorisée.']);
        }

        return [$bytes, $mime === 'image/png' ? 'png' : 'jpg'];
    }

    private function riskRules(): array
    {
        return [
            'tranche_revenus' => ['required', Rule::in(['moins_500k', '500k_1_5m', 'plus_1_5m'])],
            'epargne_possible' => ['required', Rule::in(['Oui', 'Non'])],
            'niveau_risque' => ['required', Rule::in(['faible', 'moyen', 'max'])],
            'conscience_risque' => ['required', Rule::in(['Oui', 'Non'])],
            'objectif_invest' => ['required', Rule::in(['securite', 'equilibre', 'croissance'])],
            'horizon_terme' => ['required', Rule::in(['court_terme', 'moyen_terme', 'long_terme'])],
            'niveau_perf' => ['required', Rule::in(['1', 'moderee', 'elevee'])],
            'connaissance_marche' => ['required', Rule::in(['nulle', 'moyenne', 'excellente'])],
            'invest_anterieurs' => ['required', Rule::in(['Oui', 'Non'])],
        ];
    }

    private function draftRules(): array
    {
        $rules = [];
        foreach ([...(new StoreKYCRequest)->rules(), ...(new StoreLABFTRequest)->rules(), ...$this->riskRules()] as $field => $fieldRules) {
            $normalized = is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules);
            $normalized = array_values(array_filter($normalized, function ($rule) {
                return ! is_string($rule)
                    || ($rule !== 'required' && ! str_starts_with($rule, 'required_if') && $rule !== 'accepted');
            }));

            if (in_array($field, ['ack_lecture', 'ack_donnees'], true)) {
                $normalized[] = 'boolean';
            }

            if (! in_array('nullable', $normalized, true)) {
                array_unshift($normalized, 'nullable');
            }

            array_unshift($normalized, 'sometimes');
            $rules['payload.'.$field] = $normalized;
        }

        return $rules;
    }

    private function allowedPayloadFields(): array
    {
        return array_values(array_unique([
            ...array_keys((new StoreKYCRequest)->rules()),
            ...array_keys((new StoreLABFTRequest)->rules()),
            ...array_keys($this->riskRules()),
            'agent_kam',
        ]));
    }

    private function syncSupportingDocuments(OnboardingSession $session, array $payload): void
    {
        $docMapping = [
            'doc_piece_identite' => 'doc_piece_identite',
            'doc_justificatif_domicile' => 'doc_justificatif_domicile',
            'doc_photo' => 'doc_photo',
            'doc_origine_fonds' => 'doc_origine_fonds',
        ];

        // Also allow fallbacks if doc_piece_identite or doc_photo were submitted under piece_recto / selfie_live
        if (empty($payload['doc_piece_identite']) && ! empty($payload['piece_recto'])) {
            $payload['doc_piece_identite'] = $payload['piece_recto'];
        }
        if (empty($payload['doc_photo']) && ! empty($payload['selfie_live'])) {
            $payload['doc_photo'] = $payload['selfie_live'];
        }

        $disk = Storage::disk('kyc_private');

        foreach ($docMapping as $payloadKey => $columnName) {
            if (! empty($payload[$payloadKey]) && is_string($payload[$payloadKey]) && str_starts_with($payload[$payloadKey], 'data:')) {
                if (preg_match('/^data:([a-zA-Z0-9\/\+\-\.]+);base64,(.+)$/', $payload[$payloadKey], $matches)) {
                    $mime = $matches[1];
                    $bytes = base64_decode($matches[2], true);
                    if ($bytes !== false) {
                        $ext = match ($mime) {
                            'application/pdf' => 'pdf',
                            'image/png' => 'png',
                            default => 'jpg',
                        };
                        $path = "secure_onboardings/documents/{$session->id}-{$columnName}.{$ext}";
                        $disk->put($path, $bytes);
                        $session->{$columnName} = $path;
                    }
                }
            }
        }
    }

    private function privateResponse(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
