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
        'date_vl' => 'date',
    ];

    public function getVlAttribute($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $str = (string) $value;
        if (str_contains($str, '.')) {
            return rtrim(rtrim($str, '0'), '.');
        }
        return $str;
    }

    public function setVlAttribute($value): void
    {
        $this->attributes['vl'] = is_string($value) ? str_replace(',', '.', trim($value)) : $value;
    }

    protected $appends = [
        'vl_display',
    ];

    public function getVlDisplayAttribute(): string
    {
        return number_format((float) $this->vl, 2, ',', ' ');
    }

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
