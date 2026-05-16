<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'nb_parts',
        'prix_unitaire',
        'montant_total',
        'moyen_paiement',
        'statut',
        'reference_transaction',
    ];

    protected $casts = [
        'nb_parts' => 'decimal:4',
        'prix_unitaire' => 'decimal:4',
        'montant_total' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
