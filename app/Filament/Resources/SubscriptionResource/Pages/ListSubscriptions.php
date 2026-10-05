<?php

namespace App\Filament\Resources\SubscriptionResource\Pages;

use App\Filament\Resources\SubscriptionResource;
use App\Models\Subscription;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSubscriptions extends ListRecords
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Toutes les souscriptions')
                ->icon('heroicon-o-rectangle-stack')
                ->badge(static::getResource()::getEloquentQuery()->count()),

            'pending' => Tab::make('En attente')
                ->icon('heroicon-o-clock')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('statut', 'En attente'))
                ->badge(static::getResource()::getEloquentQuery()->where('statut', 'En attente')->count())
                ->badgeColor('warning'),

            'success' => Tab::make('Validées / Succès')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('statut', 'Succès'))
                ->badge(static::getResource()::getEloquentQuery()->where('statut', 'Succès')->count())
                ->badgeColor('success'),

            'compliance_pending' => Tab::make('À valider Conformité')
                ->icon('heroicon-o-shield-exclamation')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('statut', 'Succès')->whereNull('compliance_reviewed_at'))
                ->badge(static::getResource()::getEloquentQuery()->where('statut', 'Succès')->whereNull('compliance_reviewed_at')->count())
                ->badgeColor('info'),

            'accounting_pending' => Tab::make('À valider Comptabilité')
                ->icon('heroicon-o-banknotes')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('statut', 'Succès')->whereNull('accounting_reviewed_at'))
                ->badge(static::getResource()::getEloquentQuery()->where('statut', 'Succès')->whereNull('accounting_reviewed_at')->count())
                ->badgeColor('danger'),

            'manager_pending' => Tab::make('À valider Gérant')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('statut', 'Succès')->whereNull('manager_reviewed_at'))
                ->badge(static::getResource()::getEloquentQuery()->where('statut', 'Succès')->whereNull('manager_reviewed_at')->count())
                ->badgeColor('warning'),

            'historical' => Tab::make('Historiques / Antériorités')
                ->icon('heroicon-o-archive-box')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_historical', true))
                ->badge(static::getResource()::getEloquentQuery()->where('is_historical', true)->count())
                ->badgeColor('info'),

            'failed' => Tab::make('Échecs / À vérifier')
                ->icon('heroicon-o-x-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('statut', ['Échec', 'À vérifier']))
                ->badge(static::getResource()::getEloquentQuery()->whereIn('statut', ['Échec', 'À vérifier'])->count())
                ->badgeColor('gray'),
        ];
    }
}
