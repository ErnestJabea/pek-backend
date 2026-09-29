<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Services\SubscriptionBulletinService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Panel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SubscriptionBulletinController extends Controller
{
    public function __construct(
        protected SubscriptionBulletinService $bulletinService
    ) {}

    /**
     * Vérifie que l'utilisateur a le droit d'accéder à ce bulletin.
     */
    protected function authorizeAccess(Subscription $subscription, Request $request): void
    {
        $user = $request->user();
        if ($user) {
            if ($subscription->user_id === $user->id || $user->role === 'admin' || $user->canAccessPanel(app(Panel::class))) {
                return;
            }
        }

        // Si signature d'URL valide pour accès direct/partagé
        if ($request->hasValidSignature()) {
            return;
        }

        abort(403, 'Accès non autorisé à ce bulletin de souscription.');
    }

    /**
     * Renvoie les données structurées du bulletin (JSON pour le PWA / mobile).
     */
    public function data(Subscription $subscription, Request $request): JsonResponse
    {
        $this->authorizeAccess($subscription, $request);

        $data = $this->bulletinService->getBulletinData($subscription);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Affiche le bulletin au format HTML prêt à être imprimé ou visualisé dans le navigateur.
     */
    public function show(Subscription $subscription, Request $request)
    {
        $this->authorizeAccess($subscription, $request);

        $data = $this->bulletinService->getBulletinData($subscription);

        return view('pdfs.bulletin', ['data' => $data]);
    }

    /**
     * Génère et télécharge le fichier PDF officiel du Bulletin de souscription.
     */
    public function downloadPdf(Subscription $subscription, Request $request): Response
    {
        $this->authorizeAccess($subscription, $request);

        $data = $this->bulletinService->getBulletinData($subscription);

        $pdf = Pdf::loadView('pdfs.bulletin', ['data' => $data])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        $filename = "bulletin_souscription_{$subscription->reference_transaction}.pdf";

        return $pdf->download($filename);
    }
}
