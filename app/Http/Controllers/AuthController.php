<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\OtpCode;
use App\Mail\OtpMail;
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
        
        // Optimisation : Eager loading de 'product' pour éviter N+1
        $subscriptions = $user->subscriptions()
            ->with('product')
            ->where('statut', 'Succès')
            ->get();

        $totalBalance = $subscriptions->sum(function($sub) {
            return (float)$sub->nb_parts * (float)($sub->product->vl ?? 0);
        });

        $performanceMonth = $totalBalance * 0.02;

        return response()->json([
            'total_balance' => (float)$totalBalance,
            'performance_month' => (float)$performanceMonth,
            'user' => $user,
        ]);
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
}
