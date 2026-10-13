<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'name_en',
        'description',
        'description_en',
        'is_active',
        'sort_order',
        'min_amount',
        'max_amount',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'min_amount' => 'integer',
        'max_amount' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Scope pour les moyens de paiement actifs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
