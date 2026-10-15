<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use App\Services\ClientStatementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Panel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ClientStatementController extends Controller
{
    public function __construct(
        protected ClientStatementService $statementService
    ) {}

    /**
     * Vérifie que l'utilisateur connecté a accès au relevé de ce client.
     */
    protected function authorizeAccess(User|Client $client, Request $request): void
    {
        $user = $request->user();
        if ($user) {
            if ($client->id === $user->id || (method_exists($user, 'isBackofficeAccount') && $user->isBackofficeAccount()) || $user->role === 'admin' || (method_exists($user, 'hasRole') && $user->hasRole('super_admin'))) {
                return;
            }
        }

        if ($request->hasValidSignature()) {
            return;
        }

        abort(403, 'Accès non autorisé à ce relevé de compte.');
    }

    /**
     * Affiche l'aperçu du relevé de compte au format HTML interactif.
     */
    public function show(Request $request, Client $client)
    {
        $this->authorizeAccess($client, $request);

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $productId = $request->query('product_id') ? (int) $request->query('product_id') : null;

        $data = $this->statementService->getStatementData($client, $startDate, $endDate, $productId);

        if ($request->query('download') === 'pdf') {
            return $this->generatePdfResponse($data, $client);
        }

        return view('pdfs.releve_mensuel_fcp', [
            'data' => $data,
            'is_pdf' => false,
        ]);
    }

    /**
     * Télécharge directement le relevé de compte officiel au format PDF.
     */
    public function downloadPdf(Request $request, Client $client): Response
    {
        $this->authorizeAccess($client, $request);

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $productId = $request->query('product_id') ? (int) $request->query('product_id') : null;

        $data = $this->statementService->getStatementData($client, $startDate, $endDate, $productId);

        return $this->generatePdfResponse($data, $client);
    }

    /**
     * Génère la réponse binaire PDF avec DomPDF.
     */
    protected function generatePdfResponse(array $data, Client $client): Response
    {
        $pdf = Pdf::loadView('pdfs.releve_mensuel_fcp', [
            'data' => $data,
            'is_pdf' => true,
        ])
        ->setPaper('a4', 'portrait')
        ->setOption('isRemoteEnabled', true)
        ->setOption('isHtml5ParserEnabled', true);

        $slugClient = \Illuminate\Support\Str::slug($data['client']['nom_complet'] ?: 'client');
        $slugPeriode = \Illuminate\Support\Str::slug($data['period']['label'] ?: 'releve');
        $filename = "releve_fcp_{$slugClient}_{$slugPeriode}.pdf";

        return $pdf->download($filename);
    }
}
