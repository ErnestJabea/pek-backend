<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $acceptHeader = $request->header('Accept-Language', $request->query('lang', 'fr'));
        $lang = strtolower(substr($acceptHeader, 0, 2));
        $isEn = $lang === 'en';
        $cacheKey = 'products_list_' . ($isEn ? 'en' : 'fr');

        return \Cache::remember($cacheKey, 300, function () use ($isEn) {
            return Product::where('is_active', true)->with(['vls' => function ($query) {
                $query->orderBy('date_vl', 'desc');
            }])->get()->map(function ($product) use ($isEn) {
                $latestVl = $product->vls->first();
                $prevVl = $product->vls->skip(1)->first();

                $trend = 0;
                if ($latestVl && $prevVl && $prevVl->vl > 0) {
                    $trend = (($latestVl->vl - $prevVl->vl) / $prevVl->vl) * 100;
                }

                // Récupérer les 16 dernières VL chronologiquement (la plus ancienne en premier pour le graphe)
                $history = $product->vls->take(16)->reverse()->values()->map(function ($vl) {
                    return [
                        'vl' => (float) $vl->vl,
                        'date' => $vl->date_vl->format('d/m'),
                        'full_date' => $vl->date_vl->format('d/m/Y'),
                    ];
                });

                $riskText = match ($product->risk_level) {
                    'faible' => $isEn ? 'Low' : 'Faible',
                    'modere' => $isEn ? 'Moderate' : 'Modéré',
                    'eleve' => $isEn ? 'High' : 'Élevé',
                    default => $isEn ? 'Not specified' : 'Non renseigné',
                };

                $depliantUrl = $isEn
                    ? ($product->depliant_en_url ?: $product->depliant_url)
                    : $product->depliant_url;

                $documentInfoUrl = $isEn
                    ? ($product->document_information_en_url ?: $product->document_information_url)
                    : $product->document_information_url;

                return [
                    'id' => $product->id,
                    'name' => $isEn ? ($product->libelle_en ?: $product->libelle) : $product->libelle,
                    'libelle' => $product->libelle,
                    'libelle_en' => $product->libelle_en,
                    'description' => $isEn ? ($product->description_en ?: $product->description) : $product->description,
                    'description_en' => $product->description_en,
                    'vl' => $latestVl ? (float) $latestVl->vl : (float) $product->vl,
                    'min' => (float) $product->seuil_minimum,
                    'trend' => ($trend >= 0 ? '+' : '').number_format($trend, 2).'%',
                    'risk' => $riskText,
                    'depliant_url' => $depliantUrl,
                    'depliant_en_url' => $product->depliant_en_url,
                    'document_information_url' => $documentInfoUrl,
                    'document_information_en_url' => $product->document_information_en_url,
                    'history' => $history,
                ];
            });
        });
    }

    public function show(Product $product)
    {
        return response()->json($product);
    }
}
