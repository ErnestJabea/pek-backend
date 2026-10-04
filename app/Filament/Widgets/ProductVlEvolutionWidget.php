<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Models\ProductVl;
use Filament\Widgets\ChartWidget;

class ProductVlEvolutionWidget extends ChartWidget
{
    protected static ?string $heading = '📊 Performance & VL (FCP KORI ACTIONS)';

    protected static ?string $description = 'Historique des dernières Valeurs Liquidatives officielles publiées';

    protected static ?int $sort = 4;

    protected static ?string $maxHeight = '340px';

    protected int|string|array $columnSpan = 1;

    public ?string $filter = '1'; // Product id 1 by default

    public static function canView(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasRole('super_admin') || $user->can('view_any_product') || in_array($user->adminDepartment?->name, ['Gestion des fonds', 'Activité commune'], true);
    }

    protected function getFilters(): ?array
    {
        return Product::where('is_active', true)->pluck('libelle', 'id')->mapWithKeys(fn ($val, $k) => [(string) $k => $val])->all() ?: ['1' => 'FCP KORI ACTIONS'];
    }

    protected function getData(): array
    {
        $productId = (int) ($this->filter ?? 1);
        $vls = ProductVl::where('product_id', $productId)
            ->orderBy('date_vl', 'desc')
            ->take(25)
            ->get()
            ->reverse();

        $labels = [];
        $values = [];

        foreach ($vls as $vl) {
            $labels[] = $vl->date_vl?->format('d/m/Y') ?: (string) $vl->date_vl;
            $values[] = (float) $vl->vl;
        }

        if (empty($values)) {
            $product = Product::find($productId);
            $labels = ['VL nominale'];
            $values = [(float) ($product?->vl ?? 10000.0)];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Valeur Liquidative (FCFA)',
                    'data' => $values,
                    'borderColor' => '#2563eb',
                    'backgroundColor' => 'rgba(37, 99, 235, 0.08)',
                    'fill' => 'start',
                    'tension' => 0.25,
                    'pointBackgroundColor' => '#2563eb',
                    'pointBorderColor' => '#ffffff',
                    'pointHoverRadius' => 6,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
