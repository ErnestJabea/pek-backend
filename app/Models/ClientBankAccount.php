<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientBankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bank_name',
        'account_holder_name',
        'account_number',
        'bank_code',
        'branch_code',
        'rib_key',
        'iban',
        'swift_bic',
        'rib_document_path',
        'status', // pending, validated, rejected
        'rejection_reason',
        'validated_by',
        'validated_at',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'validated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
