<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class IdentityVerification extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'onboarding_session_id',
        'provider',
        'client_reference',
        'provider_reference',
        'identity_data_hash',
        'status',
        'is_final',
        'document_validated',
        'face_matched',
        'reviewed_by_human',
        'last_event_type',
        'error_code',
        'expires_at',
        'completed_at',
    ];

    protected $hidden = [
        'client_reference',
        'provider_reference',
        'identity_data_hash',
        'error_code',
    ];

    protected $casts = [
        'is_final' => 'boolean',
        'document_validated' => 'boolean',
        'face_matched' => 'boolean',
        'reviewed_by_human' => 'boolean',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (IdentityVerification $verification) {
            $verification->id ??= (string) Str::uuid();
            $verification->client_reference ??= (string) Str::uuid();
        });
    }

    public function onboardingSession()
    {
        return $this->belongsTo(OnboardingSession::class);
    }

    public function events()
    {
        return $this->hasMany(IdentityVerificationEvent::class);
    }

    public function isApprovedFor(array $payload): bool
    {
        return $this->is_final
            && $this->status === 'approved'
            && hash_equals($this->identity_data_hash, self::fingerprint($payload));
    }

    public static function fingerprint(array $payload): string
    {
        $identity = [];
        foreach (['nom', 'prenom', 'dob', 'nat', 'piece', 'num_piece', 'expiration_piece'] as $field) {
            $identity[$field] = mb_strtoupper(trim((string) ($payload[$field] ?? '')));
        }

        return hash_hmac(
            'sha256',
            json_encode($identity, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            (string) config('app.key')
        );
    }
}
