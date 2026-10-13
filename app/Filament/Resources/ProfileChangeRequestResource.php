<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProfileChangeRequestResource\Pages;
use App\Models\ProfileChangeRequest;
use App\Services\ProfileChangeRequestService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProfileChangeRequestResource extends Resource
{
    protected static ?string $model = ProfileChangeRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Conformité & Contrôle';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Modifications Profil & RIB';

    protected static ?string $modelLabel = 'Demande de modification';

    protected static ?string $pluralModelLabel = 'Demandes de modifications Profil & RIB';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date demande')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Client')
                    ->formatStateUsing(fn ($record) => "{$record->user?->first_name} {$record->user?->last_name}")
                    ->description(fn ($record) => $record->user?->email)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('user', function ($q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                              ->orWhere('last_name', 'like', "%{$search}%")
                              ->orWhere('email', 'like', "%{$search}%");
                        });
                    }),

                Tables\Columns\BadgeColumn::make('request_type')
                    ->label('Type de demande')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'category_nature' => 'Nature & Catégorie',
                        'bank_account' => 'Coordonnées Bancaires (RIB)',
                        default => $state,
                    })
                    ->colors([
                        'primary' => 'category_nature',
                        'success' => 'bank_account',
                    ]),

                Tables\Columns\TextColumn::make('details')
                    ->label('Données demandées')
                    ->formatStateUsing(function ($record) {
                        if ($record->request_type === 'category_nature') {
                            $nature = $record->requested_data['nature_client'] ?? 'Non spécifiée';
                            $cat = $record->requested_data['categorie_client'] ?? 'Non spécifiée';
                            return "{$nature} » {$cat}";
                        }
                        $bank = $record->requested_data['bank_name'] ?? 'Banque';
                        $acc = $record->requested_data['account_number'] ?? '';
                        return "{$bank} ({$acc})";
                    })
                    ->description(function ($record) {
                        if ($record->request_type === 'category_nature' && !empty($record->current_data)) {
                            $curNat = $record->current_data['nature_client'] ?? '-';
                            $curCat = $record->current_data['categorie_client'] ?? '-';
                            return "Actuel : {$curNat} » {$curCat}";
                        }
                        if ($record->request_type === 'bank_account' && !empty($record->requested_data['account_holder_name'])) {
                            return "Titulaire : " . $record->requested_data['account_holder_name'];
                        }
                        return null;
                    }),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Statut')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'En attente Kori',
                        'approved' => 'Approuvée',
                        'rejected' => 'Rejetée',
                        default => $state,
                    })
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ]),

                Tables\Columns\TextColumn::make('reviewer.first_name')
                    ->label('Traité par')
                    ->formatStateUsing(fn ($record) => $record->reviewer ? "{$record->reviewer->first_name} {$record->reviewer->last_name}" : '-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'pending' => 'En attente',
                        'approved' => 'Approuvées',
                        'rejected' => 'Rejetées',
                    ])
                    ->default('pending'),

                Tables\Filters\SelectFilter::make('request_type')
                    ->label('Type de demande')
                    ->options([
                        'category_nature' => 'Nature & Catégorie',
                        'bank_account' => 'Coordonnées Bancaires',
                    ]),
            ])
            ->actions([
                // Action pour voir les détails et justificatifs
                Tables\Actions\Action::make('view_details')
                    ->label('Détails')
                    ->icon('heroicon-m-eye')
                    ->color('gray')
                    ->modalHeading('Détails de la demande')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer')
                    ->modalContent(function ($record) {
                        return view('filament.modals.profile_change_details', ['record' => $record]);
                    }),

                // Action Approuver
                Tables\Actions\Action::make('approve')
                    ->label('Approuver')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Approuver la mise à jour')
                    ->modalDescription('Les données actives du client seront immédiatement mises à jour et le client recevra un email et une notification InApp.')
                    ->action(function ($record) {
                        app(ProfileChangeRequestService::class)->approveRequest($record, auth()->user());
                        Notification::make()
                            ->title('Demande approuvée avec succès')
                            ->success()
                            ->send();
                    }),

                // Action Rejeter
                Tables\Actions\Action::make('reject')
                    ->label('Rejeter')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Motif du rejet (transmis au client)')
                            ->required()
                            ->placeholder('Ex: Le document de RIB est illisible ou le nom ne correspond pas.'),
                    ])
                    ->action(function ($record, array $data) {
                        app(ProfileChangeRequestService::class)->rejectRequest($record, auth()->user(), $data['reason']);
                        Notification::make()
                            ->title('Demande rejetée')
                            ->warning()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProfileChangeRequests::route('/'),
        ];
    }
}
