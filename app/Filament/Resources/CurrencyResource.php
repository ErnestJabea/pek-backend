<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CurrencyResource\Pages;
use App\Filament\Resources\CurrencyResource\RelationManagers;
use App\Models\Currency;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    public static function getModelLabel(): string
    {
        return 'Devise';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Devises';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->placeholder('ex: USD, EUR, XAF'),
                Forms\Components\TextInput::make('name')
                    ->label('Nom de la devise')
                    ->required(),
                Forms\Components\TextInput::make('symbol')
                    ->label('Symbole')
                    ->required(),
                Forms\Components\TextInput::make('exchange_rate')
                    ->label('Taux de change (par rapport au XAF)')
                    ->required()
                    ->numeric()
                    ->step('0.000001'),
                Forms\Components\Toggle::make('is_base')
                    ->label('Devise de base ?')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable(),
                Tables\Columns\TextColumn::make('symbol')
                    ->label('Symbole'),
                Tables\Columns\TextColumn::make('exchange_rate')
                    ->label('Taux')
                    ->numeric(6)
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_base')
                    ->label('Base')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Dernière mise à jour')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCurrencies::route('/'),
        ];
    }    
}
