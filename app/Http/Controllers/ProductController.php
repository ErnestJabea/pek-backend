<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        // Cache des produits pendant 5 minutes pour une vitesse éclair
        return \Cache::remember('products_list', 300, function() {
            return Product::with(['vls' => function($query) {
                $query->orderBy('date_vl', 'desc');
            }])->get()->map(function($product) {
                $latestVl = $product->vls->first();
                $prevVl = $product->vls->skip(1)->first();
                
                $trend = 0;
                if ($latestVl && $prevVl && $prevVl->vl > 0) {
                    $trend = (($latestVl->vl - $prevVl->vl) / $prevVl->vl) * 100;
                }

                // Récupérer les 12 dernières VL chronologiquement (la plus ancienne en premier pour le graphe)
                $history = $product->vls->take(12)->reverse()->values()->map(function($vl) {
                    return [
                        'vl' => (float)$vl->vl,
                        'date' => $vl->date_vl->format('d/m'),
                    ];
                });

                return [
                    'id' => $product->id,
                    'name' => $product->libelle,
                    'description' => $product->description,
                    'vl' => $latestVl ? (float)$latestVl->vl : (float)$product->vl,
                    'min' => (float)$product->seuil_minimum,
                    'trend' => ($trend >= 0 ? '+' : '') . number_format($trend, 2) . '%',
                    'risk' => 'Faible',
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
