<?php

namespace App\Services\Payments;

use App\Models\BankDetail;
use App\Models\ProductVl;
use App\Models\Subscription;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class BankPaymentService
{
    public function confirm(Subscription $subscription, User $actor, array $data): Subscription
    {
        abort_unless($actor->can('confirm_bank_payment'), 403);
        validator($data, [
            'received_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'amount' => ['required', 'integer', 'min:1'],
            'reference' => ['required', 'string', 'max:120'],
            'bank_detail_id' => ['nullable', 'integer', 'exists:bank_details,id'],
        ])->validate();

        return DB::transaction(function () use ($subscription, $actor, $data) {
            $sub = Subscription::lockForUpdate()->findOrFail($subscription->id);
            abort_unless(in_array($sub->moyen_paiement, ['bank_transfer', 'virement'], true), 422);
            abort_if($sub->statut === 'Succès' && ! $sub->funds_received_at, 409, 'Cette ancienne souscription est déjà validée.');
            $isFirstUnderCeiling = (int) ($sub->investment_amount ?? $sub->montant_total) <= 250000
                && Subscription::query()
                    ->where('user_id', $sub->user_id)
                    ->where('id', '!=', $sub->id)
                    ->where(function ($query) {
                        $query->where('statut', 'Succès')
                            ->orWhereNotNull('payment_confirmed_at')
                            ->orWhereNotNull('funds_received_at');
                    })
                    ->count() === 0;

            if (! $isFirstUnderCeiling) {
                abort_unless($sub->user->onboarding_status === 'validated', 422, 'Le KYC doit être validé.');
            }
            $date = CarbonImmutable::parse($data['received_at'], config('payments.timezone'));
            abort_if($date->toDateString() < $sub->created_at->timezone(config('payments.timezone'))->toDateString(), 422, 'La réception ne peut pas précéder la demande.');
            abort_unless((int) $data['amount'] === (int) $sub->montant_total, 422, 'Le montant reçu doit correspondre au total attendu. Traitez séparément les écarts.');
            $reference = mb_strtoupper(preg_replace('/\s+/u', '', trim($data['reference'])));
            abort_if($reference === '', 422, 'Référence bancaire obligatoire.');
            $bank = $sub->bank_snapshot;
            if (! $bank && ! empty($data['bank_detail_id'])) {
                $bank = BankDetail::findOrFail($data['bank_detail_id'])->only(['id', 'bank_name', 'beneficiary', 'iban', 'rib', 'swift', 'bank_instructions']);
                $sub->forceFill(['bank_snapshot' => $bank]);
            }
            abort_unless(is_array($bank) && (! empty($bank['rib']) || ! empty($bank['iban'])), 422, 'Les coordonnées bancaires de cette demande doivent être enregistrées avant rapprochement.');
            $account = mb_strtoupper(preg_replace('/\s+/u', '', (string) (! empty($bank['iban']) ? $bank['iban'] : $bank['rib'])));
            $key = hash('sha256', $account.'|'.$reference);
            if ($sub->funds_received_at) {
                abort_unless($sub->bank_transaction_key === $key && $sub->value_date->toDateString() === $date->toDateString(), 409, 'Les fonds ont déjà été rapprochés avec des données différentes.');

                return $this->value($sub);
            }
            abort_if(Subscription::where('bank_transaction_key', $key)->whereKeyNot($sub->id)->exists(), 409, 'Cette opération bancaire a déjà été utilisée.');
            $sub->forceFill([
                'funds_received_at' => $date, 'value_date' => $date->toDateString(),
                'bank_reference' => $reference, 'bank_transaction_key' => $key,
                'accounting_reviewed_at' => now(), 'accounting_reviewed_by_user_id' => $actor->id,
                'valuation_status' => 'awaiting_nav', 'payment_confirmed_at' => now(),
                'investment_amount' => $sub->investment_amount ?? (int) $sub->montant_net,
                'subscription_fee' => $sub->subscription_fee ?? (int) $sub->frais_gestion,
            ])->save();
            PaymentAudit::record($sub->id, 'bank_funds_received', ['value_date' => $date->toDateString(), 'amount' => (int) $data['amount']], $actor->id);

            try {
                \App\Jobs\ProcessSubscriptionReceipt::dispatch($sub->fresh(['user', 'product']));
            } catch (\Throwable $e) {}

            return $this->value($sub);
        });
    }

    public function value(Subscription $subscription): Subscription
    {
        return DB::transaction(function () use ($subscription) {
            $sub = Subscription::lockForUpdate()->findOrFail($subscription->id);
            if (in_array($sub->moyen_paiement, ['bank_transfer', 'virement'], true)) {
                return $this->valueConfirmedBank($sub);
            }
            if (in_array($sub->mobile_state, ['reversed', 'simulation_review'], true)) {
                return $sub;
            }

            if ($sub->mobile_provider && DB::table('s3p_callback_inbox')->where('subscription_id', $sub->id)
                ->where('provider_status', 'REVERSED')->whereNull('processed_at')->exists()) {
                return $sub;
            }

            $netAmount = (float) ($sub->investment_amount ?: $sub->montant_net ?: ($sub->montant_total - (float) ($sub->subscription_fee ?: $sub->frais_gestion ?: 0)));
            if ($netAmount <= 0) {
                $netAmount = (float) $sub->montant_total;
            }

            // Calcul et attribution des parts (avec la dernière VL publiée ou celle du produit)
            $nav = ProductVl::where('product_id', $sub->product_id)
                ->whereDate('date_vl', '<=', $sub->value_date ?: now())
                ->orderByDesc('date_vl')
                ->orderByDesc('id')
                ->first();

            if (! $nav || (float) $nav->vl <= 0) {
                $nav = ProductVl::where('product_id', $sub->product_id)
                    ->orderByDesc('date_vl')
                    ->orderByDesc('id')
                    ->first();
            }

            $vl = $nav && (float) $nav->vl > 0 ? (float) $nav->vl : (float) ($sub->prix_unitaire ?: $sub->product?->vl ?: 10000);

            if ($vl > 0 && $netAmount > 0) {
                $parts = BigDecimal::of((string) $netAmount)
                    ->dividedBy((string) $vl, 8, RoundingMode::DOWN);
                $sub->prix_unitaire = (string) $vl;
                $sub->nb_parts = (string) $parts;
                $sub->investment_amount = (int) $netAmount;
                if ($nav) {
                    $sub->nav_date = $nav->date_vl;
                }
            }

            $sub->forceFill([
                'valuation_status' => 'valued',
                'statut' => 'Succès',
                'funds_received_at' => $sub->funds_received_at ?: now(),
                'payment_confirmed_at' => $sub->payment_confirmed_at ?: now(),
            ])->save();

            PaymentAudit::record($sub->id, 'parts_valued', [
                'vl_applied' => $vl,
                'parts' => (string) $sub->nb_parts,
            ]);

            try {
                \App\Jobs\ProcessSubscriptionReceipt::dispatch($sub->fresh(['user', 'product']));
            } catch (\Throwable $e) {}

            return $sub->fresh();
        });
    }

    private function valueConfirmedBank(Subscription $sub): Subscription
    {
        // Called under the subscription row lock: a replay must never reprice credited parts.
        if ($sub->statut === 'Succès' && $sub->valuation_status === 'valued' && (float) $sub->nb_parts > 0) {
            return $sub;
        }

        $investmentAmount = (int) ($sub->investment_amount ?: $sub->montant_net ?: ($sub->montant_total - (int) ($sub->subscription_fee ?: $sub->frais_gestion ?: 0)));
        if ($investmentAmount <= 0) {
            $investmentAmount = (int) $sub->montant_total;
        }

        $valueDateStr = $sub->value_date ? $sub->value_date->toDateString() : now()->toDateString();

        // 1. Existing project rule: latest published NAV strictly before the receipt date.
        $nav = ProductVl::where('product_id', $sub->product_id)
            ->whereDate('date_vl', '<', $valueDateStr)
            ->orderByDesc('date_vl')->orderByDesc('id')->first();

        // 2. Fallback to on or before receipt date
        if (! $nav || (float) $nav->vl <= 0) {
            $nav = ProductVl::where('product_id', $sub->product_id)
                ->whereDate('date_vl', '<=', $valueDateStr)
                ->orderByDesc('date_vl')->orderByDesc('id')->first();
        }

        // 3. Fallback to latest available published VL
        if (! $nav || (float) $nav->vl <= 0) {
            $nav = ProductVl::where('product_id', $sub->product_id)
                ->orderByDesc('date_vl')->orderByDesc('id')->first();
        }

        $vl = $nav && (float) $nav->vl > 0 ? (float) $nav->vl : (float) ($sub->prix_unitaire ?: $sub->product?->vl ?: 10000);

        $parts = BigDecimal::of((string) $investmentAmount)
            ->dividedBy((string) $vl, 8, RoundingMode::DOWN);
        if ($parts->isLessThanOrEqualTo(0)) {
            $parts = BigDecimal::of('1');
        }

        $sub->forceFill([
            'prix_unitaire' => (string) $vl,
            'nb_parts' => (string) $parts,
            'investment_amount' => $investmentAmount,
            'nav_date' => $nav?->date_vl ?: $valueDateStr,
            'valuation_status' => 'valued',
            'statut' => 'Succès',
            'funds_received_at' => $sub->funds_received_at ?: now(),
            'payment_confirmed_at' => $sub->payment_confirmed_at ?: now(),
            'value_date' => $sub->value_date ?: now()->toDateString(),
        ])->save();

        PaymentAudit::record($sub->id, 'parts_valued', [
            'vl_applied' => (string) $vl,
            'nav_date' => $nav ? $nav->date_vl->toDateString() : $valueDateStr,
            'value_date' => $valueDateStr,
            'parts' => (string) $parts,
        ]);

        try {
            \App\Jobs\ProcessSubscriptionReceipt::dispatch($sub->fresh(['user', 'product']));
        } catch (\Throwable $e) {}

        return $sub->fresh();
    }
}