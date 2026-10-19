<?php

namespace App\Http\Controllers;

use App\Mail\NewClientRegistrationAdminMail;
use App\Mail\OtpMail;
use App\Models\OtpCode;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Auth\TotpService;
use App\Services\PortfolioService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:50',
            'city' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'employer' => 'nullable|string|max:255',
            'password' => 'required|string|min:12|confirmed',
            'type_piece' => 'required|string|in:CNI,Passeport,Carte Résident,Carte de séjour,carte_sejour,cni,passeport',
            'num_piece' => 'required|string|max:100',
            'expiration_piece' => 'required|date|after_or_equal:today + 1 month',
            'piece_recto' => 'nullable|string',
            'doc_piece_identite' => 'nullable|string',
            'piece_verso' => 'nullable|string',
            'doc_piece_verso' => 'nullable|string',
            'consent_data_retention' => 'nullable|boolean',
        ], [
            'type_piece.required' => 'Le type d\'identification (Carte de séjour, CNI ou Passeport) est obligatoire.',
            'num_piece.required' => 'Le numéro de la pièce d\'identification est obligatoire.',
            'expiration_piece.required' => 'La date d\'expiration de la pièce d\'identification est obligatoire.',
            'expiration_piece.after' => 'La date d\'expiration doit être valide pour au moins 1 mois supplémentaire.',
            'expiration_piece.after_or_equal' => 'La date d\'expiration doit être valide pour au moins 1 mois supplémentaire.',
        ]);

        $rectoDoc = $request->input('doc_piece_identite') ?: $request->input('piece_recto');
        $versoDoc = $request->input('doc_piece_verso') ?: $request->input('piece_verso');

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'city' => $request->city,
            'country' => $request->country,
            'employer' => $request->employer,
            'type_piece' => $request->type_piece,
            'num_piece' => $request->num_piece,
            'expiration_piece' => $request->expiration_piece,
            'doc_piece_identite' => $rectoDoc,
            'doc_piece_verso' => $versoDoc,
            'password' => Hash::make($request->password),
        ]);

        $pieceType = match(strtolower($user->type_piece ?? 'cni')) {
            'carte_sejour', 'carte résident', 'carte de séjour' => 'Carte Résident',
            'passeport', 'passport' => 'Passeport',
            default => 'CNI',
        };
        $user->onboardingSession()->create([
            'current_step' => 'kyc',
            'status' => 'in_progress',
            'payload' => [
                'nom' => $user->last_name,
                'prenom' => $user->first_name,
                'email' => $user->email,
                'tel' => $user->phone,
                'pays_residence' => $user->country,
                'adresse' => $user->city,
                'piece' => $pieceType,
                'type_piece' => $pieceType,
                'num_piece' => $user->num_piece,
                'expiration_piece' => $request->expiration_piece,
                'doc_piece_identite' => $rectoDoc,
                'piece_recto' => $rectoDoc,
                'doc_piece_verso' => $versoDoc,
                'piece_verso' => $versoDoc,
                'consent_data_retention' => (bool) $request->boolean('consent_data_retention', true),
                'consent_data_retention_at' => now()->toIso8601String(),
            ],
        ]);

        [$otpCode, $challengeId] = $this->issueOtp($user, 'register');

        // Send Email to client
        Mail::to($user->email)->send(new OtpMail($otpCode, $user));

        // Notification interne pour les administrateurs
        try {
            if (SystemSetting::get('registration_notifications_enabled', true)) {
                $rawEmails = SystemSetting::get('registration_notification_emails', 'contact@koriassetmanagement.com');
                $adminEmails = array_filter(array_map('trim', explode(',', (string) $rawEmails)));
                if (! empty($adminEmails)) {
                    Mail::to($adminEmails)->send(new NewClientRegistrationAdminMail($user));
                }
            }
        } catch (\Throwable $e) {
            Log::error('Erreur envoi notification admin nouvelle inscription: ' . $e->getMessage());
        }

        $responseData = [
            'message' => 'Utilisateur créé. Veuillez vérifier votre email pour le code OTP.',
            'challenge_id' => $challengeId,
        ];

        return response()->json($responseData);
    }

    public function dashboardStats(Request $request)
    {
        $user = $request->user()->loadMissing('onboardingSession');

        // Valorisation FCP en temps réel via PortfolioService
        $portfolioService = new PortfolioService;
        $valuation = $portfolioService->getClientValuation($user->id);
        $onboardingStatus = $user->onboarding_status;
        $onboardingCompleted = $user->onboarding_completed;
        $rejectionReason = $user->onboardingSession?->rejection_reason;
        $user->unsetRelation('onboardingSession');

        return response()->json([
            'total_balance' => $valuation['valorisation_totale'],
            'total_parts' => $valuation['total_parts'],
            'cout_revient' => $valuation['cout_revient_total'],
            'plus_value' => $valuation['plus_value_totale'],
            'rendement_global' => $valuation['rendement_global'],
            'nb_positions' => $valuation['nb_positions'],
            'calcule_le' => $valuation['calcule_le'],
            'portfolio' => $valuation,
            'user' => $user,
            'onboarding_completed' => $onboardingCompleted,
            'onboarding_status' => $onboardingStatus,
            'onboarding_rejection_reason' => $rejectionReason,
        ]);
    }

    /**
     * Retourne la valorisation détaillée du portefeuille FCP (positions par produit).
     * Route : GET /api/portfolio/valuation
     */
    public function portfolioValuation(Request $request)
    {
        $user = $request->user();

        $portfolioService = new PortfolioService;
        $valuation = $portfolioService->getClientValuation($user->id);

        return response()->json($valuation);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'challenge_id' => 'required|uuid',
            'code' => 'required|digits:6',
        ]);

        $otp = OtpCode::where('challenge_id', $request->challenge_id)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (! $otp || $otp->attempts >= 5) {
            return response()->json(['message' => 'Code invalide ou expiré.'], 422);
        }

        $user = User::where('email', $otp->email)->firstOrFail();
        if ($user->role === 'admin' || $user->hasRole('super_admin')) {
            return response()->json(['message' => 'Identifiants invalides.'], 401);
        }

        // Vérification selon le type (TOTP Authenticator ou OTP email/SMS)
        if ($otp->purpose === 'login_totp') {
            $isValidTotp = ! empty($user->mfa_secret) && TotpService::verify($user->mfa_secret, $request->code);
            if (! $isValidTotp) {
                $otp->increment('attempts');
                if ($otp->fresh()->attempts >= 5) {
                    $otp->delete();
                }

                return response()->json(['message' => 'Code de sécurité TOTP invalide ou expiré.'], 422);
            }
        } else {
            if (! Hash::check($request->code, $otp->code)) {
                $otp->increment('attempts');
                if ($otp->fresh()->attempts >= 5) {
                    $otp->delete();
                }

                return response()->json(['message' => 'Code invalide ou expiré.'], 422);
            }
        }

        if ($otp->purpose === 'register') {
            $user->email_verified_at = Carbon::now();
        }
        $user->save();

        $otp->forceFill(['consumed_at' => now()])->save();

        return $this->respondWithToken($user, [
            'prompt_mfa' => ! $user->mfa_prompt_dismissed && ($user->mfa_type === 'none' || empty($user->mfa_type)),
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password) || $user->role === 'admin' || $user->hasRole('super_admin')) {
            return response()->json(['message' => 'Identifiants invalides.'], 401);
        }

        if (! $user->email_verified_at) {
            return response()->json(['message' => 'Compte non vérifié.'], 403);
        }

        // 1. Si l'utilisateur n'a pas activé de MFA (choix direct ou désactivé)
        if (empty($user->mfa_type) || $user->mfa_type === 'none') {
            return $this->respondWithToken($user, [
                'requires_mfa' => false,
                'prompt_mfa' => ! $user->mfa_prompt_dismissed,
                'message' => 'Connexion réussie.',
            ]);
        }

        // 2. Si l'utilisateur utilise une application d'authentification TOTP (Google/Microsoft Authenticator)
        if ($user->mfa_type === 'totp' && ! empty($user->mfa_secret)) {
            [$otpCode, $challengeId] = $this->issueOtp($user, 'login_totp');

            return response()->json([
                'requires_mfa' => true,
                'mfa_type' => 'totp',
                'challenge_id' => $challengeId,
                'message' => 'Veuillez saisir le code à 6 chiffres généré par votre application d\'authentification.',
            ]);
        }

        // 3. Si l'utilisateur utilise l'envoi d'OTP par e-mail
        [$otpCode, $challengeId] = $this->issueOtp($user, 'login');

        // Send Email
        Mail::to($user->email)->send(new OtpMail($otpCode, $user, 'login'));

        return response()->json([
            'requires_mfa' => true,
            'mfa_type' => 'email',
            'challenge_id' => $challengeId,
            'message' => 'Un code de vérification MFA a été envoyé à votre adresse email.',
        ]);
    }

    public function resendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        $challengeId = (string) Str::uuid();
        if ($user) {
            if ($user->mfa_type === 'totp') {
                return response()->json([
                    'message' => 'Veuillez saisir le code actuel depuis votre application d\'authentification.',
                    'mfa_type' => 'totp',
                ]);
            }

            $type = $user->email_verified_at ? 'login' : 'register';
            [$otpCode, $challengeId] = $this->issueOtp($user, $type);
            Mail::to($user->email)->send(new OtpMail($otpCode, $user, $type));
        }

        return response()->json([
            'message' => 'Un nouveau code a été envoyé.',
            'challenge_id' => $challengeId,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        if ($user->onboarding_status === 'validated') {
            return response()->json([
                'message' => 'Votre profil est validé par la conformité. Les modifications de profil doivent être soumises au support client.',
            ], 403);
        }

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'employer' => 'nullable|string|max:255',
        ]);

        // Interdire le changement d'email
        // Interdire le changement de téléphone s'il était déjà renseigné
        $data = $request->only(['first_name', 'last_name', 'city', 'country', 'employer']);

        // Si le téléphone était vide, on permet de le définir une fois
        if (empty($user->phone) && $request->has('phone')) {
            $data['phone'] = $request->phone;
        }

        $user->update($data);

        return response()->json([
            'message' => 'Profil mis à jour avec succès.',
            'user' => $user,
        ]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:12',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Le mot de passe actuel est incorrect.'], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();
        $user->tokens()->where('id', '!=', $user->currentAccessToken()?->id)->delete();

        return response()->json([
            'message' => 'Mot de passe mis à jour avec succès.',
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'lang' => 'nullable|string|in:fr,en',
        ]);

        $lang = $request->input('lang');
        if (! $lang) {
            $accept = (string) $request->header('Accept-Language', '');
            if (! empty($accept) && ! app()->runningUnitTests()) {
                $lang = str_starts_with(strtolower($accept), 'en') ? 'en' : 'fr';
            } else {
                $lang = 'fr';
            }
        }
        $locale = ($lang === 'en') ? 'en' : 'fr';
        app()->setLocale($locale);

        try {
            Password::sendResetLink($request->only('email'));
        } catch (\Throwable $e) {
            Log::error('Erreur lors de l\'envoi du lien de réinitialisation de mot de passe: ' . $e->getMessage());
        }

        $message = $locale === 'en'
            ? 'If an account matches this email address, a password reset link has been sent.'
            : 'Si un compte correspond à cette adresse, un lien de réinitialisation va être envoyé.';

        return response()->json([
            'message' => $message,
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:12|confirmed',
            'lang' => 'nullable|string|in:fr,en',
        ]);

        $lang = $request->input('lang');
        if (! $lang) {
            $accept = (string) $request->header('Accept-Language', '');
            if (! empty($accept) && ! app()->runningUnitTests()) {
                $lang = str_starts_with(strtolower($accept), 'en') ? 'en' : 'fr';
            } else {
                $lang = 'fr';
            }
        }
        $locale = ($lang === 'en') ? 'en' : 'fr';
        app()->setLocale($locale);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'has_temp_password' => false,
                ])->setRememberToken(Str::random(60));
                $user->save();
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => $locale === 'en'
                    ? 'The password reset link is invalid or has expired.'
                    : 'Le lien de réinitialisation est invalide ou expiré.'
            ], 422);
        }

        return response()->json([
            'message' => $locale === 'en'
                ? 'Your password has been successfully reset.'
                : 'Votre mot de passe a été réinitialisé.'
        ]);
    }

    public function resetTempPassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:12',
        ]);

        $user = $request->user();
        if (! $user->has_temp_password || ! Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Le mot de passe temporaire est incorrect ou déjà remplacé.'], 422);
        }
        $user->password = Hash::make($request->new_password);
        $user->has_temp_password = false;
        $user->save();

        return response()->json([
            'message' => 'Votre mot de passe a été mis à jour avec succès.',
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        if ($request->user()) {
            $request->user()->currentAccessToken()?->delete();
        }

        $cookie = cookie()->forget('auth_token');

        return response()->json([
            'message' => 'Déconnecté avec succès.',
        ])->withCookie($cookie);
    }

    /**
     * @return array{string, string}
     */
    private function issueOtp(User $user, string $purpose): array
    {
        OtpCode::where('email', $user->email)->where('purpose', $purpose)->delete();

        $plainCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $challengeId = (string) Str::uuid();
        OtpCode::create([
            'email' => $user->email,
            'code' => Hash::make($plainCode),
            'challenge_id' => $challengeId,
            'purpose' => $purpose,
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
        ]);

        return [$plainCode, $challengeId];
    }

    /**
     * Helper pour standardiser la génération de token Sanctum et le cookie HTTP-Only.
     */
    private function respondWithToken(User $user, array $extra = [])
    {
        $user->tokens()->delete();
        $token = $user->createToken('auth_token', ['*'], now()->addDay())->plainTextToken;

        $cookie = cookie(
            'auth_token',
            $token,
            1440, // 24 heures
            '/',
            null,
            app()->environment('production') || config('session.same_site') === 'none' || (bool) config('session.secure'),
            true, // HttpOnly
            false,
            config('session.same_site', 'lax')
        );

        return response()->json(array_merge([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
            'requires_password_change' => (bool) $user->has_temp_password,
        ], $extra))->withCookie($cookie);
    }

    /**
     * Initialise la configuration TOTP pour l'utilisateur connecté.
     * Route : POST /api/mfa/setup-totp
     */
    public function setupTotp(Request $request)
    {
        $user = $request->user();
        $secret = TotpService::generateSecret();
        $appName = config('app.name', 'FCP Kori Asset Management');
        $provisioningUri = TotpService::getProvisioningUri($secret, $user->email, $appName);

        return response()->json([
            'secret' => $secret,
            'provisioning_uri' => $provisioningUri,
            'issuer' => $appName,
            'account' => $user->email,
        ]);
    }

    /**
     * Confirme l'activation TOTP avec validation du premier code.
     * Route : POST /api/mfa/confirm-totp
     */
    public function confirmTotp(Request $request)
    {
        $request->validate([
            'secret' => 'required|string',
            'code' => 'required|digits:6',
        ]);

        $user = $request->user();

        if (! TotpService::verify($request->secret, $request->code)) {
            return response()->json([
                'message' => 'Code d\'authentification invalide. Vérifiez l\'heure de votre appareil et réessayez.',
            ], 422);
        }

        $user->forceFill([
            'mfa_type' => 'totp',
            'mfa_secret' => $request->secret,
            'mfa_confirmed_at' => now(),
            'mfa_prompt_dismissed' => true,
        ])->save();

        return response()->json([
            'message' => 'Application d\'authentification activée avec succès.',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Met à jour la préférence MFA (désactivation 'none' ou passage à 'email').
     * Route : POST /api/mfa/update-preference
     */
    public function updateMfaPreference(Request $request)
    {
        $request->validate([
            'mfa_type' => 'required|in:none,email',
        ]);

        $user = $request->user();

        if ($request->mfa_type === 'none') {
            $user->forceFill([
                'mfa_type' => 'none',
                'mfa_secret' => null,
                'mfa_confirmed_at' => null,
                'mfa_prompt_dismissed' => true,
            ])->save();

            return response()->json([
                'message' => 'Authentification à deux facteurs désactivée.',
                'user' => $user->fresh(),
            ]);
        }

        if ($request->mfa_type === 'email') {
            $user->forceFill([
                'mfa_type' => 'email',
                'mfa_secret' => null,
                'mfa_confirmed_at' => now(),
                'mfa_prompt_dismissed' => true,
            ])->save();

            return response()->json([
                'message' => 'Double authentification par e-mail activée.',
                'user' => $user->fresh(),
            ]);
        }

        return response()->json(['message' => 'Type de MFA non pris en charge.'], 422);
    }

    /**
     * Marque que l'utilisateur a ignoré le prompt MFA pour ne plus le harceler.
     * Route : POST /api/mfa/dismiss-prompt
     */
    public function dismissMfaPrompt(Request $request)
    {
        $user = $request->user();
        $user->forceFill([
            'mfa_prompt_dismissed' => true,
        ])->save();

        return response()->json([
            'message' => 'Préférence enregistrée.',
            'user' => $user->fresh(),
        ]);
    }
}
