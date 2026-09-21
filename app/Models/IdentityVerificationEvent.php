<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdentityVerificationEvent extends Model
{
    protected $fillable = [
        'identity_verification_id',
        'delivery_hash',
        'event_type',
        'result_status',
        'is_final',
        'received_at',
    ];

    protected $hidden = [
        'delivery_hash',
    ];

    protected $casts = [
        'is_final' => 'boolean',
        'received_at' => 'datetime',
    ];

    public function identityVerification()
    {
        return $this->belongsTo(IdentityVerification::class);
    }
}
