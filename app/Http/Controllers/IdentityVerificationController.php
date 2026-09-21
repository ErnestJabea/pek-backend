<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKYCRequest;
use App\Models\IdentityVerification;
use App\Models\IdentityVerificationEvent;
use App\Models\OnboardingEvent;
use App\Services\IdentityVerification\IdentityVerificationProvider;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class IdentityVerificationController extends Controller
{
    public function __construct(private readonly IdentityVerificationProvider $provider) {}

    public function status(Request $request): JsonResponse
    {
        $session = $request->user()->onboardingSession()->first();
        $verification = $session?->latestIdentityVerification()->first();

        return $this->privateResponse([
            'required' => (bool) config('identity_verification.required'),
            'configured' => $this->isConfigured(),
            'verification' => $verification ? $this->present($verification, $session->payload) : null,
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $session = $request->user()->onboardingSession()->first();
        if (! $session) {
            return $this->privateResponse(['message' => 'Commencez le formulaire KYC avant la vérification d’identité.'], 422);
        }

        if (in_array($session->status, ['completed', 'validated'], true)) {
            return $this->privateResponse(['message' => 'Ce dossier est verrouillé et ne peut plus être vérifié à nouveau.'], 409);
        }

        $validator = Validator::make($session->payload, (new StoreKYCRequest)->rules());
        if ($validator->fails()) {
            return $this->privateResponse([
                'message' => 'Complétez les informations d’identité avant de lancer le contrôle strict.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $latest = $session->latestIdentityVerification()->first();
        if ($latest?->isApprovedFor($session->payload)) {
            return $this->privateResponse([
                'message' => 'Votre identité est déjà vérifiée pour ces informations.',
                'verification' => $this->present($latest, $session->payload),
            ]);
        }

        if (! $this->isConfigured()) {
            return $this->privateResponse(['message' => 'Le service de vérification d’identité est temporairement indisponible.'], 503);
        }

        $verification = IdentityVerification::create([
            'onboarding_session_id' => $session->id,
            'provider' => config('identity_verification.provider'),
            'identity_data_hash' => IdentityVerification::fingerprint($session->payload),
            'status' => 'initiated',
        ]);

        try {
            $providerSession = $this->provider->createSession($verification, $session);
            $verification->update([
                'provider_reference' => $providerSession['provider_reference'],
                'status' => 'pending',
                'expires_at' => $providerSession['expires_at'],
            ]);

            OnboardingEvent::create([
                'onboarding_session_id' => $session->id,
                'actor_user_id' => $request->user()->id,
                'event_type' => 'identity_verification_started',
                'from_status' => $session->status,
                'to_status' => $session->status,
            ]);

            return $this->privateResponse([
                'session_url' => $providerSession['session_url'],
                'verification' => $this->present($verification->fresh(), $session->payload),
            ], 201);
        } catch (Throwable $exception) {
            $verification->update([
                'status' => 'error',
                'error_code' => 'session_creation_failed',
                'completed_at' => now(),
            ]);
            Log::error('Identity verification session creation failed', [
                'verification_id' => $verification->id,
                'exception' => $exception::class,
            ]);

            return $this->privateResponse(['message' => 'Impossible de démarrer la vérification d’identité pour le moment.'], 502);
        }
    }

    public function handleIdenfyWebhook(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        if (! $this->provider->verifyWebhookSignature($rawBody, $request->header('Idenfy-Signature'))) {
            Log::warning('Rejected identity provider webhook with invalid signature', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Signature invalide.'], 403);
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            return response()->json(['message' => 'Payload invalide.'], 400);
        }

        $providerReference = (string) ($payload['scanRef'] ?? '');
        $clientReference = (string) ($payload['clientId'] ?? '');
        $fallbackStatus = isset($payload['status']) && is_string($payload['status']) ? $payload['status'] : '';
        $overall = strtoupper((string) data_get($payload, 'status.overall', $fallbackStatus));
        $isFinal = filter_var($payload['final'] ?? false, FILTER_VALIDATE_BOOL);
        $eventType = substr((string) $request->header('Idenfy-Event-Type', 'IDENTIFICATION'), 0, 80);

        if ($providerReference === '' || $clientReference === '' || $overall === '') {
            return response()->json(['message' => 'Références de vérification manquantes.'], 422);
        }

        $verification = IdentityVerification::query()
            ->where('provider', 'idenfy')
            ->where('provider_reference', $providerReference)
            ->where('client_reference', $clientReference)
            ->first();

        if (! $verification) {
            Log::warning('Identity webhook cannot be correlated', ['provider_reference_hash' => hash('sha256', $providerReference)]);

            return response()->json(['message' => 'Vérification inconnue.'], 404);
        }

        $mappedStatus = match ($overall) {
            'APPROVED' => 'approved',
            'DENIED' => 'denied',
            'SUSPECTED' => 'suspected',
            'EXPIRED', 'EXPIRED-DELETED', 'DELETED' => 'expired',
            'ACTIVE', 'REVIEWING' => 'reviewing',
            default => 'reviewing',
        };

        $documentStatuses = array_filter([
            data_get($payload, 'status.autoDocument'),
            data_get($payload, 'status.manualDocument'),
        ]);
        $faceStatuses = array_filter([
            data_get($payload, 'status.autoFace'),
            data_get($payload, 'status.manualFace'),
        ]);
        $deliveryHash = hash('sha256', $rawBody);

        try {
            DB::transaction(function () use (
                $verification,
                $deliveryHash,
                $eventType,
                $mappedStatus,
                $isFinal,
                $documentStatuses,
                $faceStatuses
            ) {
                IdentityVerificationEvent::create([
                    'identity_verification_id' => $verification->id,
                    'delivery_hash' => $deliveryHash,
                    'event_type' => $eventType,
                    'result_status' => $mappedStatus,
                    'is_final' => $isFinal,
                    'received_at' => now(),
                ]);

                $locked = IdentityVerification::query()->lockForUpdate()->findOrFail($verification->id);
                if ($locked->is_final) {
                    return;
                }

                $locked->update([
                    'status' => $mappedStatus,
                    'is_final' => $isFinal,
                    'document_validated' => $documentStatuses === []
                        ? null
                        : in_array('DOC_VALIDATED', $documentStatuses, true),
                    'face_matched' => $faceStatuses === []
                        ? null
                        : in_array('FACE_MATCH', $faceStatuses, true),
                    'reviewed_by_human' => str_contains($eventType, 'MANUAL'),
                    'last_event_type' => $eventType,
                    'completed_at' => $isFinal ? now() : null,
                ]);

                if ($isFinal && $mappedStatus === 'approved') {
                    $session = $locked->onboardingSession()->lockForUpdate()->first();
                    if ($session && $locked->isApprovedFor($session->payload)) {
                        $session->forceFill(['identity_verified_at' => now()])->save();
                    }
                }
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }
            // Duplicate delivery: acknowledge idempotently without reprocessing it.
        }

        return response()->json(['status' => 'received']);
    }

    private function present(IdentityVerification $verification, array $payload): array
    {
        return [
            'id' => $verification->id,
            'provider' => $verification->provider,
            'status' => $verification->status,
            'final' => $verification->is_final,
            'matches_current_data' => hash_equals(
                $verification->identity_data_hash,
                IdentityVerification::fingerprint($payload)
            ),
            'verified' => $verification->isApprovedFor($payload),
            'document_validated' => $verification->document_validated,
            'face_matched' => $verification->face_matched,
            'reviewed_by_human' => $verification->reviewed_by_human,
            'expires_at' => $verification->expires_at?->toIso8601String(),
            'completed_at' => $verification->completed_at?->toIso8601String(),
        ];
    }

    private function isConfigured(): bool
    {
        return config('identity_verification.provider') === 'idenfy'
            && filled(config('services.idenfy.api_key'))
            && filled(config('services.idenfy.api_secret'))
            && filled(config('services.idenfy.webhook_signing_secret'));
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
