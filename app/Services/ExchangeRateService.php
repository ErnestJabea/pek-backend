<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class ExchangeRateService
{
    protected string $baseUrl = 'https://open.er-api.com/v6/latest/XAF';

    public function getLatestRates(): array
    {
        return Cache::remember('exchange_rates_xaf', 3600, function () {
            try {
                $response = Http::get($this->baseUrl);
                
                if ($response->successful()) {
                    return $response->json()['rates'];
                }
            } catch (\Exception $e) {
                \Log::error("Erreur lors de la récupération des taux de change : " . $e->getMessage());
            }

            return [
                'EUR' => 0.001524, // Fallback approx (1/655.957)
                'USD' => 0.001639, // Fallback approx
            ];
        });
    }

    public function convertFromXaf(float $amount, string $toCurrency): float
    {
        $rates = $this->getLatestRates();
        $rate = $rates[$toCurrency] ?? 0;
        
        return $amount * $rate;
    }

    public function convertToXaf(float $amount, string $fromCurrency): float
    {
        $rates = $this->getLatestRates();
        $rate = $rates[$fromCurrency] ?? 0;
        
        if ($rate == 0) return 0;
        
        return $amount / $rate;
    }
}
