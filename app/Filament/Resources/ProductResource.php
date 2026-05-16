<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    public static function getModelLabel(): string
    {
        return __('messages.product');
    }

    public static function getPluralModelLabel(): string
    {
        return __('messages.products');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('libelle')
                    ->label(__('messages.product'))
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->label(__('messages.description'))
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('vl')
                    ->required()
                    ->numeric()
                    ->label(__('messages.vl_initial'))
                    ->disabledOn('edit')
                    ->helperText(__('messages.manage_vl_history')),
                Forms\Components\TextInput::make('seuil_minimum')
                    ->label(__('messages.seuil_minimum'))
                    ->required()
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('libelle')
                    ->label(__('messages.product'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('vl')
                    ->numeric()
                    ->label(__('messages.vl'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('seuil_minimum')
                    ->label(__('messages.seuil_minimum'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('messages.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('messages.updated_at'))
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
            RelationManagers\VlsRelationManager::class,
        ];
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }    
}



