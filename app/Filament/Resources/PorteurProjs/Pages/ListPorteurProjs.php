<?php

namespace App\Filament\Resources\PorteurProjs\Pages;

use App\Exports\ProjetsExport;
use App\Filament\Resources\PorteurProjs\PorteurProjResource;
use App\Imports\ProjetExcelImport;
use App\Jobs\ClassifyAlertsJob;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ListPorteurProjs extends ListRecords
{
    protected static string $resource = PorteurProjResource::class;

    /** Alerte projets orphelins (créés sans porteur_proj) — visible en haut de page */
    public function getSubheading(): ?string
    {
        $orphelinsCount = \App\Models\Projet::query()
            ->whereDoesntHave('porteurProjs')
            ->count();

        if ($orphelinsCount === 0) return null;

        return "⚠ {$orphelinsCount} projet(s) sans porteur — à compléter";
    }

    protected function getHeaderActions(): array
    {
        $orphelinsCount = \App\Models\Projet::query()
            ->whereDoesntHave('porteurProjs')
            ->count();

        return array_filter([
            $orphelinsCount > 0
                ? Action::make('voir_orphelins')
                    ->label("{$orphelinsCount} orphelin(s) à compléter")
                    ->color('warning')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->modalHeading('Projets sans porteur — à compléter')
                    ->modalDescription('Ces projets ont été créés mais n\'ont aucun porteur associé. Cliquez sur "Compléter" pour ajouter le porteur et les données de financement.')
                    ->modalContent(fn () => view('filament.modals.projets-orphelins', [
                        'projets' => \App\Models\Projet::query()
                            ->whereDoesntHave('porteurProjs')
                            ->orderByDesc('created_at')
                            ->limit(50)
                            ->get(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer')
                    ->modalWidth('4xl')
                : null,
            ...$this->buildStandardHeaderActions(),
        ]);
    }

    protected function buildStandardHeaderActions(): array
    {
        return [
            // ═════════════ MENU IMPORT (2 sous-actions) ═════════════
            ActionGroup::make([
                Action::make('importer_excel')
                    ->label('Importer un fichier (upload)')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('info')
                    ->modalHeading('Importer un fichier Excel / CSV')
                    ->modalDescription('Formats acceptés : .xlsx, .xls, .csv, .ods (taille max : 100 Mo).')
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
                            ->maxSize(102400)
                            ->helperText('Glissez votre fichier ici ou cliquez pour le sélectionner'),
                    ])
                    ->action(function (array $data): void {
                        $fichier = is_array($data['fichier']) ? reset($data['fichier']) : $data['fichier'];
                        $chemin  = Storage::disk('public')->path($fichier);
                        $this->lancerImport($chemin);
                    }),

                Action::make('importer_chemin')
                    ->label('Importer depuis un chemin local')
                    ->icon('heroicon-o-folder-open')
                    ->color('warning')
                    ->modalHeading('Importer depuis un chemin local')
                    ->modalDescription('Pour les gros fichiers (> 100 Mo) ne passant pas par l\'upload web.')
                    ->modalWidth('xl')
                    ->form([
                        TextInput::make('chemin')
                            ->label('Chemin complet du fichier sur le serveur')
                            ->required()
                            ->placeholder('Ex : E:/2026/TESTE SYNCRO CLOUD ADMIN/.../DEES_BDD_VF.xlsx')
                            ->helperText('Le fichier doit être accessible par le serveur. Utilisez / ou \\ comme séparateur.'),
                    ])
                    ->action(function (array $data): void {
                        $chemin = str_replace('\\', '/', trim($data['chemin']));

                        if (! file_exists($chemin)) {
                            Notification::make()
                                ->title('Fichier introuvable')
                                ->body("Le chemin saisi n'existe pas : $chemin")
                                ->danger()
                                ->send();
                            return;
                        }

                        $this->lancerImport($chemin);
                    }),
            ])
                ->label('Importer')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->button(),

            // ═════════════ MENU EXPORT (2 sous-actions) ═════════════
            ActionGroup::make([
                Action::make('export_excel')
                    ->label('Exporter en Excel (.xlsx)')
                    ->icon('heroicon-o-table-cells')
                    ->color('success')
                    ->action(function () {
                        $fichier = 'projets-fmfp-dees-' . now()->format('Y-m-d-His') . '.xlsx';
                        return Excel::download(new ProjetsExport(), $fichier);
                    }),

                Action::make('export_pdf')
                    ->label('Exporter en PDF (rapport direction)')
                    ->icon('heroicon-o-document-text')
                    ->color('danger')
                    ->url(fn () => route('export.pdf-projets'))
                    ->openUrlInNewTab(),
            ])
                ->label('Exporter')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->button(),

            // ═════════════ MENU OUTILS (1 sous-action pour l'instant) ═════
            ActionGroup::make([
                Action::make('classifier_alertes')
                    ->label('Classifier les alertes (🟢🟠🔴)')
                    ->icon('heroicon-o-bell-alert')
                    ->requiresConfirmation()
                    ->modalHeading('Classifier automatiquement les niveaux d\'alerte')
                    ->modalDescription('Recalcule le niveau d\'alerte (verte/orange/rouge) selon la date de fin de convention. Les relances sont créées automatiquement.')
                    ->action(function (): void {
                        $job = new ClassifyAlertsJob();
                        $job->handle();

                        Notification::make()
                            ->title('Classification terminée')
                            ->body(sprintf(
                                '%d projet(s) traité(s), %d reclassifié(s) : 🟢 %d / 🟠 %d / 🔴 %d. %d relance(s) créée(s).',
                                $job->totalTraites, $job->totalReclassifies,
                                $job->nouveauxVerte, $job->nouveauxOrange, $job->nouveauxRouge,
                                $job->relancesCreees
                            ))
                            ->success()
                            ->send();
                    }),
            ])
                ->label('Outils')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('warning')
                ->button(),

            // ═════════════ BOUTON CRÉER (visible) ═════════════════
            CreateAction::make()->label('+ Nouveau projet'),
        ];
    }

    private function lancerImport(string $chemin): void
    {
        // Augmenter les limites pour gros fichiers
        ini_set('memory_limit', '1024M');
        set_time_limit(600);

        $import = new ProjetExcelImport();
        [$headingRow, $startRow, $format] = $this->detecterFormatExcel($chemin);
        $import->setFormat($headingRow, $startRow, $format);

        try {
            Excel::import($import, $chemin);
            // Feuille "Partenaires" du nouveau template (silencieux si absente)
            $import->importerFeuillePartenaires($chemin);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Erreur d\'import')
                ->body($e->getMessage())
                ->danger()
                ->send();
            return;
        }

        $labelFormat = match ($format) {
            'dees_bdd' => 'DEES_BDD_VF (73 colonnes)',
            'idee'     => 'IDEE_DE_COLONNES_BASE (82 colonnes)',
            default    => $format,
        };

        // Message principal
        $lignes = [
            sprintf('%d projet(s) importé(s) • %d ignoré(s)', $import->imported, $import->skipped),
            "Format détecté : {$labelFormat}",
        ];

        if ($import->partenairesImportes > 0 || $import->partenairesNonAssocies > 0) {
            $lignes[] = sprintf(
                '👥 Partenaires : %d importés, %d non associés',
                $import->partenairesImportes,
                $import->partenairesNonAssocies
            );
        }

        // Doublons projets détectés (fusion automatique)
        if (! empty($import->doublonsProjets)) {
            $lignes[] = sprintf('⚠️ %d doublon(s) projet fusionné(s) automatiquement', count($import->doublonsProjets));
        }

        // Détails des rejets partenaires (max 5)
        if (! empty($import->partenairesRejets)) {
            $lignes[] = "❌ Partenaires rejetés (extrait) :";
            foreach (array_slice($import->partenairesRejets, 0, 5) as $rejet) {
                $lignes[] = "   • {$rejet}";
            }
            if (count($import->partenairesRejets) > 5) {
                $lignes[] = sprintf('   … et %d autre(s)', count($import->partenairesRejets) - 5);
            }
        }

        $hasWarnings = ! empty($import->errors)
            || ! empty($import->doublonsProjets)
            || $import->partenairesNonAssocies > 0;

        Notification::make()
            ->title($hasWarnings ? 'Import terminé avec avertissements' : 'Import réussi')
            ->body(implode("\n", $lignes))
            ->{$hasWarnings ? 'warning' : 'success'}()
            ->persistent()
            ->send();
    }

    /**
     * @return array{0:int, 1:int, 2:string}  [headingRow, startRow, format]
     */
    private function detecterFormatExcel(string $chemin): array
    {
        try {
            $spreadsheet = IOFactory::load($chemin);
            $sheet       = $spreadsheet->getActiveSheet();
            $a1          = (string) ($sheet->getCellByColumnAndRow(1, 1)->getValue() ?? '');
            $i1          = (string) ($sheet->getCellByColumnAndRow(9, 1)->getValue() ?? '');
            $j1          = (string) ($sheet->getCellByColumnAndRow(10, 1)->getValue() ?? '');

            // Détection DEES_BDD : "Matricule PA 1" ou "Partenaires associés PA 1"
            if (str_contains(mb_strtolower($j1), 'matricule pa')
                || str_contains(mb_strtolower($i1), 'partenaires associés pa')) {
                return [1, 2, 'dees_bdd'];
            }

            // Format IDEE officiel (catégories en majuscules sur L1)
            $estOfficiel = mb_strtoupper($a1) === $a1
                && strlen(trim($a1)) > 3
                && ! str_contains(mb_strtolower($a1), 'porteur')
                && ! str_contains(mb_strtolower($a1), 'secteur')
                && ! str_contains(mb_strtolower($a1), 'intitul');

            return $estOfficiel ? [2, 4, 'idee'] : [1, 2, 'idee'];
        } catch (\Throwable) {
            return [1, 2, 'idee'];
        }
    }
}
