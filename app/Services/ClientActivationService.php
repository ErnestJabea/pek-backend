<?php

namespace App\Services;

use App\Mail\OtpMail;
use App\Models\Notification;
use App\Models\OtpCode;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ClientActivationService
{
    /**
     * Valide immédiatement l'adresse e-mail du client et active son compte pour la PWA.
     */
    public function validateEmail(User $user): void
    {
        $user->forceFill([
            'email_verified_at' => Carbon::now(),
        ])->save();

        OtpCode::where('email', $user->email)->where('purpose', 'register')->delete();

        try {
            Notification::create([
                'user_id' => $user->id,
                'title' => 'Compte activé',
                'body' => 'Votre adresse e-mail a été validée avec succès.',
                'type' => 'success',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Erreur création notification activation client: ' . $e->getMessage());
        }
    }

    /**
     * Révoque la validation de l'adresse e-mail.
     */
    public function invalidateEmail(User $user): void
    {
        $user->forceFill([
            'email_verified_at' => null,
        ])->save();
    }

    /**
     * Génère un code OTP d'activation et envoie l'e-mail officiel au client.
     */
    public function sendActivationEmail(User $user): string
    {
        OtpCode::where('email', $user->email)->where('purpose', 'register')->delete();

        $plainCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $challengeId = (string) Str::uuid();

        OtpCode::create([
            'email' => $user->email,
            'code' => Hash::make($plainCode),
            'challenge_id' => $challengeId,
            'purpose' => 'register',
            'attempts' => 0,
            'expires_at' => Carbon::now()->addHours(24),
        ]);

        Mail::to($user->email)->send(new OtpMail($plainCode, $user, 'register'));

        return $plainCode;
    }

    /**
     * Indique si l'adresse e-mail est vérifiée.
     */
    public function isEmailVerified(User $user): bool
    {
        return ! empty($user->email_verified_at);
    }
}
