<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentMethodResource\Pages;
use App\Models\PaymentMethod;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentMethodResource extends Resource
{
    protected static ?string $model = PaymentMethod::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Produits & Trésorerie';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Moyens de Paiement';

    protected static ?string $modelLabel = 'Moyen de paiement';

    protected static ?string $pluralModelLabel = 'Moyens de paiement';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin')
            || (auth()->user()?->can('view_any_payment_method') ?? false)
            || (auth()->user()?->can('view_any_payment::method') ?? false);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Configuration du Moyen de Paiement')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nom (FR)')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('name_en')
                            ->label('Nom (EN)')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('code')
                            ->label('Code Technique Unique')
                            ->helperText('Ex: card, orange_money, mtn_momo, bank_transfer')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->disabled(fn (?PaymentMethod $record) => $record !== null),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Ordre d\'affichage')
                            ->numeric()
                            ->default(0),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Moyen de paiement actif sur le PWA')
                            ->helperText('Désactivez pour masquer ou bloquer ce moyen de paiement sur l\'application mobile')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Descriptions & Instructions')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label('Description / Notice (FR)')
                            ->rows(3),

                        Forms\Components\Textarea::make('description_en')
                            ->label('Description / Notice (EN)')
                            ->rows(3),
                    ]),

                Forms\Components\Section::make('Limites de Montant (Optionnel)')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('min_amount')
                            ->label('Montant minimum (XAF)')
                            ->numeric(),

                        Forms\Components\TextInput::make('max_amount')
                            ->label('Montant maximum (XAF)')
                            ->numeric(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Actif sur l\'App')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'card' => 'primary',
                        'orange_money' => 'warning',
                        'mtn_momo' => 'warning',
                        'bank_transfer' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Ordre')
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Dernière modif.')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListPaymentMethods::route('/'),
            'create' => Pages\CreatePaymentMethod::route('/create'),
            'edit' => Pages\EditPaymentMethod::route('/{record}/edit'),
        ];
    }
}
