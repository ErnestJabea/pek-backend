<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationGroup = 'Produits & Trésorerie';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('messages.product');
    }

    public static function getPluralModelLabel(): string
    {
        return __('messages.products');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Contenu Multilingue')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Français (FR)')
                            ->icon('heroicon-o-language')
                            ->schema([
                                Forms\Components\TextInput::make('libelle')
                                    ->label('Libellé / Titre (FR)')
                                    ->required(),
                                Forms\Components\Textarea::make('description')
                                    ->label('Description (FR)')
                                    ->columnSpanFull(),
                                Forms\Components\FileUpload::make('depliant')
                                    ->label('Dépliant / Brochure FR (PDF)')
                                    ->acceptedFileTypes(['application/pdf'])
                                    ->disk('public')
                                    ->directory('products/depliants')
                                    ->visibility('public')
                                    ->maxSize(10240)
                                    ->downloadable()
                                    ->openable()
                                    ->previewable(false)
                                    ->helperText('Brochure commerciale en français (PDF, max. 10 Mo).'),
                                Forms\Components\FileUpload::make('document_information')
                                    ->label('Document d\'information FR (DICI / Prospectus PDF)')
                                    ->acceptedFileTypes(['application/pdf'])
                                    ->disk('public')
                                    ->directory('products/documents_information')
                                    ->visibility('public')
                                    ->maxSize(10240)
                                    ->downloadable()
                                    ->openable()
                                    ->previewable(false)
                                    ->helperText('Document d\'information clé FR (PDF, max. 10 Mo).'),
                            ])->columns(2),

                        Forms\Components\Tabs\Tab::make('English (EN)')
                            ->icon('heroicon-o-globe-alt')
                            ->schema([
                                Forms\Components\TextInput::make('libelle_en')
                                    ->label('Name / Title (EN)')
                                    ->placeholder('e.g. FCP Money Market Fund'),
                                Forms\Components\Textarea::make('description_en')
                                    ->label('Description (EN)')
                                    ->placeholder('English description of the fund...')
                                    ->columnSpanFull(),
                                Forms\Components\FileUpload::make('depliant_en')
                                    ->label('Brochure EN (PDF)')
                                    ->acceptedFileTypes(['application/pdf'])
                                    ->disk('public')
                                    ->directory('products/depliants')
                                    ->visibility('public')
                                    ->maxSize(10240)
                                    ->downloadable()
                                    ->openable()
                                    ->previewable(false)
                                    ->helperText('English commercial brochure (PDF, max 10MB).'),
                                Forms\Components\FileUpload::make('document_information_en')
                                    ->label('Key Information Document EN (PDF)')
                                    ->acceptedFileTypes(['application/pdf'])
                                    ->disk('public')
                                    ->directory('products/documents_information')
                                    ->visibility('public')
                                    ->maxSize(10240)
                                    ->downloadable()
                                    ->openable()
                                    ->previewable(false)
                                    ->helperText('English Key Information Document / Prospectus (PDF, max 10MB).'),
                            ])->columns(2),
                    ])->columnSpanFull(),

                Forms\Components\Section::make('Paramètres Financiers & Risque')
                    ->schema([
                        Forms\Components\Select::make('risk_level')
                            ->label('Niveau de risque / Risk Level')
                            ->options([
                                'faible' => 'Faible / Low',
                                'modere' => 'Modéré / Moderate',
                                'eleve' => 'Élevé / High',
                                'non_renseigne' => 'Non renseigné / Not specified',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('vl')
                            ->required()
                            ->numeric()
                            ->label(__('messages.vl_initial'))
                            ->disabledOn('edit')
                            ->helperText(__('messages.manage_vl_history')),
                        Forms\Components\TextInput::make('seuil_minimum')
                            ->label(__('messages.seuil_minimum'))
                            ->required()
                            ->numeric(),
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('messages.is_active'))
                            ->default(true)
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('libelle')
                    ->label(__('messages.product'))
                    ->searchable()
                    ->description(fn (Product $record) => $record->libelle_en ? "EN: {$record->libelle_en}" : null),
                Tables\Columns\TextColumn::make('vl')
                    ->label(__('messages.vl'))
                    ->formatStateUsing(function ($state) {
                        if ($state === null || $state === '') return '-';
                        $str = rtrim(rtrim((string) $state, '0'), '.');
                        $parts = explode('.', $str);
                        $intPart = number_format((float) ($parts[0] ?? 0), 0, '', ' ');
                        if (!isset($parts[1]) || $parts[1] === '') {
                            return $intPart . ',00 FCFA';
                        }
                        if (strlen($parts[1]) === 1) {
                            return $intPart . ',' . $parts[1] . '0 FCFA';
                        }
                        return $intPart . ',' . $parts[1] . ' FCFA';
                    })
                    ->description(function (Product $record) {
                        $state = $record->vl;
                        if ($state === null || $state === '') return null;
                        $str = rtrim(rtrim((string) $state, '0'), '.');
                        $parts = explode('.', $str);
                        if (isset($parts[1]) && strlen($parts[1]) > 2) {
                            return 'Affichage client : ' . number_format((float) $state, 2, ',', ' ') . ' FCFA';
                        }
                        return null;
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('seuil_minimum')
                    ->label(__('messages.seuil_minimum'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('risk_level')
                    ->label('Risque / Risk')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'faible' => 'Faible / Low',
                        'modere' => 'Modéré / Moderate',
                        'eleve' => 'Élevé / High',
                        default => 'Non renseigné',
                    }),
                Tables\Columns\IconColumn::make('depliant')
                    ->label('Brochure FR')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->boolean()
                    ->trueIcon('heroicon-o-document-text')
                    ->falseIcon('heroicon-o-minus-small')
                    ->trueColor('success')
                    ->falseColor('gray'),
                Tables\Columns\IconColumn::make('depliant_en')
                    ->label('Brochure EN')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->boolean()
                    ->trueIcon('heroicon-o-document-text')
                    ->falseIcon('heroicon-o-minus-small')
                    ->trueColor('info')
                    ->falseColor('gray'),
                Tables\Columns\IconColumn::make('document_information')
                    ->label('Doc. Info FR')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->boolean()
                    ->trueIcon('heroicon-o-document-text')
                    ->falseIcon('heroicon-o-minus-small')
                    ->trueColor('success')
                    ->falseColor('gray'),
                Tables\Columns\IconColumn::make('document_information_en')
                    ->label('Doc. Info EN')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->boolean()
                    ->trueIcon('heroicon-o-document-text')
                    ->falseIcon('heroicon-o-minus-small')
                    ->trueColor('info')
                    ->falseColor('gray'),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label(__('messages.is_active'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('messages.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                \App\Filament\Filters\DashboardFilter::make(static::class),
                //
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('update_vl')
                        ->label('Mettre à jour la VL')
                        ->icon('heroicon-o-chart-bar')
                        ->color('warning')
                        ->visible(fn (Product $record) => auth()->user()?->hasRole('super_admin') || auth()->user()?->can('update_product_vl') || auth()->user()?->can('create_product_vl'))
                        ->form([
                            Forms\Components\Placeholder::make('current_vl_info')
                                ->label('Valeur Liquidative actuelle')
                                ->content(function (Product $record) {
                                    $state = $record->vl;
                                    $str = rtrim(rtrim((string) $state, '0'), '.');
                                    $parts = explode('.', $str);
                                    $intPart = number_format((float) ($parts[0] ?? 0), 0, '', ' ');
                                    $decPart = isset($parts[1]) ? (strlen($parts[1]) === 1 ? $parts[1].'0' : $parts[1]) : '00';
                                    $fullDisplay = $intPart . ',' . $decPart . ' FCFA';
                                    $dateText = $record->vls()->orderByDesc('date_vl')->value('date_vl')
                                        ? ' (au ' . \Carbon\Carbon::parse($record->vls()->orderByDesc('date_vl')->value('date_vl'))->format('d/m/Y') . ')'
                                        : '';
                                    return $fullDisplay . $dateText;
                                }),
                            Forms\Components\DatePicker::make('date_vl')
                                ->label('Date de valeur de la nouvelle VL')
                                ->default(now()->toDateString())
                                ->required(),
                            Forms\Components\TextInput::make('vl')
                                ->label('Nouvelle Valeur Liquidative (FCFA)')
                                ->numeric()
                                ->step('any')
                                ->required()
                                ->prefix('XAF')
                                ->helperText('Saisissez toutes les décimales officielles. La VL s\'affiche à 2 décimales mais toutes les décimales sont conservées pour les calculs de valorisation et de parts.'),
                        ])
                        ->action(function (Product $record, array $data): void {
                            \App\Models\ProductVl::updateOrCreate(
                                [
                                    'product_id' => $record->id,
                                    'date_vl' => $data['date_vl'],
                                ],
                                [
                                    'vl' => $data['vl'],
                                ]
                            );
                            $record->updateLatestVl();

                            \Filament\Notifications\Notification::make()
                                ->title('VL mise à jour avec succès')
                                ->body("La VL du fonds {$record->libelle} a été fixée à " . number_format((float) $data['vl'], 2, ',', ' ') . " FCFA au " . \Carbon\Carbon::parse($data['date_vl'])->format('d/m/Y') . ".")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('view_depliant_fr')
                        ->label('Brochure FR')
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->visible(fn (Product $record) => filled($record->depliant))
                        ->url(fn (Product $record) => $record->depliant_url)
                        ->openUrlInNewTab(),
                    Tables\Actions\Action::make('view_depliant_en')
                        ->label('Brochure EN')
                        ->icon('heroicon-o-document-text')
                        ->color('info')
                        ->visible(fn (Product $record) => filled($record->depliant_en))
                        ->url(fn (Product $record) => $record->depliant_en_url)
                        ->openUrlInNewTab(),
                    Tables\Actions\Action::make('view_doc_info_fr')
                        ->label('Doc Info FR')
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->visible(fn (Product $record) => filled($record->document_information))
                        ->url(fn (Product $record) => $record->document_information_url)
                        ->openUrlInNewTab(),
                    Tables\Actions\Action::make('view_doc_info_en')
                        ->label('Doc Info EN')
                        ->icon('heroicon-o-document-text')
                        ->color('info')
                        ->visible(fn (Product $record) => filled($record->document_information_en))
                        ->url(fn (Product $record) => $record->document_information_en_url)
                        ->openUrlInNewTab(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->headerActions([
                \App\Filament\Actions\TableExcelExportAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    \App\Filament\Actions\TableExcelExportAction::makeBulk(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\VlsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
