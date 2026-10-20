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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;

class ViewOnboardingSession extends ViewRecord
{
    protected static string $resource = OnboardingSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\EditAction::make()
                ->label('Modifier / Compléter')
                ->icon('heroicon-m-pencil-square'),

            Action::make('validate')
                ->label('Valider le dossier')
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->modalHeading('Valider cet onboarding')
                ->modalDescription(function ($record): string {
                    $missingDocuments = $record?->missingRequiredDocuments() ?? [];

                    if ($missingDocuments !== []) {
                        return 'Note : '.count($missingDocuments).' justificatif(s) non fourni(s) ('.implode(', ', $missingDocuments).'), mais vous pouvez valider ce dossier d\'onboarding.';
                    }

                    return 'Confirmez-vous la validation de ce dossier d\'onboarding ?';
                })
                ->visible(fn ($record) => $record && in_array($record->status, ['completed', 'in_progress']))
                ->disabled(function ($record): bool {
                    $isSuperAdmin = auth()->user()?->hasRole('super_admin');
                    $creatorId = $record?->events()->where('event_type', 'created')->value('actor_user_id')
                        ?? ($record?->payload['created_by_user_id'] ?? null)
                        ?? ($record?->payload['agent_kam_id'] ?? null);
                    return ! $isSuperAdmin && $creatorId && ($creatorId == auth()->id());
                })
                ->tooltip(function ($record): ?string {
                    $isSuperAdmin = auth()->user()?->hasRole('super_admin');
                    $isCompliance = $isSuperAdmin || (auth()->user()?->adminDepartment?->name === 'Conformité / KYC');
                    if (! $isCompliance) {
                        return "Seul un agent du département Conformité / KYC ou un Super Administrateur peut valider ce dossier.";
                    }

                    $creatorId = $record?->events()->where('event_type', 'created')->value('actor_user_id')
                        ?? ($record?->payload['created_by_user_id'] ?? null)
                        ?? ($record?->payload['agent_kam_id'] ?? null);
                    if (! $isSuperAdmin && $creatorId && ($creatorId == auth()->id())) {
                        return "Ségrégation des tâches (Maker-Checker) : Vous avez saisi ce dossier. La validation doit être effectuée par un autre agent de la conformité.";
                    }
                    return null;
                })
                ->form([
                    \Filament\Forms\Components\Toggle::make('send_notification_email')
                        ->label('Notifier le client par e-mail (Activation de compte)')
                        ->helperText('Laissez désactivé pour les clients existants ou régularisés en agence afin de ne pas leur envoyer d\'e-mail.')
                        ->default(fn ($record) => $record?->status === 'completed'),

                    \Filament\Forms\Components\Toggle::make('create_in_app_notification')
                        ->label('Créer une notification in-app dans l\'application client')
                        ->default(fn ($record) => $record->status === 'completed'),
                ])
                ->action(function ($record, array $data) {
                    Gate::authorize('update', $record);

                    $isSuperAdmin = auth()->user()?->hasRole('super_admin');
                    $isCompliance = $isSuperAdmin || (auth()->user()?->adminDepartment?->name === 'Conformité / KYC');
                    abort_unless($isCompliance, 403, "Seul le département Conformité ou un Super Administrateur peut valider un dossier d'onboarding.");

                    $creatorId = $record->events()->where('event_type', 'created')->value('actor_user_id')
                        ?? ($record->payload['created_by_user_id'] ?? null)
                        ?? ($record->payload['agent_kam_id'] ?? null);

                    abort_if(! $isSuperAdmin && $creatorId && ($creatorId == auth()->id()), 403, "Ségrégation des tâches (Maker-Checker) : Vous ne pouvez pas valider un dossier que vous avez vous-même saisi.");
                    $sendEmail = (bool) ($data['send_notification_email'] ?? false);
                    $sendInApp = (bool) ($data['create_in_app_notification'] ?? false);

                    DB::transaction(function () use ($record, $sendEmail, $sendInApp) {
                        $locked = $record->newQuery()->lockForUpdate()->findOrFail($record->id);
                        abort_unless(in_array($locked->status, ['completed', 'in_progress']), 409, 'Le statut du dossier a changé.');

                        if (config('identity_verification.required') && $locked->status === 'completed') {
                            $verification = $locked->latestIdentityVerification()->first();
                            abort_unless(
                                $verification?->isApprovedFor($locked->getSubmittedPayload()),
                                422,
                                'La vérification stricte de l’identité n’est pas approuvée.'
                            );
                        }

                        $fromStatus = $locked->status;
                        $locked->update([
                            'status' => 'validated',
                            'validated_at' => now(),
                            'rejection_reason' => null,
                        ]);

                        OnboardingEvent::create([
                            'onboarding_session_id' => $locked->id,
                            'actor_user_id' => auth()->id(),
                            'event_type' => 'validated',
                            'from_status' => $fromStatus,
                            'to_status' => 'validated',
                            'metadata' => [
                                'manual_backoffice' => true,
                                'email_sent' => $sendEmail,
                                'in_app_sent' => $sendInApp,
                            ],
                        ]);
                    });

                    $record->refresh();

                    // Create in-app notification for client if requested
                    if ($sendInApp) {
                        UserNotification::create([
                            'user_id' => $record->user_id,
                            'title' => 'Compte activé !',
                            'body' => 'Félicitations, votre dossier d’onboarding a été validé et votre compte est maintenant activé.',
                            'type' => 'success',
                        ]);
                    }

                    // Send validation email ONLY if send_notification_email is true
                    if ($sendEmail) {
                        try {
                            Mail::to($record->user->email)->send(new OnboardingValidatedMail($record));
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::error('Erreur envoi email validation onboarding: '.$e->getMessage());
                        }
                    }

                    Notification::make()
                        ->title('Dossier d\'onboarding validé avec succès !')
                        ->body($sendEmail ? 'E-mail de confirmation envoyé au client.' : 'Aucun e-mail envoyé.')
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
                    Gate::authorize('update', $record);
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
                        'body' => 'Votre dossier a été rejeté par l’équipe de KORI. Motif : '.$data['reason'],
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
