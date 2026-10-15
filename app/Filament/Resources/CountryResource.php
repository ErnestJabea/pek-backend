<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CountryResource\Pages;
use App\Models\Country;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CountryResource extends Resource
{
    protected static ?string $model = Country::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationGroup = 'Configuration Système';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Pays';

    protected static ?string $modelLabel = 'Pays';

    protected static ?string $pluralModelLabel = 'Pays';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin')
            || (auth()->user()?->can('view_any_country') ?? false);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('super_admin')
            || (auth()->user()?->can('create_country') ?? false);
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasRole('super_admin')
            || (auth()->user()?->can('update_country') ?? false);
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasRole('super_admin')
            || (auth()->user()?->can('delete_country') ?? false);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nom du pays (Français)')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('english_name')
                    ->label('Nom (Anglais)')
                    ->maxLength(255),

                Forms\Components\TextInput::make('code')
                    ->label('Code ISO (2 lettres)')
                    ->required()
                    ->maxLength(5)
                    ->placeholder('Ex: CM, FR, US')
                    ->unique(ignoreRecord: true),

                Forms\Components\TextInput::make('dial_code')
                    ->label('Indicatif téléphonique')
                    ->placeholder('Ex: +237')
                    ->maxLength(10),

                Forms\Components\TextInput::make('currency')
                    ->label('Devise')
                    ->placeholder('Ex: XAF, EUR, USD')
                    ->maxLength(10),

                Forms\Components\TextInput::make('sort_order')
                    ->label('Ordre d\'affichage')
                    ->numeric()
                    ->default(100),

                Forms\Components\Toggle::make('is_active')
                    ->label('Actif (Disponible pour les inscriptions et profils)')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->fontFamily('mono')
                    ->weight('black')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('dial_code')
                    ->label('Indicatif')
                    ->searchable(),

                Tables\Columns\TextColumn::make('currency')
                    ->label('Devise')
                    ->badge(),

                Tables\Columns\TextColumn::make('cities_count')
                    ->label('Villes enregistrées')
                    ->counts('cities')
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Actif'),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Ordre')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Statut actif'),
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
            'index' => Pages\ListCountries::route('/'),
            'create' => Pages\CreateCountry::route('/create'),
            'edit' => Pages\EditCountry::route('/{record}/edit'),
        ];
    }
}
