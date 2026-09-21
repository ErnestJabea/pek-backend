<?php

namespace App\Services\IdentityVerification;

use App\Models\IdentityVerification;
use App\Models\OnboardingSession;

interface IdentityVerificationProvider
{
    /**
     * @return array{provider_reference:string, session_url:string, expires_at:\DateTimeInterface|null}
     */
    public function createSession(IdentityVerification $verification, OnboardingSession $session): array;

    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool;
}
