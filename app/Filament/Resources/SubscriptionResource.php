<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionResource\Pages;
use App\Filament\Resources\SubscriptionResource\RelationManagers;
use App\Models\Subscription;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    public static function getModelLabel(): string
    {
        return __('messages.subscription');
    }

    public static function getPluralModelLabel(): string
    {
        return __('messages.subscriptions');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label(__('messages.client'))
                    ->relationship('user', 'last_name')
                    ->getOptionLabelFromRecordUsing(fn (\App\Models\User $record) => "{$record->first_name} {$record->last_name} - {$record->email}")
                    ->searchable(['first_name', 'last_name', 'email'])
                    ->required(),
                Forms\Components\Select::make('product_id')
                    ->label(__('messages.product'))
                    ->relationship('product', 'libelle')
                    ->required(),
                Forms\Components\TextInput::make('nb_parts')
                    ->label('Parts')
                    ->required()
                    ->numeric(),
                 Forms\Components\TextInput::make('prix_unitaire')
                    ->label('VL souscription')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('montant_total')
                    ->label(__('messages.amount'))
                    ->required()
                    ->numeric(),
                Forms\Components\Select::make('moyen_paiement')
                    ->label('Moyen de Paiement')
                    ->options([
                        'stripe' => 'Stripe',
                        'coolpay' => 'CoolPay',
                        'orange_money' => 'Orange Money',
                        'mtn_momo' => 'MTN MoMo',
                        'virement' => 'Virement Bancaire',
                    ])
                    ->required(),
                Forms\Components\Select::make('statut')
                    ->label(__('messages.status'))
                    ->options([
                        'En attente' => 'En attente',
                        'Succès' => 'Succès',
                        'Échec' => 'Échec',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('reference_transaction')
                    ->label('Réf. Transaction'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.last_name')
                    ->label(__('messages.client'))
                    ->formatStateUsing(fn ($state, $record) => "{$record->user->first_name} {$record->user->last_name}")
                    ->searchable(['first_name', 'last_name', 'email'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.libelle')
                    ->label(__('messages.product'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('nb_parts')
                    ->label('Parts')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('prix_unitaire')
                    ->label('VL souscription')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('montant_total')
                    ->label(__('messages.amount'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('moyen_paiement')
                    ->label('Moyen')
                    ->searchable(),
                Tables\Columns\TextColumn::make('statut')
                    ->label(__('messages.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Succès' => 'success',
                        'En attente' => 'warning',
                        'Échec' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('messages.date'))
                    ->dateTime()
                    ->sortable(),
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
            'index' => Pages\ListSubscriptions::route('/'),
            'create' => Pages\CreateSubscription::route('/create'),
            'edit' => Pages\EditSubscription::route('/{record}/edit'),
        ];
    }    
}
