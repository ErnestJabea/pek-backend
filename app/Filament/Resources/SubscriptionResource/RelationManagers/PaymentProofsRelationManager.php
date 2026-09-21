<?php

namespace App\Filament\Resources\SubscriptionResource\RelationManagers;

use App\Models\Notification;
use App\Models\PaymentProof;
use App\Services\Payments\PaymentAudit;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
            Tables\Columns\TextColumn::make('scan_status')->label('Antivirus')->badge(),
            Tables\Columns\TextColumn::make('review_status')->label('Revue')->badge(),
            Tables\Columns\TextColumn::make('review_note')->label('Motif')->wrap(),
        ])->actions([
            Tables\Actions\Action::make('download')->label('Télécharger')
                ->visible(fn (PaymentProof $record) => $record->scan_status === 'clean' && auth()->user()->can('view_payment_proof'))
                ->url(fn (PaymentProof $record) => route('admin.payment-proofs.download', $record))->openUrlInNewTab(),
            Tables\Actions\Action::make('review')->label('Examiner le justificatif')
                ->authorize(fn () => auth()->user()->can('review_payment_proof'))
                ->form([
                    Forms\Components\Select::make('status')->label('Décision sur le document')->options(['examined' => 'Document examiné', 'replacement_requested' => 'Demander un remplacement'])->required(),
                    Forms\Components\Textarea::make('note')->label('Motif / observations visibles par le client')->required()->maxLength(2000),
                ])->action(function (PaymentProof $record, array $data) {
                    abort_unless(auth()->user()->can('review_payment_proof'), 403);
                    abort_unless($record->scan_status === 'clean', 423, 'Analyse antivirus requise.');
                    DB::transaction(function () use ($record, $data) {
                        $record->update(['review_status' => $data['status'], 'review_note' => $data['note'], 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
                        PaymentAudit::record($record->subscription_id, 'proof_reviewed', ['proof_id' => $record->id, 'status' => $data['status'], 'note' => $data['note']], auth()->id());
                    });
                    Notification::create(['user_id' => $record->user_id, 'title' => 'Justificatif de virement examiné', 'body' => $data['note'], 'type' => 'subscription']);
                }),
        ]);
    }
}
