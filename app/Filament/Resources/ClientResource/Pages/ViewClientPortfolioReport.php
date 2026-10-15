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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_pdf')
                ->label('Télécharger le Relevé PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('warning')
                ->url(fn (): string => route('client.statement.pdf', [
                    'client' => $this->record->id,
                    'start_date' => $this->startDate,
                    'end_date' => $this->endDate,
                    'product_id' => $this->productId,
                ]))
                ->openUrlInNewTab(),

            Action::make('preview_statement')
                ->label('Aperçu / Imprimer')
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->url(fn (): string => route('client.statement.preview', [
                    'client' => $this->record->id,
                    'start_date' => $this->startDate,
                    'end_date' => $this->endDate,
                    'product_id' => $this->productId,
                ]))
                ->openUrlInNewTab(),

            Action::make('back_to_clients')
                ->label('Retour aux clients')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(ClientResource::getUrl('index')),
        ];
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
