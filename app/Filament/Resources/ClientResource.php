<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages;
use App\Models\City;
use App\Models\Client;
use App\Models\Country;
use App\Services\ClientActivationService;
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
            ->onlyClients();
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
                Forms\Components\Section::make('Informations Personnelles & Contact')
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
                            ->unique(table: 'users', column: 'email', ignorable: fn (?Client $record) => $record)
                            ->required(),
                        Forms\Components\TextInput::make('phone')
                            ->label(__('messages.phone'))
                            ->tel(),
                        Forms\Components\Select::make('country')
                            ->label(__('messages.country') !== 'messages.country' ? __('messages.country') : 'Pays de résidence')
                            ->options(function (?Client $record) {
                                $countries = Country::query()
                                    ->where('is_active', true)
                                    ->orderBy('sort_order')
                                    ->orderBy('name')
                                    ->pluck('name', 'name')
                                    ->toArray();

                                if ($record && $record->country && ! isset($countries[$record->country])) {
                                    $countries[$record->country] = $record->country;
                                }

                                if (empty($countries)) {
                                    $countries = [
                                        'Cameroun' => 'Cameroun',
                                        'Gabon' => 'Gabon',
                                        'Congo' => 'Congo',
                                        'Tchad' => 'Tchad',
                                        'Centrafrique' => 'Centrafrique',
                                        'Guinée Équatoriale' => 'Guinée Équatoriale',
                                        "Côte d'Ivoire" => "Côte d'Ivoire",
                                        'Sénégal' => 'Sénégal',
                                        'France' => 'France',
                                        'Belgique' => 'Belgique',
                                        'Canada' => 'Canada',
                                        'États-Unis' => 'États-Unis',
                                    ];
                                }

                                return $countries;
                            })
                            ->default('Cameroun')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('city', null))
                            ->required(),
                        Forms\Components\Select::make('city')
                            ->label(__('messages.city') !== 'messages.city' ? __('messages.city') : 'Ville')
                            ->options(function (Forms\Get $get, ?Client $record) {
                                $countryName = $get('country') ?? $record?->country ?? 'Cameroun';
                                if (! $countryName) {
                                    return [];
                                }

                                $country = Country::query()
                                    ->where('name', $countryName)
                                    ->orWhere('code', $countryName)
                                    ->first();

                                $cities = [];
                                if ($country) {
                                    $cities = City::query()
                                        ->where('country_id', $country->id)
                                        ->where('is_active', true)
                                        ->orderBy('sort_order')
                                        ->orderBy('name')
                                        ->pluck('name', 'name')
                                        ->toArray();
                                }

                                if ($record && $record->city && ! isset($cities[$record->city])) {
                                    $cities[$record->city] = $record->city;
                                }

                                return $cities;
                            })
                            ->searchable()
                            ->preload()
                            ->createOptionUsing(function (string $value, Forms\Get $get) {
                                $cleanVal = trim($value);
                                $countryName = $get('country') ?? 'Cameroun';
                                $country = Country::query()->where('name', $countryName)->first();
                                if ($country && $cleanVal !== '') {
                                    City::firstOrCreate([
                                        'country_id' => $country->id,
                                        'name' => $cleanVal,
                                    ]);
                                }

                                return $cleanVal;
                            })
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
                    ])->columns(2),

                Forms\Components\Section::make('Activation & Statut E-mail (Accès PWA)')
                    ->description('Définissez le statut d\'activation du compte client pour son accès à la PWA.')
                    ->schema([
                        Forms\Components\Toggle::make('email_verified_at')
                            ->label('Marquer l\'e-mail comme déjà vérifié')
                            ->helperText('Active immédiatement le compte du client pour la PWA (recommandé pour les créations manuelles / guichet).')
                            ->default(true)
                            ->formatStateUsing(fn ($state) => (bool) $state)
                            ->dehydrateStateUsing(fn ($state) => $state ? now() : null),

                        Forms\Components\Toggle::make('send_activation_email')
                            ->label('Envoyer un e-mail d\'activation / bienvenue')
                            ->helperText('Envoie un e-mail officiel KORI au client avec son code d\'activation OTP.')
                            ->default(false)
                            ->dehydrated(false),
                    ])->columns(2),
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
                Tables\Columns\TextColumn::make('country')
                    ->label(__('messages.country') !== 'messages.country' ? __('messages.country') : 'Pays')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city')
                    ->label(__('messages.city') !== 'messages.city' ? __('messages.city') : 'Ville')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('categorie_client')
                    ->label('Catégorie')
                    ->badge()
                    ->color('info')
                    ->default('Particulier')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email_verified_at')
                    ->label('Statut E-mail')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Vérifié' : 'En attente')
                    ->color(fn ($state) => $state ? 'success' : 'warning')
                    ->description(function (Client $record) {
                        if (! $record->email_verified_at) {
                            return 'Bloqué PWA';
                        }
                        try {
                            return Carbon::parse($record->email_verified_at)->format('d/m/Y');
                        } catch (\Throwable) {
                            return 'Vérifié';
                        }
                    })
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
                Tables\Filters\Filter::make('email_verified')
                    ->label('E-mail vérifié (Actif PWA)')
                    ->query(fn (Builder $query) => $query->whereNotNull('email_verified_at')),
                Tables\Filters\Filter::make('email_unverified')
                    ->label('E-mail non vérifié (En attente)')
                    ->query(fn (Builder $query) => $query->whereNull('email_verified_at')),
            ])
            ->actions([
                Tables\Actions\Action::make('verify_email')
                    ->label('Valider E-mail')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Client $record): bool => ! $record->email_verified_at && ! $record->trashed())
                    ->requiresConfirmation()
                    ->modalHeading('Valider manuellement l\'adresse e-mail')
                    ->modalDescription(fn (Client $record) => "Confirmez-vous la validation de {$record->email} ? Le client pourra immédiatement se connecter à la PWA.")
                    ->action(function (Client $record) {
                        app(ClientActivationService::class)->validateEmail($record);
                        Notification::make()
                            ->title('E-mail validé avec succès')
                            ->body("Le compte de {$record->first_name} {$record->last_name} est désormais activé.")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('resend_activation')
                    ->label('Renvoyer OTP')
                    ->icon('heroicon-o-envelope')
                    ->color('info')
                    ->visible(fn (Client $record): bool => ! $record->trashed())
                    ->requiresConfirmation()
                    ->modalHeading('Renvoyer le code d\'activation OTP')
                    ->modalDescription(fn (Client $record) => "Un nouveau code OTP de vérification va être envoyé à {$record->email}.")
                    ->action(function (Client $record) {
                        try {
                            app(ClientActivationService::class)->sendActivationEmail($record);
                            Notification::make()
                                ->title('Code d\'activation envoyé')
                                ->body("Un nouveau code OTP a été transmis à {$record->email}.")
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
                Tables\Actions\Action::make('revoke_verification')
                    ->label('Révoquer E-mail')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (Client $record): bool => (bool) $record->email_verified_at && ! $record->trashed())
                    ->requiresConfirmation()
                    ->modalHeading('Révoquer la validation de l\'e-mail')
                    ->modalDescription(fn (Client $record) => "Êtes-vous sûr de vouloir révoquer la validation de {$record->email} ? L'accès à la PWA sera suspendu jusqu'à nouvelle vérification.")
                    ->action(function (Client $record) {
                        app(ClientActivationService::class)->invalidateEmail($record);
                        Notification::make()
                            ->title('Validation révoquée')
                            ->body("L'accès PWA de {$record->first_name} {$record->last_name} est désormais suspendu.")
                            ->warning()
                            ->send();
                    }),
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
                Tables\Actions\EditAction::make()
                    ->after(function (Client $record, array $data) {
                        if (! empty($data['send_activation_email'])) {
                            try {
                                app(ClientActivationService::class)->sendActivationEmail($record);
                                Notification::make()
                                    ->title('E-mail d\'activation envoyé')
                                    ->body("Un code de vérification a été transmis à {$record->email}.")
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Erreur d\'envoi d\'e-mail')
                                    ->body($e->getMessage())
                                    ->warning()
                                    ->send();
                            }
                        }
                    }),
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
                Tables\Actions\Action::make('portfolio_report')
                    ->label('Relevé & Portefeuille')
                    ->tooltip('Consulter la valorisation à date et générer le relevé officiel FCP')
                    ->icon('heroicon-o-chart-pie')
                    ->color('warning')
                    ->url(fn (Client $record): string => ClientResource::getUrl('portfolio-report', ['record' => $record])),
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
            'portfolio-report' => Pages\ViewClientPortfolioReport::route('/{record}/portfolio-report'),
        ];
    }
}
