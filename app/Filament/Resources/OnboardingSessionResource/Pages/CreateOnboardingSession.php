<?php

namespace App\Filament\Resources\OnboardingSessionResource\Pages;

use App\Filament\Resources\OnboardingSessionResource;
use App\Mail\OnboardingValidatedMail;
use App\Models\Notification as UserNotification;
use App\Models\OnboardingEvent;
use App\Models\User;
use App\Services\ProfilRiskService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CreateOnboardingSession extends CreateRecord
{
    protected static string $resource = OnboardingSessionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $status = $data['status'] ?? 'validated';
        $data['current_step'] = in_array($status, ['validated', 'completed'], true) ? 'completed' : 'kyc';
        if ($status === 'validated') {
            $data['validated_at'] = $data['validated_at'] ?? now();
            $data['identity_verified_at'] = $data['identity_verified_at'] ?? now();
        }
        if (in_array($status, ['validated', 'completed'], true) && empty($data['submitted_at'])) {
            $data['submitted_at'] = now();
        }

        $payload = $data['payload'] ?? [];
        if ($status === 'validated') {
            $payload['face_verified'] = true;
        }

        $user = User::find($data['user_id'] ?? null);
        if ($user) {
            $payload['civ'] = $payload['civ'] ?? ($user->civ ?? 'M.');
            $payload['nom'] = $payload['nom'] ?? $user->last_name;
            $payload['prenom'] = $payload['prenom'] ?? $user->first_name;
            $payload['email'] = $payload['email'] ?? $user->email;
            $payload['tel'] = $payload['tel'] ?? $user->phone;
            $payload['pays_residence'] = $payload['pays_residence'] ?? ($user->country ?: 'Cameroun');
            $payload['adresse'] = $payload['adresse'] ?? $user->city;
            $payload['categorie_client'] = $payload['categorie_client'] ?? ($user->categorie_client ?: 'Salarié secteur privé');
            $payload['nature_client'] = $payload['nature_client'] ?? ($user->nature_client ?: 'personne_physique');
            $payload['piece'] = $payload['piece'] ?? ($user->type_piece ?: 'CNI');
            $payload['num_piece'] = $payload['num_piece'] ?? $user->num_piece;
            $payload['expiration_piece'] = $payload['expiration_piece'] ?? ($user->expiration_piece ? $user->expiration_piece->format('Y-m-d') : null);
            $payload['dob'] = $payload['dob'] ?? ($user->dob ? $user->dob->format('Y-m-d') : null);
            $payload['employeur'] = $payload['employeur'] ?? $user->employer;
            $payload['regularisation_backoffice'] = true;
        }

        if (empty($payload['agent_kam'])) {
            $admin = auth()->user();
            $payload['agent_kam'] = $admin ? ($admin->name ?: (trim(($admin->first_name ?? '') . ' ' . ($admin->last_name ?? '')) ?: ($admin->email ?? 'Administrateur Backoffice'))) : 'Administrateur Backoffice';
        }

        if (($payload['nature_client'] ?? '') === 'personne_morale') {
            $payload['civ'] = null;
            $payload['situation_mat'] = null;
            $payload['profession'] = null;
            $payload['employeur'] = null;
        }

        // Calculate risk score and profile
        $riskService = app(ProfilRiskService::class);
        $score = $riskService->calculateScore($payload);
        $payload['risk_score'] = ! empty($payload['risk_score']) ? (int) $payload['risk_score'] : $score;
        $payload['risk_profile'] = ! empty($payload['risk_profile']) ? $payload['risk_profile'] : $riskService->getProfileName($payload['risk_score']);

        $hasAlert = collect(['ppe', 'pays_risque', 'secteur_sensible', 'condamnation'])
            ->contains(fn ($field) => ($payload[$field] ?? 'Non') === 'Oui');

        $data['risk_level'] = $hasAlert ? 'HIGH' : ($data['risk_level'] ?? 'LOW');
        $data['payload'] = $payload;
        $data['submitted_payload'] = $payload;

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        $user = $record->user;
        $payload = $record->payload ?? [];

        // Synchronize KYC attributes onto User model
        if ($user) {
            $userUpdates = array_filter([
                'civ' => $payload['civ'] ?? null,
                'first_name' => $payload['prenom'] ?? null,
                'last_name' => $payload['nom'] ?? null,
                'phone' => $payload['tel'] ?? null,
                'city' => $payload['adresse'] ?? null,
                'country' => $payload['pays_residence'] ?? null,
                'categorie_client' => $payload['categorie_client'] ?? null,
                'nature_client' => $payload['nature_client'] ?? null,
                'type_piece' => $payload['piece'] ?? ($payload['type_piece'] ?? null),
                'num_piece' => $payload['num_piece'] ?? null,
                'expiration_piece' => $payload['expiration_piece'] ?? null,
                'dob' => $payload['dob'] ?? null,
                'employer' => $payload['employeur'] ?? null,
            ], fn ($val) => ! is_null($val) && $val !== '');

            if ($record->doc_piece_identite) {
                $userUpdates['doc_piece_identite'] = $record->doc_piece_identite;
            }
            if (! empty($payload['doc_piece_verso'])) {
                $userUpdates['doc_piece_verso'] = $payload['doc_piece_verso'];
            }

            if (! empty($userUpdates)) {
                $user->update($userUpdates);
            }
        }

        // Synchronize document columns into payload if needed
        $payloadUpdated = false;
        foreach (['doc_piece_identite', 'doc_justificatif_domicile', 'doc_photo', 'doc_origine_fonds'] as $col) {
            if ($record->{$col} && empty($payload[$col])) {
                $payload[$col] = $record->{$col};
                $payloadUpdated = true;
            }
        }
        if ($payloadUpdated) {
            $record->payload = $payload;
            $record->setSubmittedPayload($payload);
            $record->saveQuietly();
        }

        $sendEmail = (bool) ($this->data['send_notification_email'] ?? false);
        $sendInApp = (bool) ($this->data['create_in_app_notification'] ?? false);

        OnboardingEvent::create([
            'onboarding_session_id' => $record->id,
            'actor_user_id' => auth()->id(),
            'event_type' => ($record->status === 'validated') ? 'validated' : 'created',
            'from_status' => 'not_started',
            'to_status' => $record->status,
            'metadata' => [
                'manual_backoffice' => true,
                'email_sent' => $sendEmail,
                'in_app_sent' => $sendInApp,
            ],
        ]);

        if ($record->status === 'validated' && $user) {
            if ($sendInApp) {
                UserNotification::create([
                    'user_id' => $user->id,
                    'title' => 'Compte activé !',
                    'body' => 'Félicitations, votre dossier d’onboarding a été validé et votre compte est maintenant activé.',
                    'type' => 'success',
                ]);
            }

            if ($sendEmail) {
                try {
                    Mail::to($user->email)->send(new OnboardingValidatedMail($record));
                } catch (\Throwable $e) {
                    Log::error('Erreur envoi email onboarding régularisé: ' . $e->getMessage());
                }
            }
        }
    }
}
