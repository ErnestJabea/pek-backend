<?php

namespace App\Filament\Resources\PaymentMethodResource\Pages;

use App\Filament\Resources\PaymentMethodResource;
use App\Models\PaymentMethod;
use Database\Seeders\PaymentMethodSeeder;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPaymentMethods extends ListRecords
{
    protected static string $resource = PaymentMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('seedDefaults')
                ->label('Initialiser les Moyens de Paiement')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Réinitialiser les moyens de paiement par défaut')
                ->modalDescription('Cette action va initialiser les 4 moyens de paiement standards (Carte Stripe, Orange Money, MTN MoMo, Virement bancaire).')
                ->action(function () {
                    $seeder = new PaymentMethodSeeder();
                    $seeder->run();

                    Notification::make()
                        ->title('Moyens de paiement initialisés')
                        ->body(PaymentMethod::count() . ' moyens de paiement sont configurés.')
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
