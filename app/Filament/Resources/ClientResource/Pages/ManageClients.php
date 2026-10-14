<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use App\Models\Client;
use App\Services\ClientActivationService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageClients extends ManageRecords
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->after(function (Client $record, array $data) {
                    if (! empty($data['send_activation_email'])) {
                        try {
                            app(ClientActivationService::class)->sendActivationEmail($record);
                            Notification::make()
                                ->title('E-mail d\'activation envoyé')
                                ->body("Un code de vérification a été transmis à {$record->email}.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::error('Erreur envoi activation client : ' . $e->getMessage());
                            Notification::make()
                                ->title('Client créé, mais erreur d\'envoi d\'e-mail')
                                ->body($e->getMessage())
                                ->warning()
                                ->send();
                        }
                    }
                }),
        ];
    }
}
