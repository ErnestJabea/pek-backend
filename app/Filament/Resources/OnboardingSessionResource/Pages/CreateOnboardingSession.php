<?php

namespace App\Filament\Resources\OnboardingSessionResource\Pages;

use App\Filament\Resources\OnboardingSessionResource;
use App\Mail\OnboardingValidatedMail;
use App\Models\Notification as UserNotification;
use App\Models\OnboardingEvent;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CreateOnboardingSession extends CreateRecord
{
    protected static string $resource = OnboardingSessionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $status = $data['status'] ?? 'validated';
        $data['current_step'] = ($status === 'validated') ? 'completed' : 'kyc';
        if ($status === 'validated') {
            $data['validated_at'] = now();
        }

        $user = User::find($data['user_id'] ?? null);
        if ($user) {
            $civ = $data['civ'] ?? $user->civ ?? 'M.';
            $user->civ = $civ;
            $user->save();

            $data['payload'] = array_merge([
                'civ' => $civ,
                'nom' => $user->last_name,
                'prenom' => $user->first_name,
                'email' => $user->email,
                'tel' => $user->phone,
                'pays_residence' => $user->country,
                'adresse' => $user->city,
                'categorie_client' => $user->categorie_client ?: 'Particulier',
                'regularisation_backoffice' => true,
            ], $data['payload'] ?? []);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        $user = $record->user;
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

        if ($record->status === 'validated') {
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
