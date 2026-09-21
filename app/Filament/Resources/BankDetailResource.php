<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BankDetailResource\Pages;
use App\Models\BankDetail;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BankDetailResource extends Resource
{
    protected static ?string $model = BankDetail::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationLabel = 'Coordonnées Bancaires';

    protected static ?string $modelLabel = 'Compte Bancaire';

    protected static ?string $pluralModelLabel = 'Coordonnées Bancaires';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('bank_name')
                    ->label('Nom de la banque')
                    ->placeholder('Ex: AFRILAND FIRST BANK, UBA...')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('beneficiary')
                    ->label('Bénéficiaire / Titulaire du compte')
                    ->placeholder('Ex: KORI ASSET MANAGEMENT')
                    ->default('KORI ASSET MANAGEMENT')
                    ->maxLength(255),
                Forms\Components\TextInput::make('iban')
                    ->label('IBAN')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('rib')
                    ->label('RIB')
                    ->maxLength(255),
                Forms\Components\TextInput::make('swift')
                    ->label('Code SWIFT / BIC')
                    ->maxLength(255),
                Forms\Components\Toggle::make('is_active')
                    ->label('Compte Actif (Visible pour les souscriptions)')
                    ->required()
                    ->default(true),
                Forms\Components\Textarea::make('bank_instructions')
                    ->label('Instructions de paiement par virement')
                    ->placeholder('Ex: Précisez votre référence de souscription comme motif de virement...')
                    ->helperText('Ces instructions s’afficheront directement dans l’application mobile lorsque cette banque est sélectionnée.')
                    ->rows(4)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('om_instructions')
                    ->label('Procédure Orange Money (optionnel)')
                    ->rows(3)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('momo_instructions')
                    ->label('Procédure MTN MoMo (optionnel)')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('bank_name')
                    ->label('Banque')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('beneficiary')
                    ->label('Bénéficiaire')
                    ->searchable(),
                Tables\Columns\TextColumn::make('iban')
                    ->label('IBAN')
                    ->searchable(),
                Tables\Columns\TextColumn::make('rib')
                    ->label('RIB'),
                Tables\Columns\TextColumn::make('swift')
                    ->label('SWIFT'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date de création')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBankDetails::route('/'),
            'create' => Pages\CreateBankDetail::route('/create'),
            'edit' => Pages\EditBankDetail::route('/{record}/edit'),
        ];
    }
}
