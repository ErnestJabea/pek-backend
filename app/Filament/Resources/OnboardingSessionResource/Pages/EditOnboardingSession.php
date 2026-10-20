<?php

namespace App\Filament\Resources\OnboardingSessionResource\Pages;

use App\Filament\Resources\OnboardingSessionResource;
use App\Models\OnboardingEvent;
use App\Services\ProfilRiskService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditOnboardingSession extends EditRecord
{
    protected static string $resource = OnboardingSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $payload = $this->record->payload ?? [];
        $user = $this->record->user;
        if ($user) {
            $payload['civ'] = $payload['civ'] ?? ($user->civ ?? 'M.');
            $payload['nom'] = $payload['nom'] ?? $user->last_name;
            $payload['prenom'] = $payload['prenom'] ?? $user->first_name;
            $payload['email'] = $payload['email'] ?? $user->email;
            $payload['tel'] = $payload['tel'] ?? $user->phone;
            $payload['adresse'] = $payload['adresse'] ?? $user->city;
            $payload['pays_residence'] = $payload['pays_residence'] ?? ($user->country ?: 'Cameroun');
            $payload['categorie_client'] = $payload['categorie_client'] ?? ($user->categorie_client ?: 'Salarié secteur privé');
            $payload['nature_client'] = $payload['nature_client'] ?? ($user->nature_client ?: 'personne_physique');
            $payload['piece'] = $payload['piece'] ?? ($payload['type_piece'] ?? ($user->type_piece ?: 'CNI'));
            $payload['num_piece'] = $payload['num_piece'] ?? $user->num_piece;
            $payload['expiration_piece'] = $payload['expiration_piece'] ?? ($user->expiration_piece ? $user->expiration_piece->format('Y-m-d') : null);
            $payload['dob'] = $payload['dob'] ?? ($user->dob ? $user->dob->format('Y-m-d') : null);
            $payload['employeur'] = $payload['employeur'] ?? $user->employer;
        }
        if (($this->record->status === 'validated') && empty($data['validated_at'])) {
            $data['validated_at'] = now()->format('Y-m-d H:i:s');
        }
        if (empty($data['submitted_at'])) {
            $data['submitted_at'] = ($this->record->submitted_at ? $this->record->submitted_at->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s'));
        }

        $data['payload'] = $payload;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $existingPayload = $this->record->payload ?? [];
        $submittedPayload = $data['payload'] ?? [];
        $mergedPayload = array_merge($existingPayload, $submittedPayload);

        // Recalculate risk score and profile if answers changed
        $riskService = app(ProfilRiskService::class);
        $score = $riskService->calculateScore($mergedPayload);
        $mergedPayload['risk_score'] = ! empty($mergedPayload['risk_score']) ? (int) $mergedPayload['risk_score'] : $score;
        $mergedPayload['risk_profile'] = ! empty($mergedPayload['risk_profile']) ? $mergedPayload['risk_profile'] : $riskService->getProfileName($mergedPayload['risk_score']);

        $hasAlert = collect(['ppe', 'pays_risque', 'secteur_sensible', 'condamnation'])
            ->contains(fn ($field) => ($mergedPayload[$field] ?? 'Non') === 'Oui');

        $data['risk_level'] = $hasAlert ? 'HIGH' : ($data['risk_level'] ?? 'LOW');
        $data['payload'] = $mergedPayload;
        $data['submitted_payload'] = $mergedPayload;

        if (($data['status'] ?? null) === 'validated') {
            $data['validated_at'] = $data['validated_at'] ?? now();
            if (empty($this->record->identity_verified_at)) {
                $data['identity_verified_at'] = now();
            }
        }
        if (in_array($data['status'] ?? null, ['completed', 'validated'], true) && empty($data['submitted_at'])) {
            $data['submitted_at'] = now();
        }

        return $data;
    }

    protected function afterSave(): void
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

        // Supporting documents sync into payload if uploaded via model columns
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

        // Purge old cached generated PDFs so downloads immediately show updated data
        $disk = Storage::disk('kyc_private');
        foreach (['kyc', 'risk', 'labft'] as $type) {
            $path = "generated/{$type}_{$record->id}.pdf";
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }

        OnboardingEvent::create([
            'onboarding_session_id' => $record->id,
            'actor_user_id' => auth()->id(),
            'event_type' => 'updated_by_admin',
            'from_status' => $record->status,
            'to_status' => $record->status,
            'metadata' => [
                'updated_at' => now()->toIso8601String(),
                'updated_by' => auth()->user()?->email,
            ],
        ]);
    }
}
