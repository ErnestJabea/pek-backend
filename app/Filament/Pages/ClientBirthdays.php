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
                    ->label('Téléphone')
                    ->searchable()
                    ->default('—'),

                TextColumn::make('effective_dob')
                    ->label('Date de naissance')
                    ->state(fn (User $record) => $record->effective_dob ? Carbon::parse($record->effective_dob)->format('d/m/Y') : '—')
                    ->sortable(['dob']),

                TextColumn::make('age')
                    ->label('ge actuel')
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
                    ->dateTime('d/m/Y H:i')
                    ->default('Aucun')
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
                    ->label('Envoyer les vœux aux clients sélectionnés')
                    ->icon('heroicon-o-gift')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Collection $records) {
                        $count = 0;
                        foreach ($records as $record) {
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
                            ->body("Les souhaits ont été envoyés à {$count} client(s).")
                            ->success()
                            ->send();
                    }),
            ]);
    }
}