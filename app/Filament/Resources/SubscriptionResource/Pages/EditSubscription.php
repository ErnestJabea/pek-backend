<?php

namespace App\Filament\Resources\SubscriptionResource\Pages;

use App\Filament\Actions\BankSubscriptionActions;
use App\Filament\Resources\SubscriptionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditSubscription extends EditRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        $refresh = function () {
            $this->getRecord()->refresh();
            $this->refreshFormData(['statut', 'nb_parts', 'prix_unitaire', 'value_date', 'valuation_status']);
        };

        return [
            BankSubscriptionActions::confirm(Action::class)->after($refresh),
            BankSubscriptionActions::value(Action::class)->after($refresh),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Only notes are editable. Financial transitions must use a checked payment service.
        return array_intersect_key($data, ['internal_notes' => true]);
    }
}
