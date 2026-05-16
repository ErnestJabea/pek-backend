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
                $query->orderBy('date_vl', 'desc')->limit(2);
            }])->get()->map(function($product) {
                $latestVl = $product->vls->first();
                $prevVl = $product->vls->skip(1)->first();
                
                $trend = 0;
                if ($latestVl && $prevVl && $prevVl->vl > 0) {
                    $trend = (($latestVl->vl - $prevVl->vl) / $prevVl->vl) * 100;
                }

                return [
                    'id' => $product->id,
                    'name' => $product->libelle,
                    'description' => $product->description,
                    'vl' => $latestVl ? $latestVl->vl : $product->vl,
                    'min' => $product->seuil_minimum,
                    'trend' => ($trend >= 0 ? '+' : '') . number_format($trend, 2) . '%',
                    'risk' => 'Faible',
                ];
            });
        });
    }

    public function show(Product $product)
    {
        return response()->json($product);
    }
}
