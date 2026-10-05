<?php

namespace App\Filament\Actions;

use App\Models\BankDetail;
use App\Models\Subscription;
use App\Services\Payments\BankPaymentService;
use Filament\Actions\MountableAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class BankSubscriptionActions
{
    public static function confirm(string $actionClass, string $name = 'reviewAccounting'): MountableAction
    {
        return $actionClass::make($name)
            ->label('Confirmer les fonds reçus')
            ->authorize(fn () => auth()->user()->can('confirm_bank_payment'))
            ->form([
                Forms\Components\DatePicker::make('received_at')->label('Date effective de réception des fonds')->maxDate(now())->required(),
                Forms\Components\TextInput::make('amount')->label('Montant effectivement reçu (XAF)')->numeric()->minValue(1)->required(),
                Forms\Components\TextInput::make('reference')->label('Référence de l’écriture bancaire')->maxLength(120)->required(),
                Forms\Components\Select::make('bank_detail_id')->label('Compte ayant reçu les fonds (ancienne demande)')
                    ->options(fn () => BankDetail::pluck('bank_name', 'id'))
                    ->visible(fn (Subscription $record) => ! $record->bank_snapshot)
                    ->required(fn (Subscription $record) => ! $record->bank_snapshot),
            ])
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Valider le rapprochement comptable interne')
            ->modalDescription('Confirmez-vous la réception des fonds et le rapprochement comptable pour cette souscription ?')
            ->visible(fn (Subscription $record) => in_array($record->moyen_paiement, ['bank_transfer', 'virement']) && ! $record->funds_received_at && $record->statut !== 'Succès')
            ->action(function (Subscription $record, array $data) {
                try {
                    $record = app(BankPaymentService::class)->confirm($record, auth()->user(), $data);
                } catch (HttpExceptionInterface $e) {
                    if (! in_array($e->getStatusCode(), [409, 422], true)) {
                        throw $e;
                    }
                    Notification::make()->title('Confirmation impossible')->body($e->getMessage())->warning()->send();

                    return;
                }

                Notification::make()
                    ->title($record->statut === 'Succès' ? 'Fonds confirmés et parts valorisées' : 'Fonds reçus — VL antérieure à la date de réception manquante')
                    ->body($record->statut === 'Succès' ? $record->nb_parts.' parts attribuées.' : 'Publiez une VL antérieure à la date de réception, puis utilisez Attribuer les parts.')
                    ->status($record->statut === 'Succès' ? 'success' : 'warning')
                    ->send();
            });
    }

    public static function value(string $actionClass): MountableAction
    {
        return $actionClass::make('valueBankParts')
            ->label('Attribuer les parts')->icon('heroicon-o-calculator')->color('success')
            ->authorize(fn () => auth()->user()->can('confirm_bank_payment'))
            ->visible(fn (Subscription $record) => in_array($record->moyen_paiement, ['bank_transfer', 'virement'], true)
                && $record->funds_received_at && $record->statut !== 'Succès')
            ->requiresConfirmation()
            ->modalDescription('Les fonds sont déjà confirmés. Calculer les parts avec la dernière VL publiée strictement antérieure à la date de réception.')
            ->action(function (Subscription $record) {
                abort_unless(auth()->user()->can('confirm_bank_payment'), 403);
                try {
                    $record = app(BankPaymentService::class)->value($record);
                } catch (HttpExceptionInterface $e) {
                    if ($e->getStatusCode() !== 422) {
                        throw $e;
                    }
                    Notification::make()->title('Attribution impossible')->body($e->getMessage())->warning()->send();

                    return;
                }
                Notification::make()
                    ->title($record->statut === 'Succès' ? 'Parts attribuées' : 'Fonds reçus — VL manquante')
                    ->body($record->statut === 'Succès' ? $record->nb_parts.' parts attribuées.' : 'Publiez une VL strictement antérieure au '.$record->value_date->format('d/m/Y').', puis relancez cette action.')
                    ->status($record->statut === 'Succès' ? 'success' : 'warning')->send();
            });
    }

    public static function reconcileAction(?string $actionClass = null): MountableAction
    {
        $class = $actionClass ?? \Filament\Tables\Actions\Action::class;

        /** @var MountableAction $action */
        $action = static::confirm($class, 'reconcileBankPayment');

        return $action
            ->label('Rapprochement bancaire')
            ->modalHeading('Valider le rapprochement bancaire');
    }

    public static function valueAction(?string $actionClass = null): MountableAction
    {
        $class = $actionClass ?? \Filament\Tables\Actions\Action::class;

        return static::value($class);
    }
}
