<?php

namespace App\Filament\Filters;

use App\Services\BackofficeDashboard;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class DashboardFilter
{
    public static function make(string $resource): SelectFilter
    {
        return SelectFilter::make('dashboard')
            ->label('Indicateur du tableau de bord')
            ->options(fn () => app(BackofficeDashboard::class)->filterOptions($resource, auth()->user()))
            ->query(function (Builder $query, array $data) use ($resource): Builder {
                if (empty($data['value'])) {
                    return $query;
                }
                $dashboard = app(BackofficeDashboard::class);
                abort_unless(isset($dashboard->filterOptions($resource, auth()->user())[$data['value']]), 403);
                $subset = $dashboard->query($data['value'], auth()->user());

                return $query->whereIn($query->getModel()->getQualifiedKeyName(), $subset->select($subset->getModel()->getQualifiedKeyName()));
            });
    }
}
