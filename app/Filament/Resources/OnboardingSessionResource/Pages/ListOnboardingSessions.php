<?php

namespace App\Filament\Resources\OnboardingSessionResource\Pages;

use App\Filament\Resources\OnboardingSessionResource;
use App\Models\OnboardingSession;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListOnboardingSessions extends ListRecords
{
    protected static string $resource = OnboardingSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('À traiter / En cours')
                ->icon('heroicon-o-clock')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['completed', 'in_progress']))
                ->badge(static::getResource()::getEloquentQuery()->whereIn('status', ['completed', 'in_progress'])->count())
                ->badgeColor('warning'),
            'validated' => Tab::make('Dossiers Validés')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'validated'))
                ->badge(static::getResource()::getEloquentQuery()->where('status', 'validated')->count())
                ->badgeColor('success'),
            'rejected' => Tab::make('Rejetés')
                ->icon('heroicon-o-x-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'rejected')),
            'all' => Tab::make('Tous les dossiers'),
        ];
    }
}
