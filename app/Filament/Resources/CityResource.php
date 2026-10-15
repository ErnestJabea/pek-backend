<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CityResource\Pages;
use App\Models\City;
use App\Models\Country;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CityResource extends Resource
{
    protected static ?string $model = City::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Configuration Système';

    protected static ?int $navigationSort = 11;

    protected static ?string $navigationLabel = 'Villes';

    protected static ?string $modelLabel = 'Ville';

    protected static ?string $pluralModelLabel = 'Villes';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin')
            || (auth()->user()?->can('view_any_city') ?? false);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('super_admin')
            || (auth()->user()?->can('create_city') ?? false);
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasRole('super_admin')
            || (auth()->user()?->can('update_city') ?? false);
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasRole('super_admin')
            || (auth()->user()?->can('delete_city') ?? false);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('country_id')
                    ->label('Pays')
                    ->relationship('country', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\TextInput::make('name')
                    ->label('Nom de la ville')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('sort_order')
                    ->label('Ordre')
                    ->numeric()
                    ->default(0),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Ville')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('country.name')
                    ->label('Pays')
                    ->searchable()
                    ->sortable()
                    ->badge(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Ordre')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('country_id')
                    ->label('Filtrer par pays')
                    ->relationship('country', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Statut active'),
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
            'index' => Pages\ListCities::route('/'),
            'create' => Pages\CreateCity::route('/create'),
            'edit' => Pages\EditCity::route('/{record}/edit'),
        ];
    }
}
