<?php

namespace App\Services\Payments;

use App\Models\Notification;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MobilePaymentService
{
    public function __construct(private S3pGateway $gateway) {}

    public function start(Subscription $subscription): Subscription
    {
        abort_unless($subscription->user->onboarding_status === 'validated', 403, 'Le KYC doit être validé.');
        abort_unless($this->gateway->available((string) $subscription->mobile_provider), 503, 'Cet opérateur n’est pas encore activé.');
        $claimed = DB::transaction(function () use ($subscription) {
            $sub = Subscription::lockForUpdate()->findOrFail($subscription->id);
            if (($sub->s3p_reference && $sub->mobile_state !== 'quote_failed') || $sub->statut === 'Succès') {
                return false;
            }
            $sub->forceFill(['s3p_reference' => $sub->s3p_reference ?: 'PEK-'.str_replace('-', '', Str::uuid()),
                'mobile_state' => 'preparing', 'mobile_initiated_at' => now()])->save();
            PaymentAudit::record($sub->id, 'mobile_started', ['operator' => $sub->mobile_provider], $sub->user_id);

            return true;
        });
        $sub = $subscription->fresh();
        if (! $claimed) {
            return $sub;
        }
        $phase = 'quote';
        try {
            $quote = $this->gateway->quote($sub);
            // Persist all recovery identifiers BEFORE the operation that may debit funds.
            $sub->forceFill(['s3p_quote_id' => $quote['id'], 's3p_pay_item' => $quote['item'],
                's3p_context' => $quote['context'], 's3p_quote_expires_at' => $quote['expires_at'], 'mobile_state' => 'submitted'])->save();
            $phase = 'collect';
            $result = $this->gateway->collect($sub);
            $phase = 'store_result';
            if (empty($result['ptn'])) {
                throw new \RuntimeException('PTN absent.');
            }
            DB::transaction(function () use ($sub, $result) {
                $locked = Subscription::lockForUpdate()->findOrFail($sub->id);
                if ($locked->s3p_ptn && $locked->s3p_ptn !== (string) $result['ptn']) {
                    throw new \RuntimeException('PTN incohérent.');
                }
                $locked->forceFill(['s3p_ptn' => (string) $result['ptn']])->save();
            });
            if ($this->gateway->isSimulation()) {
                return $this->refresh($sub->fresh());
            }
        } catch (\Throwable $e) {
            DB::transaction(function () use ($sub, $e, $phase) {
                $locked = Subscription::lockForUpdate()->findOrFail($sub->id);
                if (! in_array($locked->mobile_state, ['success', 'errored', 'reversed'], true)) {
                    $locked->forceFill(['mobile_state' => $locked->mobile_state === 'preparing' ? 'quote_failed' : 'verification_required'])->save();
                }
                PaymentAudit::record($sub->id, 'mobile_check_required', ['phase' => $phase] + S3pFailureDiagnostic::from($e));
            });
        }

        return $sub->fresh();
    }

    public function refresh(Subscription $subscription, bool $requireFresh = false, ?object $callback = null): Subscription
    {
        $eligible = DB::transaction(function () use ($subscription) {
            $sub = Subscription::lockForUpdate()->findOrFail($subscription->id);
            if (! $sub->s3p_reference || ! $sub->s3p_pay_item || (! $this->gateway->isSimulation() && $sub->mobile_checked_at && $sub->mobile_checked_at->gt(now()->subSeconds(10)))) {
                return false;
            }
            $sub->forceFill(['mobile_checked_at' => now()])->save();

            return true;
        });
        if (! $eligible) {
            if ($requireFresh) {
                throw new \RuntimeException('Vérification différée par la cadence fournisseur.');
            }

            return $subscription->fresh();
        }
        $state = $this->gateway->verify($subscription->fresh());
        if ($callback && ($callback->ptn !== $state['ptn']
            || ($callback->provider_status === 'REVERSED' && $state['status'] !== 'REVERSED')
            || ($callback->provider_status === 'ERRORED' && ! in_array($state['status'], ['ERRORED', 'REVERSED'], true))
            || ($callback->provider_status === 'SUCCESS' && ! in_array($state['status'], ['SUCCESS', 'REVERSED'], true))
            || ($callback->provider_status !== 'PENDING' && $state['status'] === 'PENDING'))) {
            throw new \RuntimeException('Callback et vérification fournisseur à rapprocher.');
        }

        return DB::transaction(function () use ($subscription, $state, $callback) {
            $sub = Subscription::lockForUpdate()->findOrFail($subscription->id);
            $status = (string) ($state['status'] ?? '');
            if ($sub->mobile_state === 'reversed') {
                return $sub;
            }
            if (! in_array($status, ['PENDING', 'SUCCESS', 'ERRORED', 'REVERSED'], true)) {
                throw new \RuntimeException('Statut S3P inconnu.');
            }
            if ($sub->s3p_ptn && $sub->s3p_ptn !== (string) $state['ptn']) {
                throw new \RuntimeException('PTN différent.');
            }
            if ($status === 'SUCCESS' && $callback?->provider_status === 'SUCCESS'
                && ($sub->s3p_context['receipt_timestamp_source'] ?? null) === 'verifytx_staging'
                && $sub->funds_received_at) {
                $callbackTime = S3pTimestamp::parse($callback->provider_timestamp)->utc();
                if (! $callbackTime->equalTo($sub->funds_received_at)) {
                    $context = $sub->s3p_context;
                    $conflict = $callbackTime->toIso8601String();
                    if (($context['timestamp_conflict'] ?? null) !== $conflict) {
                        $context['timestamp_conflict'] = $conflict;
                        $sub->forceFill(['s3p_context' => $context]);
                        if ($sub->statut !== 'Succès') {
                            $sub->valuation_status = 'payment_date_conflict';
                        }
                        $sub->save();
                        PaymentAudit::record($sub->id, 'mobile_payment_date_conflict', [
                            'retained_utc' => $sub->funds_received_at->toIso8601String(),
                            'callback_utc' => $conflict,
                        ]);
                        Notification::create(['user_id' => $sub->user_id, 'title' => 'Date du paiement à rapprocher',
                            'body' => 'Une confirmation tardive indique une date différente pour '.$sub->reference_transaction.'. Contactez le support. Les parts déjà attribuées sont conservées.', 'type' => 'warning']);
                    }

                    return $sub->fresh();
                }
            }
            if ($sub->statut === 'Succès' && $status !== 'REVERSED') {
                return $sub;
            }
            // Terminal states cannot be overwritten by older concurrent responses.
            if ($sub->mobile_state === 'success' && ! in_array($status, ['SUCCESS', 'REVERSED'], true)) {
                return $sub;
            }
            if ($sub->mobile_state === 'errored' && $status !== 'ERRORED' && $status !== 'REVERSED') {
                PaymentAudit::record($sub->id, 'mobile_terminal_conflict', ['status' => $status]);

                return $sub;
            }
            $sub->forceFill(['s3p_ptn' => (string) $state['ptn'], 'mobile_state' => strtolower($status)]);
            $sub->forceFill(['s3p_error_code' => isset($state['errorCode']) ? (string) $state['errorCode'] : null,
                's3p_receipt_number' => $state['receiptNumber'] ?? null, 's3p_verification_code' => $state['veriCode'] ?? null,
                's3p_provider_timestamp' => $state['timestamp'] ?? null,
                's3p_response_hash' => hash('sha256', json_encode($state, JSON_THROW_ON_ERROR))]);
            if ($status === 'SUCCESS') {
                $sub->statut = 'Succès';
                $sub->mobile_state = 'success';
                $sub->valuation_status = 'valued';
                $sub->payment_confirmed_at = $sub->payment_confirmed_at ?: now();
                $sub->funds_received_at = $sub->funds_received_at ?: now();
                $sub->value_date = $sub->value_date ?: now()->toDateString();
                $sub->payment_currency = 'XAF';

                // Attribution immédiate des parts
                if ((float) $sub->nb_parts <= 0 && (float) $sub->investment_amount > 0) {
                    $vl = (float) ($sub->prix_unitaire ?: $sub->product?->vl ?: 10000);
                    $sub->nb_parts = (string) round((float) $sub->investment_amount / $vl, 4);
                    $sub->prix_unitaire = $vl;
                }
            } elseif ($status === 'ERRORED' && $sub->statut !== 'Succès') {
                $sub->statut = 'Échec';
            } elseif ($status === 'REVERSED') {
                // Exclude reversed funds from the portfolio, preserve the entire audit trail.
                $sub->statut = 'À vérifier';
            }
            if ($sub->isDirty()) {
                $sub->save();
                PaymentAudit::record($sub->id, 'mobile_verified', ['status' => $status, 'ptn' => $sub->s3p_ptn,
                    'error_code' => $sub->s3p_error_code, 'response_hash' => $sub->s3p_response_hash]);
                if ($status === 'REVERSED') {
                    Notification::create(['user_id' => $sub->user_id, 'title' => 'Paiement à vérifier',
                        'body' => 'Le prestataire a signalé une annulation du paiement '.$sub->reference_transaction.'. Contactez le support.', 'type' => 'danger']);
                }
            }
            if ($status === 'SUCCESS') {
                return app(BankPaymentService::class)->value($sub);
            }

            return $sub->fresh();
        });
    }
}
