<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'libelle',
        'description',
        'vl',
        'seuil_minimum',
    ];

    protected $casts = [
        'vl' => 'decimal:4',
        'seuil_minimum' => 'decimal:2',
    ];

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function vls()
    {
        return $this->hasMany(ProductVl::class);
    }

    public function updateLatestVl()
    {
        $latestVl = $this->vls()->orderBy('date_vl', 'desc')->first();
        
        if ($latestVl) {
            // Update without firing events to avoid infinite loops
            $this->vl = $latestVl->vl;
            $this->saveQuietly();
        }
    }
}
