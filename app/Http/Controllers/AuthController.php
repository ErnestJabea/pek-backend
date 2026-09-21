<?php

namespace App\Http\Controllers;

use App\Mail\OtpMail;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\PortfolioService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
        ]);

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'city' => $request->city,
            'country' => $request->country,
            'employer' => $request->employer,
            'password' => Hash::make($request->password),
        ]);

        [$otpCode, $challengeId] = $this->issueOtp($user, 'register');

        // Send Email
        Mail::to($user->email)->send(new OtpMail($otpCode, $user));

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

        if (! $otp || $otp->attempts >= 5 || ! Hash::check($request->code, $otp->code)) {
            if ($otp) {
                $otp->increment('attempts');
                if ($otp->fresh()->attempts >= 5) {
                    $otp->delete();
                }
            }

            return response()->json(['message' => 'Code invalide ou expiré.'], 422);
        }

        $user = User::where('email', $otp->email)->firstOrFail();
        if ($otp->purpose === 'register') {
            $user->email_verified_at = Carbon::now();
        }
        $user->save();

        $user->tokens()->delete();
        $token = $user->createToken('auth_token', ['*'], now()->addDay())->plainTextToken;

        $otp->forceFill(['consumed_at' => now()])->save();

        $cookie = cookie(
            'auth_token',
            $token,
            1440, // 24 heures
            '/',
            null,
            (bool) config('session.secure'),
            true, // HttpOnly
            false,
            app()->environment('local') ? 'Lax' : 'Strict'
        );

        return response()->json([
            'access_token' => 'cookie_session',
            'token_type' => 'Bearer',
            'user' => $user,
            'requires_password_change' => (bool) $user->has_temp_password,
        ])->withCookie($cookie);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Identifiants invalides.'], 401);
        }

        if (! $user->email_verified_at) {
            return response()->json(['message' => 'Compte non vérifié.'], 403);
        }

        [$otpCode, $challengeId] = $this->issueOtp($user, 'login');

        // Send Email
        Mail::to($user->email)->send(new OtpMail($otpCode, $user, 'login'));

        $responseData = [
            'requires_mfa' => true,
            'challenge_id' => $challengeId,
            'message' => 'Un code de vérification MFA a été envoyé à votre adresse email.',
        ];

        return response()->json($responseData);
    }

    public function resendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        $challengeId = (string) Str::uuid();
        if ($user) {
            $type = $user->email_verified_at ? 'login' : 'register';
            [$otpCode, $challengeId] = $this->issueOtp($user, $type);
            Mail::to($user->email)->send(new OtpMail($otpCode, $user, $type));
        }

        $responseData = [
            'message' => 'Un nouveau code a été envoyé.',
            'challenge_id' => $challengeId,
        ];

        return response()->json($responseData);
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
        ]);

        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => 'Si un compte correspond à cette adresse, un lien de réinitialisation va être envoyé.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:12|confirmed',
        ]);

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
            return response()->json(['message' => 'Le lien de réinitialisation est invalide ou expiré.'], 422);
        }

        return response()->json(['message' => 'Votre mot de passe a été réinitialisé.']);
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
}
