<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OnboardingSessionResource\Pages;
use App\Models\OnboardingSession;
use Filament\Forms;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class OnboardingSessionResource extends Resource
{
    protected static ?string $model = OnboardingSession::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Opérations & Souscriptions';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Onboarding Clients';

    protected static ?string $modelLabel = 'Session d\'Onboarding';

    protected static ?string $pluralModelLabel = 'Onboarding Clients';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') 
            || (auth()->user()?->can('view_any_onboarding::session') ?? false)
            || (auth()->user()?->can('view_any_onboarding_session') ?? false);
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->hasRole('super_admin') 
            || (auth()->user()?->can('view_onboarding::session') ?? false)
            || (auth()->user()?->can('view_onboarding_session') ?? false)
            || static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('super_admin') 
            || (auth()->user()?->can('create_onboarding::session') ?? false)
            || (auth()->user()?->can('create_onboarding_session') ?? false);
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasRole('super_admin') 
            || (auth()->user()?->can('update_onboarding::session') ?? false)
            || (auth()->user()?->can('update_onboarding_session') ?? false);
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasRole('super_admin') 
            || (auth()->user()?->can('delete_onboarding::session') ?? false)
            || (auth()->user()?->can('delete_onboarding_session') ?? false);
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') 
            || (auth()->user()?->can('delete_any_onboarding::session') ?? false)
            || (auth()->user()?->can('delete_any_onboarding_session') ?? false);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('user', function (Builder $query) {
                $query->onlyClients();
            });
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Paramètres du Dossier Onboarding')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Client associé')
                            ->relationship('user', 'email', fn (Builder $query, $record) => $query->where('role', 'client')->when(! $record, fn ($q) => $q->whereDoesntHave('onboardingSession')))
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->salutation} ({$record->email})")
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabled(fn ($record) => $record && $record->exists)
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if (! $state) return;
                                $user = \App\Models\User::find($state);
                                if (! $user) return;
                                $set('payload.civ', $user->civ ?? 'M.');
                                $set('payload.nom', $user->last_name);
                                $set('payload.prenom', $user->first_name);
                                $set('payload.email', $user->email);
                                $set('payload.tel', $user->phone);
                                $set('payload.adresse', $user->city);
                                $set('payload.pays_residence', $user->country ?: 'Cameroun');
                                $set('payload.categorie_client', $user->categorie_client ?: 'Salarié secteur privé');
                                $set('payload.nature_client', $user->nature_client ?: 'personne_physique');
                                $set('payload.piece', $user->type_piece ?: 'CNI');
                                $set('payload.num_piece', $user->num_piece);
                                $set('payload.expiration_piece', $user->expiration_piece ? $user->expiration_piece->format('Y-m-d') : null);
                                $set('payload.dob', $user->dob ? $user->dob->format('Y-m-d') : null);
                                $set('payload.employeur', $user->employer);
                            }),

                        Forms\Components\Select::make('status')
                            ->label('Statut Onboarding')
                            ->options(function ($operation, $record) {
                                $isSuperAdmin = auth()->user()?->hasRole('super_admin');
                                $isCompliance = $isSuperAdmin || (auth()->user()?->adminDepartment?->name === 'Conformité / KYC');
                                $creatorId = $record?->events()->where('event_type', 'created')->value('actor_user_id')
                                    ?? ($record?->payload['created_by_user_id'] ?? null)
                                    ?? ($record?->payload['agent_kam_id'] ?? null);
                                $isCreator = $creatorId && ($creatorId == auth()->id());

                                if ($operation === 'create') {
                                    $opts = [
                                        'completed' => 'Complet (Soumis pour contrôle conformité)',
                                        'in_progress' => 'Brouillon / En cours',
                                    ];
                                    if ($isSuperAdmin) {
                                        $opts['validated'] = 'Validé par la Conformité (Régularisation Super Admin)';
                                    }
                                    return $opts;
                                }

                                $opts = [
                                    'completed' => 'Complet (Soumis pour contrôle)',
                                    'in_progress' => 'Brouillon / En cours',
                                    'rejected' => 'Rejeté',
                                ];
                                if ($isCompliance && ($isSuperAdmin || ! $isCreator)) {
                                    $opts['validated'] = 'Validé par la Conformité (Activé)';
                                }
                                return $opts;
                            })
                            ->default('completed')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if ($state === 'validated') {
                                    if (! $get('validated_at')) {
                                        $set('validated_at', now()->format('Y-m-d H:i:s'));
                                    }
                                    if (! $get('submitted_at')) {
                                        $set('submitted_at', now()->format('Y-m-d H:i:s'));
                                    }
                                } elseif ($state === 'completed') {
                                    if (! $get('submitted_at')) {
                                        $set('submitted_at', now()->format('Y-m-d H:i:s'));
                                    }
                                    $set('validated_at', null);
                                } elseif ($state === 'in_progress') {
                                    $set('validated_at', null);
                                }
                            }),

                        Forms\Components\Select::make('risk_level')
                            ->label('Niveau de Risque Global')
                            ->options([
                                'LOW' => 'LOW (Faible)',
                                'HIGH' => 'HIGH (Élevé - Alerte LCB-FT / PPE)',
                            ])
                            ->default('LOW')
                            ->required(),

                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Motif du rejet')
                            ->placeholder('Précisez le motif du rejet...')
                            ->visible(fn (Forms\Get $get) => $get('status') === 'rejected')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('send_notification_email')
                            ->label('Envoyer un e-mail au client')
                            ->helperText('Désactivé par défaut. Laissez désactivé pour les clients existants sans envoyer de mail.')
                            ->default(false)
                            ->visible(fn ($record) => ! $record || ! $record->exists),

                        Forms\Components\Toggle::make('create_in_app_notification')
                            ->label('Créer une notification in-app dans l\'application client')
                            ->default(false)
                            ->visible(fn ($record) => ! $record || ! $record->exists),
                    ])
                    ->columns(3),

                Tabs::make('Details')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Informations KYC & Identité')
                            ->icon('heroicon-o-identification')
                            ->schema([
                                Section::make('Nature & Profil du Client')
                                    ->description('Définissez d\'abord la nature du client afin d\'adapter automatiquement les champs du formulaire.')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Forms\Components\Select::make('payload.nature_client')
                                                ->label('Nature du client')
                                                ->options([
                                                    'personne_physique' => 'Personne Physique',
                                                    'personne_morale' => 'Personne Morale (Entreprise / Institution)',
                                                ])
                                                ->default('personne_physique')
                                                ->required()
                                                ->live()
                                                ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                    if ($state === 'personne_morale') {
                                                        $set('payload.civ', null);
                                                        $set('payload.situation_mat', null);
                                                        $set('payload.profession', null);
                                                        $set('payload.employeur', null);
                                                        $set('payload.categorie_client', 'Société commerciale / SARL / SA');
                                                    } else {
                                                        $set('payload.civ', 'M.');
                                                        $set('payload.rccm', null);
                                                        $set('payload.categorie_client', 'Salarié secteur privé');
                                                    }
                                                }),

                                            Forms\Components\Select::make('payload.categorie_client')
                                                ->label('Type / Catégorie du client')
                                                ->options(fn (Forms\Get $get) => ($get('payload.nature_client') ?? 'personne_physique') === 'personne_morale'
                                                    ? \App\Services\ClientCategoryService::getCategoriesForType(\App\Services\ClientCategoryService::TYPE_MORALE)
                                                    : \App\Services\ClientCategoryService::getCategoriesForType(\App\Services\ClientCategoryService::TYPE_PHYSIQUE))
                                                ->searchable()
                                                ->default('Salarié secteur privé')
                                                ->required(),
                                        ]),
                                    ]),

                                Section::make(fn (Forms\Get $get) => ($get('payload.nature_client') ?? 'personne_physique') === 'personne_morale' ? 'Informations de l\'Entreprise / Personne Morale' : 'Identité de la Personne Physique')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            Forms\Components\Select::make('payload.civ')
                                                ->label('Civilité')
                                                ->options([
                                                    'M.' => 'M. (Monsieur)',
                                                    'Mme' => 'Mme (Madame)',
                                                ])
                                                ->default('M.')
                                                ->required(fn (Forms\Get $get) => ($get('payload.nature_client') ?? 'personne_physique') === 'personne_physique')
                                                ->visible(fn (Forms\Get $get) => ($get('payload.nature_client') ?? 'personne_physique') !== 'personne_morale'),

                                            Forms\Components\TextInput::make('payload.nom')
                                                ->label(fn (Forms\Get $get) => ($get('payload.nature_client') ?? 'personne_physique') === 'personne_morale' ? 'Raison Sociale / Dénomination' : 'Nom de famille')
                                                ->placeholder(fn (Forms\Get $get) => ($get('payload.nature_client') ?? 'personne_physique') === 'personne_morale' ? 'Ex: KORI CAPITAL SAS' : 'Ex: KAMGA')
                                                ->required(),

                                            Forms\Components\TextInput::make('payload.prenom')
                                                ->label(fn (Forms\Get $get) => ($get('payload.nature_client') ?? 'personne_physique') === 'personne_morale' ? 'Représentant légal / Sigle' : 'Prénom')
                                                ->placeholder(fn (Forms\Get $get) => ($get('payload.nature_client') ?? 'personne_physique') === 'personne_morale' ? 'Ex: Jean DUPONT (DG)' : 'Ex: Jean')
                                                ->required(fn (Forms\Get $get) => ($get('payload.nature_client') ?? 'personne_physique') === 'personne_physique'),

                                            Forms\Components\TextInput::make('payload.rccm')
                                                ->label('N° RCCM (Entreprise)')
                                                ->placeholder('Ex: RC/DLA/2023/B/1234')
                                                ->required(fn (Forms\Get $get) => ($get('payload.nature_client') ?? '') === 'personne_morale')
                                                ->visible(fn (Forms\Get $get) => ($get('payload.nature_client') ?? '') === 'personne_morale'),

                                            Forms\Components\Select::make('payload.situation_mat')
                                                ->label('Situation matrimoniale')
                                                ->options([
                                                    'Célibataire' => 'Célibataire',
                                                    'Marié(e)' => 'Marié(e)',
                                                    'Divorcé(e)' => 'Divorcé(e)',
                                                    'Veuf/Veuve' => 'Veuf/Veuve',
                                                    'Autre' => 'Autre',
                                                ])
                                                ->default('Célibataire')
                                                ->visible(fn (Forms\Get $get) => ($get('payload.nature_client') ?? 'personne_physique') !== 'personne_morale'),

                                            Forms\Components\TextInput::make('payload.nat')
                                                ->label(fn (Forms\Get $get) => ($get('payload.nature_client') ?? 'personne_physique') === 'personne_morale' ? 'Pays du Siège / Nationalité' : 'Nationalité')
                                                ->default('Camerounaise'),

                                            Forms\Components\DatePicker::make('payload.dob')
                                                ->label(fn (Forms\Get $get) => ($get('payload.nature_client') ?? 'personne_physique') === 'personne_morale' ? 'Date de création / immatriculation' : 'Date de naissance')
                                                ->native(false)
                                                ->displayFormat('d/m/Y'),

                                            Forms\Components\TextInput::make('payload.lieu_naiss')
                                                ->label(fn (Forms\Get $get) => ($get('payload.nature_client') ?? 'personne_physique') === 'personne_morale' ? 'Lieu d\'immatriculation / Ville du Siège' : 'Lieu de naissance'),

                                            Forms\Components\TextInput::make('payload.profession')
                                                ->label('Profession / Activité')
                                                ->visible(fn (Forms\Get $get) => ($get('payload.nature_client') ?? 'personne_physique') !== 'personne_morale'),

                                            Forms\Components\TextInput::make('payload.employeur')
                                                ->label('Employeur / Entreprise')
                                                ->visible(fn (Forms\Get $get) => ($get('payload.nature_client') ?? 'personne_physique') !== 'personne_morale'),

                                            Forms\Components\TextInput::make('payload.tel')
                                                ->label('Numéro de téléphone')
                                                ->tel(),

                                            Forms\Components\TextInput::make('payload.email')
                                                ->label('E-mail déclaré')
                                                ->email(),

                                            Forms\Components\TextInput::make('payload.adresse')
                                                ->label(fn (Forms\Get $get) => ($get('payload.nature_client') ?? 'personne_physique') === 'personne_morale' ? 'Adresse du siège social / Ville' : 'Adresse de résidence / Ville'),

                                            Forms\Components\TextInput::make('payload.pays_residence')
                                                ->label(fn (Forms\Get $get) => ($get('payload.nature_client') ?? 'personne_physique') === 'personne_morale' ? 'Pays du siège social' : 'Pays de résidence')
                                                ->default('Cameroun'),

                                            Forms\Components\Hidden::make('payload.agent_kam')
                                                ->default(fn () => auth()->user()?->name ?: (trim((auth()->user()?->first_name ?? '') . ' ' . (auth()->user()?->last_name ?? '')) ?: (auth()->user()?->email ?: 'Administrateur Backoffice'))),
                                        ]),
                                    ]),
                            ]),

                        Tab::make('Pièce d\'Identité & Justificatifs KYC')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make('Pièce d\'identification officielle')
                                    ->columns(3)
                                    ->schema([
                                        Forms\Components\Select::make('payload.piece')
                                            ->label('Type de pièce')
                                            ->options([
                                                'CNI' => 'CNI (Carte Nationale d\'Identité)',
                                                'Passeport' => 'Passeport',
                                                'Carte Résident' => 'Carte de Séjour / Résident',
                                                'Permis de Conduire' => 'Permis de Conduire',
                                            ])
                                            ->default('CNI'),

                                        Forms\Components\TextInput::make('payload.num_piece')
                                            ->label('N° de la pièce'),

                                        Forms\Components\DatePicker::make('payload.expiration_piece')
                                            ->label('Date d\'expiration')
                                            ->native(false)
                                            ->displayFormat('d/m/Y'),
                                    ]),

                                Section::make('Fichiers & Pièces justificatives téléversées')
                                    ->description('Documents stockés sur le disque sécurisé privé KYC.')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\FileUpload::make('doc_piece_identite')
                                            ->label('1. Pièce d\'identité (Recto / Document principal)')
                                            ->disk('kyc_private')
                                            ->visibility('private')
                                            ->directory('secure_onboardings/documents')
                                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                            ->maxSize(10240)
                                            ->helperText(fn ($record) => $record && $record->doc_piece_identite
                                                ? new HtmlString('<a href="'.route('admin.document.download', ['session' => $record->id, 'type' => 'piece_identite']).'" target="_blank" style="color: #009a4d; font-weight: bold; text-decoration: underline;">⬇️ Télécharger le document actuel</a>')
                                                : 'Aucun document téléversé.'),

                                        Forms\Components\FileUpload::make('payload.doc_piece_verso')
                                            ->label('2. Pièce d\'identité (Verso - optionnel)')
                                            ->disk('kyc_private')
                                            ->visibility('private')
                                            ->directory('secure_onboardings/documents')
                                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                            ->maxSize(10240)
                                            ->helperText(fn ($record) => $record && (! empty($record->payload['doc_piece_verso']) || ! empty($record->payload['piece_verso']))
                                                ? new HtmlString('<a href="'.route('admin.document.download', ['session' => $record->id, 'type' => 'piece_verso']).'" target="_blank" style="color: #009a4d; font-weight: bold; text-decoration: underline;">⬇️ Télécharger le verso actuel</a>')
                                                : 'Aucun verso téléversé.'),

                                        Forms\Components\FileUpload::make('doc_photo')
                                            ->label('3. Photo d\'identité récente')
                                            ->disk('kyc_private')
                                            ->visibility('private')
                                            ->directory('secure_onboardings/documents')
                                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                            ->maxSize(10240)
                                            ->helperText(fn ($record) => $record && $record->doc_photo
                                                ? new HtmlString('<a href="'.route('admin.document.download', ['session' => $record->id, 'type' => 'photo']).'" target="_blank" style="color: #009a4d; font-weight: bold; text-decoration: underline;">⬇️ Télécharger la photo actuelle</a>')
                                                : 'Aucune photo téléversée.'),

                                        Forms\Components\FileUpload::make('doc_justificatif_domicile')
                                            ->label('4. Justificatif de domicile (< 3 mois)')
                                            ->disk('kyc_private')
                                            ->visibility('private')
                                            ->directory('secure_onboardings/documents')
                                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                            ->maxSize(10240)
                                            ->helperText(fn ($record) => $record && $record->doc_justificatif_domicile
                                                ? new HtmlString('<a href="'.route('admin.document.download', ['session' => $record->id, 'type' => 'justificatif_domicile']).'" target="_blank" style="color: #009a4d; font-weight: bold; text-decoration: underline;">⬇️ Télécharger le justificatif actuel</a>')
                                                : 'Aucun justificatif téléversé.'),

                                        Forms\Components\FileUpload::make('doc_origine_fonds')
                                            ->label('5. Justificatif d\'origine des fonds')
                                            ->disk('kyc_private')
                                            ->visibility('private')
                                            ->directory('secure_onboardings/documents')
                                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                            ->maxSize(10240)
                                            ->helperText(fn ($record) => $record && $record->doc_origine_fonds
                                                ? new HtmlString('<a href="'.route('admin.document.download', ['session' => $record->id, 'type' => 'origine_fonds']).'" target="_blank" style="color: #009a4d; font-weight: bold; text-decoration: underline;">⬇️ Télécharger le justificatif actuel</a>')
                                                : 'Aucun document téléversé.'),
                                    ]),

                                Section::make('Vérification Biométrique & OCR (Optionnels)')
                                    ->collapsible()
                                    ->collapsed(fn ($record) => $record && empty($record->payload['face_match_score']))
                                    ->columns(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('payload.face_match_score')
                                            ->label('Score de correspondance faciale (%)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->placeholder('ex: 85'),

                                        Forms\Components\Toggle::make('payload.face_verified')
                                            ->label('Vérification faciale validée')
                                            ->default(false),

                                        Forms\Components\TextInput::make('payload.ocr_score')
                                            ->label('Score de concordance OCR (%)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->placeholder('ex: 90'),
                                    ]),
                            ]),

                        Tab::make('Profil Investisseur')
                            ->icon('heroicon-o-chart-bar')
                            ->schema([
                                Section::make('Synthèse du profil de risque')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('payload.risk_score')
                                            ->label('Score de Risque (Points)')
                                            ->numeric()
                                            ->helperText('Calculé automatiquement ou ajustable.'),

                                        Forms\Components\Select::make('payload.risk_profile')
                                            ->label('Profil d\'Investisseur déterminé')
                                            ->options([
                                                'Prudent' => 'Prudent (Conservateur)',
                                                'Modéré' => 'Modéré (Équilibré)',
                                                'Dynamique' => 'Dynamique (Performances)',
                                            ])
                                            ->helperText('Barème : <= 10 pts Prudent, 11-19 pts Modéré, >= 20 pts Dynamique.'),
                                    ]),

                                Section::make('Questionnaire réglementaire COSUMAF (9 Questions)')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\Select::make('payload.tranche_revenus')
                                            ->label('1. Tranche de revenus mensuels')
                                            ->options([
                                                'moins_500k' => 'Moins de 500 000 FCFA',
                                                '500k_1_5m' => '500 000 FCFA - 1 500 000 FCFA',
                                                'plus_1_5m' => 'Plus de 1 500 000 FCFA',
                                            ])
                                            ->default('500k_1_5m'),

                                        Forms\Components\Select::make('payload.epargne_possible')
                                            ->label('2. Capacité d\'épargne régulière')
                                            ->options([
                                                'Oui' => 'Oui',
                                                'Non' => 'Non',
                                            ])
                                            ->default('Oui'),

                                        Forms\Components\Select::make('payload.niveau_risque')
                                            ->label('3. Niveau de risque accepté pour vos placements')
                                            ->options([
                                                'faible' => 'Faible risque (Privilégie la sécurité absolue du capital)',
                                                'moyen' => 'Risque modéré (Accepte des fluctuations légères)',
                                                'max' => 'Risque élevé (Recherche performance max, pertes acceptées)',
                                            ])
                                            ->default('moyen'),

                                        Forms\Components\Select::make('payload.conscience_risque')
                                            ->label('4. Conscience des risques inhérents (perte en capital)')
                                            ->options([
                                                'Oui' => 'Oui (Pleine conscience)',
                                                'Non' => 'Non',
                                            ])
                                            ->default('Oui'),

                                        Forms\Components\Select::make('payload.objectif_invest')
                                            ->label('5. Objectif principal d\'investissement')
                                            ->options([
                                                'securite' => 'Préservation du capital (Recherche de sécurité)',
                                                'equilibre' => 'Recherche d\'équilibre entre valorisation et sécurité',
                                                'croissance' => 'Croissance et valorisation à long terme',
                                            ])
                                            ->default('equilibre'),

                                        Forms\Components\Select::make('payload.horizon_terme')
                                            ->label('6. Horizon de placement visé')
                                            ->options([
                                                'court_terme' => 'Court terme (Moins de 1 an)',
                                                'moyen_terme' => 'Moyen terme (1 à 3 ans)',
                                                'long_terme' => 'Long terme (Plus de 3 ans)',
                                            ])
                                            ->default('moyen_terme'),

                                        Forms\Components\Select::make('payload.niveau_perf')
                                            ->label('7. Objectif de performance cible')
                                            ->options([
                                                '1' => 'Rendement faible mais sécurité maximale',
                                                'moderee' => 'Rendement modéré avec fluctuation modérée',
                                                'elevee' => 'Rendement élevé avec fluctuations acceptées',
                                            ])
                                            ->default('moderee'),

                                        Forms\Components\Select::make('payload.connaissance_marche')
                                            ->label('8. Connaissance des marchés financiers & OPCVM')
                                            ->options([
                                                'nulle' => 'Aucune connaissance',
                                                'moyenne' => 'Connaissance générale / intermédiaire',
                                                'excellente' => 'Bonne / Excellente connaissance',
                                            ])
                                            ->default('moyenne'),

                                        Forms\Components\Select::make('payload.invest_anterieurs')
                                            ->label('9. Investissements similaires antérieurs réalisés')
                                            ->options([
                                                'Oui' => 'Oui',
                                                'Non' => 'Non',
                                            ])
                                            ->default('Non'),
                                    ]),
                            ]),

                        Tab::make('Questionnaire LAB-FT & Conformité')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Section::make('Activité professionnelle et origine des fonds')
                                    ->columns(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('payload.secteur')
                                            ->label('Secteur d\'activité professionnelle')
                                            ->placeholder('ex: Services, Commerce, Santé...')
                                            ->datalist([
                                                'Commerce & Distribution',
                                                'Banque, Finance & Assurance',
                                                'Technologies, IT & Digital',
                                                'Santé, Médical & Pharmacie',
                                                'BTP, Immobilier & Construction',
                                                'Éducation, Recherche & Formation',
                                                'Fonction Publique & Administration',
                                                'Transport, Logistique & Fret',
                                                'Agriculture & Agro-industrie',
                                                'Industrie, Énergie & Mines',
                                                'Services, Hôtellerie & Restauration',
                                            ]),

                                        Forms\Components\Select::make('payload.revenus_annuels')
                                            ->label('Revenus annuels estimés')
                                            ->options([
                                                'moins_5m' => 'Moins de 5 000 000 FCFA',
                                                '5m_15m' => 'Entre 5 000 000 et 15 000 000 FCFA',
                                                'plus_15m' => 'Plus de 15 000 000 FCFA',
                                            ])
                                            ->default('5m_15m'),

                                        Forms\Components\TextInput::make('payload.origine_fonds')
                                            ->label('Origine des fonds investis')
                                            ->placeholder('ex: Épargne personnelle, Revenus professionnels...'),
                                    ]),

                                Section::make('Sources de revenus déclarées')
                                    ->columns(3)
                                    ->schema([
                                        Forms\Components\Toggle::make('payload.src_salaire')
                                            ->label('Salaire')
                                            ->default(true),

                                        Forms\Components\Toggle::make('payload.src_pro_liberal')
                                            ->label('Profession Libérale')
                                            ->default(false),

                                        Forms\Components\Toggle::make('payload.src_foncier')
                                            ->label('Revenus Fonciers')
                                            ->default(false),

                                        Forms\Components\Toggle::make('payload.src_dividendes')
                                            ->label('Dividendes')
                                            ->default(false),

                                        Forms\Components\Toggle::make('payload.src_heritage')
                                            ->label('Héritage')
                                            ->default(false),

                                        Forms\Components\Toggle::make('payload.src_autre_check')
                                            ->label('Autre source')
                                            ->default(false)
                                            ->live(),

                                        Forms\Components\TextInput::make('payload.src_autre')
                                            ->label('Précision autre source')
                                            ->visible(fn (Forms\Get $get) => (bool) $get('payload.src_autre_check'))
                                            ->columnSpan(2),
                                    ]),

                                Section::make('Informations bancaires déclarées')
                                    ->columns(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('payload.banque')
                                            ->label('Nom de la banque'),

                                        Forms\Components\TextInput::make('payload.num_compte')
                                            ->label('Numéro de compte / RIB'),

                                        Forms\Components\TextInput::make('payload.pays_compte')
                                            ->label('Pays du compte bancaire')
                                            ->default('Cameroun'),
                                    ]),

                                Section::make('Déclarations et alertes de conformité (LCB-FT / GAFI / PPE)')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\Select::make('payload.pays_risque')
                                            ->label('1. Relations d\'affaires avec un pays à haut risque (liste GAFI / sanctions)')
                                            ->options([
                                                'Non' => 'Non',
                                                'Oui' => '⚠️ Oui (Alerte Risque Pays)',
                                            ])
                                            ->default('Non'),

                                        Forms\Components\Select::make('payload.secteur_sensible')
                                            ->label('2. Exercice dans un secteur sensible (armement, minier, jeux...)')
                                            ->options([
                                                'Non' => 'Non',
                                                'Oui' => '⚠️ Oui (Alerte Secteur Sensible)',
                                            ])
                                            ->default('Non'),

                                        Forms\Components\Select::make('payload.ppe')
                                            ->label('3. Statut Personne Politiquement Exposée (PPE)')
                                            ->options([
                                                'Non' => 'Non',
                                                'Oui' => '⚠️ Oui (Alerte Statut PPE)',
                                            ])
                                            ->default('Non')
                                            ->live(),

                                        Forms\Components\Textarea::make('payload.ppe_detail')
                                            ->label('Détail des fonctions publiques exercées (si PPE)')
                                            ->visible(fn (Forms\Get $get) => ($get('payload.ppe') ?? 'Non') === 'Oui')
                                            ->rows(2)
                                            ->columnSpanFull(),

                                        Forms\Components\Select::make('payload.condamnation')
                                            ->label('4. Condamnation pénale antérieure pour délits financiers')
                                            ->options([
                                                'Non' => 'Non',
                                                'Oui' => '⚠️ Oui (Alerte Condamnation)',
                                            ])
                                            ->default('Non'),
                                    ]),
                            ]),

                        Tab::make('Signatures & Validation')
                            ->icon('heroicon-o-pencil-square')
                            ->schema([
                                Grid::make(2)->schema([
                                    Section::make('Signature manuscrite / électronique du client')
                                        ->schema([
                                            Forms\Components\FileUpload::make('signature_path')
                                                ->label('Image de la signature')
                                                ->disk('kyc_private')
                                                ->directory('signatures')
                                                ->acceptedFileTypes(['image/jpeg', 'image/png'])
                                                ->maxSize(2048)
                                                ->helperText(fn ($record) => $record && $record->signature_path
                                                    ? new HtmlString('<a href="'.route('admin.document.download', ['session' => $record->id, 'type' => 'signature']).'" target="_blank" style="color: #009a4d; font-weight: bold; text-decoration: underline;">⬇️ Télécharger la signature actuelle</a>')
                                                    : 'Aucune signature téléversée.'),

                                            Placeholder::make('signature_preview')
                                                ->label('Aperçu de la signature')
                                                ->content(function ($record) {
                                                    $disk = Storage::disk('kyc_private');
                                                    if ($record && $record->signature_path && $disk->exists($record->signature_path)) {
                                                        $imgData = $disk->get($record->signature_path);
                                                        $mime = (str_ends_with($record->signature_path, '.jpg') || str_ends_with($record->signature_path, '.jpeg')) ? 'image/jpeg' : 'image/png';
                                                        $base64 = 'data:'.$mime.';base64,'.base64_encode($imgData);

                                                        return new HtmlString("<div style='background: white; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; display: inline-block;'><img src='{$base64}' style='max-height: 100px; object-fit: contain;' /></div>");
                                                    }

                                                    return new HtmlString('<span style="color: #94a3b8; font-style: italic;">Aucune signature enregistrée.</span>');
                                                }),
                                        ]),

                                    Section::make('Consentements & Horodatages')
                                        ->schema([
                                            Forms\Components\Toggle::make('payload.ack_lecture')
                                                ->label('Attestation de prise de connaissance des règlements & statuts')
                                                ->default(true),

                                            Forms\Components\Toggle::make('payload.ack_donnees')
                                                ->label('Certification de sincérité et exactitude des déclarations')
                                                ->default(true),

                                            Forms\Components\DateTimePicker::make('submitted_at')
                                                ->label('Date & Heure de soumission')
                                                ->default(fn () => now())
                                                ->native(false)
                                                ->displayFormat('d/m/Y H:i')
                                                ->helperText('Renseigné automatiquement lors de la soumission du dossier.'),

                                            Forms\Components\DateTimePicker::make('validated_at')
                                                ->label('Date & Heure de validation (Conformité)')
                                                ->default(fn (Forms\Get $get) => ($get('status') ?? 'validated') === 'validated' ? now() : null)
                                                ->native(false)
                                                ->displayFormat('d/m/Y H:i')
                                                ->helperText('Validé automatiquement par la conformité à la date et heure courantes.'),
                                        ]),
                                ]),
                            ]),

                        Tab::make('Documents Certifiés & Téléchargements')
                            ->icon('heroicon-o-folder-arrow-down')
                            ->visible(fn ($record) => $record && $record->exists)
                            ->schema([
                                Section::make('Téléchargement des documents officiels')
                                    ->description('Documents PDF certifiés conformes COSUMAF et archive ZIP complète.')
                                    ->schema([
                                        Actions::make([
                                            FormAction::make('download_zip')
                                                ->label('Télécharger le dossier complet (ZIP)')
                                                ->icon('heroicon-o-folder-arrow-down')
                                                ->color('primary')
                                                ->url(fn (OnboardingSession $record) => route('admin.zip.download', ['session' => $record->id]))
                                                ->openUrlInNewTab(),
                                            FormAction::make('download_kyc')
                                                ->label('Fiche KYC (PDF)')
                                                ->icon('heroicon-o-document-arrow-down')
                                                ->color('success')
                                                ->url(fn (OnboardingSession $record) => route('admin.pdf.download', ['session' => $record->id, 'type' => 'kyc']))
                                                ->openUrlInNewTab(),
                                            FormAction::make('download_risk')
                                                ->label('Profil Investisseur (PDF)')
                                                ->icon('heroicon-o-document-arrow-down')
                                                ->color('info')
                                                ->url(fn (OnboardingSession $record) => route('admin.pdf.download', ['session' => $record->id, 'type' => 'risk']))
                                                ->openUrlInNewTab(),
                                            FormAction::make('download_labft')
                                                ->label('Questionnaire LAB-FT (PDF)')
                                                ->icon('heroicon-o-document-arrow-down')
                                                ->color('warning')
                                                ->url(fn (OnboardingSession $record) => route('admin.pdf.download', ['session' => $record->id, 'type' => 'labft']))
                                                ->openUrlInNewTab(),
                                        ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.salutation')
                    ->label('Client')
                    ->searchable(['first_name', 'last_name', 'civ'])
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('risk_level')
                    ->label('Niveau de Risque')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'LOW' => 'success',
                        'HIGH' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('payload.risk_profile')
                    ->label('Profil Investisseur')
                    ->sortable(),
                TextColumn::make('payload.face_match_score')
                    ->label('Score Faciale')
                    ->formatStateUsing(fn ($state) => $state !== null && $state !== '' ? $state.'%' : '-')
                    ->badge()
                    ->color(fn ($state): string => (int) $state >= 60 ? 'success' : ((int) $state >= 40 ? 'warning' : 'gray'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'validated' => 'success',
                        'completed' => 'info',
                        'rejected' => 'danger',
                        'in_progress' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'validated' => 'Validé',
                        'completed' => 'Soumis / En attente',
                        'rejected' => 'Rejeté',
                        'in_progress' => 'En cours',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Dernière activité')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                \App\Filament\Filters\DashboardFilter::make(static::class),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'in_progress' => 'En cours',
                        'completed' => 'Soumis / En attente',
                        'validated' => 'Validé',
                        'rejected' => 'Rejeté',
                    ]),
                Tables\Filters\SelectFilter::make('risk_level')
                    ->label('Niveau de Risque')
                    ->options([
                        'LOW' => 'LOW (Faible)',
                        'HIGH' => 'HIGH (Élevé)',
                    ]),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make()->label('Voir les détails'),
                    Tables\Actions\EditAction::make()->label('Modifier / Compléter'),
                    TableAction::make('update_categorie')
                        ->label('Modifier Catégorie Client')
                        ->icon('heroicon-o-identification')
                        ->color('warning')
                        ->visible(fn (): bool => auth()->user()?->hasRole('super_admin') || auth()->user()?->can('update_onboarding::session') || auth()->user()?->can('update_onboarding_session'))
                        ->modalHeading('Mettre à jour la catégorie du client')
                        ->modalDescription('Modifie le type et la catégorie du client pour ses futurs bulletins officiels.')
                        ->fillForm(function (OnboardingSession $record): array {
                            $currentCategory = $record->payload['categorie_client'] ?? ($record->user?->categorie_client ?? null);
                            $type = \App\Services\ClientCategoryService::getTypeForCategory($currentCategory);

                            $nature = strtolower($record->payload['nature_client'] ?? '');
                            if (str_contains($nature, 'morale') || ! empty($record->payload['rccm'])) {
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
                        ->action(function (OnboardingSession $record, array $data) {
                            $payload = $record->payload ?? [];
                            $payload['categorie_client'] = $data['categorie_client'];
                            $payload['nature_client'] = $data['type_client'] === \App\Services\ClientCategoryService::TYPE_MORALE ? 'personne_morale' : 'personne_physique';
                            $record->payload = $payload;

                            $submitted = $record->submitted_payload ?? [];
                            $submitted['categorie_client'] = $data['categorie_client'];
                            $submitted['nature_client'] = $payload['nature_client'];
                            $record->submitted_payload = $submitted;
                            $record->save();

                            if ($record->user) {
                                $record->user->update(['categorie_client' => $data['categorie_client']]);
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('Catégorie client mise à jour')
                                ->body("La catégorie a été enregistrée : {$data['categorie_client']}")
                                ->success()
                                ->send();
                        }),
                    TableAction::make('download_zip')
                        ->label('Télécharger Dossier Complet (ZIP)')
                        ->icon('heroicon-o-folder-arrow-down')
                        ->color('primary')
                        ->url(fn (OnboardingSession $record) => route('admin.zip.download', [
                            'session' => $record->id,
                        ]))
                        ->openUrlInNewTab(),
                    TableAction::make('download_kyc')
                        ->label('Télécharger Fiche KYC')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('success')
                        ->url(fn (OnboardingSession $record) => route('admin.pdf.download', [
                            'session' => $record->id,
                            'type' => 'kyc',
                        ]))
                        ->openUrlInNewTab(),
                    TableAction::make('download_risk')
                        ->label('Télécharger Profil Investisseur')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('info')
                        ->url(fn (OnboardingSession $record) => route('admin.pdf.download', [
                            'session' => $record->id,
                            'type' => 'risk',
                        ]))
                        ->openUrlInNewTab(),
                    TableAction::make('download_labft')
                        ->label('Télécharger Questionnaire LAB-FT')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('warning')
                        ->url(fn (OnboardingSession $record) => route('admin.pdf.download', [
                            'session' => $record->id,
                            'type' => 'labft',
                        ]))
                        ->openUrlInNewTab(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->headerActions([
                \App\Filament\Actions\TableExcelExportAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    \App\Filament\Actions\TableExcelExportAction::makeBulk(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()->label('Créer un Onboarding'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOnboardingSessions::route('/'),
            'create' => Pages\CreateOnboardingSession::route('/create'),
            'view' => Pages\ViewOnboardingSession::route('/{record}'),
            'edit' => Pages\EditOnboardingSession::route('/{record}/edit'),
        ];
    }
}
