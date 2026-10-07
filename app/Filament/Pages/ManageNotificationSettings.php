<?php

namespace App\Filament\Pages;

use App\Models\SystemSetting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
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

    protected static ?string $title = 'Réglages Notifications, Délais SLA & Relances CRM';

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

            // Notifications d'événements immédiats
            'registration_notifications_enabled' => SystemSetting::get('registration_notifications_enabled', true),
            'registration_notification_emails' => SystemSetting::get('registration_notification_emails', 'contact@koriassetmanagement.com'),

            'onboarding_submission_notifications_enabled' => SystemSetting::get('onboarding_submission_notifications_enabled', true),
            'onboarding_submission_notification_emails' => SystemSetting::get('onboarding_submission_notification_emails', 'conformite@koriassetmanagement.com'),

            'subscription_notifications_enabled' => SystemSetting::get('subscription_notifications_enabled', true),
            'subscription_notification_emails' => SystemSetting::get('subscription_notification_emails', 'comptabilite@koriassetmanagement.com'),

            // Pièces d'identité
            'id_expiry_reminders_enabled' => SystemSetting::get('id_expiry_reminders_enabled', true),
            'id_expiry_notice_days' => SystemSetting::get('id_expiry_notice_days', 30),
            'id_expiry_throttle_days' => SystemSetting::get('id_expiry_throttle_days', 7),

            // Anniversaires
            'birthday_wishes_enabled' => SystemSetting::get('birthday_wishes_enabled', true),
            'birthday_notice_days' => SystemSetting::get('birthday_notice_days', 0),
            'birthday_custom_message' => SystemSetting::get('birthday_custom_message', 'Toute l\'équipe de KORI Asset Management vous adresse ses vœux les plus chaleureux de santé, de prospérité et de succès continu dans tous vos projets.'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Notifications Immédiates d\'Événements (Alertes Équipe)')
                    ->description('Configurez les adresses e-mails spécifiques qui reçoivent immédiatement une alerte à chaque nouvel événement client.')
                    ->schema([
                        // Inscriptions
                        Toggle::make('registration_notifications_enabled')
                            ->label('Alerter par e-mail à chaque nouvelle inscription client')
                            ->default(true),

                        TextInput::make('registration_notification_emails')
                            ->label('E-mails destinataires — Nouvelles Inscriptions')
                            ->placeholder('contact@koriassetmanagement.com, admin@pek.com')
                            ->helperText('Adresses e-mails recevant les alertes de nouveaux comptes créés. Séparer par des virgules si plusieurs.')
                            ->required(fn (\Filament\Forms\Get $get) => (bool) $get('registration_notifications_enabled')),

                        // Onboarding soumis
                        Toggle::make('onboarding_submission_notifications_enabled')
                            ->label('Alerter par e-mail dès qu\'un client soumet son dossier KYC à valider')
                            ->default(true),

                        TextInput::make('onboarding_submission_notification_emails')
                            ->label('E-mails destinataires — Dossiers Onboarding Soumis (Conformité)')
                            ->placeholder('conformite@koriassetmanagement.com')
                            ->helperText('Adresses e-mails chargées de la vérification des pièces d\'identité et de l\'activation des comptes.')
                            ->required(fn (\Filament\Forms\Get $get) => (bool) $get('onboarding_submission_notifications_enabled')),

                        // Souscriptions
                        Toggle::make('subscription_notifications_enabled')
                            ->label('Alerter par e-mail lors de chaque nouvelle souscription client')
                            ->default(true),

                        TextInput::make('subscription_notification_emails')
                            ->label('E-mails destinataires — Nouvelles Souscriptions (Opérations & Trésorerie)')
                            ->placeholder('comptabilite@koriassetmanagement.com, operations@koriassetmanagement.com')
                            ->helperText('Adresses e-mails chargées du suivi des règlements et de la comptabilisation des souscriptions.')
                            ->required(fn (\Filament\Forms\Get $get) => (bool) $get('subscription_notifications_enabled')),
                    ]),

                Section::make('Rappels Réglementaires — Pièces d\'Identité (KYC)')
                    ->description('Paramétrez les relances automatiques lorsque la pièce d\'identification d\'un investisseur arrive à expiration ou est expirée.')
                    ->schema([
                        Toggle::make('id_expiry_reminders_enabled')
                            ->label('Activer les rappels automatiques d\'expiration de pièce d\'identité (Email + In-App)')
                            ->default(true),

                        TextInput::make('id_expiry_notice_days')
                            ->label('Délai de préavis avant expiration')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(90)
                            ->suffix('jours')
                            ->helperText('Nombre de jours avant l\'échéance pour commencer les rappels préventifs (ex: 30 jours).'),

                        TextInput::make('id_expiry_throttle_days')
                            ->label('Intervalle minimum entre deux relances')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(30)
                            ->suffix('jours')
                            ->helperText('Délai anti-spam pour ne pas saturer la messagerie du client (ex: 7 jours).'),
                    ]),

                Section::make('Relation Client (CRM) — Dates de Naissance & Anniversaires')
                    ->description('Paramétrez l\'envoi automatique des souhaits d\'anniversaire aux clients.')
                    ->schema([
                        Toggle::make('birthday_wishes_enabled')
                            ->label('Activer l\'envoi automatique des souhaits d\'anniversaire (Email + In-App)')
                            ->default(true),

                        TextInput::make('birthday_notice_days')
                            ->label('Moment de l\'envoi')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(7)
                            ->suffix('jour(s) avant')
                            ->helperText('0 pour envoyer le jour même de l\'anniversaire à 08h00, 1 pour la veille.'),

                        Textarea::make('birthday_custom_message')
                            ->label('Message de vœux personnalisé de la Société de Gestion')
                            ->rows(3)
                            ->required()
                            ->helperText('Texte inséré au cœur du courriel festif KORI Asset Management.'),
                    ]),

                Section::make('Destinataires des Contrôles & Alertes SLA')
                    ->description('Définissez les adresses e-mail des équipes chargées des contrôles internes et des dépassements de délais.')
                    ->schema([
                        TextInput::make('compliance_emails')
                            ->label('E-mails Équipe Conformité (Alertes SLA)')
                            ->placeholder('conformite@example.com, responsable@example.com')
                            ->helperText('Séparer les adresses par des virgules si vous en avez plusieurs.')
                            ->required(),

                        TextInput::make('accounting_emails')
                            ->label('E-mails Équipe Comptabilité (Alertes SLA)')
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

        // Notifications d'événements immédiats
        SystemSetting::set('registration_notifications_enabled', (bool) ($state['registration_notifications_enabled'] ?? true), 'bool');
        SystemSetting::set('registration_notification_emails', (string) ($state['registration_notification_emails'] ?? 'contact@koriassetmanagement.com'), 'string');

        SystemSetting::set('onboarding_submission_notifications_enabled', (bool) ($state['onboarding_submission_notifications_enabled'] ?? true), 'bool');
        SystemSetting::set('onboarding_submission_notification_emails', (string) ($state['onboarding_submission_notification_emails'] ?? 'conformite@koriassetmanagement.com'), 'string');

        SystemSetting::set('subscription_notifications_enabled', (bool) ($state['subscription_notifications_enabled'] ?? true), 'bool');
        SystemSetting::set('subscription_notification_emails', (string) ($state['subscription_notification_emails'] ?? 'comptabilite@koriassetmanagement.com'), 'string');

        // SLA
        SystemSetting::set('compliance_emails', $state['compliance_emails']);
        SystemSetting::set('accounting_emails', $state['accounting_emails']);
        SystemSetting::set('onboarding_sla_hours', (int) $state['onboarding_sla_hours']);
        SystemSetting::set('subscription_sla_hours', (int) $state['subscription_sla_hours']);
        SystemSetting::set('sla_alerts_enabled', (bool) $state['sla_alerts_enabled']);

        // Pièces d'identité
        SystemSetting::set('id_expiry_reminders_enabled', (bool) $state['id_expiry_reminders_enabled'], 'bool');
        SystemSetting::set('id_expiry_notice_days', (int) $state['id_expiry_notice_days'], 'int');
        SystemSetting::set('id_expiry_throttle_days', (int) $state['id_expiry_throttle_days'], 'int');

        // Anniversaires
        SystemSetting::set('birthday_wishes_enabled', (bool) $state['birthday_wishes_enabled'], 'bool');
        SystemSetting::set('birthday_notice_days', (int) $state['birthday_notice_days'], 'int');
        SystemSetting::set('birthday_custom_message', (string) $state['birthday_custom_message'], 'string');

        Notification::make()
            ->title('Paramètres enregistrés avec succès')
            ->success()
            ->send();
    }
}