<?php
namespace App\Filament\Resources;
use App\Models\AdminDepartment;
use App\Filament\Resources\AdminDepartmentResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;

class AdminDepartmentResource extends Resource
{
    protected static ?string $model = AdminDepartment::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?string $modelLabel = 'Département';
    protected static ?string $pluralModelLabel = 'Départements';
    public static function canViewAny(): bool { return auth()->user()?->hasRole('super_admin') ?? false; }
    public static function canCreate(): bool { return static::canViewAny(); }
    public static function canEdit(Model $record): bool { return static::canViewAny(); }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nom')->required()->maxLength(120)->unique(ignoreRecord: true),
            Forms\Components\Textarea::make('description')->label('Description')->maxLength(2000),
            Forms\Components\Toggle::make('is_active')->label('Département actif')->default(true)
                ->helperText('La désactivation bloque les actions et le backoffice pour ses membres, sauf les super-administrateurs.'),
            Forms\Components\CheckboxList::make('permissions')->label('Actions autorisées')->searchable()->columns(2)
                ->options(function (?AdminDepartment $record) {
                    $names = Permission::where('guard_name', 'web')->pluck('name')->merge($record?->permissions ?? [])->unique()->sort();
                    $labels = ['access_admin_panel' => 'Accéder au backoffice', 'confirm_bank_payment' => 'Confirmer les fonds et attribuer les parts',
                        'review_payment_proof' => 'Examiner les justificatifs', 'view_payment_proof' => 'Consulter les justificatifs',
                        'review_subscription_compliance' => 'Valider la conformité des souscriptions'];
                    return $names->mapWithKeys(fn ($name) => [$name => $labels[$name] ?? static::permissionLabel($name)])->all();
                })
                ->helperText('Un membre doit aussi posséder la permission dans ses rôles individuels. Un département ne donne jamais de droits supplémentaires.'),
        ]);
    }
    private static function permissionLabel(string $name): string
    {
        $actions = ['view_any' => 'Consulter la liste', 'force_delete_any' => 'Supprimer définitivement en masse',
            'force_delete' => 'Supprimer définitivement', 'delete_any' => 'Supprimer en masse',
            'restore_any' => 'Restaurer en masse', 'view' => 'Consulter', 'create' => 'Créer', 'update' => 'Modifier',
            'delete' => 'Supprimer', 'restore' => 'Restaurer', 'replicate' => 'Dupliquer', 'reorder' => 'Réordonner'];
        $modules = ['subscription' => 'Souscriptions', 'onboarding_session' => 'Dossiers KYC', 'user' => 'Utilisateurs',
            'client' => 'Clients', 'product' => 'Fonds', 'product_vl' => 'Valeurs liquidatives', 'bank_detail' => 'Comptes bancaires',
            'currency' => 'Devises', 'role' => 'Rôles de sécurité'];
        foreach ($actions as $prefix => $label) {
            if (str_starts_with($name, $prefix.'_')) {
                $module = substr($name, strlen($prefix) + 1);
                return ($modules[$module] ?? str_replace('_', ' ', $module)).' — '.$label;
            }
        }
        return str_replace('_', ' ', $name);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Département')->searchable(),
            Tables\Columns\TextColumn::make('users_count')->counts('users')->label('Membres'),
            Tables\Columns\IconColumn::make('is_active')->label('Actif')->boolean(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }
    public static function getPages(): array
    {
        return ['index' => Pages\ListAdminDepartments::route('/'), 'create' => Pages\CreateAdminDepartment::route('/create'), 'edit' => Pages\EditAdminDepartment::route('/{record}/edit')];
    }
}
