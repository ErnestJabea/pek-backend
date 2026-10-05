<?php

namespace App\Filament\Pages;

use App\Mail\IdDocumentExpiryReminderMail;
use App\Models\Notification as InAppNotification;
use App\Models\User;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;

class IdDocumentExpirations extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Conformité & CRM';

    protected static ?string $navigationLabel = 'Échéances Pièces d\'Identité';

    protected static ?string $title = 'Suivi des Échéances des Pièces d\'Identité';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.id-document-expirations';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->where('role', '!=', 'admin')
                    ->whereDoesntHave('roles', fn (Builder $q) => $q->where('name', 'super_admin'))
                    ->whereNull('deleted_at')
                    ->with('onboardingSession')
                    ->orderByRaw('expiration_piece IS NULL, expiration_piece ASC')
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Client')
                    ->state(fn (User $record) => trim("{$record->first_name} {$record->last_name}"))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['last_name']),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('phone')
                    ->label('Téléphone')
                    ->searchable()
                    ->default('—'),

                TextColumn::make('type_piece')
                    ->label('Type de pièce')
                    ->badge()
                    ->color('info')
                    ->state(fn (User $record) => $record->type_piece ?: ($record->onboardingSession?->payload['type_piece'] ?? 'CNI')),

                TextColumn::make('num_piece')
                    ->label('N° Document')
                    ->searchable()
                    ->state(fn (User $record) => $record->num_piece ?: ($record->onboardingSession?->payload['num_piece'] ?? '—')),

                TextColumn::make('effective_expiration_piece')
                    ->label('Date d\'expiration')
                    ->state(fn (User $record) => $record->effective_expiration_piece ? Carbon::parse($record->effective_expiration_piece)->format('d/m/Y') : '—')
                    ->sortable(['expiration_piece']),

                TextColumn::make('id_status')
                    ->label('Statut')
                    ->badge()
                    ->state(function (User $record) {
                        if ($record->is_id_expired) return 'Expirée';
                        if ($record->is_id_expiring_soon) return 'Expire bientôt';
                        if ($record->effective_expiration_piece) return 'Valide';
                        return 'Non renseignée';
                    })
                    ->color(function (User $record) {
                        if ($record->is_id_expired) return 'danger';
                        if ($record->is_id_expiring_soon) return 'warning';
                        if ($record->effective_expiration_piece) return 'success';
                        return 'gray';
                    }),

                TextColumn::make('id_days_until_expiration')
                    ->label('Jours restants')
                    ->state(function (User $record) {
                        $days = $record->id_days_until_expiration;
                        if ($days === null) return '—';
                        if ($days < 0) return abs($days) . ' j passés';
                        if ($days === 0) return 'Aujourd\'hui !';
                        return $days . ' j';
                    })
                    ->color(fn (User $record) => ($record->id_days_until_expiration !== null && $record->id_days_until_expiration <= 0) ? 'danger' : 'gray'),

                TextColumn::make('last_id_expiry_reminder_at')
                    ->label('Dernier rappel')
                    ->state(fn (User $record) => $record->last_id_expiry_reminder_at ? Carbon::parse($record->last_id_expiry_reminder_at)->format('d/m/Y H:i') : 'Aucun')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('statut_piece')
                    ->label('Statut d\'échéance')
                    ->options([
                        'expired' => 'Pièces Expirées',
                        'expiring_30' => 'Expire dans 30 jours',
                        'expiring_60' => 'Expire dans 60 jours',
                        'valid' => 'Valides (> 30 jours)',
                        'missing' => 'Date non renseignée',
                    ])
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? null;
                        if (! $value) return;

                        $now = Carbon::now()->toDateString();
                        $in30 = Carbon::now()->addDays(30)->toDateString();
                        $in60 = Carbon::now()->addDays(60)->toDateString();

                        match ($value) {
                            'expired' => $query->whereNotNull('expiration_piece')->where('expiration_piece', '<=', $now),
                            'expiring_30' => $query->whereNotNull('expiration_piece')->where('expiration_piece', '>', $now)->where('expiration_piece', '<=', $in30),
                            'expiring_60' => $query->whereNotNull('expiration_piece')->where('expiration_piece', '>', $now)->where('expiration_piece', '<=', $in60),
                            'valid' => $query->whereNotNull('expiration_piece')->where('expiration_piece', '>', $in30),
                            'missing' => $query->whereNull('expiration_piece'),
                            default => null,
                        };
                    }),
            ])
            ->actions([
                Action::make('send_reminder')
                    ->label('Relancer')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->visible(fn (User $record): bool => (bool) $record->is_id_expired)
                    ->requiresConfirmation()
                    ->modalHeading('Envoyer un rappel d\'expiration de pièce')
                    ->modalDescription(fn (User $record) => "Un email transactionnel de mise à jour ainsi qu'une alerte in-app seront transmis à {$record->first_name} {$record->last_name} ({$record->email}).")
                    ->action(function (User $record) {
                        try {
                            $days = $record->id_days_until_expiration ?? 0;
                            Mail::to($record->email)->send(new IdDocumentExpiryReminderMail($record, $days));

                            InAppNotification::create([
                                'user_id' => $record->id,
                                'title' => 'Action requise : Votre pièce d\'identité a expiré',
                                'body' => 'Veuillez renouveler votre document d\'identification dans votre profil pour maintenir la conformité de votre compte.',
                                'type' => 'warning',
                            ]);

                            $record->last_id_expiry_reminder_at = now();
                            $record->save();

                            Notification::make()
                                ->title('Rappel envoyé avec succès')
                                ->body("Le rappel a été adressé à {$record->email}.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Erreur lors de l\'envoi')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                BulkAction::make('bulk_send_reminders')
                    ->label('Envoyer un rappel aux pièces expirées sélectionnées')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Relancer les pièces expirées')
                    ->modalDescription('Seuls les clients dont la pièce est effectivement expirée recevront le rappel.')
                    ->action(function (Collection $records) {
                        $expiredRecords = $records->filter(fn (User $u) => (bool) $u->is_id_expired);

                        if ($expiredRecords->isEmpty()) {
                            Notification::make()
                                ->title('Aucune pièce expirée')
                                ->body('Aucun des clients sélectionnés ne possède de pièce expirée.')
                                ->warning()
                                ->send();
                            return;
                        }

                        $count = 0;
                        foreach ($expiredRecords as $record) {
                            try {
                                $days = $record->id_days_until_expiration ?? 0;
                                Mail::to($record->email)->send(new IdDocumentExpiryReminderMail($record, $days));
                                InAppNotification::create([
                                    'user_id' => $record->id,
                                    'title' => 'Action requise : Votre pièce d\'identité a expiré',
                                    'body' => 'Veuillez renouveler votre document d\'identification dans votre profil pour maintenir la conformité de votre compte.',
                                    'type' => 'warning',
                                ]);
                                $record->last_id_expiry_reminder_at = now();
                                $record->save();
                                $count++;
                            } catch (\Throwable $e) {
                                // continue
                            }
                        }

                        Notification::make()
                            ->title("Rappels envoyés ({$count})")
                            ->body("Les rappels ont été envoyés à {$count} client(s) ayant une pièce expirée.")
                            ->success()
                            ->send();
                    }),
            ]);
    }
}