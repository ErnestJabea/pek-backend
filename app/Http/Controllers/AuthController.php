<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\OtpCode;
use App\Mail\OtpMail;
use App\Mail\ResetPasswordMail;
use App\Services\PortfolioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

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
            'password' => 'required|string|min:8',
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

        // Generate OTP
        $otpCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        OtpCode::create([
            'email' => $user->email,
            'code' => $otpCode,
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        // Send Email
        Mail::to($user->email)->send(new OtpMail($otpCode, $user));

        return response()->json([
            'message' => 'Utilisateur créé. Veuillez vérifier votre email pour le code OTP.',
            'otp_debug' => $otpCode, // For testing prototype
        ]);
    }

    public function dashboardStats(Request $request)
    {
        $user = $request->user();

        // Valorisation FCP en temps réel via PortfolioService
        $portfolioService = new PortfolioService();
        $valuation = $portfolioService->getClientValuation($user->id);

        return response()->json([
            'total_balance'       => $valuation['valorisation_totale'],
            'cout_revient'        => $valuation['cout_revient_total'],
            'plus_value'          => $valuation['plus_value_totale'],
            'rendement_global'    => $valuation['rendement_global'],
            'nb_positions'        => $valuation['nb_positions'],
            'calcule_le'          => $valuation['calcule_le'],
            'user'                => $user,
        ]);
    }

    /**
     * Retourne la valorisation détaillée du portefeuille FCP (positions par produit).
     * Route : GET /api/portfolio/valuation
     */
    public function portfolioValuation(Request $request)
    {
        $user = $request->user();

        $portfolioService = new PortfolioService();
        $valuation = $portfolioService->getClientValuation($user->id);

        return response()->json($valuation);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        $otp = OtpCode::where('email', $request->email)
            ->where('code', $request->code)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$otp) {
            return response()->json(['message' => 'Code invalide ou expiré.'], 422);
        }

        $user = User::where('email', $request->email)->first();
        $user->email_verified_at = Carbon::now();
        $user->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        $otp->delete();

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Identifiants invalides.'], 401);
        }

        if (!$user->email_verified_at) {
            return response()->json(['message' => 'Compte non vérifié.'], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function resendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Utilisateur non trouvé.'], 404);
        }

        // Delete old OTPs
        OtpCode::where('email', $request->email)->delete();

        // Generate new OTP
        $otpCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        OtpCode::create([
            'email' => $user->email,
            'code' => $otpCode,
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        // Send Email
        Mail::to($user->email)->send(new OtpMail($otpCode, $user));

        return response()->json([
            'message' => 'Un nouveau code a été envoyé.',
            'otp_debug' => $otpCode,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

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
            'user' => $user
        ]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:8',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Le mot de passe actuel est incorrect.'], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'message' => 'Mot de passe mis à jour avec succès.',
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Aucun compte associé à cet email.'], 404);
        }

        // Générer un mot de passe temporaire lisible
        $tempPassword = strtoupper(Str::random(4)) . rand(10, 99);

        $user->password = Hash::make($tempPassword);
        $user->email_verified_at = $user->email_verified_at ?? now();
        $user->save();

        Mail::to($user->email)->send(new ResetPasswordMail($tempPassword, $user));

        return response()->json([
            'message' => 'Un email avec votre nouveau mot de passe vous a été envoyé.',
        ]);
    }
}
