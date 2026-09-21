<?php

namespace App\Filament\Resources\OnboardingSessionResource\Pages;

use App\Filament\Resources\OnboardingSessionResource;
use App\Mail\OnboardingRejectedMail;
use App\Mail\OnboardingValidatedMail;
use App\Models\Notification as UserNotification;
use App\Models\OnboardingEvent;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ViewOnboardingSession extends ViewRecord
{
    protected static string $resource = OnboardingSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('validate')
                ->label('Valider le dossier')
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Valider cet onboarding')
                ->modalDescription(function ($record): string {
                    $missingDocuments = $record?->missingRequiredDocuments() ?? [];

                    if ($missingDocuments !== []) {
                        return 'Note : '.count($missingDocuments).' justificatif(s) manquant(s) ('.implode(', ', $missingDocuments).'), mais vous pouvez valider ce dossier d\'onboarding. Le client sera notifié par e-mail des pièces à fournir ultérieurement.';
                    }

                    return 'Êtes-vous sûr de vouloir valider ce dossier d\'onboarding ? Le client recevra un e-mail de confirmation d\'activation de compte.';
                })
                ->visible(fn ($record) => $record && $record->status === 'completed')
                ->action(function ($record) {
                    DB::transaction(function () use ($record) {
                        $locked = $record->newQuery()->lockForUpdate()->findOrFail($record->id);
                        abort_unless($locked->status === 'completed', 409, 'Le statut du dossier a changé.');

                        if (config('identity_verification.required')) {
                            $verification = $locked->latestIdentityVerification()->first();
                            abort_unless(
                                $verification?->isApprovedFor($locked->getSubmittedPayload()),
                                422,
                                'La vérification stricte de l’identité n’est pas approuvée.'
                            );
                        }

                        $locked->update([
                            'status' => 'validated',
                            'validated_at' => now(),
                            'rejection_reason' => null,
                        ]);

                        OnboardingEvent::create([
                            'onboarding_session_id' => $locked->id,
                            'actor_user_id' => auth()->id(),
                            'event_type' => 'validated',
                            'from_status' => 'completed',
                            'to_status' => 'validated',
                        ]);
                    });

                    $record->refresh();

                    // Create in-app notification for client
                    UserNotification::create([
                        'user_id' => $record->user_id,
                        'title' => 'Compte activé !',
                        'body' => 'Félicitations, votre dossier d’onboarding a été validé et votre compte est maintenant activé.',
                        'type' => 'success',
                    ]);

                    // Send validation email
                    Mail::to($record->user->email)->send(new OnboardingValidatedMail($record));

                    Notification::make()
                        ->title('Dossier d\'onboarding validé avec succès !')
                        ->success()
                        ->send();
                }),

            Action::make('reject')
                ->label('Rejeter le dossier')
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->form([
                    Textarea::make('reason')
                        ->label('Motif du rejet')
                        ->placeholder('Indiquez ici la raison du rejet (ex: CNI expirée, justificatif de domicile non lisible, etc.)')
                        ->required()
                        ->rows(3),
                ])
                ->modalHeading('Rejeter cet onboarding')
                ->modalDescription('Veuillez indiquer le motif du rejet. Le client en sera notifié par e-mail.')
                ->visible(fn ($record) => $record && $record->status === 'completed')
                ->action(function ($record, array $data) {
                    DB::transaction(function () use ($record, $data) {
                        $locked = $record->newQuery()->lockForUpdate()->findOrFail($record->id);
                        abort_unless($locked->status === 'completed', 409, 'Le statut du dossier a changé.');

                        $locked->update([
                            'status' => 'rejected',
                            'current_step' => 'kyc',
                            'rejection_reason' => $data['reason'],
                            'rejected_at' => now(),
                        ]);

                        OnboardingEvent::create([
                            'onboarding_session_id' => $locked->id,
                            'actor_user_id' => auth()->id(),
                            'event_type' => 'rejected',
                            'from_status' => 'completed',
                            'to_status' => 'rejected',
                            'reason' => $data['reason'],
                        ]);
                    });

                    // Create in-app notification for client
                    UserNotification::create([
                        'user_id' => $record->user_id,
                        'title' => 'Dossier d’onboarding rejeté',
                        'body' => 'Votre dossier a été rejeté par l’équipe de conformité. Motif : '.$data['reason'],
                        'type' => 'danger',
                    ]);

                    // Send rejection email
                    Mail::to($record->user->email)->send(new OnboardingRejectedMail($record, $data['reason']));

                    Notification::make()
                        ->title('Dossier d\'onboarding rejeté et notifié au client.')
                        ->danger()
                        ->send();
                }),
        ];
    }
}
