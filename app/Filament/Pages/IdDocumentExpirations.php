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

    protected static ?string $navigationGroup = 'ConformitÃ© & CRM';

    protected static ?string $navigationLabel = 'Ã‰chÃ©ances PiÃ¨ces d\'IdentitÃ©';

    protected static ?string $title = 'Suivi des Ã‰chÃ©ances des PiÃ¨ces d\'IdentitÃ©';

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
                    ->label('TÃ©lÃ©phone')
                    ->searchable()
                    ->default('â€”'),

                TextColumn::make('type_piece')
                    ->label('Type de piÃ¨ce')
                    ->badge()
                    ->color('info')
                    ->state(fn (User $record) => $record->type_piece ?: ($record->onboardingSession?->payload['type_piece'] ?? 'CNI')),

                TextColumn::make('num_piece')
                    ->label('NÂ° Document')
                    ->searchable()
                    ->state(fn (User $record) => $record->num_piece ?: ($record->onboardingSession?->payload['num_piece'] ?? 'â€”')),

                TextColumn::make('effective_expiration_piece')
                    ->label('Date d\'expiration')
                    ->state(fn (User $record) => $record->effective_expiration_piece ? Carbon::parse($record->effective_expiration_piece)->format('d/m/Y') : 'â€”')
                    ->sortable(['expiration_piece']),

                TextColumn::make('id_status')
                    ->label('Statut')
                    ->badge()
                    ->state(function (User $record) {
                        if ($record->is_id_expired) return 'ExpirÃ©e';
                        if ($record->is_id_expiring_soon) return 'Expire bientÃ´t';
                        if ($record->effective_expiration_piece) return 'Valide';
                        return 'Non renseignÃ©e';
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
                        if ($days === null) return 'â€”';
                        if ($days < 0) return abs($days) . ' j passÃ©s';
                        if ($days === 0) return 'Aujourd\'hui !';
                        return $days . ' j';
                    })
                    ->color(fn (User $record) => ($record->id_days_until_expiration !== null && $record->id_days_until_expiration <= 0) ? 'danger' : 'gray'),

                TextColumn::make('last_id_expiry_reminder_at')
                    ->label('Dernier rappel')
                    ->dateTime('d/m/Y H:i')
                    ->default('Aucun')
                    ->sortable(),
            ])
            ->defaultSort('expiration_piece', 'asc')
            ->filters([
                SelectFilter::make('statut_piece')
                    ->label('Statut d\'Ã©chÃ©ance')
                    ->options([
                        'expired' => 'PiÃ¨ces ExpirÃ©es',
                        'expiring_30' => 'Expire dans 30 jours',
                        'expiring_60' => 'Expire dans 60 jours',
                        'valid' => 'Valides (> 30 jours)',
                        'missing' => 'Date non renseignÃ©e',
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
                    ->requiresConfirmation()
                    ->modalHeading('Envoyer un rappel d\'expiration de piÃ¨ce')
                    ->modalDescription(fn (User $record) => "Un email transactionnel de mise Ã  jour ainsi qu'une alerte in-app seront transmis Ã  {$record->first_name} {$record->last_name} ({$record->email}).")
                    ->action(function (User $record) {
                        try {
                            $days = $record->id_days_until_expiration ?? 0;
                            Mail::to($record->email)->send(new IdDocumentExpiryReminderMail($record, $days));

                            InAppNotification::create([
                                'user_id' => $record->id,
                                'title' => $days <= 0 ? 'Action requise : Votre piÃ¨ce d\'identitÃ© a expirÃ©' : "Rappel : Votre piÃ¨ce d'identitÃ© expire dans {$days} jours",
                                'body' => 'Veuillez renouveler votre document d\'identification dans votre profil pour maintenir la conformitÃ© de votre compte.',
                                'type' => 'warning',
                            ]);

                            $record->last_id_expiry_reminder_at = now();
                            $record->save();

                            Notification::make()
                                ->title('Rappel envoyÃ© avec succÃ¨s')
                                ->body("Le rappel a Ã©tÃ© adressÃ© Ã  {$record->email}.")
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
                    ->label('Envoyer un rappel aux clients sÃ©lectionnÃ©s')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (Collection $records) {
                        $count = 0;
                        foreach ($records as $record) {
                            try {
                                $days = $record->id_days_until_expiration ?? 0;
                                Mail::to($record->email)->send(new IdDocumentExpiryReminderMail($record, $days));
                                InAppNotification::create([
                                    'user_id' => $record->id,
                                    'title' => $days <= 0 ? 'Action requise : Votre piÃ¨ce d\'identitÃ© a expirÃ©' : "Rappel : Votre piÃ¨ce d'identitÃ© expire dans {$days} jours",
                                    'body' => 'Veuillez renouveler votre document d\'identification dans votre profil pour maintenir la conformitÃ© de votre compte.',
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
                            ->title("Rappels envoyÃ©s ({$count})")
                            ->body("Les rappels ont Ã©tÃ© envoyÃ©s Ã  {$count} client(s).")
                            ->success()
                            ->send();
                    }),
            ]);
    }
}