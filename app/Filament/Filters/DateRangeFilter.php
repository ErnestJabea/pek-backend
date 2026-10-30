<?php

namespace App\Filament\Filters;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class DateRangeFilter
{
    /**
     * Crée un filtre de plage de dates (Date de début et Date de fin) réutilisable pour les tableaux Filament.
     *
     * @param string $column Colonne en base de données (ex: 'created_at', 'date_vl', 'value_date')
     * @param string $label Intitulé du filtre (ex: 'Période', 'Période de souscription')
     * @return Filter
     */
    public static function make(string $column = 'created_at', string $label = 'Période'): Filter
    {
        return Filter::make(str_replace('.', '_', $column) . '_date_range')
            ->label($label)
            ->columns(2)
            ->columnSpan(['sm' => 1, 'md' => 2])
            ->form([
                DatePicker::make('from')
                    ->label('Date de début')
                    ->placeholder('JJ/MM/AAAA')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->closeOnDateSelection(),
                DatePicker::make('until')
                    ->label('Date de fin')
                    ->placeholder('JJ/MM/AAAA')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->closeOnDateSelection(),
            ])
            ->indicateUsing(function (array $data) use ($label): array {
                $indicators = [];
                if (! empty($data['from'])) {
                    $indicators['from'] = $label . ' dès le ' . Carbon::parse($data['from'])->format('d/m/Y');
                }
                if (! empty($data['until'])) {
                    $indicators['until'] = $label . ' jusqu\'au ' . Carbon::parse($data['until'])->format('d/m/Y');
                }

                return $indicators;
            })
            ->query(function (Builder $query, array $data) use ($column): Builder {
                $targetColumn = str_contains($column, '.') ? $column : $query->getModel()->qualifyColumn($column);

                return $query
                    ->when(
                        $data['from'] ?? null,
                        fn (Builder $q, $date): Builder => $q->whereDate($targetColumn, '>=', $date)
                    )
                    ->when(
                        $data['until'] ?? null,
                        fn (Builder $q, $date): Builder => $q->whereDate($targetColumn, '<=', $date)
                    );
            });
    }
}
