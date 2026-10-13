<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeoController extends Controller
{
    /**
     * Liste des pays actifs triés par ordre de priorité puis nom
     */
    public function countries(): JsonResponse
    {
        $countries = Country::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'english_name', 'code', 'dial_code', 'currency']);

        return response()->json($countries);
    }

    /**
     * Liste des villes actives d'un pays
     */
    public function cities(string $country): JsonResponse
    {
        $countryModel = Country::query()
            ->where('is_active', true)
            ->where(function ($q) use ($country) {
                $q->where('code', strtoupper($country))
                  ->orWhere('name', $country)
                  ->orWhere('id', is_numeric($country) ? (int)$country : 0);
            })
            ->first();

        if (!$countryModel) {
            return response()->json([]);
        }

        $cities = City::query()
            ->where('country_id', $countryModel->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name');

        return response()->json($cities);
    }
}
