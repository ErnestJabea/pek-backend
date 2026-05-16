<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WebhookController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('/run-migrations', function() {
    Artisan::call('migrate', ['--force' => true]);
    return Artisan::output();
});

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::get('/products', [ProductController::class, 'index']);
Route::get('/bank-details', function() {
    return response()->json(\App\Models\BankDetail::where('is_active', true)->first());
});

// Protected routes
Route::post('/stripe/webhook', [WebhookController::class, 'handleStripe']);
Route::post('/coolpay/webhook', [WebhookController::class, 'handleCoolPay']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    Route::get('/dashboard-stats', [AuthController::class, 'dashboardStats']);
    Route::post('/update-profile', [AuthController::class, 'updateProfile']);
    Route::get('/notifications', function (Request $request) {
        return $request->user()->notifications()->orderBy('created_at', 'desc')->get();
    });
    Route::get('/subscriptions', [SubscriptionController::class, 'index']);
    Route::post('/subscriptions', [SubscriptionController::class, 'store']);
});
