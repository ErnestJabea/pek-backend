<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdminDepartmentResource\Pages;
use App\Models\AdminDepartment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AdminDepartmentResource extends Resource
{
    protected static ?string $model = AdminDepartment::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $modelLabel = 'Département';

    protected static ?string $pluralModelLabel = 'Départements';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDeleteAny(): bool
    {
        return static::canViewAny();
    }

    public static function getPermissionsByModule(): array
    {
        return [
            'clients' => [
                'label' => 'Clients & CRM',
                'icon' => 'heroicon-o-users',
                'description' => 'Gestion des comptes clients, vœux d\'anniversaire et échéances des pièces.',
                'permissions' => [
                    'view_any_client' => 'Consulter la liste des clients',
                    'view_client' => 'Consulter la fiche client et relevé',
                    'create_client' => 'Créer un client',
                    'update_client' => 'Modifier un client (profil, statut, email, catégorie)',
                    'replicate_client' => 'Dupliquer un client',
                    'delete_client' => 'Supprimer un client',
                    'delete_any_client' => 'Supprimer des clients en masse',
                    'restore_client' => 'Restaurer un client',
                    'restore_any_client' => 'Restaurer des clients en masse',
                    'force_delete_client' => 'Supprimer définitivement un client',
                    'force_delete_any_client' => 'Supprimer définitivement en masse',
                    'reorder_client' => 'Réordonner les clients',
                    'page_ClientBirthdays' => 'Consulter les anniversaires clients (Rubrique & Menu)',
                    'wish_client_birthday' => 'Envoyer les vœux d\'anniversaire au client',
                    'page_IdDocumentExpirations' => 'Consulter les échéances des pièces d\'identité (Rubrique & Menu)',
                    'remind_client_id' => 'Envoyer les rappels d\'expiration de pièce d\'identité',
                ],
            ],
            'onboarding' => [
                'label' => 'Dossiers KYC & Profils',
                'icon' => 'heroicon-o-document-check',
                'description' => 'Parcours d\'onboarding, vérification d\'identité et modifications de RIB/profil.',
                'permissions' => [
                    'view_any_onboarding::session' => 'Consulter la liste des dossiers KYC',
                    'view_onboarding::session' => 'Consulter le détail du dossier KYC',
                    'create_onboarding::session' => 'Créer un dossier d\'onboarding',
                    'update_onboarding::session' => 'Valider / rejeter un dossier KYC ou modifier la catégorie',
                    'replicate_onboarding::session' => 'Dupliquer un dossier',
                    'delete_onboarding::session' => 'Supprimer un dossier',
                    'delete_any_onboarding::session' => 'Supprimer des dossiers en masse',
                    'restore_onboarding::session' => 'Restaurer un dossier',
                    'restore_any_onboarding::session' => 'Restaurer des dossiers en masse',
                    'force_delete_onboarding::session' => 'Supprimer définitivement un dossier',
                    'force_delete_any_onboarding::session' => 'Supprimer définitivement en masse',
                    'reorder_onboarding::session' => 'Réordonner les dossiers',
                    'view_any_profile_change_request' => 'Consulter les demandes de modification Profil & RIB (Rubrique & Menu)',
                    'update_profile_change_request' => 'Approuver ou rejeter les demandes de modification Profil & RIB',
                    'delete_profile_change_request' => 'Supprimer une demande de modification',
                ],
            ],
            'subscriptions' => [
                'label' => 'Souscriptions & Paiements',
                'icon' => 'heroicon-o-banknotes',
                'description' => 'Souscriptions de fonds, validation de conformité et gestion financière.',
                'permissions' => [
                    'view_any_subscription' => 'Consulter la liste des souscriptions',
                    'view_subscription' => 'Consulter le détail d\'une souscription',
                    'create_subscription' => 'Créer une souscription',
                    'update_subscription' => 'Modifier une souscription (Montants, Frais en sus/déduits, Parts, Date de valeur, Statut)',
                    'replicate_subscription' => 'Dupliquer une souscription',
                    'delete_subscription' => 'Supprimer une souscription',
                    'delete_any_subscription' => 'Supprimer des souscriptions en masse',
                    'restore_subscription' => 'Restaurer une souscription',
                    'restore_any_subscription' => 'Restaurer des souscriptions en masse',
                    'force_delete_subscription' => 'Supprimer définitivement une souscription',
                    'force_delete_any_subscription' => 'Supprimer définitivement en masse',
                    'reorder_subscription' => 'Réordonner les souscriptions',
                    'confirm_bank_payment' => 'Apposer le Visa Comptabilité & Attribuer les parts (Finance)',
                    'review_subscription_compliance' => 'Apposer le Visa de Conformité (Compliance)',
                    'review_payment_proof' => 'Examiner et valider les justificatifs de paiement',
                    'view_payment_proof' => 'Consulter les justificatifs de paiement',
                ],
            ],
            'products' => [
                'label' => 'Fonds (Produits)',
                'icon' => 'heroicon-o-chart-bar',
                'description' => 'Fonds d\'investissement, OPCVM et portefeuilles financiers.',
                'permissions' => [
                    'view_any_product' => 'Consulter la liste des fonds',
                    'view_product' => 'Consulter le détail d\'un fonds',
                    'create_product' => 'Créer un fonds',
                    'update_product' => 'Modifier un fonds',
                    'view_any_product_vl' => 'Consulter l\'historique des VL',
                    'view_product_vl' => 'Consulter une VL',
                    'create_product_vl' => 'Ajouter une nouvelle VL (Valeur Liquidative)',
                    'update_product_vl' => 'Modifier une VL existante',
                    'delete_product_vl' => 'Supprimer une VL',
                    'replicate_product' => 'Dupliquer un fonds',
                    'delete_product' => 'Supprimer un fonds',
                    'delete_any_product' => 'Supprimer des fonds en masse',
                    'restore_product' => 'Restaurer un fonds',
                    'restore_any_product' => 'Restaurer des fonds en masse',
                    'force_delete_product' => 'Supprimer définitivement un fonds',
                    'force_delete_any_product' => 'Supprimer définitivement en masse',
                    'reorder_product' => 'Réordonner les fonds',
                ],
            ],
            'bank_details' => [
                'label' => 'Comptes Bancaires',
                'icon' => 'heroicon-o-building-library',
                'description' => 'Coordonnées bancaires officielles pour les virements et règlements.',
                'permissions' => [
                    'view_any_bank::detail' => 'Consulter la liste des comptes bancaires',
                    'view_bank::detail' => 'Consulter un compte bancaire',
                    'create_bank::detail' => 'Créer un compte bancaire',
                    'update_bank::detail' => 'Modifier un compte bancaire',
                    'replicate_bank::detail' => 'Dupliquer un compte bancaire',
                    'delete_bank::detail' => 'Supprimer un compte bancaire',
                    'delete_any_bank::detail' => 'Supprimer des comptes en masse',
                    'restore_bank::detail' => 'Restaurer un compte bancaire',
                    'restore_any_bank::detail' => 'Restaurer des comptes en masse',
                    'force_delete_bank::detail' => 'Supprimer définitivement un compte',
                    'force_delete_any_bank::detail' => 'Supprimer définitivement en masse',
                    'reorder_bank::detail' => 'Réordonner les comptes bancaires',
                ],
            ],
            'currencies' => [
                'label' => 'Devises',
                'icon' => 'heroicon-o-currency-dollar',
                'description' => 'Gestion des devises et parités de change.',
                'permissions' => [
                    'view_any_currency' => 'Consulter la liste des devises',
                    'view_currency' => 'Consulter une devise',
                    'create_currency' => 'Créer une devise',
                    'update_currency' => 'Modifier une devise',
                    'replicate_currency' => 'Dupliquer une devise',
                    'delete_currency' => 'Supprimer une devise',
                    'delete_any_currency' => 'Supprimer des devises en masse',
                    'restore_currency' => 'Restaurer une devise',
                    'restore_any_currency' => 'Restaurer des devises en masse',
                    'force_delete_currency' => 'Supprimer définitivement une devise',
                    'force_delete_any_currency' => 'Supprimer définitivement en masse',
                    'reorder_currency' => 'Réordonner les devises',
                    'widget_CurrencyStats' => 'Consulter les statistiques des devises (Widget)',
                ],
            ],
            'analytics' => [
                'label' => 'Tableaux de bord & Alertes',
                'icon' => 'heroicon-o-chart-bar-square',
                'description' => 'Accès aux graphiques analytiques, KPI et configuration des notifications SLA.',
                'permissions' => [
                    'page_KpiAnalytics' => 'Consulter les graphiques analytiques et KPI (Rubrique & Menu)',
                    'page_ManageNotificationSettings' => 'Gérer les réglages de notifications et délais SLA (Rubrique & Menu)',
                    'widget_SubscriptionsCollectChartWidget' => 'Visualiser le graphique des collectes de souscriptions',
                    'widget_PaymentMethodsDistributionWidget' => 'Visualiser le graphique de distribution des paiements',
                    'widget_ProductVlEvolutionWidget' => 'Visualiser le graphique d\'évolution des VL',
                    'widget_KycFunnelChartWidget' => 'Visualiser le graphique de l\'entonnoir KYC',
                    'widget_UserRegistrationsChartWidget' => 'Visualiser le graphique des inscriptions clients',
                    'widget_SubscriptionsStatusChartWidget' => 'Visualiser le graphique des statuts de souscription',
                ],
            ],
            'system_config' => [
                'label' => 'Configuration Système & PWA',
                'icon' => 'heroicon-o-cog-6-tooth',
                'description' => 'Gestion des contenus de l\'application mobile/PWA, pays, villes et moyens de paiement.',
                'permissions' => [
                    'view_any_app_content' => 'Consulter les contenus de l\'application PWA (Rubrique & Menu)',
                    'create_app_content' => 'Créer un contenu d\'application',
                    'update_app_content' => 'Modifier les contenus d\'application',
                    'delete_app_content' => 'Supprimer un contenu d\'application',
                    'view_any_payment_method' => 'Consulter les moyens de paiement (Rubrique & Menu)',
                    'create_payment_method' => 'Créer un moyen de paiement',
                    'update_payment_method' => 'Modifier les moyens de paiement',
                    'delete_payment_method' => 'Supprimer un moyen de paiement',
                    'view_any_country' => 'Consulter la liste des pays (Rubrique & Menu)',
                    'create_country' => 'Créer un pays',
                    'update_country' => 'Modifier un pays',
                    'delete_country' => 'Supprimer un pays',
                    'view_any_city' => 'Consulter la liste des villes (Rubrique & Menu)',
                    'create_city' => 'Créer une ville',
                    'update_city' => 'Modifier une ville',
                    'delete_city' => 'Supprimer une ville',
                ],
            ],
            'users' => [
                'label' => 'Utilisateurs & Collaborateurs',
                'icon' => 'heroicon-o-user-group',
                'description' => 'Gestion des comptes d\'accès et profils collaborateurs.',
                'permissions' => [
                    'view_any_user' => 'Consulter la liste des utilisateurs',
                    'view_user' => 'Consulter un profil utilisateur',
                    'create_user' => 'Créer un utilisateur',
                    'update_user' => 'Modifier un utilisateur',
                    'replicate_user' => 'Dupliquer un utilisateur',
                    'delete_user' => 'Supprimer un utilisateur',
                    'delete_any_user' => 'Supprimer des utilisateurs en masse',
                    'restore_user' => 'Restaurer un utilisateur',
                    'restore_any_user' => 'Restaurer des utilisateurs en masse',
                    'force_delete_user' => 'Supprimer définitivement un utilisateur',
                    'force_delete_any_user' => 'Supprimer définitivement en masse',
                    'reorder_user' => 'Réordonner les utilisateurs',
                ],
            ],
            'admin' => [
                'label' => 'Administration & Sécurité',
                'icon' => 'heroicon-o-shield-check',
                'description' => 'Accès général au backoffice et gestion des rôles.',
                'permissions' => [
                    'access_admin_panel' => 'Accéder au panneau d\'administration (Backoffice)',
                    'view_any_role' => 'Consulter la liste des rôles de sécurité',
                    'view_role' => 'Consulter un rôle de sécurité',
                    'create_role' => 'Créer un rôle de sécurité',
                    'update_role' => 'Modifier un rôle de sécurité',
                    'delete_role' => 'Supprimer un rôle de sécurité',
                    'delete_any_role' => 'Supprimer des rôles en masse',
                ],
            ],
        ];
    }

    public static function form(Form $form): Form
    {
        $modules = static::getPermissionsByModule();

        return $form->schema([
            Forms\Components\Section::make('Informations générales')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nom du département')
                        ->required()
                        ->maxLength(120)
                        ->unique(ignoreRecord: true),
                    Forms\Components\Hidden::make('permissions'),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Département actif')
                        ->default(true)
                        ->helperText('La désactivation bloque les actions et le backoffice pour ses membres, sauf les super-administrateurs.'),
                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->maxLength(2000)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Permissions et Actions autorisées par Module')
                ->description('Activez les actions autorisées pour ce département en naviguant dans chaque module ci-dessous. Un membre doit également posséder la permission dans ses rôles individuels.')
                ->schema([
                    Forms\Components\Tabs::make('Modules')
                        ->tabs(
                            collect($modules)->map(function ($module, $key) {
                                return Forms\Components\Tabs\Tab::make($module['label'])
                                    ->icon($module['icon'])
                                    ->schema([
                                        Forms\Components\Placeholder::make("desc_{$key}")
                                            ->hiddenLabel()
                                            ->content($module['description']),
                                        Forms\Components\CheckboxList::make("module_permissions.{$key}")
                                            ->hiddenLabel()
                                            ->options($module['permissions'])
                                            ->columns(2)
                                            ->bulkToggleable()
                                            ->searchable(),
                                    ]);
                            })->values()->all()
                        )
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')
                ->label('Département')
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('users_count')
                ->counts('users')
                ->label('Membres'),
            Tables\Columns\IconColumn::make('is_active')
                ->label('Actif')
                ->boolean(),
            Tables\Columns\TextColumn::make('updated_at')
                ->label('Dernière modification')
                ->dateTime('d/m/Y H:i')
                ->sortable(),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminDepartments::route('/'),
            'create' => Pages\CreateAdminDepartment::route('/create'),
            'edit' => Pages\EditAdminDepartment::route('/{record}/edit'),
        ];
    }
}
