<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'libelle',
        'libelle_en',
        'description',
        'description_en',
        'vl',
        'seuil_minimum',
        'risk_level',
        'depliant',
        'depliant_en',
        'document_information',
        'document_information_en',
        'is_active',
    ];

    protected $appends = [
        'depliant_url',
        'depliant_en_url',
        'document_information_url',
        'document_information_en_url',
    ];

    protected $casts = [
        'vl' => 'decimal:4',
        'seuil_minimum' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function getDepliantUrlAttribute(): ?string
    {
        return $this->depliant ? '/storage/'.ltrim($this->depliant, '/') : null;
    }

    public function getDepliantEnUrlAttribute(): ?string
    {
        return $this->depliant_en ? '/storage/'.ltrim($this->depliant_en, '/') : null;
    }

    public function getDocumentInformationUrlAttribute(): ?string
    {
        return $this->document_information ? '/storage/'.ltrim($this->document_information, '/') : null;
    }

    public function getDocumentInformationEnUrlAttribute(): ?string
    {
        return $this->document_information_en ? '/storage/'.ltrim($this->document_information_en, '/') : null;
    }

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

    protected static function booted()
    {
        static::saved(function ($product) {
            \Cache::forget('products_list');
            \Cache::forget('products_list_fr');
            \Cache::forget('products_list_en');
        });
        static::deleted(function ($product) {
            \Cache::forget('products_list');
            \Cache::forget('products_list_fr');
            \Cache::forget('products_list_en');
        });
    }
}
