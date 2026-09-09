<?php

namespace App\Filament\Resources\Projets\Pages;

use App\Filament\Resources\Projets\ProjetResource;
use App\Imports\ProjetExcelImport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ListProjets extends ListRecords
{
    protected static string $resource = ProjetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importer_excel')
                ->label('Importer Excel / CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->modalHeading('Import des projets DEES')
                ->modalDescription('Formats acceptés : Excel (.xlsx, .xls), CSV (.csv), OpenDocument (.ods).')
                ->modalWidth('lg')
                ->form([
                    FileUpload::make('fichier')
                        ->label('Fichier à importer')
                        ->required()
                        ->disk('public')
                        ->directory('imports-tmp')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                            'text/plain',
                            'application/csv',
                            'application/vnd.oasis.opendocument.spreadsheet',
                        ])
                        ->maxSize(20480)
                        ->helperText('Formats acceptés : .xlsx, .xls, .csv, .ods'),
                ])
                ->action(function (array $data): void {
                    $fichier = is_array($data['fichier']) ? reset($data['fichier']) : $data['fichier'];
                    $chemin  = Storage::disk('public')->path($fichier);

                    $import = new ProjetExcelImport();

                    // Détection automatique du format du fichier
                    [$headingRow, $startRow] = $this->detecterFormatExcel($chemin);
                    $import->setFormat($headingRow, $startRow);

                    Excel::import($import, $chemin);

                    $message = $import->imported . ' projet(s) importé(s)';
                    if ($import->skipped > 0) {
                        $message .= ', ' . $import->skipped . ' ligne(s) ignorée(s)';
                    }

                    if (! empty($import->errors)) {
                        Notification::make()
                            ->title('Import terminé avec avertissements')
                            ->body($message . '. Consultez les logs pour les détails.')
                            ->warning()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Import réussi (' . "Format : L{$headingRow} en-têtes, données dès L{$startRow})")
                            ->body($message . '.')
                            ->success()
                            ->send();
                    }
                }),

            CreateAction::make(),
        ];
    }

    // Détecte si le fichier est au format DEES officiel (3 lignes d'en-tête)
    // ou au format simplifié (1 ligne d'en-tête)
    private function detecterFormatExcel(string $chemin): array
    {
        try {
            $spreadsheet = IOFactory::load($chemin);
            $sheet       = $spreadsheet->getActiveSheet();
            $cellA1      = (string) ($sheet->getCellByColumnAndRow(1, 1)->getValue() ?? '');
            $cellA2      = (string) ($sheet->getCellByColumnAndRow(1, 2)->getValue() ?? '');

            // Format DEES officiel : ligne 1 = catégorie en majuscules (ex: "INFORMATION GENERAL")
            // et ligne 2 contient les vrais noms de colonnes
            $estFormatOfficiel = mb_strtoupper($cellA1) === $cellA1
                && strlen(trim($cellA1)) > 3
                && ! str_contains(mb_strtolower($cellA1), 'porteur')
                && ! str_contains(mb_strtolower($cellA1), 'secteur')
                && ! str_contains(mb_strtolower($cellA1), 'intitul');

            if ($estFormatOfficiel) {
                // L1=titre section (SIGNALÉTIQUE), L2=en-têtes cols, L3=1ère donnée
                return [2, 3];
            }
        } catch (\Throwable) {
            // En cas d'erreur on utilise le format simplifié
        }

        return [1, 2]; // headingRow=1, startRow=2
    }
}
