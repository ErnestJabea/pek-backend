<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        static::saved(function ($productVl) {
            $productVl->product->updateLatestVl();
        });

        static::deleted(function ($productVl) {
            $productVl->product->updateLatestVl();
        });
    }
}
