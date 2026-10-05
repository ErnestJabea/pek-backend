<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages;
use App\Models\Client;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Opérations & Souscriptions';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->where('role', '!=', 'admin')
            ->whereNull('admin_department_id')
            ->whereDoesntHave('roles');
    }

    public static function getModelLabel(): string
    {
        return __('messages.client');
    }

    public static function getPluralModelLabel(): string
    {
        return __('messages.clients');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('first_name')
                    ->label(__('messages.first_name'))
                    ->required(),
                Forms\Components\TextInput::make('last_name')
                    ->label(__('messages.last_name'))
                    ->required(),
                Forms\Components\TextInput::make('email')
                    ->label(__('messages.email'))
                    ->email()
                    ->required(),
                Forms\Components\TextInput::make('phone')
                    ->label(__('messages.phone'))
                    ->tel(),
                Forms\Components\TextInput::make('city')
                    ->label(__('messages.city') !== 'messages.city' ? __('messages.city') : 'Ville')
                    ->required(),
                Forms\Components\TextInput::make('country')
                    ->label(__('messages.country') !== 'messages.country' ? __('messages.country') : 'Pays')
                    ->required(),
                Forms\Components\Select::make('type_client')
                    ->label('Type de client')
                    ->options(\App\Services\ClientCategoryService::getTypes())
                    ->dehydrated(false)
                    ->afterStateHydrated(function (Forms\Components\Select $component, ?Client $record) {
                        if ($record) {
                            $component->state(\App\Services\ClientCategoryService::getTypeForCategory($record->categorie_client));
                        }
                    })
                    ->live()
                    ->afterStateUpdated(fn (Forms\Set $set) => $set('categorie_client', null)),
                Forms\Components\Select::make('categorie_client')
                    ->label('Catégorie du client')
                    ->options(fn (Forms\Get $get): array => \App\Services\ClientCategoryService::getCategoriesForType($get('type_client')))
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('password')
                    ->label(__('messages.password'))
                    ->password()
                    ->required(fn (string $context): bool => $context === 'create')
                    ->dehydrated(fn ($state) => filled($state)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('first_name')
                    ->label(__('messages.first_name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->label(__('messages.last_name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label(__('messages.email'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label(__('messages.phone'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('city')
                    ->label(__('messages.city') !== 'messages.city' ? __('messages.city') : 'Ville')
                    ->searchable(),
                Tables\Columns\TextColumn::make('country')
                    ->label(__('messages.country') !== 'messages.country' ? __('messages.country') : 'Pays')
                    ->searchable(),
                Tables\Columns\TextColumn::make('categorie_client')
                    ->label('Catégorie')
                    ->badge()
                    ->color('info')
                    ->default('Particulier')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('messages.inscribed_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('categorie_client')
                    ->label('Catégorie client')
                    ->options(\App\Services\ClientCategoryService::getCategoriesByType()),
            ])
            ->actions([
                Tables\Actions\Action::make('remind_id')
                    ->label('Rappel Pièce')
                    ->icon('heroicon-o-identification')
                    ->color('warning')
                    ->visible(fn (Client $record): bool => (bool) $record->is_id_expired)
                    ->requiresConfirmation()
                    ->modalHeading('Envoyer un rappel de renouvellement de pièce')
                    ->action(function (Client $record) {
                        $days = $record->id_days_until_expiration ?? 0;
                        \Illuminate\Support\Facades\Mail::to($record->email)->send(new \App\Mail\IdDocumentExpiryReminderMail($record, $days));
                        \App\Models\Notification::create([
                            'user_id' => $record->id,
                            'title' => 'Action requise : Votre pièce d\'identité a expiré',
                            'body' => 'Veuillez renouveler votre pièce d\'identité dans votre profil.',
                            'type' => 'warning',
                        ]);
                        $record->last_id_expiry_reminder_at = now();
                        $record->save();
                        Notification::make()->title('Rappel envoyé au client')->success()->send();
                    }),
                Tables\Actions\Action::make('wish_birthday')
                    ->label('Anniversaire')
                    ->icon('heroicon-o-cake')
                    ->color('success')
                    ->visible(fn (Client $record): bool => (bool) $record->is_birthday_today)
                    ->requiresConfirmation()
                    ->modalHeading('Envoyer les vœux d\'anniversaire')
                    ->action(function (Client $record) {
                        \Illuminate\Support\Facades\Mail::to($record->email)->send(new \App\Mail\ClientBirthdayMail($record));
                        \App\Models\Notification::create([
                            'user_id' => $record->id,
                            'title' => 'Joyeux Anniversaire ! 🎉',
                            'body' => "Toute l'équipe de KORI Asset Management vous souhaite un très heureux anniversaire !",
                            'type' => 'info',
                        ]);
                        $record->last_birthday_wish_sent_at = now();
                        $record->save();
                        Notification::make()->title('Vœux d\'anniversaire envoyés')->success()->send();
                    }),
                Tables\Actions\Action::make('update_categorie')
                    ->label('Catégorie')
                    ->icon('heroicon-o-identification')
                    ->color('warning')
                    ->modalHeading('Modifier la catégorie du client')
                    ->modalDescription('Définissez le type et la catégorie d\'investisseur du client pour ses bulletins de souscription.')
                    ->fillForm(function (Client $record): array {
                        $currentCategory = $record->categorie_client ?? ($record->onboardingSession?->payload['categorie_client'] ?? null);
                        $type = \App\Services\ClientCategoryService::getTypeForCategory($currentCategory);

                        if ($record->onboardingSession && (! empty($record->onboardingSession->payload['rccm']) || ! empty($record->onboardingSession->payload['denomination']))) {
                            $type = \App\Services\ClientCategoryService::TYPE_MORALE;
                        }

                        return [
                            'type_client' => $type,
                            'categorie_client' => $currentCategory,
                        ];
                    })
                    ->form([
                        Forms\Components\Select::make('type_client')
                            ->label('Type de client')
                            ->options(\App\Services\ClientCategoryService::getTypes())
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('categorie_client', null)),

                        Forms\Components\Select::make('categorie_client')
                            ->label('Catégorie du client')
                            ->options(fn (Forms\Get $get): array => \App\Services\ClientCategoryService::getCategoriesForType($get('type_client')))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Client $record, array $data) {
                        $record->update(['categorie_client' => $data['categorie_client']]);

                        if ($record->onboardingSession) {
                            $session = $record->onboardingSession;
                            $payload = $session->payload ?? [];
                            $payload['categorie_client'] = $data['categorie_client'];
                            $payload['nature_client'] = $data['type_client'] === \App\Services\ClientCategoryService::TYPE_MORALE ? 'personne_morale' : 'personne_physique';
                            $session->payload = $payload;

                            $submitted = $session->submitted_payload ?? [];
                            $submitted['categorie_client'] = $data['categorie_client'];
                            $submitted['nature_client'] = $payload['nature_client'];
                            $session->submitted_payload = $submitted;

                            $session->save();
                        }

                        Notification::make()
                            ->title('Catégorie client mise à jour')
                            ->body("La catégorie de {$record->first_name} {$record->last_name} est désormais : {$data['categorie_client']}")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
                Tables\Actions\Action::make('create_onboarding')
                    ->label('Créer Onboarding')
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Créer la session d\'onboarding')
                    ->modalDescription('Êtes-vous sûr de vouloir initialiser la session d\'onboarding pour ce client ? Ses informations de base seront pré-remplies.')
                    ->modalSubmitActionLabel('Créer')
                    ->visible(fn (Client $record) => ! $record->trashed() && ! $record->onboardingSession()->exists())
                    ->action(function (Client $record) {
                        $record->onboardingSession()->create([
                            'current_step' => 'kyc',
                            'status' => 'in_progress',
                            'payload' => [
                                'nom' => $record->last_name,
                                'prenom' => $record->first_name,
                                'email' => $record->email,
                                'tel' => $record->phone,
                                'pays_residence' => $record->country,
                                'adresse' => $record->city,
                            ],
                        ]);

                        \App\Models\Notification::create([
                            'user_id' => $record->id,
                            'title' => 'Session d’onboarding initialisée',
                            'body' => 'Votre dossier d’onboarding a été créé par votre conseiller. Vous pouvez dès à présent compléter vos informations.',
                            'type' => 'info',
                        ]);

                        Notification::make()
                            ->title('Session d\'onboarding créée avec succès')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('view_onboarding')
                    ->label('Voir Onboarding')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('info')
                    ->visible(fn (Client $record) => $record->onboardingSession()->exists())
                    ->url(fn (Client $record) => OnboardingSessionResource::getUrl('view', ['record' => $record->onboardingSession])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('export_csv')
                        ->label('Exporter en CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (Collection $records) {
                            return response()->streamDownload(function () use ($records) {
                                $stream = fopen('php://output', 'wb');
                                fwrite($stream, "\xEF\xBB\xBF");
                                fputcsv($stream, ['Prénom', 'Nom', 'Email', 'Téléphone', 'Ville', 'Pays', 'Inscrit le'], ';');

                                foreach ($records as $record) {
                                    fputcsv($stream, array_map(
                                        static function ($value): string {
                                            $value = (string) $value;

                                            return preg_match('/^[=+\-@]/u', ltrim($value)) ? "'{$value}" : $value;
                                        },
                                        [
                                            $record->first_name,
                                            $record->last_name,
                                            $record->email,
                                            $record->phone,
                                            $record->city,
                                            $record->country,
                                            $record->created_at?->toIso8601String(),
                                        ]
                                    ), ';');
                                }

                                fclose($stream);
                            }, 'clients-'.now()->format('Ymd-His').'.csv', [
                                'Content-Type' => 'text/csv; charset=UTF-8',
                                'Cache-Control' => 'no-store, private',
                            ]);
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageClients::route('/'),
        ];
    }
}
