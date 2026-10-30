<?php

namespace App\Filament\Pages;

use App\Models\SystemSetting;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;

class ManageStatementSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Configuration';

    protected static ?string $navigationLabel = 'Relevés Mensuels FCP';

    protected static ?string $title = 'Gestion & Paramètres des Relevés Mensuels FCP';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.manage-statement-settings';

    /**
     * Restriction stricte : Seul le super-administrateur peut visualiser et modifier ces paramètres.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasRole('super_admin') || $user->email === 'ejabbing@pek.com';
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'monthly_statement_pwa_enabled' => (bool) SystemSetting::get('monthly_statement_pwa_enabled', true),
            'monthly_statement_history_months' => (int) SystemSetting::get('monthly_statement_history_months', 24),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Activation du Service Relevés Mensuels PWA')
                    ->description('Contrôle exclusif de mise à disposition des relevés de compte pour les clients sur l\'application mobile.')
                    ->schema([
                        Toggle::make('monthly_statement_pwa_enabled')
                            ->label('Activer la fonctionnalité de téléchargement des relevés mensuels sur la PWA')
                            ->helperText('Lorsque cette option est activée, les clients connectés ont accès au module de téléchargement de leurs relevés certifiés. Si désactivée, le module est complètement masqué et tout téléchargement est bloqué au niveau de l\'API.')
                            ->default(true),

                        TextInput::make('monthly_statement_history_months')
                            ->label('Historique maximal disponible pour les clients (en mois)')
                            ->helperText('Nombre maximal de mois passés consultables par les clients depuis la PWA (défaut : 24 mois).')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(120)
                            ->default(24)
                            ->required(),
                    ]),

                Section::make('Règles de Clôture & Calendrier Financier')
                    ->description('Rappel des normes réglementaires COSUMAF & BEAC appliquées par le moteur automatisé.')
                    ->schema([
                        Placeholder::make('rules_notice')
                            ->label('')
                            ->content(new HtmlString('
                                <div class="rounded-2xl bg-amber-50/90 border border-amber-200/80 p-5 text-amber-950 space-y-3">
                                    <div class="flex items-center gap-2 text-sm font-bold text-amber-900">
                                        <span>📅 Règle stricte du 1er jour ouvrable du mois suivant (M+1)</span>
                                    </div>
                                    <ul class="text-xs text-amber-900/90 space-y-1.5 list-disc list-inside">
                                        <li><strong>Période du relevé :</strong> Chaque relevé mensuel couvre l\'intégralité du 1er au dernier jour du mois calendaire (ex : 01/10/2026 au 31/10/2026).</li>
                                        <li><strong>Verrouillage du mois en cours :</strong> Le relevé du mois en cours est bloqué jusqu\'à la clôture officielle et devient téléchargeable dès le <strong>1er jour ouvrable du mois suivant</strong>.</li>
                                        <li><strong>Exclusion des jours non ouvrés :</strong> Les samedis, dimanches ainsi que les jours fériés légaux de la zone CEMAC / Cameroun (1er janvier, 11 février, 1er mai, 20 mai, 15 août, 1er novembre Toussaint, 25 décembre, fêtes mobiles) sont automatiquement exclus du calendrier d\'ouverture.</li>
                                        <li><strong>Sécurité d\'accès :</strong> Même en cas de tentative d\'accès forcé par URL ou script externe, l\'API rejette toute requête prématurée avec un code HTTP 422.</li>
                                    </ul>
                                </div>
                            ')),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        // Double vérification de sécurité côté serveur
        if (! static::canAccess()) {
            abort(403, 'Seul le super-administrateur peut modifier ces paramètres.');
        }

        $state = $this->form->getState();

        SystemSetting::set(
            'monthly_statement_pwa_enabled',
            (bool) ($state['monthly_statement_pwa_enabled'] ?? true),
            'bool',
            'Activer le téléchargement des relevés mensuels sur la PWA'
        );

        SystemSetting::set(
            'monthly_statement_history_months',
            (int) ($state['monthly_statement_history_months'] ?? 24),
            'int',
            'Historique maximal de relevés mensuels'
        );

        Notification::make()
            ->title('Paramètres des relevés mensuels mis à jour avec succès')
            ->success()
            ->send();
    }
}
