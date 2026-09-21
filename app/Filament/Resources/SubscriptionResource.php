<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionResource\Pages;
use App\Jobs\ProcessSubscriptionReceipt;
use App\Models\Subscription;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriptionResource extends Resource
{
    public static function canCreate(): bool { return false; }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }
    protected static ?string $model = Subscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    public static function getModelLabel(): string
    {
        return __('messages.subscription');
    }

    public static function getPluralModelLabel(): string
    {
        return __('messages.subscriptions');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Rapprochement Maviance S3P')
                    ->visible(fn (?Subscription $record) => (bool) $record?->mobile_provider)
                    ->schema([
                        Forms\Components\TextInput::make('s3p_reference')->label('Référence PEK transmise')->disabled()->dehydrated(false),
                        Forms\Components\TextInput::make('s3p_ptn')->label('PTN unique du prestataire')->disabled()->dehydrated(false),
                        Forms\Components\TextInput::make('s3p_receipt_number')->label('Numéro du reçu opérateur (non unique)')->disabled()->dehydrated(false),
                        Forms\Components\TextInput::make('mobile_state')->label('État fournisseur')->disabled()->dehydrated(false),
                        Forms\Components\TextInput::make('s3p_error_code')->label('Code de retour')->disabled()->dehydrated(false),
                        Forms\Components\TextInput::make('s3p_provider_timestamp')->label('Horodatage de vérification fournisseur')->disabled()->dehydrated(false),
                        Forms\Components\TextInput::make('value_date')->label('Date de valeur retenue')->disabled()->dehydrated(false),
                        Forms\Components\TextInput::make('valuation_status')->label('État de valorisation')->disabled()->dehydrated(false),
                    ])->columns(2),
                Forms\Components\Select::make('user_id')
                    ->disabled()->dehydrated(false)
                    ->label(__('messages.client'))
                    ->relationship('user', 'last_name')
                    ->getOptionLabelFromRecordUsing(fn (User $record) => "{$record->first_name} {$record->last_name} - {$record->email}")
                    ->searchable(['first_name', 'last_name', 'email'])
                    ->required(),
                Forms\Components\Select::make('product_id')
                    ->disabled()->dehydrated(false)
                    ->label(__('messages.product'))
                    ->relationship('product', 'libelle')
                    ->required(),
                Forms\Components\TextInput::make('nb_parts')
                    ->disabled()->dehydrated(false)
                    ->label('Parts')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('prix_unitaire')
                    ->disabled()->dehydrated(false)
                    ->label('VL souscription')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('montant_total')
                    ->disabled()->dehydrated(false)
                    ->label(__('messages.amount'))
                    ->required()
                    ->numeric(),
                Forms\Components\Select::make('moyen_paiement')
                    ->disabled()->dehydrated(false)
                    ->label('Moyen de Paiement')
                    ->options([
                        'stripe' => 'Stripe',
                        'card' => 'Carte',
                        'bank_transfer' => 'Virement bancaire',
                        'maviance' => 'Maviance',
                        'mobile_money' => 'Mobile Money (e-nkap)',
                        'orange_money' => 'Orange Money',
                        'mtn_momo' => 'MTN MoMo',
                        'virement' => 'Virement Bancaire',
                        'manuel' => 'Demande de souscription',
                    ])
                    ->required(),
                Forms\Components\Select::make('statut')
                    ->disabled()->dehydrated(false)
                    ->label(__('messages.status'))
                    ->options([
                        'En attente' => 'En attente',
                        'Succès' => 'Succès',
                        'Échec' => 'Échec',
                        'À vérifier' => 'À vérifier',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('reference_transaction')
                    ->disabled()->dehydrated(false)
                    ->label('Réf. Transaction'),
                Forms\Components\TextInput::make('value_date')->label('Date de valeur (réception des fonds)')->disabled()->dehydrated(false),
                Forms\Components\TextInput::make('valuation_status')->label('Valorisation')->disabled()->dehydrated(false),
                Forms\Components\TextInput::make('mobile_state')->label('État S3P')->disabled()->dehydrated(false),
                Forms\Components\Textarea::make('internal_notes')->label('Notes internes')->maxLength(5000),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.last_name')
                    ->label(__('messages.client'))
                    ->formatStateUsing(fn ($state, $record) => "{$record->user->first_name} {$record->user->last_name}")
                    ->searchable(['first_name', 'last_name', 'email'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.libelle')
                    ->label(__('messages.product'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('nb_parts')
                    ->label('Parts')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('prix_unitaire')
                    ->label('VL souscription')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('montant_total')
                    ->label(__('messages.amount'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('moyen_paiement')
                    ->label('Moyen')
                    ->searchable(),
                Tables\Columns\TextColumn::make('statut')
                    ->label(__('messages.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Succès' => 'success',
                        'En attente' => 'warning',
                        'Échec' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),
                Tables\Columns\IconColumn::make('compliance_reviewed_at')
                    ->label('Conformité Int.')
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->tooltip(fn (Subscription $record) => $record->compliance_reviewed_at ? "Validé par {$record->complianceReviewer?->first_name} le {$record->compliance_reviewed_at->format('d/m/Y H:i')}" : 'En attente de revue conformité'),
                Tables\Columns\IconColumn::make('accounting_reviewed_at')
                    ->label('Comptabilité Int.')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->tooltip(fn (Subscription $record) => $record->accounting_reviewed_at ? "Validé par {$record->accountingReviewer?->first_name} le {$record->accounting_reviewed_at->format('d/m/Y H:i')}" : 'En attente de rapprochement comptable'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('messages.date'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('value_date')->label('Date de valeur')->date(),
                Tables\Columns\TextColumn::make('valuation_status')->label('Valorisation')->badge(),
                Tables\Columns\TextColumn::make('mobile_state')->label('État mobile')->badge(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('reviewCompliance')
                    ->authorize(fn () => auth()->user()->can('review_subscription_compliance'))
                    ->label('Conformité OK')
                    ->icon('heroicon-o-shield-check')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Valider le contrôle de conformité interne')
                    ->modalDescription('Confirmez-vous que le contrôle interne de conformité est validé pour cette souscription ?')
                    ->visible(fn (Subscription $record) => ! $record->compliance_reviewed_at)
                    ->action(function (Subscription $record) {
                        abort_unless(auth()->user()->can('review_subscription_compliance'), 403);
                        $record->update([
                            'compliance_reviewed_at' => now(),
                            'compliance_reviewed_by_user_id' => auth()->id(),
                        ]);

                        Notification::make()
                            ->title('Contrôle conformité interne enregistré')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('reviewAccounting')
                    ->label('Confirmer les fonds reçus')
                    ->authorize(fn () => auth()->user()->can('confirm_bank_payment'))
                    ->form([
                        Forms\Components\DatePicker::make('received_at')->label('Date effective de réception des fonds')->maxDate(now())->required(),
                        Forms\Components\TextInput::make('amount')->label('Montant effectivement reçu (XAF)')->numeric()->minValue(1)->required(),
                        Forms\Components\TextInput::make('reference')->label('Référence de l’écriture bancaire')->maxLength(120)->required(),
                        Forms\Components\Select::make('bank_detail_id')->label('Compte ayant reçu les fonds (ancienne demande)')
                            ->options(fn () => \App\Models\BankDetail::pluck('bank_name', 'id'))
                            ->visible(fn (Subscription $record) => !$record->bank_snapshot)
                            ->required(fn (Subscription $record) => !$record->bank_snapshot),
                    ])
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Valider le rapprochement comptable interne')
                    ->modalDescription('Confirmez-vous la réception des fonds et le rapprochement comptable pour cette souscription ?')
                    ->visible(fn (Subscription $record) => in_array($record->moyen_paiement, ['bank_transfer', 'virement']) && !$record->funds_received_at && $record->statut !== 'Succès')
                    ->action(function (Subscription $record, array $data) {
                        $record = app(\App\Services\Payments\BankPaymentService::class)->confirm($record, auth()->user(), $data);

                        Notification::make()
                            ->title($record->statut === 'Succès' ? 'Fonds confirmés et parts valorisées' : 'Fonds confirmés — en attente de la VL de la date de réception')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('resendReceipt')
                    ->authorize(fn () => auth()->user()->can('update_subscription'))
                    ->visible(fn (Subscription $record) => $record->statut === 'Succès')
                    ->label('Envoyer le reçu')
                    ->icon('heroicon-o-envelope')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function (Subscription $record) {
                        try {
                            ProcessSubscriptionReceipt::dispatch($record);
                            Notification::make()
                                ->title('Reçu envoyé')
                                ->body('Le reçu de souscription a été mis en file d\'attente pour envoi.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Erreur')
                                ->body("Impossible d'envoyer le reçu : ".$e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\SubscriptionResource\RelationManagers\PaymentProofsRelationManager::class,
            \App\Filament\Resources\SubscriptionResource\RelationManagers\PaymentEventsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptions::route('/'),
            'create' => Pages\CreateSubscription::route('/create'),
            'edit' => Pages\EditSubscription::route('/{record}/edit'),
        ];
    }
}
