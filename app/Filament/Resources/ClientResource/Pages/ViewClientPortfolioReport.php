<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use App\Models\Client;
use App\Models\Product;
use App\Services\ClientStatementService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ViewClientPortfolioReport extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ClientResource::class;

    protected static string $view = 'filament.resources.client-resource.pages.view-client-portfolio-report';

    public ?string $startDate = null;

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->hasRole('super_admin')
            || (auth()->user()?->can('view_client') ?? false)
            || (auth()->user()?->can('view_any_client') ?? false);
    }

    public ?string $endDate = null;

    public ?int $productId = null;

    public string $activeFilter = 'current_month';

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $now = now();
        $this->endDate = $now->format('Y-m-d');
        $this->startDate = $now->copy()->startOfMonth()->format('Y-m-d');
        $this->productId = null;
        $this->activeFilter = 'current_month';
    }

    public function getTitle(): string
    {
        /** @var Client $client */
        $client = $this->record;
        $name = trim("{$client->first_name} {$client->last_name}");
        return "Portefeuille & Relevé • " . ($name ?: 'Client #' . $client->id);
    }

    public function getSubheading(): ?string
    {
        return "Visibilité financière à date, valorisation du portefeuille, historique des mouvements et génération du relevé officiel FCP.";
    }

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.resources.client-resource.pages.portfolio-report-header', [
            'record' => $this->record,
            'heading' => $this->getTitle(),
            'subheading' => $this->getSubheading(),
            'breadcrumbs' => filament()->hasBreadcrumbs() ? $this->getBreadcrumbs() : [],
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'productId' => $this->productId,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getProductsProperty()
    {
        return Product::query()
            ->where('is_active', true)
            ->orderBy('libelle')
            ->get();
    }

    public function getStatementDataProperty(): array
    {
        return app(ClientStatementService::class)->getStatementData(
            $this->record,
            $this->startDate,
            $this->endDate,
            $this->productId ? (int) $this->productId : null
        );
    }

    /**
     * Historique complet des opérations du client groupées par mois avec sous-totaux
     */
    public function getTransactionsGroupedByMonthProperty(): array
    {
        /** @var Client $client */
        $client = $this->record;

        $query = $client->subscriptions()
            ->with('product')
            ->orderByDesc('value_date')
            ->orderByDesc('created_at');

        if ($this->productId) {
            $query->where('product_id', (int) $this->productId);
        }

        $subscriptions = $query->get();

        $grouped = [];

        foreach ($subscriptions as $sub) {
            $date = $sub->value_date ?: $sub->created_at;
            $carbon = $date ? Carbon::parse($date) : now();
            $monthKey = $carbon->format('Y-m');
            $monthLabel = ucfirst($carbon->translatedFormat('F Y'));

            if (! isset($grouped[$monthKey])) {
                $grouped[$monthKey] = [
                    'key' => $monthKey,
                    'label' => $monthLabel,
                    'year' => $carbon->format('Y'),
                    'count' => 0,
                    'total_brut' => 0.0,
                    'total_frais' => 0.0,
                    'total_net' => 0.0,
                    'total_parts' => 0.0,
                    'transactions' => [],
                ];
            }

            $montantTotal = (float) $sub->montant_total;
            $frais = (float) ($sub->subscription_fee ?? 0);
            $montantNet = (float) ($sub->investment_amount ?: ($montantTotal - $frais));
            $parts = (float) ($sub->nb_parts ?? 0);

            $grouped[$monthKey]['count']++;
            $grouped[$monthKey]['total_brut'] += $montantTotal;
            $grouped[$monthKey]['total_frais'] += $frais;
            $grouped[$monthKey]['total_net'] += $montantNet;
            $grouped[$monthKey]['total_parts'] += $parts;

            $grouped[$monthKey]['transactions'][] = [
                'id' => $sub->id,
                'reference' => $sub->reference_transaction ?: ('SUB-' . $sub->id),
                'date' => $carbon->format('d/m/Y'),
                'product_name' => $sub->product?->libelle ?: 'FCP',
                'product_code' => $sub->product?->code ?: '',
                'montant_brut' => $montantTotal,
                'frais' => $frais,
                'montant_net' => $montantNet,
                'vl' => (float) ($sub->prix_unitaire ?: 0),
                'nb_parts' => $parts,
                'statut' => $sub->statut,
                'is_historical' => (bool) $sub->is_historical,
                'compliance_reviewed' => (bool) $sub->compliance_reviewed_at,
                'accounting_reviewed' => (bool) $sub->accounting_reviewed_at,
                'moyen_paiement' => $sub->moyen_paiement,
                'bulletin_show_url' => route('subscriptions.bulletin.show', $sub->id),
                'bulletin_download_url' => route('subscriptions.bulletin.download', $sub->id),
            ];
        }

        return $grouped;
    }

    public function getBreadcrumbs(): array
    {
        /** @var Client $client */
        $client = $this->record;
        $name = trim("{$client->first_name} {$client->last_name}");
        return [
            ClientResource::getUrl('index') => 'Clients',
            '#' => 'Portefeuille • ' . ($name ?: '#' . $client->id),
        ];
    }

    public function updatedStartDate(): void
    {
        $this->activeFilter = 'custom';
    }

    public function updatedEndDate(): void
    {
        $this->activeFilter = 'custom';
    }

    public function resetFilters(): void
    {
        $now = now();
        $this->endDate = $now->format('Y-m-d');
        $this->startDate = $now->copy()->startOfMonth()->format('Y-m-d');
        $this->productId = null;
        $this->activeFilter = 'current_month';
    }

    public function setPeriodCurrentMonth(): void
    {
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
        $this->activeFilter = 'current_month';
    }

    public function setPeriodLastMonth(): void
    {
        $lastMonth = now()->subMonth();
        $this->startDate = $lastMonth->copy()->startOfMonth()->format('Y-m-d');
        $this->endDate = $lastMonth->copy()->endOfMonth()->format('Y-m-d');
        $this->activeFilter = 'last_month';
    }

    public function setPeriodYearToDate(): void
    {
        $this->startDate = now()->startOfYear()->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
        $this->activeFilter = 'ytd';
    }

    public function setPeriodAllHistory(): void
    {
        $data = $this->statementData;
        $firstSub = $data['client']['first_subscription_date'] ?? null;
        if ($firstSub) {
            $this->startDate = Carbon::createFromFormat('d/m/Y', $firstSub)->format('Y-m-d');
        } else {
            $this->startDate = now()->subYears(5)->format('Y-m-d');
        }
        $this->endDate = now()->format('Y-m-d');
        $this->activeFilter = 'all';
    }
}
