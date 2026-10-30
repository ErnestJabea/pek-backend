<?php

namespace App\Filament\Resources;

use App\Filament\Filters\DateRangeFilter;

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
            'subscriptions' => [
                'label' => 'Souscriptions & Paiements',
                'icon' => 'heroicon-o-banknotes',
                'description' => "Actions financières, engagements et visas réglementaires sur les souscriptions.",
                'permissions' => [
                    'create_subscription' => "Saisir / créer une souscription",
                    'update_subscription' => "Modifier une souscription (montants, frais, parts, date de valeur)",
                    'confirm_bank_payment' => "Apposer le Visa Comptabilité & Attribuer les parts (Finance)",
                    'review_subscription_compliance' => "Apposer le Visa de Conformité (Compliance)",
                    'review_payment_proof' => "Examiner et valider les justificatifs de paiement",
                    'replicate_subscription' => "Dupliquer une souscription",
                    'delete_subscription' => "Supprimer une souscription",
                    'delete_any_subscription' => "Supprimer des souscriptions en masse",
                    'restore_subscription' => "Restaurer une souscription",
                    'force_delete_subscription' => "Supprimer définitivement une souscription",
                ],
            ],
            'onboarding' => [
                'label' => 'Dossiers KYC & Profils',
                'icon' => 'heroicon-o-document-check',
                'description' => "Traitement des dossiers KYC, validation d'identité et modifications de RIB.",
                'permissions' => [
                    'create_onboarding::session' => "Créer un dossier d'onboarding",
                    'update_onboarding::session' => "Valider / rejeter un dossier KYC ou modifier la catégorie",
                    'update_profile_change_request' => "Approuver ou rejeter les demandes de modification Profil & RIB",
                    'delete_onboarding::session' => "Supprimer un dossier d'onboarding",
                    'delete_profile_change_request' => "Supprimer une demande de modification",
                    'restore_onboarding::session' => "Restaurer un dossier KYC",
                    'force_delete_onboarding::session' => "Supprimer définitivement un dossier KYC",
                ],
            ],
            'clients' => [
                'label' => 'Clients & CRM',
                'icon' => 'heroicon-o-users',
                'description' => "Actions opérationnelles sur les clients et relations clientèle.",
                'permissions' => [
                    'create_client' => "Créer une fiche client",
                    'update_client' => "Modifier un client (profil, statut, email, catégorie)",
                    'wish_client_birthday' => "Envoyer les vœux d'anniversaire au client",
                    'remind_client_id' => "Envoyer les rappels d'expiration de pièce d'identité",
                    'replicate_client' => "Dupliquer une fiche client",
                    'delete_client' => "Supprimer un client",
                    'delete_any_client' => "Supprimer des clients en masse",
                    'restore_client' => "Restaurer un client",
                    'force_delete_client' => "Supprimer définitivement un client",
                ],
            ],
            'products' => [
                'label' => 'Fonds (Produits) & VL',
                'icon' => 'heroicon-o-chart-bar',
                'description' => "Administration des OPCVM et saisie des valeurs liquidatives.",
                'permissions' => [
                    'create_product_vl' => "Ajouter une nouvelle Valeur Liquidative (VL)",
                    'update_product_vl' => "Modifier une Valeur Liquidative (VL)",
                    'delete_product_vl' => "Supprimer une Valeur Liquidative (VL)",
                    'create_product' => "Créer un nouveau fonds",
                    'update_product' => "Modifier les caractéristiques d'un fonds",
                    'delete_product' => "Supprimer un fonds",
                    'delete_any_product' => "Supprimer des fonds en masse",
                    'restore_product' => "Restaurer un fonds",
                    'force_delete_product' => "Supprimer définitivement un fonds",
                ],
            ],
            'bank_details' => [
                'label' => 'Comptes Bancaires',
                'icon' => 'heroicon-o-building-library',
                'description' => "Gestion des coordonnées bancaires officielles de la société de gestion.",
                'permissions' => [
                    'create_bank::detail' => "Créer un compte bancaire officiel",
                    'update_bank::detail' => "Modifier un compte bancaire",
                    'delete_bank::detail' => "Supprimer un compte bancaire",
                    'restore_bank::detail' => "Restaurer un compte bancaire",
                    'force_delete_bank::detail' => "Supprimer définitivement un compte bancaire",
                ],
            ],
            'currencies' => [
                'label' => 'Devises & Parités',
                'icon' => 'heroicon-o-currency-dollar',
                'description' => "Administration des devises et parités de change.",
                'permissions' => [
                    'create_currency' => "Créer une devise",
                    'update_currency' => "Modifier une devise ou son taux",
                    'delete_currency' => "Supprimer une devise",
                    'restore_currency' => "Restaurer une devise",
                    'force_delete_currency' => "Supprimer définitivement une devise",
                ],
            ],
            'system_config' => [
                'label' => 'Configuration Système & PWA',
                'icon' => 'heroicon-o-cog-6-tooth',
                'description' => "Gestion des contenus applicatifs, pays, villes et moyens de paiement.",
                'permissions' => [
                    'create_app_content' => "Créer un contenu d'application",
                    'update_app_content' => "Modifier les contenus d'application",
                    'delete_app_content' => "Supprimer un contenu d'application",
                    'create_payment_method' => "Créer un moyen de paiement",
                    'update_payment_method' => "Modifier les moyens de paiement",
                    'delete_payment_method' => "Supprimer un moyen de paiement",
                    'create_country' => "Créer un pays",
                    'update_country' => "Modifier un pays",
                    'delete_country' => "Supprimer un pays",
                    'create_city' => "Créer une ville",
                    'update_city' => "Modifier une ville",
                    'delete_city' => "Supprimer une ville",
                ],
            ],
            'admin' => [
                'label' => 'Comptes Collaborateurs & Sécurité',
                'icon' => 'heroicon-o-shield-check',
                'description' => "Gestion des accès collaborateurs et des rôles de sécurité.",
                'permissions' => [
                    'create_user' => "Créer un compte utilisateur",
                    'update_user' => "Modifier un utilisateur",
                    'delete_user' => "Supprimer un utilisateur",
                    'delete_any_user' => "Supprimer des utilisateurs en masse",
                    'restore_user' => "Restaurer un utilisateur",
                    'force_delete_user' => "Supprimer définitivement un utilisateur",
                    'create_role' => "Créer un rôle de sécurité",
                    'update_role' => "Modifier un rôle de sécurité",
                    'delete_role' => "Supprimer un rôle de sécurité",
                    'delete_any_role' => "Supprimer des rôles en masse",
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

            Forms\Components\Section::make('Actions et Opérations Métier Autorisées')
                ->description('Sélectionnez les actions opérationnelles permises pour ce département. Les rubriques et menus visibles par les collaborateurs sont quant à eux gouvernés par leur Rôle.')
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
            Tables\Columns\TextColumn::make('created_at')
                ->label('Date création')
                ->dateTime('d/m/Y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('updated_at')
                ->label('Dernière modification')
                ->dateTime('d/m/Y H:i')
                ->sortable(),
        ])
        ->filters([
            DateRangeFilter::make('created_at', 'Période de création'),
        ])
        ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent)
        ->filtersFormColumns(2)
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
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
        ->defaultSort('updated_at', 'desc');
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
