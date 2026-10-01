<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppContentResource\Pages;
use App\Models\AppContent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AppContentResource extends Resource
{
    protected static ?string $model = AppContent::class;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationGroup = 'Configuration';

    protected static ?string $navigationLabel = 'Contenus PWA';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return 'Contenu PWA';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Contenus PWA';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Paramètres du Contenu')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Select::make('section')
                            ->label('Emplacement / Écran')
                            ->options([
                                'subscription' => 'Souscription (Bannières & Plafonds)',
                                'welcome_slides' => "Bienvenue / Slides d'introduction",
                                'home' => 'Accueil / Tableau de bord',
                                'onboarding' => 'Onboarding / KYC',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('key')
                            ->label('Identifiant Unique (Clé)')
                            ->helperText('Ex: first_sub_banner, slide_1')
                            ->required()
                            ->unique(ignoreRecord: true),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Actif sur le PWA')
                            ->default(true)
                            ->inline(false),
                    ]),

                Forms\Components\Tabs::make('Contenus Multilingues')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Français (FR)')
                            ->icon('heroicon-o-language')
                            ->schema([
                                Forms\Components\TextInput::make('title_fr')
                                    ->label('Titre (FR)')
                                    ->maxLength(255),

                                Forms\Components\Textarea::make('body_fr')
                                    ->label('Texte / Description (FR)')
                                    ->rows(4),
                            ]),

                        Forms\Components\Tabs\Tab::make('Anglais (EN)')
                            ->icon('heroicon-o-globe-alt')
                            ->schema([
                                Forms\Components\TextInput::make('title_en')
                                    ->label('Titre (EN)')
                                    ->maxLength(255),

                                Forms\Components\Textarea::make('body_en')
                                    ->label('Texte / Description (EN)')
                                    ->rows(4),
                            ]),
                    ]),

                Forms\Components\Section::make('Média & Paramètres avancés')
                    ->columns(2)
                    ->schema([
                        Forms\Components\FileUpload::make('image_path')
                            ->label('Image / Illustration associée')
                            ->image()
                            ->directory('app-contents')
                            ->visibility('public'),

                        Forms\Components\KeyValue::make('metadata')
                            ->label('Données additionnelles (Plafonds, etc.)')
                            ->keyLabel('Paramètre')
                            ->valueLabel('Valeur')
                            ->helperText('Exemple: max_amount => 250000'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('section')
                    ->label('Section')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('key')
                    ->label('Clé')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('title_fr')
                    ->label('Titre (FR)')
                    ->limit(40)
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Dernière modif.')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('section')
                    ->options([
                        'subscription' => 'Souscription',
                        'welcome_slides' => 'Slides de bienvenue',
                        'home' => 'Accueil',
                        'onboarding' => 'Onboarding',
                    ]),
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

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppContents::route('/'),
            'create' => Pages\CreateAppContent::route('/create'),
            'edit' => Pages\EditAppContent::route('/{record}/edit'),
        ];
    }
}