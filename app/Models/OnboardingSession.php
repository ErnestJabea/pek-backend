<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;

class OnboardingSession extends Model
{
    use HasFactory;

    public const REQUIRED_DOCUMENTS = [
        'doc_piece_identite' => 'Pièce d’identité (CNI / passeport)',
        'doc_justificatif_domicile' => 'Justificatif de domicile de moins de 3 mois',
        'doc_photo' => 'Photo d’identité récente',
        'doc_origine_fonds' => 'Justificatif d’origine des fonds',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'current_step',
        'payload',
        'submitted_payload',
        'risk_level',
        'status',
        'revision',
        'rejection_reason',
        'submitted_at',
        'validated_at',
        'rejected_at',
        'identity_verified_at',
        'signature_path',
        'doc_piece_identite',
        'doc_justificatif_domicile',
        'doc_photo',
        'doc_origine_fonds',
    ];

    protected $hidden = [
        'encrypted_payload',
        'submitted_payload',
        'signature_path',
        'doc_piece_identite',
        'doc_justificatif_domicile',
        'doc_photo',
        'doc_origine_fonds',
    ];

    protected $casts = [
        'revision' => 'integer',
        'submitted_at' => 'datetime',
        'validated_at' => 'datetime',
        'rejected_at' => 'datetime',
        'identity_verified_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->payload)) {
                $user = User::find($model->user_id);
                if ($user) {
                    $model->payload = [
                        'nom' => $user->last_name,
                        'prenom' => $user->first_name,
                        'email' => $user->email,
                        'tel' => $user->phone,
                        'pays_residence' => $user->country,
                        'adresse' => $user->city,
                    ];
                }
            }
        });
    }

    public function getReferenceAttribute()
    {
        if (! $this->created_at) {
            return 'KYC-'.now()->format('Ymd').'-001';
        }

        $dateStr = $this->created_at->format('Ymd');
        $dateStart = $this->created_at->format('Y-m-d 00:00:00');
        $dateEnd = $this->created_at->format('Y-m-d 23:59:59');

        // Find all sessions created on the same day, ordered by created_at, then id
        $sessions = self::whereBetween('created_at', [$dateStart, $dateEnd])
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $index = 1;
        foreach ($sessions as $session) {
            if ($session->id === $this->id) {
                break;
            }
            $index++;
        }

        return sprintf('KYC-%s-%03d', $dateStr, $index);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function identityVerifications()
    {
        return $this->hasMany(IdentityVerification::class);
    }

    public function latestIdentityVerification()
    {
        return $this->hasOne(IdentityVerification::class)->latestOfMany();
    }

    public function events()
    {
        return $this->hasMany(OnboardingEvent::class);
    }

    /**
     * @return array<int, string>
     */
    public function missingRequiredDocuments(): array
    {
        $disk = Storage::disk('kyc_private');

        return collect(self::REQUIRED_DOCUMENTS)
            ->filter(function (string $label, string $attribute) use ($disk): bool {
                $path = $this->getAttribute($attribute);

                return ! is_string($path)
                    || trim($path) === ''
                    || ! $disk->exists($path);
            })
            ->values()
            ->all();
    }

    public function getPayloadAttribute($value): array
    {
        if (! empty($this->attributes['encrypted_payload'])) {
            try {
                return json_decode(
                    Crypt::decryptString($this->attributes['encrypted_payload']),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            } catch (JsonException $exception) {
                report($exception);
            }
        }

        if (is_array($value)) {
            return $value;
        }

        return $value ? (json_decode($value, true) ?: []) : [];
    }

    public function setPayloadAttribute($value): void
    {
        $this->attributes['payload'] = null;
        $this->attributes['encrypted_payload'] = Crypt::encryptString(json_encode(
            $value ?: [],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
    }

    public function getSubmittedPayload(): array
    {
        if (empty($this->attributes['submitted_payload'])) {
            return [];
        }

        return json_decode(
            Crypt::decryptString($this->attributes['submitted_payload']),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    public function setSubmittedPayload(array $payload): void
    {
        $this->attributes['submitted_payload'] = Crypt::encryptString(json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
    }
}
