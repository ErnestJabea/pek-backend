<?php

namespace App\Filament\Pages;

use App\Mail\ClientBirthdayMail;
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

class ClientBirthdays extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-cake';

    protected static ?string $navigationGroup = 'Conformité & CRM';

    protected static ?string $navigationLabel = 'Anniversaires Clients';

    protected static ?string $title = 'Suivi des Dates de Naissance & Anniversaires';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.client-birthdays';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->where('role', '!=', 'admin')
                    ->whereNull('admin_department_id')
                    ->whereDoesntHave('roles')
                    ->whereNull('deleted_at')
                    ->with('onboardingSession')
                    ->orderByRaw('dob IS NULL, dob DESC')
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

                TextColumn::make('effective_dob')
                    ->label('Date de naissance')
                    ->state(fn (User $record) => $record->effective_dob ? Carbon::parse($record->effective_dob)->format('d/m/Y') : '—')
                    ->sortable(['dob']),

                TextColumn::make('age')
                    ->label('Âge actuel')
                    ->state(fn (User $record) => $record->age !== null ? "{$record->age} ans" : '—'),

                TextColumn::make('birthday_status')
                    ->label('Statut')
                    ->badge()
                    ->state(function (User $record) {
                        if ($record->is_birthday_today) return 'Aujourd\'hui 🎉';
                        $days = $record->days_until_next_birthday;
                        if ($days !== null && $days <= 7) return 'Cette semaine';
                        if ($record->effective_dob) {
                            $m = Carbon::parse($record->effective_dob)->month;
                            if ($m === now()->month) return 'Ce mois-ci';
                            return 'À venir';
                        }
                        return 'Non renseignée';
                    })
                    ->color(function (User $record) {
                        if ($record->is_birthday_today) return 'warning';
                        $days = $record->days_until_next_birthday;
                        if ($days !== null && $days <= 7) return 'info';
                        if ($record->effective_dob && Carbon::parse($record->effective_dob)->month === now()->month) return 'primary';
                        if ($record->effective_dob) return 'gray';
                        return 'danger';
                    }),

                TextColumn::make('days_until_next_birthday')
                    ->label('Prochain anniversaire')
                    ->state(function (User $record) {
                        $days = $record->days_until_next_birthday;
                        if ($days === null) return '—';
                        if ($days === 0) return 'Aujourd\'hui !';
                        return "dans {$days} jour(s)";
                    })
                    ->color(fn (User $record) => $record->days_until_next_birthday === 0 ? 'warning' : 'gray'),

                TextColumn::make('last_birthday_wish_sent_at')
                    ->label('Dernier souhait')
                    ->state(fn (User $record) => $record->last_birthday_wish_sent_at ? Carbon::parse($record->last_birthday_wish_sent_at)->format('d/m/Y H:i') : 'Aucun')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('periode')
                    ->label('Période d\'anniversaire')
                    ->options([
                        'today' => 'Aujourd\'hui 🎉',
                        'this_week' => 'Dans les 7 prochains jours',
                        'this_month' => 'Ce mois-ci',
                        'missing' => 'Date non renseignée',
                    ])
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? null;
                        if (! $value) return;

                        $today = Carbon::now();

                        match ($value) {
                            'today' => $query->whereNotNull('dob')->whereMonth('dob', $today->month)->whereDay('dob', $today->day),
                            'this_week' => $query->whereNotNull('dob')->where(function ($q) use ($today) {
                                for ($i = 0; $i <= 7; $i++) {
                                    $d = $today->copy()->addDays($i);
                                    $q->orWhere(fn ($sub) => $sub->whereMonth('dob', $d->month)->whereDay('dob', $d->day));
                                }
                            }),
                            'this_month' => $query->whereNotNull('dob')->whereMonth('dob', $today->month),
                            'missing' => $query->whereNull('dob'),
                            default => null,
                        };
                    }),
            ])
            ->actions([
                Action::make('send_wish')
                    ->label('Souhaiter')
                    ->icon('heroicon-o-gift')
                    ->color('success')
                    ->visible(fn (User $record): bool => (bool) $record->is_birthday_today)
                    ->requiresConfirmation()
                    ->modalHeading('Envoyer les vœux d\'anniversaire')
                    ->modalDescription(fn (User $record) => "Un email festif KAM ainsi qu'une notification in-app seront transmis à {$record->first_name} {$record->last_name} ({$record->email}).")
                    ->action(function (User $record) {
                        try {
                            Mail::to($record->email)->send(new ClientBirthdayMail($record));

                            InAppNotification::create([
                                'user_id' => $record->id,
                                'title' => 'Joyeux Anniversaire ! 🎉',
                                'body' => "Toute l'équipe de KORI Asset Management vous souhaite un très heureux anniversaire !",
                                'type' => 'info',
                            ]);

                            $record->last_birthday_wish_sent_at = now();
                            $record->save();

                            Notification::make()
                                ->title('Souhait d\'anniversaire envoyé')
                                ->body("Les vœux ont été adressés à {$record->email}.")
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
                BulkAction::make('bulk_send_wishes')
                    ->label('Envoyer les vœux aux anniversaires du jour')
                    ->icon('heroicon-o-gift')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Envoyer les vœux du jour')
                    ->modalDescription('Seuls les clients dont l\'anniversaire est aujourd\'hui recevront les vœux par email et notification.')
                    ->action(function (Collection $records) {
                        $todaysBirthdays = $records->filter(fn (User $u) => (bool) $u->is_birthday_today);

                        if ($todaysBirthdays->isEmpty()) {
                            Notification::make()
                                ->title('Aucun anniversaire aujourd\'hui')
                                ->body('Aucun des clients sélectionnés ne fête son anniversaire aujourd\'hui.')
                                ->warning()
                                ->send();
                            return;
                        }

                        $count = 0;
                        foreach ($todaysBirthdays as $record) {
                            try {
                                Mail::to($record->email)->send(new ClientBirthdayMail($record));
                                InAppNotification::create([
                                    'user_id' => $record->id,
                                    'title' => 'Joyeux Anniversaire ! 🎉',
                                    'body' => "Toute l'équipe de KORI Asset Management vous souhaite un très heureux anniversaire !",
                                    'type' => 'info',
                                ]);
                                $record->last_birthday_wish_sent_at = now();
                                $record->save();
                                $count++;
                            } catch (\Throwable $e) {
                                // continue
                            }
                        }

                        Notification::make()
                            ->title("Vœux envoyés ({$count})")
                            ->body("Les souhaits ont été envoyés à {$count} client(s) fêtant leur anniversaire aujourd'hui.")
                            ->success()
                            ->send();
                    }),
            ]);
    }
}