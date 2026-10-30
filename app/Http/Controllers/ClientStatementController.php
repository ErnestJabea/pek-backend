<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ClientStatementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
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

    /**
     * Retourne la liste des mois éligibles pour l'utilisateur mobile connecté,
     * en vérifiant si la fonctionnalité est activée par le super-administrateur.
     */
    public function userMonthlyStatements(Request $request)
    {
        $isEnabled = (bool) SystemSetting::get('monthly_statement_pwa_enabled', true);
        if (! $isEnabled) {
            return response()->json([
                'enabled' => false,
                'message' => 'Le service de téléchargement des relevés mensuels n\'est pas activé.',
                'months' => [],
            ]);
        }

        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $maxMonths = (int) SystemSetting::get('monthly_statement_history_months', 24);
        $months = $this->statementService->getAvailableMonthsForClient($user, $maxMonths);

        return response()->json([
            'enabled' => true,
            'months' => $months,
        ]);
    }

    /**
     * Télécharge le relevé mensuel officiel d'un utilisateur mobile connecté,
     * avec contrôle strict de l'activation superadmin et de la règle du 1er jour ouvrable.
     */
    public function downloadUserMonthlyStatement(Request $request, int $year, int $month)
    {
        // 1. Contrôle de l'activation par le super-administrateur
        $isEnabled = (bool) SystemSetting::get('monthly_statement_pwa_enabled', true);
        if (! $isEnabled) {
            return response()->json([
                'message' => 'Le service de relevés mensuels est actuellement désactivé par l\'administration.',
            ], 403);
        }

        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        // 2. Contrôle de cohérence de date
        if ($year < 2020 || $year > 2050 || $month < 1 || $month > 12) {
            return response()->json([
                'message' => 'Période mensuelle invalide demandée.',
            ], 422);
        }

        // 3. Contrôle strict de la règle du premier jour ouvrable du mois suivant
        $firstBusinessDay = $this->statementService->getFirstBusinessDayOfNextMonth($year, $month);
        if (now()->startOfDay()->lt($firstBusinessDay)) {
            return response()->json([
                'message' => "Le relevé du mois de {$month}/{$year} n'est pas encore disponible. Il sera accessible au téléchargement à compter du " . $firstBusinessDay->translatedFormat('l j F Y') . '.',
                'first_business_day' => $firstBusinessDay->toDateString(),
            ], 422);
        }

        // 4. Délimitation de la période du 1er au dernier jour du mois
        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        // 5. Génération des données de relevé certifié
        $data = $this->statementService->getStatementData($user, $startOfMonth->toDateString(), $endOfMonth->toDateString());

        $pdf = Pdf::loadView('pdfs.releve_mensuel_fcp', [
            'data' => $data,
            'is_pdf' => true,
        ])
        ->setPaper('a4', 'portrait')
        ->setOption('isRemoteEnabled', true)
        ->setOption('isHtml5ParserEnabled', true);

        $slugClient = \Illuminate\Support\Str::slug($data['client']['nom_complet'] ?: 'client');
        $moisStr = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
        $filename = "releve_fcp_{$slugClient}_{$year}_{$moisStr}.pdf";

        return $pdf->download($filename);
    }
}
