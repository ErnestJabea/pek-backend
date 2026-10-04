<?php

namespace App\Services;

use App\Filament\Resources\OnboardingSessionResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\SubscriptionResource;
use App\Filament\Resources\UserResource;
use App\Models\OnboardingSession;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

class BackofficeDashboard
{
    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('payments.timezone', 'Africa/Douala'))->startOfDay();
    }

    public function canAccess(?User $user): bool
    {
        return $user && $user->canAccessPanel(Filament::getPanel('admin'));
    }

    /** Shared definitions keep card counts and destination filters consistent. */
    public function definitions(): array
    {
        return [
            'subscriptions_today' => ['Activité commune', 'Souscriptions du jour', SubscriptionResource::class, [], 'count', 'Demandes créées aujourd’hui · tous statuts', 'heroicon-o-document-plus'],
            'registrations_today' => ['Activité commune', 'Inscriptions du jour', UserResource::class, [], 'count', 'Nouveaux comptes clients · hors personnel', 'heroicon-o-user-plus'],
            'confirmed_today' => ['Activité commune', 'Souscriptions confirmées du jour', SubscriptionResource::class, [], 'sum', 'Montant brut confirmé · frais inclus', 'heroicon-o-banknotes'],
            'confirmed_total' => ['Activité commune', 'Souscriptions confirmées cumulées', SubscriptionResource::class, [], 'sum', 'Depuis l’origine · montant brut, frais inclus', 'heroicon-o-chart-bar'],
            'kyc_pending' => ['Conformité / KYC', 'Dossiers KYC à examiner', OnboardingSessionResource::class, ['view_any_onboarding_session'], 'count', 'Dossiers soumis en attente de décision', 'heroicon-o-identification'],
            'kyc_rejected' => ['Conformité / KYC', 'Dossiers KYC rejetés', OnboardingSessionResource::class, ['view_any_onboarding_session'], 'count', 'Dossiers actuellement rejetés', 'heroicon-o-exclamation-circle'],
            'compliance_pending' => ['Conformité / KYC', 'Souscriptions à contrôler', SubscriptionResource::class, ['view_any_subscription', 'review_subscription_compliance'], 'count', 'Hors échecs · revue de conformité absente', 'heroicon-o-shield-check'],
            'bank_pending' => ['Comptabilité / Trésorerie', 'Virements à confirmer', SubscriptionResource::class, ['view_any_subscription', 'confirm_bank_payment'], 'count', 'Réception des fonds non confirmée', 'heroicon-o-building-library'],
            'bank_pending_amount' => ['Comptabilité / Trésorerie', 'Montant des virements à confirmer', SubscriptionResource::class, ['view_any_subscription', 'confirm_bank_payment'], 'sum', 'Montant attendu · ne constitue pas un encaissement', 'heroicon-o-banknotes'],
            'accounting_awaiting_nav' => ['Comptabilité / Trésorerie', 'Fonds reçus à valoriser', SubscriptionResource::class, ['view_any_subscription', 'confirm_bank_payment'], 'count', 'Fonds reçus · en attente de valeur liquidative', 'heroicon-o-clock'],
            'active_products' => ['Gestion des fonds', 'Fonds actifs', ProductResource::class, ['view_any_product'], 'count', 'Produits ouverts à la souscription', 'heroicon-o-briefcase'],
            'missing_nav' => ['Gestion des fonds', 'VL du jour non renseignées', ProductResource::class, ['view_any_product', 'view_any_product_vl'], 'count', 'Fonds actifs sans VL positive à la date du jour', 'heroicon-o-calendar-days'],
            'funds_awaiting_nav' => ['Gestion des fonds', 'Souscriptions à valoriser', SubscriptionResource::class, ['view_any_subscription', 'view_any_product_vl'], 'count', 'Fonds reçus · en attente de valeur liquidative', 'heroicon-o-calculator'],
            'clients_without_subscription' => ['Support client', 'Clients sans souscription', UserResource::class, ['view_any_user', 'view_any_subscription'], 'count', 'Aucune demande réelle enregistrée', 'heroicon-o-users'],
            'payments_pending' => ['Support client', 'Paiements en attente', SubscriptionResource::class, ['view_any_user', 'view_any_subscription'], 'count', 'Demandes en attente · fonds non reçus', 'heroicon-o-clock'],
            'failures_today' => ['Support client', 'Demandes du jour en échec', SubscriptionResource::class, ['view_any_user', 'view_any_subscription'], 'count', 'Créées aujourd’hui · actuellement en échec', 'heroicon-o-x-circle'],
        ];
    }

    public function allowed(User $user): array
    {
        abort_unless($this->canAccess($user), 403);

        return array_filter($this->definitions(), fn ($definition) => collect($definition[3])->every(fn ($permission) => $user->can($permission)));
    }

    public function snapshot(User $user): array
    {
        $groups = [];
        foreach ($this->allowed($user) as $key => [$group, $label, $resource, $permissions, $operation, $description, $icon]) {
            $query = $this->query($key, $user);
            $value = $operation === 'sum' ? $query->sum('montant_total') : $query->count();
            $groups[$group][] = [
                'key' => $key, 'label' => $label, 'value' => $value,
                'formatted' => number_format((float) $value, 0, ',', ' ').($operation === 'sum' ? ' FCFA' : ''),
                'description' => $description, 'icon' => $icon,
                'url' => $resource::canViewAny() ? $resource::getUrl('index', ['tableFilters' => ['dashboard' => ['value' => $key]]], panel: 'admin') : null,
            ];
        }

        return $groups;
    }

    public function filterOptions(string $resource, User $user): array
    {
        $options = [];
        foreach ($this->allowed($user) as $key => $definition) {
            if ($definition[2] === $resource) {
                $options[$key] = $definition[1];
            }
        }

        return $options;
    }

    public function query(string $key, User $user): Builder
    {
        abort_unless(isset($this->allowed($user)[$key]), 403);
        $today = $this->today();
        $start = $today->setTimezone(config('app.timezone'))->toDateTimeString();
        $end = $today->addDay()->setTimezone(config('app.timezone'))->toDateTimeString();
        $day = fn (Builder $query) => $query->where('created_at', '>=', $start)->where('created_at', '<', $end);

        if (in_array($key, ['registrations_today', 'clients_without_subscription'], true)) {
            $query = User::query()->where('role', 'client')->whereNull('admin_department_id')
                ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'super_admin')
                    ->orWhereHas('permissions', fn ($p) => $p->where('name', 'access_admin_panel')))
                ->whereDoesntHave('permissions', fn ($q) => $q->where('name', 'access_admin_panel'));

            return $key === 'registrations_today' ? $day($query)
                : $query->whereDoesntHave('subscriptions', fn (Builder $q) => $this->realSubscriptions($q));
        }
        if (in_array($key, ['kyc_pending', 'kyc_rejected'], true)) {
            return OnboardingSession::query()->where('status', $key === 'kyc_pending' ? 'completed' : 'rejected');
        }
        if (in_array($key, ['active_products', 'missing_nav'], true)) {
            return Product::query()->where('is_active', true)
                ->when($key === 'missing_nav', fn ($q) => $q->whereDoesntHave('vls', fn ($vl) => $vl->whereDate('date_vl', $today->toDateString())->where('vl', '>', 0)));
        }

        $query = $this->realSubscriptions(Subscription::query());

        return match ($key) {
            'subscriptions_today' => $day($query),
            'confirmed_total' => $this->confirmed($query),
            'confirmed_today' => $this->confirmed($query)->where(function ($q) use ($today, $start, $end) {
                $q->whereDate('value_date', $today->toDateString())
                    ->orWhere(function ($fallback) use ($start, $end) {
                        $fallback->whereNull('value_date')
                            ->whereRaw('COALESCE(funds_received_at, payment_confirmed_at, created_at) >= ?', [$start])
                            ->whereRaw('COALESCE(funds_received_at, payment_confirmed_at, created_at) < ?', [$end]);
                    });
            }),
            'compliance_pending' => $query->whereIn('statut', ['En attente', 'À vérifier', 'Succès'])->whereNull('compliance_reviewed_at'),
            'bank_pending', 'bank_pending_amount' => $query->whereIn('moyen_paiement', ['bank_transfer', 'virement'])
                ->whereIn('statut', ['En attente', 'À vérifier'])->whereNull('funds_received_at'),
            'accounting_awaiting_nav', 'funds_awaiting_nav' => $query->where('valuation_status', 'awaiting_nav')->whereNotNull('funds_received_at')->where('statut', '!=', 'Succès'),
            'payments_pending' => $query->where('statut', 'En attente')->whereNull('funds_received_at'),
            'failures_today' => $day($query)->where('statut', 'Échec'),
            default => abort(404),
        };
    }

    private function confirmed(Builder $query): Builder
    {
        // montant_total is stored in FCFA by the subscription workflow, fees included.
        return $query->where('statut', 'Succès')
            ->where(fn ($q) => $q->whereNull('mobile_state')->orWhere('mobile_state', '!=', 'reversed'));
    }

    private function realSubscriptions(Builder $query): Builder
    {
        return $query
            ->where(fn ($q) => $q->whereNull('valuation_status')->orWhereNotIn('valuation_status', ['staging_only', 'simulation_review']))
            ->where(fn ($q) => $q->whereNull('mobile_state')->orWhere('mobile_state', '!=', 'simulation_review'))
            ->where(fn ($q) => $q->whereNull('s3p_quote_id')->orWhere('s3p_quote_id', 'not like', 'SIM-%'))
            ->where(fn ($q) => $q->whereNull('s3p_context->simulation')->orWhere('s3p_context->simulation', false))
            ->where(fn ($q) => $q->whereNull('s3p_context->base_url')->orWhere('s3p_context->base_url', 'not like', '%staging%'));
    }
}
