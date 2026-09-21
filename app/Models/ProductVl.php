<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ProductVl extends Model
{
    protected $fillable = [
        'product_id',
        'vl',
        'date_vl',
    ];

    protected $casts = [
        'vl' => 'decimal:4',
        'date_vl' => 'date',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    protected static function booted()
    {
        static::deleting(function ($productVl) {
            if ($productVl->product->vls()->count() <= 1) {
                throw ValidationException::withMessages([
                    'vl' => 'La dernière valeur liquidative d’un produit ne peut pas être supprimée.',
                ]);
            }
        });

        static::saved(function ($productVl) {
            $productVl->product->updateLatestVl();
            \Cache::forget('products_list');
            \Cache::forget('products_list_fr');
            \Cache::forget('products_list_en');
        });

        static::deleted(function ($productVl) {
            $productVl->product->updateLatestVl();
            \Cache::forget('products_list');
            \Cache::forget('products_list_fr');
            \Cache::forget('products_list_en');
        });
    }
}
