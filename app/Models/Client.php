<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class Client extends User
{
    protected $table = 'users';

    protected static function booted()
    {
        static::addGlobalScope('client', function (Builder $builder) {
            $builder->where('role', 'client');
        });

        static::creating(function ($client) {
            $client->role = 'client';
        });
    }
}
