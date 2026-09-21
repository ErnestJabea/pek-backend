<?php

use App\Http\Controllers\Admin\PdfDownloadController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/payment-proofs/{proof}/download', [\App\Http\Controllers\PaymentProofController::class, 'download'])
    ->middleware(['web', 'auth'])->name('admin.payment-proofs.download');

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('language/{locale}', function ($locale) {
    if (in_array($locale, ['en', 'fr'])) {
        session()->put('locale', $locale);
    }

    return redirect()->back();
})->name('language.switch');

Route::get('/', function () {
    return response()->json(['status' => 'online', 'service' => 'PEK FCP API']);
});

// Téléchargement des PDFs d'onboarding (protégé par l'authentification Filament)
Route::middleware(['web', 'auth'])
    ->prefix('admin/pdf')
    ->name('admin.pdf.')
    ->group(function () {
        Route::get('/onboarding/{session}/{type}', [PdfDownloadController::class, 'download'])
            ->name('download')
            ->where('type', 'kyc|risk|labft');
    });

// Téléchargement des pièces justificatives (protégé par l'authentification Filament)
Route::middleware(['web', 'auth'])
    ->prefix('admin/document')
    ->name('admin.document.')
    ->group(function () {
        Route::get('/onboarding/{session}/{type}', [PdfDownloadController::class, 'downloadDocument'])
            ->name('download')
            ->where('type', 'piece_identite|justificatif_domicile|photo|origine_fonds');
    });

// Téléchargement du dossier complet (ZIP) (protégé par l'authentification Filament)
Route::middleware(['web', 'auth'])
    ->prefix('admin/zip')
    ->name('admin.zip.')
    ->group(function () {
        Route::get('/onboarding/{session}', [PdfDownloadController::class, 'downloadZip'])
            ->name('download');
    });
