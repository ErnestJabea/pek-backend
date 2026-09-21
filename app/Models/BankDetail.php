<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_name',
        'beneficiary',
        'iban',
        'rib',
        'swift',
        'is_active',
        'om_instructions',
        'momo_instructions',
        'bank_instructions',
    ];
}
