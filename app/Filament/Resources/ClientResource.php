<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages;
use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

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
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('messages.inscribed_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('create_onboarding')
                    ->label('Créer Onboarding')
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Créer la session d\'onboarding')
                    ->modalDescription('Êtes-vous sûr de vouloir initialiser la session d\'onboarding pour ce client ? Ses informations de base seront pré-remplies.')
                    ->modalSubmitActionLabel('Créer')
                    ->visible(fn (Client $record) => ! $record->onboardingSession()->exists())
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
