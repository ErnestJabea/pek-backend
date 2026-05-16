<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CurrencyStats extends BaseWidget
{
    protected function getStats(): array
    {
        $service = new \App\Services\ExchangeRateService();
        $rates = $service->getLatestRates();
        
        $stats = [];
        
        $stats[] = Stat::make(
            "Devise de Base",
            "XAF"
        )
        ->description("Franc CFA")
        ->color('gray');

        // On affiche les taux inverses (1 Devise = X XAF) car c'est plus parlant
        $eurRate = isset($rates['EUR']) ? (1 / $rates['EUR']) : 0;
        $usdRate = isset($rates['USD']) ? (1 / $rates['USD']) : 0;

        $stats[] = Stat::make(
            "Taux EUR (Live)",
            number_format($eurRate, 2) . " XAF"
        )
        ->description("1 Euro")
        ->descriptionIcon('heroicon-m-globe-europe-africa')
        ->color('success');

        $stats[] = Stat::make(
            "Taux USD (Live)",
            number_format($usdRate, 2) . " XAF"
        )
        ->description("1 Dollar US")
        ->descriptionIcon('heroicon-m-globe-americas')
        ->color('primary');

        return $stats;
    }
}
