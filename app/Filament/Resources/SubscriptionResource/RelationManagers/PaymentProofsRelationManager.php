<?php

namespace App\Filament\Resources\SubscriptionResource\RelationManagers;

use App\Models\PaymentProof;
use App\Services\Payments\PaymentProofReviewService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class PaymentProofsRelationManager extends RelationManager
{
    protected static string $relationship = 'paymentProofs';

    protected static ?string $title = 'Justificatifs de virement';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view_payment_proof') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('original_name')->label('Document'),
            Tables\Columns\TextColumn::make('created_at')->label('Déposé le')->dateTime(),
            Tables\Columns\TextColumn::make('declared_date')->label('Date déclarée')->date(),
            Tables\Columns\TextColumn::make('declared_amount')->label('Montant déclaré')->numeric(),
            Tables\Columns\TextColumn::make('scan_status')->label('Antivirus')->badge()->formatStateUsing(fn (string $state) => match ($state) {
                'clean' => 'Analyse réussie', 'infected' => 'Document bloqué', default => 'En quarantaine - analyse requise'
            }),
            Tables\Columns\TextColumn::make('review_status')->label('Revue')->badge(),
            Tables\Columns\TextColumn::make('review_note')->label('Motif')->wrap(),
        ])->actions([
            Tables\Actions\Action::make('download')->label('Télécharger')
                ->visible(fn (PaymentProof $record) => $record->scan_status === 'clean' && auth()->user()->can('view_payment_proof'))
                ->url(fn (PaymentProof $record) => route('admin.payment-proofs.download', $record))->openUrlInNewTab(),
            Tables\Actions\Action::make('review')->label('Examiner le justificatif')
                ->authorize(fn () => auth()->user()->can('review_payment_proof'))
                ->modalDescription('Un document en quarantaine nécessite une analyse antivirus sur le serveur. Le document ne prouve pas la réception des fonds : vérifiez le compte bancaire avant de confirmer le virement.')
                ->form([
                    Forms\Components\Select::make('status')->label('Décision sur le document')->options(fn (PaymentProof $record) => $record->scan_status === 'clean' ? ['examined' => 'Document examiné', 'replacement_requested' => 'Demander un remplacement'] : ['replacement_requested' => 'Demander un remplacement'])->required(),
                    Forms\Components\Textarea::make('note')->label('Motif / observations visibles par le client')->required()->maxLength(2000),
                ])->action(function (PaymentProof $record, array $data) {
                    try {
                        app(PaymentProofReviewService::class)->review($record, auth()->user(), $data);
                    } catch (ValidationException $e) {
                        Notification::make()->title('Examen non enregistré')->body($e->validator->errors()->first())->warning()->send();

                        return;
                    }
                    Notification::make()->title('Décision enregistrée')->success()->send();
                }),
        ]);
    }
}
