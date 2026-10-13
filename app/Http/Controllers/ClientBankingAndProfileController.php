<?php

namespace App\Http\Controllers;

use App\Models\ClientBankAccount;
use App\Models\ProfileChangeRequest;
use App\Services\ClientCategoryService;
use App\Services\ProfileChangeRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ClientBankingAndProfileController extends Controller
{
    public function __construct(
        protected ProfileChangeRequestService $changeService
    ) {}

    /**
     * Retourne l'état complet réglementaire et bancaire de l'utilisateur connecté
     */
    public function getProfileStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        // Récupérer le compte bancaire actif ou en cours
        $bankAccount = ClientBankAccount::where('user_id', $user->id)->first();

        // Récupérer les demandes en attente
        $pendingCategoryRequest = ProfileChangeRequest::where('user_id', $user->id)
            ->where('request_type', 'category_nature')
            ->where('status', 'pending')
            ->latest()
            ->first();

        $pendingBankRequest = ProfileChangeRequest::where('user_id', $user->id)
            ->where('request_type', 'bank_account')
            ->where('status', 'pending')
            ->latest()
            ->first();

        return response()->json([
            'nature_client' => $user->nature_client ?? ClientCategoryService::getTypeForCategory($user->categorie_client),
            'categorie_client' => $user->categorie_client,
            'pending_category_request' => $pendingCategoryRequest ? [
                'id' => $pendingCategoryRequest->id,
                'requested_nature' => $pendingCategoryRequest->requested_data['nature_client'] ?? null,
                'requested_categorie' => $pendingCategoryRequest->requested_data['categorie_client'] ?? null,
                'created_at' => $pendingCategoryRequest->created_at?->format('d/m/Y H:i'),
            ] : null,
            'bank_account' => $bankAccount ? [
                'id' => $bankAccount->id,
                'bank_name' => $bankAccount->bank_name,
                'account_holder_name' => $bankAccount->account_holder_name,
                'account_number' => $bankAccount->account_number,
                'bank_code' => $bankAccount->bank_code,
                'branch_code' => $bankAccount->branch_code,
                'rib_key' => $bankAccount->rib_key,
                'iban' => $bankAccount->iban,
                'swift_bic' => $bankAccount->swift_bic,
                'status' => $bankAccount->status,
                'has_document' => !empty($bankAccount->rib_document_path),
                'document_url' => $bankAccount->rib_document_path ? asset('storage/' . $bankAccount->rib_document_path) : null,
                'rejection_reason' => $bankAccount->rejection_reason,
            ] : null,
            'pending_bank_request' => $pendingBankRequest ? [
                'id' => $pendingBankRequest->id,
                'bank_name' => $pendingBankRequest->requested_data['bank_name'] ?? null,
                'account_number' => $pendingBankRequest->requested_data['account_number'] ?? null,
                'created_at' => $pendingBankRequest->created_at?->format('d/m/Y H:i'),
            ] : null,
            'taxonomies' => [
                'natures' => array_values(ClientCategoryService::getTypes()),
                'categories_by_nature' => ClientCategoryService::getCategoriesByType(),
            ],
        ]);
    }

    /**
     * Soumet une demande de modification de Nature & Catégorie client
     */
    public function requestCategoryUpdate(Request $request): JsonResponse
    {
        $allCategories = ClientCategoryService::getAllCategories();

        $validated = $request->validate([
            'nature_client' => ['required', 'string', Rule::in([
                ClientCategoryService::TYPE_PHYSIQUE,
                ClientCategoryService::TYPE_MORALE,
            ])],
            'categorie_client' => ['required', 'string', Rule::in(array_keys($allCategories))],
        ], [
            'nature_client.required' => 'La nature du client est obligatoire.',
            'nature_client.in' => 'Nature client non reconnue.',
            'categorie_client.required' => 'La catégorie du client est obligatoire.',
            'categorie_client.in' => 'Catégorie non reconnue.',
        ]);

        $changeRequest = $this->changeService->submitCategoryNatureRequest(
            $request->user(),
            $validated['nature_client'],
            $validated['categorie_client']
        );

        return response()->json([
            'message' => 'Votre demande a été soumise avec succès et est en cours d\'examen par l\'équipe Kori.',
            'request' => $changeRequest,
        ]);
    }

    /**
     * Soumet ou met à jour les coordonnées bancaires avec justificatif RIB
     */
    public function requestBankAccountUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:120'],
            'account_holder_name' => ['required', 'string', 'max:150'],
            'account_number' => ['required', 'string', 'max:50'],
            'bank_code' => ['nullable', 'string', 'max:10'],
            'branch_code' => ['nullable', 'string', 'max:10'],
            'rib_key' => ['nullable', 'string', 'max:5'],
            'iban' => ['nullable', 'string', 'max:50'],
            'swift_bic' => ['nullable', 'string', 'max:20'],
            'rib_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'], // 5MB max
        ], [
            'bank_name.required' => 'Le nom de la banque est requis.',
            'account_holder_name.required' => 'Le nom du titulaire du compte est requis.',
            'account_number.required' => 'Le numéro de compte ou RIB est requis.',
            'rib_document.mimes' => 'Le justificatif doit être au format PDF, JPG ou PNG.',
            'rib_document.max' => 'La taille maximale du fichier est de 5 Mo.',
        ]);

        $changeRequest = $this->changeService->submitBankAccountRequest(
            $request->user(),
            $validated,
            $request->file('rib_document')
        );

        return response()->json([
            'message' => 'Vos coordonnées bancaires ont bien été envoyées à l\'équipe de conformité Kori.',
            'request' => $changeRequest,
        ]);
    }
}
