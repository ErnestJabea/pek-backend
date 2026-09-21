<?php

namespace App\Filament\Resources\SubscriptionResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PaymentEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'paymentEvents';
    protected static ?string $title = 'Journal du paiement';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view_subscription') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            Tables\Columns\TextColumn::make('created_at')->label('Date')->dateTime(),
            Tables\Columns\TextColumn::make('type')->label('Événement'),
            Tables\Columns\TextColumn::make('actor_id')->label('ID de l’auteur')->placeholder('Système'),
            Tables\Columns\TextColumn::make('details')->label('Détails')->getStateUsing(fn ($record) => json_encode($record->details, JSON_UNESCAPED_UNICODE))->wrap(),
        ]);
    }
}
