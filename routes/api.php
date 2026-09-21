<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\IdentityVerificationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WebhookController;
use App\Models\BankDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (Versionnées v1)
|--------------------------------------------------------------------------
*/

$defineApiRoutes = function () {
    // Public routes
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:auth-otp');
    Route::post('/resend-otp', [AuthController::class, 'resendOtp'])->middleware('throttle:auth-otp');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-login')->name('login');
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:password-reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:password-reset');
    Route::get('/bank-details', function () {
        return response()->json(BankDetail::where('is_active', true)->get());
    });

    // Provider callbacks are public by necessity, but each handler verifies a cryptographic signature.
    Route::post('/stripe/webhook', [WebhookController::class, 'handleStripe'])->middleware('throttle:provider-webhooks');
    Route::post('/s3p/webhook', \App\Http\Controllers\S3pWebhookController::class)->middleware('throttle:provider-webhooks');
    Route::get('/payment-options', function (\App\Services\Payments\S3pGateway $gateway) {
        return response()->json(['orange_money' => $gateway->available('orange_money'), 'mtn_momo' => $gateway->available('mtn_momo'),
            's3p_mode' => $gateway->isStaging() ? 'staging' : 'live',
            'fee_basis_points' => config('payments.fee_basis_points'), 'max_investment' => config('payments.max_investment')]);
    });
    Route::post('/stripe/checkout-return', [SubscriptionController::class, 'stripeCheckoutReturn'])
        ->middleware('throttle:payment-return');
    Route::post('/maviance/webhook', [WebhookController::class, 'handleMaviance'])->middleware('throttle:provider-webhooks');
    Route::match(['get', 'put'], '/enkap/webhook/{reference?}', [SubscriptionController::class, 'handleEnkapNotification'])
        ->middleware('throttle:provider-webhooks');
    Route::post('/identity-verification/idenfy/webhook', [IdentityVerificationController::class, 'handleIdenfyWebhook'])
        ->middleware('throttle:provider-webhooks');

    Route::middleware(['auth:sanctum', 'cookie.origin'])->group(function () {
        Route::get('/user', function (Request $request) {
            return $request->user();
        });
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::get('/dashboard-stats', [AuthController::class, 'dashboardStats']);
        Route::post('/update-profile', [AuthController::class, 'updateProfile']);
        Route::post('/update-password', [AuthController::class, 'updatePassword']);
        Route::post('/reset-temp-password', [AuthController::class, 'resetTempPassword']);
        Route::get('/notifications', function (Request $request) {
            return $request->user()->notifications()->orderByDesc('created_at')->paginate(30);
        });
        Route::post('/notifications/read-all', function (Request $request) {
            $request->user()->notifications()->whereNull('read_at')->update(['read_at' => now()]);

            return response()->json(['message' => 'Toutes les notifications ont été marquées comme lues.']);
        });
        Route::get('/subscriptions', [SubscriptionController::class, 'index']);
        Route::post('/subscriptions', [SubscriptionController::class, 'store'])->middleware('throttle:payment-initiation');
        Route::get('/subscriptions/{id}/proofs', [\App\Http\Controllers\PaymentProofController::class, 'index']);
        Route::post('/subscriptions/{id}/proofs', [\App\Http\Controllers\PaymentProofController::class, 'store'])->middleware('throttle:proof-upload');
        Route::get('/payment-proofs/{proof}/download', [\App\Http\Controllers\PaymentProofController::class, 'download']);
        Route::post('/subscriptions/{id}/payment-session', [SubscriptionController::class, 'startPayment'])->middleware('throttle:payment-initiation');
        Route::get('/subscriptions/{id}/payment-status', [SubscriptionController::class, 'paymentStatus']);
        Route::get('/subscriptions/reference/{reference}/payment-status', [SubscriptionController::class, 'paymentStatusByReference']);
        Route::post('/subscriptions/{id}/check-status', [SubscriptionController::class, 'checkMavianceStatus']);

        // Valorisation en temps réel du portefeuille FCP (positions détaillées)
        Route::get('/portfolio/valuation', [AuthController::class, 'portfolioValuation']);

        // Onboarding Client FCP
        Route::get('/onboarding/status', [OnboardingController::class, 'status']);
        Route::post('/onboarding/save-progress', [OnboardingController::class, 'saveProgress']);
        Route::post('/onboarding/finalize', [OnboardingController::class, 'finalize']);

        Route::get('/identity-verification/status', [IdentityVerificationController::class, 'status']);
        Route::post('/identity-verification/session', [IdentityVerificationController::class, 'start'])
            ->middleware('throttle:identity-verification');
    });
};

// API Version 1 Prefix (Route officielle /api/v1/...)
Route::prefix('v1')->group($defineApiRoutes);

// Fallback pour la compatibilité legacy (/api/...)
$defineApiRoutes();
