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
                'label' => 'Clients',
                'icon' => 'heroicon-o-users',
                'description' => 'Gestion des comptes clients et de leurs informations.',
                'permissions' => [
                    'view_any_client' => 'Consulter la liste des clients',
                    'view_client' => 'Consulter la fiche client',
                    'create_client' => 'Créer un client',
                    'update_client' => 'Modifier un client',
                    'replicate_client' => 'Dupliquer un client',
                    'delete_client' => 'Supprimer un client',
                    'delete_any_client' => 'Supprimer des clients en masse',
                    'restore_client' => 'Restaurer un client',
                    'restore_any_client' => 'Restaurer des clients en masse',
                    'force_delete_client' => 'Supprimer définitivement un client',
                    'force_delete_any_client' => 'Supprimer définitivement en masse',
                    'reorder_client' => 'Réordonner les clients',
                ],
            ],
            'onboarding' => [
                'label' => 'Dossiers KYC (Onboarding)',
                'icon' => 'heroicon-o-document-check',
                'description' => 'Parcours d\'onboarding, vérification d\'identité et conformité KYC.',
                'permissions' => [
                    'view_any_onboarding::session' => 'Consulter la liste des dossiers KYC',
                    'view_onboarding::session' => 'Consulter le détail du dossier KYC',
                    'create_onboarding::session' => 'Créer un dossier d\'onboarding',
                    'update_onboarding::session' => 'Modifier un dossier d\'onboarding',
                    'replicate_onboarding::session' => 'Dupliquer un dossier',
                    'delete_onboarding::session' => 'Supprimer un dossier',
                    'delete_any_onboarding::session' => 'Supprimer des dossiers en masse',
                    'restore_onboarding::session' => 'Restaurer un dossier',
                    'restore_any_onboarding::session' => 'Restaurer des dossiers en masse',
                    'force_delete_onboarding::session' => 'Supprimer définitivement un dossier',
                    'force_delete_any_onboarding::session' => 'Supprimer définitivement en masse',
                    'reorder_onboarding::session' => 'Réordonner les dossiers',
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
                    'update_subscription' => 'Modifier une souscription',
                    'replicate_subscription' => 'Dupliquer une souscription',
                    'delete_subscription' => 'Supprimer une souscription',
                    'delete_any_subscription' => 'Supprimer des souscriptions en masse',
                    'restore_subscription' => 'Restaurer une souscription',
                    'restore_any_subscription' => 'Restaurer des souscriptions en masse',
                    'force_delete_subscription' => 'Supprimer définitivement une souscription',
                    'force_delete_any_subscription' => 'Supprimer définitivement en masse',
                    'reorder_subscription' => 'Réordonner les souscriptions',
                    'confirm_bank_payment' => 'Confirmer les fonds et attribuer les parts (Finance)',
                    'review_subscription_compliance' => 'Valider la conformité des souscriptions (Compliance)',
                    'review_payment_proof' => 'Examiner les justificatifs de paiement',
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
                'label' => 'Administration & Système',
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
