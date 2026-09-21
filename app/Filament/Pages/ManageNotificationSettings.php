<?php

namespace App\Filament\Pages;

use App\Models\SystemSetting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageNotificationSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Configuration';

    protected static ?string $navigationLabel = 'Réglages Notifications & SLA';

    protected static ?string $title = 'Réglages Notifications & Délais SLA';

    protected static string $view = 'filament.pages.manage-notification-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'compliance_emails' => SystemSetting::get('compliance_emails', 'conformite@koriassetmanagement.com'),
            'accounting_emails' => SystemSetting::get('accounting_emails', 'comptabilite@koriassetmanagement.com'),
            'onboarding_sla_hours' => SystemSetting::get('onboarding_sla_hours', 24),
            'subscription_sla_hours' => SystemSetting::get('subscription_sla_hours', 48),
            'sla_alerts_enabled' => SystemSetting::get('sla_alerts_enabled', true),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Destinataires des Notifications Internes')
                    ->description('Définissez les adresses e-mail des équipes chargées des contrôles.')
                    ->schema([
                        TextInput::make('compliance_emails')
                            ->label('E-mails Équipe Conformité')
                            ->placeholder('conformite@example.com, responsable@example.com')
                            ->helperText('Séparer les adresses par des virgules si vous en avez plusieurs.')
                            ->required(),

                        TextInput::make('accounting_emails')
                            ->label('E-mails Équipe Comptabilité')
                            ->placeholder('comptabilite@example.com')
                            ->helperText('Séparer les adresses par des virgules si vous en avez plusieurs.')
                            ->required(),
                    ]),

                Section::make('Seuils de Délais (SLA) & Alertes Automatiques')
                    ->description('Fixez les durées maximales d\'attente avant déclenchement des alertes automatiques.')
                    ->schema([
                        TextInput::make('onboarding_sla_hours')
                            ->label('SLA Validation Onboarding (Heures)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->suffix('heures'),

                        TextInput::make('subscription_sla_hours')
                            ->label('SLA Contrôle Interne Souscription (Heures)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->suffix('heures'),

                        Toggle::make('sla_alerts_enabled')
                            ->label('Activer les alertes automatiques par e-mail lors du dépassement des SLA')
                            ->default(true),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        SystemSetting::set('compliance_emails', $state['compliance_emails']);
        SystemSetting::set('accounting_emails', $state['accounting_emails']);
        SystemSetting::set('onboarding_sla_hours', (int) $state['onboarding_sla_hours']);
        SystemSetting::set('subscription_sla_hours', (int) $state['subscription_sla_hours']);
        SystemSetting::set('sla_alerts_enabled', (bool) $state['sla_alerts_enabled']);

        Notification::make()
            ->title('Paramètres enregistrés avec succès')
            ->success()
            ->send();
    }
}
