<?php

namespace App\Services;

use App\Models\User;
use App\Models\ProfileChangeRequest;
use App\Models\ClientBankAccount;
use App\Models\Notification as UserNotification;
use App\Models\SystemSetting;
use App\Mail\ProfileChangeRequestedAdminMail;
use App\Mail\ProfileChangeReviewedClientMail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ProfileChangeRequestService
{
    /**
     * Soumission d'une demande de modification de la nature et catégorie client
     */
    public function submitCategoryNatureRequest(User $user, string $nature, string $categorie): ProfileChangeRequest
    {
        return DB::transaction(function () use ($user, $nature, $categorie) {
            // Annuler toute ancienne demande pending de ce type
            ProfileChangeRequest::where('user_id', $user->id)
                ->where('request_type', 'category_nature')
                ->where('status', 'pending')
                ->update(['status' => 'rejected', 'rejection_reason' => 'Remplacée par une nouvelle demande']);

            $changeRequest = ProfileChangeRequest::create([
                'user_id' => $user->id,
                'request_type' => 'category_nature',
                'current_data' => [
                    'nature_client' => $user->nature_client,
                    'categorie_client' => $user->categorie_client,
                ],
                'requested_data' => [
                    'nature_client' => $nature,
                    'categorie_client' => $categorie,
                ],
                'status' => 'pending',
            ]);

            // Notification InApp au client
            UserNotification::create([
                'user_id' => $user->id,
                'title' => 'Demande de mise à jour transmise',
                'body' => "Votre demande de modification ({$nature} - {$categorie}) a bien été transmise à l'équipe Kori et est en attente de validation.",
                'type' => 'info',
            ]);

            // Alerte aux administrateurs (InApp + Mail)
            $this->notifyAdmins($changeRequest);

            return $changeRequest;
        });
    }

    /**
     * Soumission d'une demande de coordonnées bancaires (RIB pour virements)
     */
    public function submitBankAccountRequest(User $user, array $data, ?UploadedFile $ribDoc = null): ProfileChangeRequest
    {
        return DB::transaction(function () use ($user, $data, $ribDoc) {
            $docPath = null;
            if ($ribDoc) {
                $docPath = $ribDoc->store('client_ribs/' . $user->id, 'public');
            }

            // Récupérer le compte bancaire existant s'il y en a un
            $existingAccount = ClientBankAccount::where('user_id', $user->id)->first();
            $currentData = $existingAccount ? $existingAccount->toArray() : null;

            $requestedData = [
                'bank_name' => $data['bank_name'],
                'account_holder_name' => $data['account_holder_name'] ?? trim("{$user->first_name} {$user->last_name}"),
                'account_number' => $data['account_number'],
                'bank_code' => $data['bank_code'] ?? null,
                'branch_code' => $data['branch_code'] ?? null,
                'rib_key' => $data['rib_key'] ?? null,
                'iban' => $data['iban'] ?? null,
                'swift_bic' => $data['swift_bic'] ?? null,
                'rib_document_path' => $docPath ?? ($existingAccount?->rib_document_path),
            ];

            // Créer ou mettre à jour l'entrée client_bank_accounts avec le statut 'pending'
            if ($existingAccount) {
                $existingAccount->update([
                    'status' => 'pending',
                    'rejection_reason' => null,
                ]);
            } else {
                ClientBankAccount::create([
                    'user_id' => $user->id,
                    'bank_name' => $requestedData['bank_name'],
                    'account_holder_name' => $requestedData['account_holder_name'],
                    'account_number' => $requestedData['account_number'],
                    'bank_code' => $requestedData['bank_code'],
                    'branch_code' => $requestedData['branch_code'],
                    'rib_key' => $requestedData['rib_key'],
                    'iban' => $requestedData['iban'],
                    'swift_bic' => $requestedData['swift_bic'],
                    'rib_document_path' => $requestedData['rib_document_path'],
                    'status' => 'pending',
                    'is_primary' => true,
                ]);
            }

            // Annuler toute ancienne demande pending de ce type
            ProfileChangeRequest::where('user_id', $user->id)
                ->where('request_type', 'bank_account')
                ->where('status', 'pending')
                ->update(['status' => 'rejected', 'rejection_reason' => 'Remplacée par une nouvelle demande']);

            $changeRequest = ProfileChangeRequest::create([
                'user_id' => $user->id,
                'request_type' => 'bank_account',
                'current_data' => $currentData,
                'requested_data' => $requestedData,
                'document_path' => $requestedData['rib_document_path'],
                'status' => 'pending',
            ]);

            // Notification InApp au client
            UserNotification::create([
                'user_id' => $user->id,
                'title' => 'Coordonnées bancaires en cours de validation',
                'body' => "Vos coordonnées bancaires ({$data['bank_name']}) ont été envoyées pour vérification par l'équipe Kori.",
                'type' => 'info',
            ]);

            // Alerte aux administrateurs (InApp + Mail)
            $this->notifyAdmins($changeRequest);

            return $changeRequest;
        });
    }

    /**
     * Approbation par l'équipe Kori
     */
    public function approveRequest(ProfileChangeRequest $request, User $reviewer): void
    {
        DB::transaction(function () use ($request, $reviewer) {
            $user = $request->user;

            if ($request->request_type === 'category_nature') {
                $nature = $request->requested_data['nature_client'] ?? 'Personne Physique';
                $category = $request->requested_data['categorie_client'] ?? 'Particulier';

                $user->update([
                    'nature_client' => $nature,
                    'categorie_client' => $category,
                ]);
            } elseif ($request->request_type === 'bank_account') {
                $reqData = $request->requested_data;

                ClientBankAccount::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'bank_name' => $reqData['bank_name'],
                        'account_holder_name' => $reqData['account_holder_name'],
                        'account_number' => $reqData['account_number'],
                        'bank_code' => $reqData['bank_code'] ?? null,
                        'branch_code' => $reqData['branch_code'] ?? null,
                        'rib_key' => $reqData['rib_key'] ?? null,
                        'iban' => $reqData['iban'] ?? null,
                        'swift_bic' => $reqData['swift_bic'] ?? null,
                        'rib_document_path' => $reqData['rib_document_path'] ?? null,
                        'status' => 'validated',
                        'validated_by' => $reviewer->id,
                        'validated_at' => now(),
                        'rejection_reason' => null,
                        'is_primary' => true,
                    ]
                );
            }

            $request->update([
                'status' => 'approved',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            // Notification InApp au client
            $typeLabel = $request->request_type === 'bank_account' ? 'coordonnées bancaires' : 'catégorie client';
            UserNotification::create([
                'user_id' => $user->id,
                'title' => 'Mise à jour validée ✅',
                'body' => "Votre demande relative à vos {$typeLabel} a été validée avec succès par l'équipe Kori.",
                'type' => 'success',
            ]);

            // Notification Email au client
            try {
                if ($user->email) {
                    Mail::to($user->email)->send(new ProfileChangeReviewedClientMail($request));
                }
            } catch (\Throwable $e) {
                Log::error('Erreur envoi email approbation profil: ' . $e->getMessage());
            }
        });
    }

    /**
     * Rejet par l'équipe Kori avec motif
     */
    public function rejectRequest(ProfileChangeRequest $request, User $reviewer, string $reason): void
    {
        DB::transaction(function () use ($request, $reviewer, $reason) {
            $user = $request->user;

            $request->update([
                'status' => 'rejected',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            if ($request->request_type === 'bank_account') {
                ClientBankAccount::where('user_id', $user->id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'rejected',
                        'rejection_reason' => $reason,
                    ]);
            }

            // Notification InApp au client
            $typeLabel = $request->request_type === 'bank_account' ? 'coordonnées bancaires' : 'catégorie client';
            UserNotification::create([
                'user_id' => $user->id,
                'title' => 'Mise à jour non validée ⚠️',
                'body' => "Votre demande de modification ({$typeLabel}) n'a pas été retenue : {$reason}",
                'type' => 'warning',
            ]);

            // Notification Email au client
            try {
                if ($user->email) {
                    Mail::to($user->email)->send(new ProfileChangeReviewedClientMail($request));
                }
            } catch (\Throwable $e) {
                Log::error('Erreur envoi email rejet profil: ' . $e->getMessage());
            }
        });
    }

    /**
     * Alertes InApp et Email pour les administrateurs
     */
    protected function notifyAdmins(ProfileChangeRequest $changeRequest): void
    {
        $client = $changeRequest->user;
        $typeLabel = $changeRequest->request_type === 'bank_account' 
            ? 'coordonnées bancaires' 
            : 'nature & catégorie client';

        // 1. Notification InApp pour tous les administrateurs du backoffice
        $admins = User::where(function ($q) {
            $q->where('role', 'admin')
              ->orWhereNotNull('admin_department_id');
        })->get();

        foreach ($admins as $admin) {
            UserNotification::create([
                'user_id' => $admin->id,
                'title' => 'Demande de modification client',
                'body' => "Le client {$client->first_name} {$client->last_name} a soumis une mise à jour ({$typeLabel}) à valider.",
                'type' => 'compliance_alert',
            ]);
        }

        // 2. Notification Email aux administrateurs
        try {
            $rawEmails = SystemSetting::get('compliance_notification_emails', 'contact@koriassetmanagement.com,koriam2023@gmail.com');
            $adminEmails = array_filter(array_map('trim', explode(',', (string) $rawEmails)));
            if (!empty($adminEmails)) {
                Mail::to($adminEmails)->send(new ProfileChangeRequestedAdminMail($changeRequest));
            }
        } catch (\Throwable $e) {
            Log::error('Erreur envoi email admin notification demande profil: ' . $e->getMessage());
        }
    }
}
