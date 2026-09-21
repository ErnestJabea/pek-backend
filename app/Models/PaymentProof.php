<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentProof extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['path', 'sha256'];

    protected $casts = ['declared_date' => 'date', 'reviewed_at' => 'datetime'];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}
