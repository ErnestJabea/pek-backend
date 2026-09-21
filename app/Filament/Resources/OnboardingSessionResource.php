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

    protected static ?string $navigationLabel = 'Onboarding Clients';

    protected static ?string $modelLabel = 'Session d\'Onboarding';

    protected static ?string $pluralModelLabel = 'Onboarding Clients';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return $record->status === 'completed' && parent::canEdit($record);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Création de la session')
                    ->visible(fn ($record) => $record === null || ! $record->exists)
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Client')
                            ->relationship('user', 'email', fn (Builder $query) => $query->where('role', 'client')->whereDoesntHave('onboardingSession'))
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->first_name} {$record->last_name} ({$record->email})")
                            ->required()
                            ->searchable()
                            ->preload(),
                    ]),

                Section::make('Statut de la session')
                    ->visible(fn ($record) => $record && $record->exists)
                    ->columns(3)
                    ->schema([
                        Placeholder::make('user_name')
                            ->label('Client')
                            ->content(fn ($record) => $record && $record->user ? trim($record->user->first_name.' '.$record->user->last_name) : '-'),
                        Placeholder::make('user_email')
                            ->label('Email client')
                            ->content(fn ($record) => $record && $record->user ? $record->user->email : '-'),
                        Placeholder::make('status')
                            ->label('Statut Onboarding')
                            ->content(fn ($record) => $record && $record->status ? match ($record->status) {
                                'validated' => new HtmlString('<span style="color: green; font-weight: bold;">✓ Validé / Activé</span>'),
                                'completed' => new HtmlString('<span style="color: blue; font-weight: bold;">⏳ Soumis / En attente de validation</span>'),
                                'rejected' => new HtmlString('<span style="color: red; font-weight: bold;">❌ Rejeté</span>'),
                                'in_progress' => new HtmlString('<span style="color: orange; font-weight: bold;">⏳ En cours</span>'),
                                default => $record->status,
                            } : '-'),
                    ]),

                Tabs::make('Details')
                    ->visible(fn ($record) => $record && $record->exists)
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Vérification Biométrique & CNI (KYC)')
                            ->icon('heroicon-o-finger-print')
                            ->schema([
                                Section::make('Synthèse du contrôle automatisé d\'identité')
                                    ->columns(4)
                                    ->schema([
                                        Placeholder::make('face_score_badge')
                                            ->label('Correspondance Faciale')
                                            ->content(function ($record) {
                                                $score = $record->payload['face_match_score'] ?? null;
                                                if ($score === null) {
                                                    return new HtmlString('<span style="color: #64748b; font-size: 0.9rem;">Non analysé</span>');
                                                }
                                                $color = $score >= 60 ? '#16a34a' : ($score >= 45 ? '#d97706' : '#dc2626');
                                                $bg = $score >= 60 ? '#dcfce7' : ($score >= 45 ? '#fef3c7' : '#fee2e2');
                                                $icon = $score >= 60 ? '✓' : '⚠️';
                                                return new HtmlString("<span style='background-color: {$bg}; color: {$color}; padding: 6px 14px; border-radius: 6px; font-weight: bold; font-size: 1rem;'>{$icon} {$score}%</span>");
                                            }),

                                        Placeholder::make('ocr_score_badge')
                                            ->label('Concordance OCR (Texte CNI)')
                                            ->content(function ($record) {
                                                $score = $record->payload['ocr_score'] ?? null;
                                                if ($score === null) {
                                                    return new HtmlString('<span style="color: #64748b; font-size: 0.9rem;">Non analysé</span>');
                                                }
                                                $color = $score >= 50 ? '#16a34a' : '#d97706';
                                                $bg = $score >= 50 ? '#dcfce7' : '#fef3c7';
                                                return new HtmlString("<span style='background-color: {$bg}; color: {$color}; padding: 6px 14px; border-radius: 6px; font-weight: bold; font-size: 1rem;'>{$score}% Concordance</span>");
                                            }),

                                        Placeholder::make('audit_status_badge')
                                            ->label('Statut de l\'audit')
                                            ->content(function ($record) {
                                                $verified = $record->payload['face_verified'] ?? false;
                                                return $verified
                                                    ? new HtmlString('<span style="color: #16a34a; font-weight: bold;">✓ Pré-validé par IA</span>')
                                                    : new HtmlString('<span style="color: #d97706; font-weight: bold;">⏳ Revue manuelle requise</span>');
                                            }),

                                        Placeholder::make('scan_timestamp')
                                            ->label('Date & Heure du scan')
                                            ->content(fn ($record) => !empty($record->payload['verification_timestamp']) ? date('d/m/Y H:i:s', strtotime($record->payload['verification_timestamp'])) : '-'),
                                    ]),

                                Section::make('Comparateur visuel : Pièce d\'identité vs Selfie en direct')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            Placeholder::make('cni_recto_preview')
                                                ->label('1. Pièce d\'identité (Recto)')
                                                ->content(function ($record) {
                                                    $img = $record->payload['piece_recto'] ?? null;
                                                    if ($img) {
                                                        return new HtmlString("<div style='border: 2px solid #cbd5e1; border-radius: 8px; overflow: hidden; background: #0f172a; text-align: center;'><img src='{$img}' style='max-height: 240px; width: 100%; object-fit: contain; cursor: pointer;' onclick='window.open(this.src)' title='Cliquer pour agrandir' /></div>");
                                                    }
                                                    return new HtmlString('<div style="color: #94a3b8; font-style: italic; padding: 20px; border: 1px dashed #cbd5e1; border-radius: 8px; text-align: center;">Non fournie</div>');
                                                }),

                                            Placeholder::make('cni_verso_preview')
                                                ->label('2. Pièce d\'identité (Verso)')
                                                ->content(function ($record) {
                                                    $img = $record->payload['piece_verso'] ?? null;
                                                    if ($img) {
                                                        return new HtmlString("<div style='border: 2px solid #cbd5e1; border-radius: 8px; overflow: hidden; background: #0f172a; text-align: center;'><img src='{$img}' style='max-height: 240px; width: 100%; object-fit: contain; cursor: pointer;' onclick='window.open(this.src)' title='Cliquer pour agrandir' /></div>");
                                                    }
                                                    return new HtmlString('<div style="color: #94a3b8; font-style: italic; padding: 20px; border: 1px dashed #cbd5e1; border-radius: 8px; text-align: center;">Non fournie (Optionnel)</div>');
                                                }),

                                            Placeholder::make('selfie_live_preview')
                                                ->label('3. Selfie en Temps Réel (Caméra)')
                                                ->content(function ($record) {
                                                    $img = $record->payload['selfie_live'] ?? null;
                                                    if ($img) {
                                                        return new HtmlString("<div style='border: 2px solid #16a34a; border-radius: 8px; overflow: hidden; background: #0f172a; text-align: center;'><img src='{$img}' style='max-height: 240px; width: 100%; object-fit: contain; cursor: pointer;' onclick='window.open(this.src)' title='Cliquer pour agrandir' /></div>");
                                                    }
                                                    return new HtmlString('<div style="color: #94a3b8; font-style: italic; padding: 20px; border: 1px dashed #cbd5e1; border-radius: 8px; text-align: center;">Non fourni</div>');
                                                }),
                                        ]),
                                    ]),
                            ]),

                        Tab::make('Informations KYC & Identité')
                            ->schema([
                                Grid::make(3)->schema([
                                    Placeholder::make('payload.civ')->label('Civilité')->content(fn ($state) => $state ?? '-'),
                                    Placeholder::make('payload.nom')->label('Nom')->content(fn ($state) => $state ?? '-'),
                                    Placeholder::make('payload.prenom')->label('Prénom')->content(fn ($state) => $state ?? '-'),
                                    Placeholder::make('payload.nat')->label('Nationalité')->content(fn ($state) => $state ?? '-'),
                                    Placeholder::make('payload.dob')->label('Date de naissance')->content(fn ($state) => new HtmlString('<div style="font-weight: 600; color: #1e293b;">'.($state ? date('d/m/Y', strtotime($state)) : '-').'</div>')),
                                    Placeholder::make('payload.lieu_naiss')->label('Lieu de naissance')->content(fn ($state) => $state ?? '-'),
                                    Placeholder::make('payload.tel')->label('Téléphone')->content(fn ($state) => $state ?? '-'),
                                    Placeholder::make('payload.email')->label('E-mail déclaré')->content(fn ($state) => $state ?? '-'),
                                    Placeholder::make('payload.adresse')->label('Adresse de résidence')->content(fn ($state) => $state ?? '-'),
                                    Placeholder::make('payload.piece')->label('Type de pièce')->content(fn ($state) => $state ?? '-'),
                                    Placeholder::make('payload.num_piece')->label('N° de pièce')->content(fn ($state) => $state ?? '-'),
                                    Placeholder::make('payload.expiration_piece')->label('Date d\'expiration')->content(fn ($state) => new HtmlString('<div style="font-weight: 600; color: #1e293b;">'.($state ? date('d/m/Y', strtotime($state)) : '-').'</div>')),
                                    Placeholder::make('payload.profession')->label('Profession')->content(fn ($state) => $state ?? '-'),
                                    Placeholder::make('payload.employeur')->label('Employeur')->content(fn ($state) => $state ?? '-'),
                                    Placeholder::make('payload.situation_mat')->label('Situation matrimoniale')->content(fn ($state) => $state ?? '-'),
                                    Placeholder::make('payload.pays_residence')->label('Pays de résidence')->content(fn ($state) => $state ?? '-'),
                                ]),
                            ]),

                        Tab::make('Profil Investisseur')
                            ->schema([
                                Section::make('Synthèse du profil de risque')
                                    ->columns(3)
                                    ->schema([
                                        Placeholder::make('risk_level')
                                            ->label('Niveau de Risque Global')
                                            ->content(fn ($record) => match ($record->risk_level) {
                                                'LOW' => new HtmlString('<span style="background-color: #dcfce7; color: #16a34a; padding: 6px 14px; border-radius: 6px; font-weight: bold; font-size: 0.95rem;">LOW (Faible)</span>'),
                                                'HIGH' => new HtmlString('<span style="background-color: #fee2e2; color: #dc2626; padding: 6px 14px; border-radius: 6px; font-weight: bold; font-size: 0.95rem;">⚠️ HIGH (Élevé)</span>'),
                                                default => e($record->risk_level ?? '-'),
                                            }),
                                        Placeholder::make('payload.risk_score')
                                            ->label('Score de Risque calculé')
                                            ->content(fn ($state) => is_numeric($state) ? $state.' pts' : '-'),
                                        Placeholder::make('payload.risk_profile')
                                            ->label('Profil d\'Investisseur')
                                            ->content(fn ($state) => match ($state) {
                                                'Prudent' => new HtmlString('<span style="background-color: #e0f2fe; color: #0369a1; padding: 6px 14px; border-radius: 6px; font-weight: bold; font-size: 0.95rem;">Prudent (Conservateur)</span>'),
                                                'Modéré', 'Moyen', 'Modéré' => new HtmlString('<span style="background-color: #fef9c3; color: #a16207; padding: 6px 14px; border-radius: 6px; font-weight: bold; font-size: 0.95rem;">Modéré (Équilibré)</span>'),
                                                'Dynamique' => new HtmlString('<span style="background-color: #fae8ff; color: #a21caf; padding: 6px 14px; border-radius: 6px; font-weight: bold; font-size: 0.95rem;">Dynamique (Performances)</span>'),
                                                default => $state ?? '-',
                                            }),
                                    ]),
                                Section::make('Détail des réponses au questionnaire')
                                    ->collapsible()
                                    ->schema([
                                        Grid::make(1)->schema([
                                            Placeholder::make('payload.tranche_revenus')
                                                ->label('1. Quelle est votre tranche de revenus mensuels ?')
                                                ->content(fn ($state) => new HtmlString('<div style="font-size: 0.95rem; font-weight: 600; color: #1e293b; border-left: 3px solid #009a4d; padding-left: 10px; margin-bottom: 8px;">'.match ($state) {
                                                    'moins_500k' => 'Moins de 500 000 FCFA',
                                                    '500k_1_5m' => '500 000 FCFA - 1 500 000 FCFA',
                                                    'plus_1_5m' => 'Plus de 1 500 000 FCFA',
                                                    default => e($state ?? '-'),
                                                }.'</div>')),

                                            Placeholder::make('payload.epargne_possible')
                                                ->label('2. Disposez-vous d\'une capacité d\'épargne régulière ?')
                                                ->content(fn ($state) => new HtmlString(
                                                    ($state === 'Oui' || $state === true)
                                                        ? '<div style="margin-bottom: 8px; border-left: 3px solid #009a4d; padding-left: 10px;"><span style="background-color: #dcfce7; color: #16a34a; padding: 3px 10px; border-radius: 4px; font-weight: bold; font-size: 0.85rem;">OUI</span></div>'
                                                        : '<div style="margin-bottom: 8px; border-left: 3px solid #009a4d; padding-left: 10px;"><span style="background-color: #f1f5f9; color: #475569; padding: 3px 10px; border-radius: 4px; font-weight: bold; font-size: 0.85rem;">NON</span></div>'
                                                )),

                                            Placeholder::make('payload.niveau_risque')
                                                ->label('3. Quel niveau de risque acceptez-vous pour vos placements ?')
                                                ->content(fn ($state) => new HtmlString('<div style="font-size: 0.95rem; font-weight: 600; color: #1e293b; border-left: 3px solid #009a4d; padding-left: 10px; margin-bottom: 8px;">'.match ($state) {
                                                    'faible' => 'Faible risque (Privilégie la sécurité absolue du capital)',
                                                    'moyen' => 'Risque modéré (Accepte des fluctuations légères pour optimiser les rendements)',
                                                    'max' => 'Risque élevé (Recherche la performance maximale, accepte des pertes temporaires)',
                                                    default => e($state ?? '-'),
                                                }.'</div>')),

                                            Placeholder::make('payload.conscience_risque')
                                                ->label('4. Avez-vous conscience des risques inhérents à un placement financier (perte en capital) ?')
                                                ->content(fn ($state) => new HtmlString(
                                                    ($state === 'Oui' || $state === true)
                                                        ? '<div style="margin-bottom: 8px; border-left: 3px solid #009a4d; padding-left: 10px;"><span style="background-color: #dcfce7; color: #16a34a; padding: 3px 10px; border-radius: 4px; font-weight: bold; font-size: 0.85rem;">OUI</span></div>'
                                                        : '<div style="margin-bottom: 8px; border-left: 3px solid #009a4d; padding-left: 10px;"><span style="background-color: #fee2e2; color: #dc2626; padding: 3px 10px; border-radius: 4px; font-weight: bold; font-size: 0.85rem;">NON (Alerte Risque)</span></div>'
                                                )),

                                            Placeholder::make('payload.objectif_invest')
                                                ->label('5. Quel est votre objectif principal d\'investissement ?')
                                                ->content(fn ($state) => new HtmlString('<div style="font-size: 0.95rem; font-weight: 600; color: #1e293b; border-left: 3px solid #009a4d; padding-left: 10px; margin-bottom: 8px;">'.match ($state) {
                                                    'securite' => 'Préservation du capital (Recherche de sécurité)',
                                                    'equilibre' => 'Recherche d\'équilibre entre valorisation et sécurité',
                                                    'croissance' => 'Croissance et valorisation à long terme',
                                                    default => e($state ?? '-'),
                                                }.'</div>')),

                                            Placeholder::make('payload.horizon_terme')
                                                ->label('6. Quel est l\'horizon de placement visé pour vos fonds ?')
                                                ->content(fn ($state) => new HtmlString('<div style="font-size: 0.95rem; font-weight: 600; color: #1e293b; border-left: 3px solid #009a4d; padding-left: 10px; margin-bottom: 8px;">'.match ($state) {
                                                    'court_terme' => 'Court terme (Moins de 1 an)',
                                                    'moyen_terme' => 'Moyen terme (1 à 3 ans)',
                                                    'long_terme' => 'Long terme (Plus de 3 ans)',
                                                    default => e($state ?? '-'),
                                                }.'</div>')),

                                            Placeholder::make('payload.niveau_perf')
                                                ->label('7. Quel objectif de performance ciblez-vous ?')
                                                ->content(fn ($state) => new HtmlString('<div style="font-size: 0.95rem; font-weight: 600; color: #1e293b; border-left: 3px solid #009a4d; padding-left: 10px; margin-bottom: 8px;">'.match ($state) {
                                                    '1' => 'Rendement faible mais sécurité maximale',
                                                    'moderee' => 'Rendement modéré avec fluctuation modérée',
                                                    'elevee' => 'Rendement élevé avec fluctuations importantes acceptées',
                                                    default => e($state ?? '-'),
                                                }.'</div>')),

                                            Placeholder::make('payload.connaissance_marche')
                                                ->label('8. Quelle est votre connaissance des marchés financiers et OPCVM ?')
                                                ->content(fn ($state) => new HtmlString('<div style="font-size: 0.95rem; font-weight: 600; color: #1e293b; border-left: 3px solid #009a4d; padding-left: 10px; margin-bottom: 8px;">'.match ($state) {
                                                    'nulle' => 'Aucune connaissance',
                                                    'moyenne' => 'Connaissance générale / intermédiaire',
                                                    'excellente' => 'Bonne / Excellente connaissance',
                                                    default => e($state ?? '-'),
                                                }.'</div>')),

                                            Placeholder::make('payload.invest_anterieurs')
                                                ->label('9. Avez-vous déjà réalisé des investissements similaires dans le passé ?')
                                                ->content(fn ($state) => new HtmlString(
                                                    ($state === 'Oui' || $state === true)
                                                        ? '<div style="border-left: 3px solid #009a4d; padding-left: 10px;"><span style="background-color: #dcfce7; color: #16a34a; padding: 3px 10px; border-radius: 4px; font-weight: bold; font-size: 0.85rem;">OUI</span></div>'
                                                        : '<div style="border-left: 3px solid #009a4d; padding-left: 10px;"><span style="background-color: #f1f5f9; color: #475569; padding: 3px 10px; border-radius: 4px; font-weight: bold; font-size: 0.85rem;">NON</span></div>'
                                                )),
                                        ]),
                                    ]),
                            ]),

                        Tab::make('Questionnaire LAB-FT & Conformité')
                            ->schema([
                                Grid::make(3)->schema([
                                    Placeholder::make('payload.agent_kam')->label('Agent KAM de référence')->content(fn ($state) => $state ?? '-'),
                                    Placeholder::make('payload.secteur')->label('Secteur d\'activité professionnelle')->content(fn ($state) => $state ?? '-'),
                                    Placeholder::make('payload.revenus_annuels')
                                        ->label('Revenus annuels estimés')
                                        ->content(fn ($state) => new HtmlString('<div style="font-weight: 600; color: #1e293b;">'.match ($state) {
                                            'moins_5m' => 'Moins de 5 000 000 FCFA',
                                            '5m_15m', '5_15m' => 'Entre 5 000 000 et 15 000 000 FCFA',
                                            'plus_15m' => 'Plus de 15 000 000 FCFA',
                                            default => e($state ?? '-'),
                                        }.'</div>')),
                                    Placeholder::make('payload.origine_fonds')->label('Origine des fonds investis')->content(fn ($state) => $state ?? '-'),
                                    Placeholder::make('sources_revenus')
                                        ->label('Sources de revenus déclarées')
                                        ->content(function ($record) {
                                            $sources = [];
                                            $payload = $record->payload ?? [];
                                            if ($payload['src_salaire'] ?? false) {
                                                $sources[] = 'Salaire';
                                            }
                                            if ($payload['src_pro_liberal'] ?? false) {
                                                $sources[] = 'Profession Libérale';
                                            }
                                            if ($payload['src_foncier'] ?? false) {
                                                $sources[] = 'Revenus Fonciers';
                                            }
                                            if ($payload['src_dividendes'] ?? false) {
                                                $sources[] = 'Dividendes';
                                            }
                                            if ($payload['src_heritage'] ?? false) {
                                                $sources[] = 'Héritage';
                                            }
                                            if (! empty($payload['src_autre'])) {
                                                $sources[] = 'Autre: '.$payload['src_autre'];
                                            }

                                            return count($sources) > 0 ? implode(', ', $sources) : 'Aucune source';
                                        }),
                                ]),
                                Section::make('Informations bancaires déclarées')
                                    ->collapsible()
                                    ->columns(3)
                                    ->schema([
                                        Placeholder::make('payload.banque')->label('Nom de la banque')->content(fn ($state) => $state ?? '-'),
                                        Placeholder::make('payload.num_compte')->label('Numéro de compte / RIB')->content(fn ($state) => $state ?? '-'),
                                        Placeholder::make('payload.pays_compte')->label('Pays du compte bancaire')->content(fn ($state) => $state ?? '-'),
                                    ]),
                                Section::make('Déclarations de conformité (LAB-FT / LCB-FT)')
                                    ->collapsible()
                                    ->schema([
                                        Grid::make(1)->schema([
                                            Placeholder::make('payload.pays_risque')
                                                ->label('1. Avez-vous ou entretenez-vous des relations d\'affaires étroites avec un pays à haut risque (ex: pays sous sanctions / liste grise GAFI) ?')
                                                ->content(fn ($state) => new HtmlString(
                                                    $state === 'Oui'
                                                        ? '<div style="border-left: 3px solid #dc2626; padding-left: 10px; margin-bottom: 8px;"><span style="background-color: #fee2e2; color: #dc2626; padding: 4px 12px; border-radius: 4px; font-weight: bold; font-size: 0.85rem;">⚠️ OUI (Alerte Risque Pays)</span></div>'
                                                        : '<div style="border-left: 3px solid #10b981; padding-left: 10px; margin-bottom: 8px;"><span style="background-color: #f1f5f9; color: #475569; padding: 4px 12px; border-radius: 4px; font-weight: 500; font-size: 0.85rem;">Non</span></div>'
                                                )),
                                            Placeholder::make('payload.secteur_sensible')
                                                ->label('2. Exercez-vous dans un secteur d\'activité jugé sensible (ex: armement, minier, jeux de hasard, trading de matières premières) ?')
                                                ->content(fn ($state) => new HtmlString(
                                                    $state === 'Oui'
                                                        ? '<div style="border-left: 3px solid #dc2626; padding-left: 10px; margin-bottom: 8px;"><span style="background-color: #fee2e2; color: #dc2626; padding: 4px 12px; border-radius: 4px; font-weight: bold; font-size: 0.85rem;">⚠️ OUI (Alerte Secteur Sensible)</span></div>'
                                                        : '<div style="border-left: 3px solid #10b981; padding-left: 10px; margin-bottom: 8px;"><span style="background-color: #f1f5f9; color: #475569; padding: 4px 12px; border-radius: 4px; font-weight: 500; font-size: 0.85rem;">Non</span></div>'
                                                )),
                                            Placeholder::make('payload.ppe')
                                                ->label('3. Êtes-vous (ou un membre de votre famille proche) une Personne Politiquement Exposée (PPE) exerçant une fonction publique/politique de premier plan ?')
                                                ->content(fn ($state) => new HtmlString(
                                                    $state === 'Oui'
                                                        ? '<div style="border-left: 3px solid #dc2626; padding-left: 10px; margin-bottom: 8px;"><span style="background-color: #fee2e2; color: #dc2626; padding: 4px 12px; border-radius: 4px; font-weight: bold; font-size: 0.85rem;">⚠️ OUI (Alerte Statut PPE)</span></div>'
                                                        : '<div style="border-left: 3px solid #10b981; padding-left: 10px; margin-bottom: 8px;"><span style="background-color: #f1f5f9; color: #475569; padding: 4px 12px; border-radius: 4px; font-weight: 500; font-size: 0.85rem;">Non</span></div>'
                                                )),
                                            Placeholder::make('payload.ppe_detail')
                                                ->label('Détail des fonctions publiques exercées (si PPE) :')
                                                ->content(fn ($state) => $state ?? 'Aucun détail fourni.'),
                                            Placeholder::make('payload.condamnation')
                                                ->label('4. Avez-vous fait l\'objet d\'une condamnation pénale / administrative par le passé relative à des délits financiers ?')
                                                ->content(fn ($state) => new HtmlString(
                                                    $state === 'Oui'
                                                        ? '<div style="border-left: 3px solid #dc2626; padding-left: 10px;"><span style="background-color: #fee2e2; color: #dc2626; padding: 4px 12px; border-radius: 4px; font-weight: bold; font-size: 0.85rem;">⚠️ OUI (Alerte Condamnation)</span></div>'
                                                        : '<div style="border-left: 3px solid #10b981; padding-left: 10px;"><span style="background-color: #f1f5f9; color: #475569; padding: 4px 12px; border-radius: 4px; font-weight: 500; font-size: 0.85rem;">Non</span></div>'
                                                )),
                                        ]),
                                    ]),
                            ]),

                        Tab::make('Signatures & Téléchargements')
                            ->schema([
                                Grid::make(2)->schema([
                                    Section::make('Signature du client')
                                        ->columnSpan(1)
                                        ->schema([
                                            Placeholder::make('signature_preview')
                                                ->label('Aperçu de la signature')
                                                ->content(function ($record) {
                                                    $disk = Storage::disk('kyc_private');
                                                    if ($record->signature_path && $disk->exists($record->signature_path)) {
                                                        $imgData = $disk->get($record->signature_path);
                                                        $mime = 'image/png';
                                                        if (str_ends_with($record->signature_path, '.jpg') || str_ends_with($record->signature_path, '.jpeg')) {
                                                            $mime = 'image/jpeg';
                                                        }
                                                        $base64 = 'data:'.$mime.';base64,'.base64_encode($imgData);

                                                        return new HtmlString("<img src='{$base64}' style='max-height: 100px; border: 1px solid #ccc; padding: 5px; background: white;' />");
                                                    }

                                                    return 'Aucune signature enregistrée.';
                                                }),
                                        ]),

                                    Section::make('Téléchargement des documents (PDF)')
                                        ->columnSpan(1)
                                        ->schema([
                                            Actions::make([
                                                FormAction::make('download_zip')
                                                    ->label('Télécharger le dossier complet (ZIP)')
                                                    ->icon('heroicon-o-folder-arrow-down')
                                                    ->color('primary')
                                                    ->url(fn (OnboardingSession $record) => route('admin.zip.download', ['session' => $record->id]))
                                                    ->openUrlInNewTab(),
                                                FormAction::make('download_kyc')
                                                    ->label('Télécharger la Fiche KYC')
                                                    ->icon('heroicon-o-document-arrow-down')
                                                    ->color('success')
                                                    ->url(fn (OnboardingSession $record) => route('admin.pdf.download', ['session' => $record->id, 'type' => 'kyc']))
                                                    ->openUrlInNewTab(),
                                                FormAction::make('download_risk')
                                                    ->label('Télécharger le Profil Investisseur')
                                                    ->icon('heroicon-o-document-arrow-down')
                                                    ->color('info')
                                                    ->url(fn (OnboardingSession $record) => route('admin.pdf.download', ['session' => $record->id, 'type' => 'risk']))
                                                    ->openUrlInNewTab(),
                                                FormAction::make('download_labft')
                                                    ->label('Télécharger le Questionnaire LAB-FT')
                                                    ->icon('heroicon-o-document-arrow-down')
                                                    ->color('warning')
                                                    ->url(fn (OnboardingSession $record) => route('admin.pdf.download', ['session' => $record->id, 'type' => 'labft']))
                                                    ->openUrlInNewTab(),
                                            ]),
                                        ]),
                                ]),
                            ]),
                        Tab::make('Pièces Justificatives (Admin)')
                            ->schema([
                                Grid::make(2)->schema([
                                    Forms\Components\FileUpload::make('doc_piece_identite')
                                        ->label('Pièce d\'identité (CNI / Passeport)')
                                        ->disk('kyc_private')
                                        ->visibility('private')
                                        ->openable(false)
                                        ->downloadable(false)
                                        ->previewable(false)
                                        ->directory('secure_onboardings/documents')
                                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                        ->maxSize(10240)
                                        ->dehydrated(fn ($state) => filled($state))
                                        ->helperText(fn ($record) => $record && $record->doc_piece_identite
                                            ? new HtmlString('<a href="'.route('admin.document.download', ['session' => $record->id, 'type' => 'piece_identite']).'" target="_blank" style="color: #009a4d; font-weight: bold; text-decoration: underline;">⬇️ Télécharger le document actuel</a>')
                                            : 'Aucun document téléversé.'),

                                    Forms\Components\FileUpload::make('doc_justificatif_domicile')
                                        ->label('Justificatif de domicile (< 3 mois)')
                                        ->disk('kyc_private')
                                        ->visibility('private')
                                        ->openable(false)
                                        ->downloadable(false)
                                        ->previewable(false)
                                        ->directory('secure_onboardings/documents')
                                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                        ->maxSize(10240)
                                        ->dehydrated(fn ($state) => filled($state))
                                        ->helperText(fn ($record) => $record && $record->doc_justificatif_domicile
                                            ? new HtmlString('<a href="'.route('admin.document.download', ['session' => $record->id, 'type' => 'justificatif_domicile']).'" target="_blank" style="color: #009a4d; font-weight: bold; text-decoration: underline;">⬇️ Télécharger le document actuel</a>')
                                            : 'Aucun document téléversé.'),

                                    Forms\Components\FileUpload::make('doc_photo')
                                        ->label('Photo d\'identité récente')
                                        ->disk('kyc_private')
                                        ->visibility('private')
                                        ->openable(false)
                                        ->downloadable(false)
                                        ->previewable(false)
                                        ->directory('secure_onboardings/documents')
                                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                        ->maxSize(10240)
                                        ->dehydrated(fn ($state) => filled($state))
                                        ->helperText(fn ($record) => $record && $record->doc_photo
                                            ? new HtmlString('<a href="'.route('admin.document.download', ['session' => $record->id, 'type' => 'photo']).'" target="_blank" style="color: #009a4d; font-weight: bold; text-decoration: underline;">⬇️ Télécharger le document actuel</a>')
                                            : 'Aucun document téléversé.'),

                                    Forms\Components\FileUpload::make('doc_origine_fonds')
                                        ->label('Justificatif d\'origine des fonds')
                                        ->disk('kyc_private')
                                        ->visibility('private')
                                        ->openable(false)
                                        ->downloadable(false)
                                        ->previewable(false)
                                        ->directory('secure_onboardings/documents')
                                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                        ->maxSize(10240)
                                        ->dehydrated(fn ($state) => filled($state))
                                        ->helperText(fn ($record) => $record && $record->doc_origine_fonds
                                            ? new HtmlString('<a href="'.route('admin.document.download', ['session' => $record->id, 'type' => 'origine_fonds']).'" target="_blank" style="color: #009a4d; font-weight: bold; text-decoration: underline;">⬇️ Télécharger le document actuel</a>')
                                            : 'Aucun document téléversé.'),
                                ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.first_name')
                    ->label('Prénom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.last_name')
                    ->label('Nom')
                    ->searchable()
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
                    ->sortable(),
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
                    ->sortable(),
            ])
            ->filters([
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
                    Tables\Actions\EditAction::make()->label('Uploader Documents'),
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
                ]),
            ])
            ->bulkActions([
                // Pas d'actions de masse en lecture seule en dehors de l'export éventuel
            ])
            ->emptyStateActions([
                // Pas de création manuelle
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
            'view' => Pages\ViewOnboardingSession::route('/{record}'),
            'edit' => Pages\EditOnboardingSession::route('/{record}/edit'),
        ];
    }

    // Le téléchargement des PDFs est géré par App\Http\Controllers\Admin\PdfDownloadController
    // via la route nommée 'admin.pdf.download' pour éviter les problèmes de réponse HTTP dans Livewire.
}
