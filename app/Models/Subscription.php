<?php

namespace App\Models;

use App\Jobs\ProcessSubscriptionReceipt;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Subscription extends Model
{
    use HasFactory;

    protected static function booted()
    {
        static::created(function (Subscription $subscription) {
            if ($subscription->statut === 'Succès') {
                self::dispatchSuccessEffects($subscription->id);
            } elseif ($subscription->statut === 'Échec') {
                self::dispatchFailureEffects($subscription->id);
            }
        });

        static::updated(function (Subscription $subscription) {
            if ($subscription->wasChanged('statut')) {
                if ($subscription->statut === 'Succès') {
                    self::dispatchSuccessEffects($subscription->id);
                } elseif ($subscription->statut === 'Échec') {
                    self::dispatchFailureEffects($subscription->id);
                }
            }
        });
    }

    private static function dispatchSuccessEffects(int $subscriptionId): void
    {
        DB::afterCommit(function () use ($subscriptionId) {
            $confirmed = self::with(['user', 'product'])->find($subscriptionId);
            if (! $confirmed) {
                return;
            }
            Notification::firstOrCreate([
                'user_id' => $confirmed->user_id,
                'title' => 'Souscription Validée ✅',
                'body' => "Votre souscription {$confirmed->reference_transaction} pour {$confirmed->product->libelle} a été validée. Vos parts sont créditées.",
                'type' => 'success',
            ]);
            ProcessSubscriptionReceipt::dispatch($confirmed);
        });
    }

    private static function dispatchFailureEffects(int $subscriptionId): void
    {
        DB::afterCommit(function () use ($subscriptionId) {
            $failed = self::with(['user', 'product'])->find($subscriptionId);
            if (! $failed) {
                return;
            }
            Notification::firstOrCreate([
                'user_id' => $failed->user_id,
                'title' => 'Paiement non abouti ⚠️',
                'body' => "Votre paiement pour la souscription {$failed->reference_transaction} ({$failed->product->libelle}) a échoué.",
                'type' => 'danger',
            ]);
        });
    }

    protected $fillable = [
        'user_id',
        'product_id',
        'nb_parts',
        'prix_unitaire',
        'montant_total',
        'moyen_paiement',
        'statut',
        'reference_transaction',
        'maviance_transaction_ref',
        'enkap_merchant_reference',
        'idempotency_key',
        'stripe_payment_intent_id',
        'stripe_checkout_session_id',
        'provider_checkout_url',
        'payment_currency',
        'provider_payload_hash',
        'payment_confirmed_at',
        'payment_attempt',
        'payment_initiated_at',
        'payment_expires_at',
        'compliance_reviewed_at',
        'compliance_reviewed_by_user_id',
        'accounting_reviewed_at',
        'accounting_reviewed_by_user_id',
        'internal_notes',
    ];

    protected $hidden = [
        's3p_context',
        's3p_response_hash',
        's3p_verification_code',
        'internal_notes',
        'payment_phone',
        'bank_transaction_key',
        's3p_quote_id',
        's3p_pay_item',
        'idempotency_key',
        'provider_payload_hash',
        'stripe_payment_intent_id',
        'stripe_checkout_session_id',
        'provider_checkout_url',
    ];

    protected $appends = [
        'montant_net',
        'frais_gestion',
    ];

    protected $casts = [
        's3p_context' => 'array',
        's3p_quote_expires_at' => 'immutable_datetime',
        'investment_amount' => 'integer',
        'subscription_fee' => 'integer',
        'bank_snapshot' => 'array',
        'payment_phone' => 'encrypted',
        'funds_received_at' => 'datetime',
        'value_date' => 'date',
        'mobile_checked_at' => 'datetime',
        'mobile_initiated_at' => 'datetime',
        'nb_parts' => 'decimal:8',
        'prix_unitaire' => 'decimal:4',
        'montant_total' => 'decimal:2',
        'payment_confirmed_at' => 'datetime',
        'payment_initiated_at' => 'datetime',
        'payment_expires_at' => 'datetime',
        'compliance_reviewed_at' => 'datetime',
        'accounting_reviewed_at' => 'datetime',
    ];

    public function getMontantNetAttribute(): float
    {
        if ($this->investment_amount !== null) {
            return (float) $this->investment_amount;
        }

        return round((float) $this->nb_parts * (float) $this->prix_unitaire);
    }

    public function getFraisGestionAttribute(): float
    {
        if ($this->subscription_fee !== null) {
            return (float) $this->subscription_fee;
        }
        $net = $this->montant_net;
        if ((float) $this->montant_total > 0) {
            return max(0, (float) $this->montant_total - $net);
        }

        return round($net * 0.01);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function paymentProofs()
    {
        return $this->hasMany(PaymentProof::class);
    }

    public function paymentEvents()
    {
        return $this->hasMany(PaymentEvent::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function complianceReviewer()
    {
        return $this->belongsTo(User::class, 'compliance_reviewed_by_user_id');
    }

    public function accountingReviewer()
    {
        return $this->belongsTo(User::class, 'accounting_reviewed_by_user_id');
    }
}
