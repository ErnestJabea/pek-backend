<?php

namespace App\Filament\Resources;

use App\Filament\Actions\BankSubscriptionActions;
use App\Filament\Resources\SubscriptionResource\Pages;
use App\Filament\Resources\SubscriptionResource\RelationManagers\PaymentEventsRelationManager;
use App\Filament\Resources\SubscriptionResource\RelationManagers\PaymentProofsRelationManager;
use App\Jobs\ProcessSubscriptionReceipt;
use App\Models\Product;
use App\Models\ProductVl;
use App\Models\Subscription;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SubscriptionResource extends Resource
{
    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('super_admin') || auth()->user()?->can('create_subscription');
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasRole('super_admin') || auth()->user()?->can('update_subscription');
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasRole('super_admin') || auth()->user()?->can('delete_subscription');
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') || auth()->user()?->can('delete_any_subscription');
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->hasRole('super_admin') || auth()->user()?->can('view_subscription') || auth()->user()?->can('view_any_subscription');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') || auth()->user()?->can('view_any_subscription');
    }

    protected static ?string $model = Subscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Opérations & Souscriptions';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('user', function (Builder $query) {
                $query->onlyClients();
            });
    }

    public static function getModelLabel(): string
    {
        return __('messages.subscription');
    }

    public static function getPluralModelLabel(): string
    {
        return __('messages.subscriptions');
    }

    /**
     * Recherche et applique strictement la VL officielle la plus proche (<= date de valeur)
     * et calcule automatiquement :
     * 1. Le Montant de placement net = Montant total / (1 + Taux frais)
     * 2. Les Frais d'entrée = Montant total - Montant placement
     * 3. Le Nombre de parts = Montant placement net / VL
     */
    public static function calculateVlAndParts(Forms\Get $get, Forms\Set $set): void
    {
        $productId = $get('product_id');
        $valueDate = $get('value_date');
        $montantTotal = (float) $get('montant_total');
        $tauxFrais = (float) ($get('taux_frais') ?? 1.0); // 1.00% par défaut
        $modeCalcul = $get('mode_calcul_frais') ?? 'en_sus';

        $montantPlacement = 0.0;
        $fraisEntree = 0.0;

        if ($modeCalcul === 'en_sus') {
            // Mode Frais en sus : Le client paie le capital net souhaité + la commission (ex: 50 500 versés => 50 000 net + 500 frais)
            if ($montantTotal > 0) {
                $taux = max(0.0, $tauxFrais) / 100.0;
                $montantPlacement = round($montantTotal / (1.0 + $taux));
                $fraisEntree = round($montantTotal - $montantPlacement);

                $set('investment_amount', (string) ((int) $montantPlacement));
                $set('subscription_fee', (string) ((int) $fraisEntree));
            } else {
                $set('investment_amount', '0');
                $set('subscription_fee', '0');
            }
        } elseif ($modeCalcul === 'deduit') {
            // Mode Frais déduits : Frais prélevés sur une enveloppe fixe (ex: 50 000 versés => 49 500 net + 500 frais)
            if ($montantTotal > 0) {
                $taux = max(0.0, $tauxFrais) / 100.0;
                $fraisEntree = round($montantTotal * $taux);
                $montantPlacement = max(0.0, $montantTotal - $fraisEntree);

                $set('investment_amount', (string) ((int) $montantPlacement));
                $set('subscription_fee', (string) ((int) $fraisEntree));
            } else {
                $set('investment_amount', '0');
                $set('subscription_fee', '0');
            }
        } else {
            // Mode manuel : l'opérateur a la main directe sur les montants
            $montantPlacement = (float) ($get('investment_amount') ?? 0);
            $fraisEntree = (float) ($get('subscription_fee') ?? 0);

            if ($montantPlacement <= 0 && $montantTotal > 0) {
                $montantPlacement = $montantTotal;
                $set('investment_amount', (string) ((int) $montantPlacement));
            }
        }

        $vl = 0.0;
        if ($modeCalcul === 'manuel' && (float) $get('prix_unitaire') > 0) {
            $vl = (float) $get('prix_unitaire');
        } elseif ($productId && $valueDate) {
            $nearestVl = ProductVl::where('product_id', $productId)
                ->where('date_vl', '<=', $valueDate)
                ->orderByDesc('date_vl')
                ->first();

            if ($nearestVl) {
                $vl = (float) $nearestVl->vl;
                $set('prix_unitaire', (string) $vl);
                $set('nav_date', $nearestVl->date_vl?->toDateString() ?: (string) $nearestVl->date_vl);
            } else {
                $earliestVl = ProductVl::where('product_id', $productId)
                    ->orderBy('date_vl', 'asc')
                    ->first();

                $product = Product::find($productId);
                $vl = $earliestVl ? (float) $earliestVl->vl : (float) ($product?->vl ?? 10000.0);
                $set('prix_unitaire', (string) $vl);
                $set('nav_date', $earliestVl?->date_vl?->toDateString() ?? $valueDate);
            }
        }

        if ($vl <= 0 && (float) $get('prix_unitaire') > 0) {
            $vl = (float) $get('prix_unitaire');
        }

        $baseCalcul = $montantPlacement > 0 ? $montantPlacement : $montantTotal;
        if ($baseCalcul > 0 && $vl > 0) {
            $set('nb_parts', (string) round($baseCalcul / $vl, 4));
        }
    }

    public static function form(Form $form): Form
    {
        $canEdit = auth()->user()?->hasRole('super_admin') || auth()->user()?->can('update_subscription');

        return $form->schema([
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

            Forms\Components\Section::make('1. Client & Fonds Souscrit')
                ->schema([
                    Forms\Components\Select::make('user_id')
                        ->label(__('messages.client'))
                        ->relationship(
                            'user',
                            'last_name',
                            fn (Builder $query) => $query->onlyClients()
                        )
                        ->getOptionLabelFromRecordUsing(fn (User $record) => "{$record->first_name} {$record->last_name} ({$record->email} - {$record->phone})")
                        ->searchable(['first_name', 'last_name', 'email', 'phone'])
                        ->preload()
                        ->required()
                        ->disabled(fn ($operation) => !$canEdit || ($operation === 'edit' && !auth()->user()?->hasRole('super_admin'))),

                    Forms\Components\Select::make('product_id')
                        ->label(__('messages.product'))
                        ->relationship('product', 'libelle')
                        ->default(1)
                        ->required()
                        ->disabled(!$canEdit)
                        ->live()
                        ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => self::calculateVlAndParts($get, $set)),

                    Forms\Components\Toggle::make('is_historical')
                        ->label('Opération historique / Reprise d\'antériorité')
                        ->default(true)
                        ->disabled(!$canEdit)
                        ->helperText('En mode historique, aucun email ni notification automatique ne sera envoyé au client.')
                        ->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make('2. Date de Valeur & Valeur Liquidative')
                ->schema([
                    Forms\Components\DatePicker::make('value_date')
                        ->label('Date de valeur (date de souscription)')
                        ->default(now()->toDateString())
                        ->maxDate(now())
                        ->required()
                        ->disabled(!$canEdit)
                        ->live()
                        ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => self::calculateVlAndParts($get, $set)),

                    Forms\Components\TextInput::make('prix_unitaire')
                        ->label('Valeur Liquidative retenue (FCFA)')
                        ->numeric()
                        ->step('any')
                        ->disabled(fn (Forms\Get $get) => !$canEdit || $get('mode_calcul_frais') !== 'manuel')
                        ->dehydrated()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => self::calculateVlAndParts($get, $set))
                        ->default(fn () => (float) (ProductVl::where('product_id', 1)->where('date_vl', '<=', now()->toDateString())->orderByDesc('date_vl')->value('vl') ?? 10000.0))
                        ->helperText(fn (Forms\Get $get) => $get('mode_calcul_frais') === 'manuel'
                            ? 'Déverrouillé en mode manuel (ajustable librement)'
                            : 'Calculée automatiquement : dernière VL officielle <= Date de valeur'),

                    Forms\Components\TextInput::make('nav_date')
                        ->label('Date officielle de la VL')
                        ->disabled()
                        ->dehydrated()
                        ->placeholder('Auto-détectée'),
                ])->columns(3),

            Forms\Components\Section::make('3. Montant, Frais & Attribution des Parts')
                ->schema([
                    Forms\Components\Radio::make('mode_calcul_frais')
                        ->label('Modalité de prise en compte des frais')
                        ->options([
                            'en_sus' => 'Frais en sus / Net rond (Ex: 50 500 versés → 50 000 net + 500 frais)',
                            'deduit' => 'Frais déduits du total (Ex: 50 000 versés → 49 500 net + 500 frais)',
                            'manuel' => 'Saisie manuelle personnalisée (Déverrouillage des montants)',
                        ])
                        ->descriptions([
                            'en_sus' => 'Le client a versé le capital désiré + 1% de commission (ex: 50 500 pour 50 000 net, 101 000 pour 100 000 net).',
                            'deduit' => 'Le client a versé une enveloppe forfaitaire dont la commission de 1% est retranchée.',
                            'manuel' => 'Déverrouille le montant net placé et les frais pour un ajustement sur mesure au franc près.',
                        ])
                        ->default('deduit')
                        ->dehydrated()
                        ->disabled(!$canEdit)
                        ->live()
                        ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => self::calculateVlAndParts($get, $set))
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('montant_total')
                        ->label('Montant total versé (FCFA)')
                        ->numeric()
                        ->required()
                        ->disabled(!$canEdit)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => self::calculateVlAndParts($get, $set))
                        ->helperText(fn (Forms\Get $get) => $get('mode_calcul_frais') === 'en_sus'
                            ? 'Exemple : 50 500 FCFA (pour 50 000 FCFA net) ou 101 000 FCFA'
                            : 'Exemple : 50 000 FCFA (pour 49 500 FCFA net) ou 100 000 FCFA'),

                    Forms\Components\Select::make('taux_frais')
                        ->label('Barème des frais d\'entrée')
                        ->options([
                            '1' => '1,00 % (Standard KORI)',
                            '0' => '0,00 % (Exonéré / Sans frais)',
                            '0.5' => '0,50 % (Tarif réduit)',
                            '1.5' => '1,50 %',
                            '2' => '2,00 %',
                        ])
                        ->default('1')
                        ->disabled(!$canEdit)
                        ->live()
                        ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => self::calculateVlAndParts($get, $set))
                        ->dehydrated(false)
                        ->helperText('Clé de répartition entre placement net et commission'),

                    Forms\Components\TextInput::make('investment_amount')
                        ->label('Montant de placement net (FCFA)')
                        ->numeric()
                        ->disabled(fn (Forms\Get $get) => !$canEdit || $get('mode_calcul_frais') !== 'manuel')
                        ->dehydrated()
                        ->required()
                        ->default('0')
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => self::calculateVlAndParts($get, $set))
                        ->helperText('Montant affecté à l\'achat de parts (génère la valeur liquidative du portefeuille)'),

                    Forms\Components\TextInput::make('subscription_fee')
                        ->label('Frais d\'entrée prélevés (FCFA)')
                        ->numeric()
                        ->disabled(fn (Forms\Get $get) => !$canEdit || $get('mode_calcul_frais') !== 'manuel')
                        ->dehydrated()
                        ->required()
                        ->default('0')
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => self::calculateVlAndParts($get, $set))
                        ->helperText('Commission prélevée par la société de gestion'),

                    Forms\Components\TextInput::make('nb_parts')
                        ->label('Nombre de parts calculées')
                        ->numeric()
                        ->step('any')
                        ->disabled(fn (Forms\Get $get) => !$canEdit || $get('mode_calcul_frais') !== 'manuel')
                        ->dehydrated()
                        ->default('0')
                        ->required()
                        ->helperText('Calculé automatiquement : Montant de placement net ÷ VL'),

                    Forms\Components\Select::make('moyen_paiement')
                        ->label('Moyen de paiement')
                        ->options([
                            'bank_transfer' => 'Virement bancaire',
                            'cheque' => 'Chèque',
                            'cash_deposit' => 'Dépôt d\'espèces / Bordereau',
                            'apport_titres' => 'Apport de titres',
                            'orange_money' => 'Orange Money',
                            'mtn_momo' => 'MTN Mobile Money',
                            'card' => 'Carte bancaire',
                            'autre' => 'Autre',
                        ])
                        ->default('bank_transfer')
                        ->disabled(!$canEdit)
                        ->required(),

                    Forms\Components\TextInput::make('bank_reference')
                        ->label('Référence bordereau / virement')
                        ->disabled(!$canEdit)
                        ->placeholder('Ex: VIR-2025-001'),

                    Forms\Components\TextInput::make('reference_transaction')
                        ->label('Référence de la transaction')
                        ->default(fn () => 'HIST-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)))
                        ->disabled(fn ($operation) => !$canEdit || ($operation === 'edit' && !auth()->user()?->hasRole('super_admin')))
                        ->required(),

                    Forms\Components\Select::make('statut')
                        ->label('Statut de l\'opération')
                        ->options([
                            'En attente' => 'En attente',
                            'Succès' => 'Succès (Validée)',
                            'Échec' => 'Échec',
                            'À vérifier' => 'À vérifier',
                        ])
                        ->default('En attente')
                        ->disabled(!$canEdit)
                        ->required(),

                    Forms\Components\Textarea::make('internal_notes')
                        ->label('Notes internes & motifs de modification')
                        ->maxLength(5000)
                        ->columnSpanFull(),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.last_name')
                    ->label(__('messages.client'))
                    ->formatStateUsing(fn ($state, $record) => "{$record->user?->first_name} {$record->user?->last_name}")
                    ->searchable(['first_name', 'last_name', 'email'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.libelle')
                    ->label(__('messages.product'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('montant_total')
                    ->label('Montant total')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' FCFA')
                    ->sortable(),
                Tables\Columns\TextColumn::make('investment_amount')
                    ->label('Placement net')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' FCFA')
                    ->placeholder(fn ($record) => number_format((float) ($record->montant_net ?? $record->montant_total), 0, ',', ' ') . ' FCFA')
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscription_fee')
                    ->label('Frais')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' FCFA')
                    ->placeholder(fn ($record) => number_format((float) ($record->frais_gestion ?? 0), 0, ',', ' ') . ' FCFA')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('prix_unitaire')
                    ->label('VL retenue')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(' FCFA')
                    ->description(function (Subscription $record): ?string {
                        if (!$record->prix_unitaire) return null;
                        $str = rtrim(rtrim((string) $record->prix_unitaire, '0'), '.');
                        $parts = explode('.', $str);
                        if (isset($parts[1]) && strlen($parts[1]) > 2) {
                            $intPart = number_format((float) ($parts[0] ?? 0), 0, '', ' ');
                            return 'Exacte : ' . $intPart . ',' . $parts[1] . ' FCFA';
                        }
                        return null;
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('nb_parts')
                    ->label('Parts')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),
                Tables\Columns\TextColumn::make('moyen_paiement')
                    ->label('Moyen')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'orange_money' => 'Orange Money',
                        'mtn_momo' => 'MTN MoMo',
                        'mobile_money' => 'Mobile Money',
                        'card' => 'Carte Bancaire',
                        'stripe' => 'Stripe',
                        'bank_transfer', 'virement' => 'Virement Bancaire',
                        'cheque' => 'Chèque',
                        'cash_deposit' => 'Dépôt d\'espèces',
                        'apport_titres' => 'Apport de titres',
                        default => $state ?? '-',
                    })
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('statut')
                    ->label(__('messages.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Succès' => 'success',
                        'En attente' => 'warning',
                        'Échec' => 'danger',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_historical')
                    ->label('Type')
                    ->boolean()
                    ->trueIcon('heroicon-o-archive-box')
                    ->falseIcon('heroicon-o-device-phone-mobile')
                    ->trueColor('warning')
                    ->falseColor('info')
                    ->tooltip(fn (Subscription $record) => $record->is_historical ? 'Opération historique / reprise d\'antériorité (Mode silencieux)' : 'Opération directe PWA / Mobile')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('compliance_reviewed_at')
                    ->label('Conformité')
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->tooltip(fn (Subscription $record) => $record->compliance_reviewed_at ? "Validé par {$record->complianceReviewer?->first_name} le {$record->compliance_reviewed_at->format('d/m/Y H:i')}" : 'En attente de revue conformité')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('accounting_reviewed_at')
                    ->label('Comptabilité')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->tooltip(fn (Subscription $record) => $record->accounting_reviewed_at ? "Validé par {$record->accountingReviewer?->first_name} le {$record->accounting_reviewed_at->format('d/m/Y H:i')}" : 'En attente de rapprochement comptable')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('manager_reviewed_at')
                    ->label('Gérant')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->tooltip(fn (Subscription $record) => $record->manager_reviewed_at ? "Visé par {$record->managerReviewer?->first_name} le {$record->manager_reviewed_at->format('d/m/Y H:i')}" : 'En attente du visa gérant')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('value_date')
                    ->label('Date valeur')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Saisi le')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_proof_reminder_at')
                    ->label('Dernière relance')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                \App\Filament\Filters\DashboardFilter::make(static::class),
                Tables\Filters\TernaryFilter::make('is_historical')
                    ->label('Type d\'opération')
                    ->placeholder('Toutes les opérations')
                    ->trueLabel('Souscriptions Historiques uniquement')
                    ->falseLabel('Souscriptions Directes PWA uniquement'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                // 1. Visa Conformité
                Tables\Actions\Action::make('reviewCompliance')
                    ->label('Visa Conformité')
                    ->icon('heroicon-o-shield-check')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Apposer le visa de Conformité')
                    ->modalDescription('Confirmez-vous que les pièces d\'identification et les règles LAB/FT sont conformes pour cette opération ?')
                    ->visible(fn (Subscription $record) => ! $record->compliance_reviewed_at && (auth()->user()?->hasRole('super_admin') || auth()->user()?->can('review_subscription_compliance')))
                    ->action(function (Subscription $record) {
                        $record->update([
                            'compliance_reviewed_at' => now(),
                            'compliance_reviewed_by_user_id' => auth()->id(),
                        ]);

                        Notification::make()
                            ->title('Visa de Conformité apposé ✅')
                            ->success()
                            ->send();
                    }),

                // 2. Visa Comptabilité & Attribution des parts
                Tables\Actions\Action::make('reviewAccounting')
                    ->label('Visa Comptabilité')
                    ->icon('heroicon-o-check-badge')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmation des fonds & Attribution des parts')
                    ->modalDescription(fn (Subscription $record) => "Confirmez-vous que les fonds ont bien été reçus sur le compte séquestre du FCP ? Cela validera immédiatement l'attribution de {$record->nb_parts} parts du fonds {$record->product?->libelle} pour le client {$record->user?->first_name} {$record->user?->last_name}." . ($record->is_historical ? ' (Mode historique : Aucun mail ne sera envoyé au client)' : ''))
                    ->visible(fn (Subscription $record) => $record->statut !== 'Succès' && (auth()->user()?->hasRole('super_admin') || auth()->user()?->can('confirm_bank_payment')))
                    ->action(function (Subscription $record) {
                        $record->forceFill([
                            'accounting_reviewed_at' => now(),
                            'accounting_reviewed_by_user_id' => auth()->id(),
                            'manager_reviewed_at' => now(),
                            'manager_reviewed_by_user_id' => auth()->id(),
                            'statut' => 'Succès',
                            'valuation_status' => 'valued',
                            'funds_received_at' => $record->funds_received_at ?: now(),
                            'payment_confirmed_at' => $record->payment_confirmed_at ?: now(),
                        ])->save();

                        Notification::make()
                            ->title('Fonds confirmés & Parts attribuées ! ✅')
                            ->body("La souscription {$record->reference_transaction} est validée. Les {$record->nb_parts} parts sont désormais disponibles sur le compte du client.")
                            ->success()
                            ->send();
                    }),

                // 3. Visa Gérant & Émission des Parts (Masqué suite à attribution directe par la comptabilité)
                Tables\Actions\Action::make('reviewManager')
                    ->label('Visa Gérant / Émettre')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(false),

                // 4. Visa Global Historique (Raccourci Super Admin / Gérant pour reprises massives sans goulot d'étranglement)
                Tables\Actions\Action::make('globalHistoricalValidation')
                    ->label('Visa Global Historique')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Visa Global de Régularisation Historique')
                    ->modalDescription(fn (Subscription $record) => "Certifier en un clic les 3 visas (Conformité + Comptabilité + Gérant) pour cette opération historique ({$record->reference_transaction}). Aucun mail ne sera envoyé au client.")
                    ->visible(fn (Subscription $record) => $record->is_historical && $record->statut !== 'Succès' && (auth()->user()?->hasRole('super_admin') || (auth()->user()?->can('confirm_bank_payment') && auth()->user()?->can('review_subscription_compliance'))))
                    ->action(function (Subscription $record) {
                        $record->forceFill([
                            'compliance_reviewed_at' => now(),
                            'compliance_reviewed_by_user_id' => auth()->id(),
                            'accounting_reviewed_at' => now(),
                            'accounting_reviewed_by_user_id' => auth()->id(),
                            'manager_reviewed_at' => now(),
                            'manager_reviewed_by_user_id' => auth()->id(),
                            'statut' => 'Succès',
                            'valuation_status' => 'valued',
                            'funds_received_at' => $record->funds_received_at ?: now(),
                            'payment_confirmed_at' => $record->payment_confirmed_at ?: now(),
                        ])->save();

                        Notification::make()
                            ->title('Opération historique certifiée et validée ! ✅')
                            ->body("Les visas Conformité, Comptabilité et Gérant ont été apposés. Parts créditées au client.")
                            ->success()
                            ->send();
                    }),

                BankSubscriptionActions::reconcileAction(),
                BankSubscriptionActions::valueAction(),
                Tables\Actions\Action::make('remindTransferProof')
                    ->label('Relancer preuve virement')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->visible(fn (Subscription $record) => in_array($record->moyen_paiement, ['bank_transfer', 'virement'], true) && $record->statut === 'En attente' && !$record->funds_received_at && !$record->paymentProofs()->exists() && (auth()->user()?->hasRole('super_admin') || auth()->user()?->can('confirm_bank_payment') || auth()->user()?->can('review_subscription_compliance') || auth()->user()?->can('update_subscription')))
                    ->requiresConfirmation()
                    ->modalHeading('Relancer le client pour la preuve de virement')
                    ->modalDescription(fn (Subscription $record) => 'Envoyer immédiatement un email de rappel avec les coordonnées bancaires et une notification In-App à ' . ($record->user ? ($record->user->first_name . ' ' . $record->user->last_name . ' (' . $record->user->email . ')') : 'ce client') . ' pour la souscription ' . $record->reference_transaction . ' ?')
                    ->modalSubmitActionLabel('Envoyer le rappel')
                    ->action(function (Subscription $record) {
                        $sent = \App\Services\Payments\BankTransferProofReminderService::sendReminder($record, auth()->id());
                        if ($sent) {
                            Notification::make()
                                ->title('Rappel envoyé avec succès')
                                ->body('L\'email avec les coordonnées bancaires et la notification in-app ont été transmis au client.')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Échec de l\'envoi')
                                ->body('Impossible d\'envoyer le rappel. Vérifiez l\'adresse e-mail du client.')
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('printBulletin')
                    ->label('Bulletin de souscription')
                    ->icon('heroicon-o-printer')
                    ->color('secondary')
                    ->visible(fn (): bool => auth()->user()?->hasRole('super_admin') || auth()->user()?->can('view_subscription') || auth()->user()?->can('view_any_subscription'))
                    ->url(fn (Subscription $record) => route('subscriptions.bulletin.show', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('downloadPaymentProof')
                    ->label('Preuve de paiement')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->visible(fn (Subscription $record) => in_array($record->moyen_paiement, ['bank_transfer', 'virement'], true) && $record->paymentProofs()->exists() && (auth()->user()?->hasRole('super_admin') || auth()->user()?->can('view_payment_proof') || auth()->user()?->can('review_payment_proof')))
                    ->action(function (Subscription $record) {
                        $proof = $record->paymentProofs()->latest()->first();
                        if (!$proof || !$proof->path || !\Illuminate\Support\Facades\Storage::disk('payment_private')->exists($proof->path)) {
                            Notification::make()
                                ->title('Justificatif non disponible')
                                ->body('Ce justificatif n\'existe pas sur le serveur ou n\'a pas été téléversé par le client.')
                                ->warning()
                                ->send();

                            return;
                        }

                        return redirect()->away(route('admin.payment-proofs.download', $proof));
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulkRemindTransferProof')
                        ->label('Relancer pour preuve de virement')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Relancer les clients sélectionnés pour leur preuve de virement')
                        ->modalDescription('Un email et une notification in-app seront envoyés aux clients dont les souscriptions par virement sont en attente de justificatif.')
                        ->modalSubmitActionLabel('Envoyer les rappels')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if (in_array($record->moyen_paiement, ['bank_transfer', 'virement'], true) && $record->statut === 'En attente' && !$record->funds_received_at && !$record->paymentProofs()->exists()) {
                                    if (\App\Services\Payments\BankTransferProofReminderService::sendReminder($record, auth()->id())) {
                                        $count++;
                                    }
                                }
                            }
                            Notification::make()
                                ->title("Rappels envoyés ({$count})")
                                ->body("{$count} client(s) ont été relancés par email et notification in-app.")
                                ->success()
                                ->send();
                        }),
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
            PaymentProofsRelationManager::class,
            PaymentEventsRelationManager::class,
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
