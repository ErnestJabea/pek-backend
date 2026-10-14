<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class VlsRelationManager extends RelationManager
{
    protected static string $relationship = 'vls';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
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
                    ->step('any')
                    ->label(__('messages.vl'))
                    ->prefix('XAF')
                    ->helperText('Saisissez la valeur liquidative officielle avec toutes ses décimales.'),
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
                    ->label(__('messages.vl'))
                    ->formatStateUsing(function ($state) {
                        if ($state === null || $state === '') return '-';
                        $str = rtrim(rtrim((string) $state, '0'), '.');
                        $parts = explode('.', $str);
                        $intPart = number_format((float) ($parts[0] ?? 0), 0, '', ' ');
                        if (!isset($parts[1]) || $parts[1] === '') {
                            return $intPart . ',00 FCFA';
                        }
                        if (strlen($parts[1]) === 1) {
                            return $intPart . ',' . $parts[1] . '0 FCFA';
                        }
                        return $intPart . ',' . $parts[1] . ' FCFA';
                    })
                    ->description(function ($state) {
                        if ($state === null || $state === '') return null;
                        $str = rtrim(rtrim((string) $state, '0'), '.');
                        $parts = explode('.', $str);
                        if (isset($parts[1]) && strlen($parts[1]) > 2) {
                            return 'Affichage client : ' . number_format((float) $state, 2, ',', ' ') . ' FCFA';
                        }
                        return null;
                    })
                    ->sortable(),
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
