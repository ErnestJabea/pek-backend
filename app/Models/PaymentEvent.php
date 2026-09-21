<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected $casts = ['details' => 'array', 'created_at' => 'datetime'];
}
