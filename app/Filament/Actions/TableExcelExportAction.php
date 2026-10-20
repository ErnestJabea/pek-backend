<?php

namespace App\Filament\Actions;

use Filament\Forms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TableExcelExportAction
{
    /**
     * Crée une action d'en-tête de tableau pour exporter la liste (filtrée ou complète)
     */
    public static function make(string $name = 'export_excel'): Action
    {
        return Action::make($name)
            ->label('Exporter en Excel (.xlsx)')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->modalHeading('Exporter les données en Excel (.xlsx)')
            ->modalDescription('Sélectionnez les colonnes que vous souhaitez inclure dans le fichier exporté.')
            ->modalSubmitActionLabel('Télécharger le fichier')
            ->form(fn (Table $table) => static::getFormSchema($table))
            ->action(function (array $data, Table $table, $livewire): ?BinaryFileResponse {
                return static::exportFromTable($data, $table, $livewire);
            });
    }

    /**
     * Crée une action de masse (BulkAction) pour exporter uniquement les lignes cochées
     */
    public static function makeBulk(string $name = 'export_excel_bulk'): BulkAction
    {
        return BulkAction::make($name)
            ->label('Exporter la sélection en Excel (.xlsx)')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->modalHeading('Exporter la sélection en Excel (.xlsx)')
            ->modalDescription('Sélectionnez les colonnes que vous souhaitez inclure pour les éléments sélectionnés.')
            ->modalSubmitActionLabel('Télécharger le fichier')
            ->form(fn (Table $table) => static::getFormSchema($table))
            ->action(function (Collection $records, array $data, Table $table): ?BinaryFileResponse {
                return static::exportFromCollection($records, $data, $table);
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * Définition du formulaire modal avec sélection dynamique des colonnes
     */
    public static function getFormSchema(Table $table): array
    {
        $columns = $table->getColumns();
        $columnOptions = [];
        $defaultSelected = [];

        foreach ($columns as $name => $col) {
            $label = $col->getLabel();
            if (empty($label)) {
                $label = str($name)->replace('.', ' ')->headline()->toString();
            }
            $columnOptions[$name] = (string) $label;
            if (! $col->isToggledHiddenByDefault()) {
                $defaultSelected[] = $name;
            }
        }

        if (empty($defaultSelected)) {
            $defaultSelected = array_keys($columnOptions);
        }

        return [
            Forms\Components\CheckboxList::make('columns')
                ->label('Colonnes à inclure dans l\'export')
                ->options($columnOptions)
                ->default($defaultSelected)
                ->columns(2)
                ->bulkToggleable()
                ->searchable()
                ->required(),

            Forms\Components\Grid::make(2)
                ->schema([
                    Forms\Components\Select::make('format')
                        ->label('Format de fichier')
                        ->options([
                            'xlsx' => 'Fichier Excel (.xlsx)',
                            'csv' => 'Fichier CSV (.csv séparateur virgule)',
                        ])
                        ->default('xlsx')
                        ->required(),

                    Forms\Components\TextInput::make('filename')
                        ->label('Nom du fichier (sans extension)')
                        ->default(fn () => 'export-' . now()->format('Ymd-His'))
                        ->required(),
                ]),
        ];
    }

    /**
     * Exécute l'export pour toutes les lignes filtrées du tableau
     */
    public static function exportFromTable(array $data, Table $table, $livewire): ?BinaryFileResponse
    {
        $query = null;
        if ($livewire && method_exists($livewire, 'getFilteredTableQuery')) {
            try {
                $query = $livewire->getFilteredTableQuery();
            } catch (\Throwable) {
                $query = null;
            }
        }
        if (! $query && $livewire && method_exists($livewire, 'getTableQuery')) {
            try {
                $query = $livewire->getTableQuery();
            } catch (\Throwable) {
                $query = null;
            }
        }
        if (! $query && $livewire && method_exists($livewire, 'getResource')) {
            try {
                $resource = $livewire->getResource();
                if ($resource && method_exists($resource, 'getEloquentQuery')) {
                    $query = $resource::getEloquentQuery();
                }
            } catch (\Throwable) {
                $query = null;
            }
        }
        if (! $query) {
            try {
                $query = $table->getQuery();
            } catch (\Throwable) {
                $model = $table->getModel();
                if ($model && class_exists($model)) {
                    $query = $model::query();
                }
            }
        }

        $selectedColumns = (array) ($data['columns'] ?? []);
        $format = $data['format'] ?? 'xlsx';
        $filename = $data['filename'] ?? ('export-' . now()->format('Ymd-His'));

        return static::generateExportFile(
            function ($rowCallback) use ($query) {
                $query->chunk(250, function ($records) use ($rowCallback) {
                    foreach ($records as $record) {
                        $rowCallback($record);
                    }
                });
            },
            $table,
            $selectedColumns,
            $format,
            $filename
        );
    }

    /**
     * Exécute l'export pour une collection de lignes sélectionnées
     */
    public static function exportFromCollection(Collection $records, array $data, Table $table): ?BinaryFileResponse
    {
        $selectedColumns = (array) ($data['columns'] ?? []);
        $format = $data['format'] ?? 'xlsx';
        $filename = $data['filename'] ?? ('export-' . now()->format('Ymd-His'));

        return static::generateExportFile(
            function ($rowCallback) use ($records) {
                foreach ($records as $record) {
                    $rowCallback($record);
                }
            },
            $table,
            $selectedColumns,
            $format,
            $filename
        );
    }

    /**
     * Génère physiquement le fichier .xlsx ou .csv via OpenSpout et prépare le téléchargement
     */
    protected static function generateExportFile(
        callable $recordFeeder,
        Table $table,
        array $selectedColumns,
        string $format,
        string $filename
    ): ?BinaryFileResponse {
        $tableColumns = $table->getColumns();
        $ext = ($format === 'csv') ? 'csv' : 'xlsx';

        $headerLabels = [];
        foreach ($selectedColumns as $colName) {
            $col = $tableColumns[$colName] ?? null;
            $label = $col ? $col->getLabel() : $colName;
            if (empty($label)) {
                $label = str($colName)->replace('.', ' ')->headline()->toString();
            }
            $headerLabels[] = (string) $label;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'fcp_export_') . '.' . $ext;

        if ($ext === 'csv') {
            $writer = new CsvWriter();
        } else {
            $writer = new XlsxWriter();
        }

        $writer->openToFile($tmpPath);
        $writer->addRow(Row::fromValues($headerLabels));

        $recordFeeder(function ($record) use ($writer, $tableColumns, $selectedColumns) {
            $rowValues = [];
            foreach ($selectedColumns as $colName) {
                $col = $tableColumns[$colName] ?? null;
                if (! $col) {
                    $rowValues[] = '';
                    continue;
                }

                $col->record($record);
                $state = $col->getState();
                $val = method_exists($col, 'formatState') ? $col->formatState($state) : $state;

                if (is_array($val)) {
                    $val = implode(', ', $val);
                } elseif ($val instanceof \DateTimeInterface) {
                    $val = $val->format('d/m/Y H:i');
                } elseif (is_bool($val)) {
                    $val = $val ? 'Oui' : 'Non';
                }

                $rowValues[] = is_string($val) ? trim(strip_tags($val)) : ($val ?? '');
            }

            $writer->addRow(Row::fromValues($rowValues));
        });

        $writer->close();

        $cleanFilename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
        if (! str_ends_with($cleanFilename, '.' . $ext)) {
            $cleanFilename .= '.' . $ext;
        }

        $contentType = ($ext === 'csv')
            ? 'text/csv; charset=UTF-8'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->download($tmpPath, $cleanFilename, [
            'Content-Type' => $contentType,
        ])->deleteFileAfterSend(true);
    }
}
