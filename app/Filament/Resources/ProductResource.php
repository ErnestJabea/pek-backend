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
                    ->numeric()
                    ->label(__('messages.vl'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('seuil_minimum')
                    ->label(__('messages.seuil_minimum'))
                    ->numeric()
                    ->sortable(),
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
                    ->boolean()
                    ->trueIcon('heroicon-o-document-text')
                    ->falseIcon('heroicon-o-minus-small')
                    ->trueColor('success')
                    ->falseColor('gray'),
                Tables\Columns\IconColumn::make('depliant_en')
                    ->label('Brochure EN')
                    ->boolean()
                    ->trueIcon('heroicon-o-document-text')
                    ->falseIcon('heroicon-o-minus-small')
                    ->trueColor('info')
                    ->falseColor('gray'),
                Tables\Columns\IconColumn::make('document_information')
                    ->label('Doc. Info FR')
                    ->boolean()
                    ->trueIcon('heroicon-o-document-text')
                    ->falseIcon('heroicon-o-minus-small')
                    ->trueColor('success')
                    ->falseColor('gray'),
                Tables\Columns\IconColumn::make('document_information_en')
                    ->label('Doc. Info EN')
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
                //
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
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
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
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
