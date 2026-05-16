<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class VlsRelationManager extends RelationManager
{
    protected static string $relationship = 'vls';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('messages.vl_history');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('vl')
                    ->required()
                    ->numeric()
                    ->label(__('messages.vl'))
                    ->prefix('XAF'),
                Forms\Components\DatePicker::make('date_vl')
                    ->required()
                    ->label(__('messages.date'))
                    ->default(now()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('vl')
            ->defaultSort('date_vl', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('date_vl')
                    ->date()
                    ->label(__('messages.date'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('vl')
                    ->money('XAF')
                    ->label(__('messages.vl')),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
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
}
