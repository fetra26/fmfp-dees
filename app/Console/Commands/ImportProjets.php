<?php

namespace App\Console\Commands;

use App\Imports\ProjetExcelImport;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportProjets extends Command
{
    protected $signature = 'import:projets
                            {fichier? : Chemin du fichier Excel (défaut: IDEE_DE_COLONNES_BASE.xlsx)}
                            {--dry-run : Simuler sans enregistrer en base}';

    protected $description = 'Importer les projets depuis le fichier Excel DEES';

    public function handle(): int
    {
        $fichier = $this->argument('fichier') ?? base_path('IDEE_DE_COLONNES_BASE.xlsx');

        if (! file_exists($fichier)) {
            $this->error("Fichier introuvable : $fichier");
            return 1;
        }

        $this->info("Fichier : $fichier");
        $this->info("Taille  : " . round(filesize($fichier) / 1024, 1) . " Ko");

        // Détecter le format
        [$headingRow, $startRow, $format] = $this->detecterFormat($fichier);

        $labels = [
            'idee'     => 'IDEE_DE_COLONNES_BASE (82 colonnes)',
            'dees_bdd' => 'DEES_BDD_VF (73 colonnes — 17 partenaires)',
        ];
        $this->info("Format  : " . ($labels[$format] ?? 'inconnu'));
        $this->info("        en-têtes ligne $headingRow, données à partir de ligne $startRow");

        if ($this->option('dry-run')) {
            $this->warn('Mode dry-run — aucune donnée ne sera enregistrée.');
        }

        $import = new ProjetExcelImport();
        $import->setFormat($headingRow, $startRow, $format);

        $this->newLine();
        $this->info('Import en cours, veuillez patienter...');

        try {
            Excel::import($import, $fichier);
            $import->importerFeuillePartenaires($fichier);
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('Erreur : ' . $e->getMessage());
            return 1;
        }

        $this->newLine();

        $this->info("✔ Projets importés : {$import->imported}");
        if ($import->skipped > 0) {
            $this->warn("  Ignorés         : {$import->skipped} (lignes vides ou invalides)");
        }

        if ($import->partenairesImportes > 0 || $import->partenairesNonAssocies > 0) {
            $this->info("✔ Partenaires importés    : {$import->partenairesImportes}");
            if ($import->partenairesNonAssocies > 0) {
                $this->warn("  Partenaires non associés : {$import->partenairesNonAssocies}");
            }
        }

        if (! empty($import->doublonsProjets)) {
            $this->newLine();
            $this->warn(count($import->doublonsProjets) . ' doublon(s) projet fusionné(s) :');
            foreach (array_slice($import->doublonsProjets, 0, 10) as $d) {
                $this->line("  • $d");
            }
        }

        if (! empty($import->partenairesRejets)) {
            $this->newLine();
            $this->warn(count($import->partenairesRejets) . ' partenaire(s) rejeté(s) :');
            foreach (array_slice($import->partenairesRejets, 0, 10) as $r) {
                $this->line("  • $r");
            }
        }

        if (! empty($import->errors)) {
            $this->newLine();
            $this->warn('Premières 10 lignes en erreur :');
            foreach (array_slice($import->errors, 0, 10) as $err) {
                $this->line("  • $err");
            }
            if (count($import->errors) > 10) {
                $this->line('  ... et ' . (count($import->errors) - 10) . ' autres erreurs (voir log).');
            }
        }

        $this->newLine();
        $this->table(
            ['Table', 'Enregistrements'],
            [
                ['porteur',      \App\Models\Porteur::count()],
                ['projet',       \App\Models\Projet::count()],
                ['porteur_proj', \App\Models\PorteurProj::count()],
                ['partenaire',   \App\Models\Partenaire::count()],
                ['benef',        \App\Models\Benef::count()],
                ['formation',    \App\Models\Formation::count()],
                ['paiement',     \App\Models\Paiement::count()],
            ]
        );

        return 0;
    }

    /**
     * Détecte le format du fichier Excel.
     *
     * @return array{0:int, 1:int, 2:string}  [headingRow, startRow, format]
     *   format = 'idee' | 'dees_bdd'
     */
    private function detecterFormat(string $chemin): array
    {
        try {
            $spreadsheet = IOFactory::load($chemin);
            $sheet = $spreadsheet->getActiveSheet();
            $a1 = (string) ($sheet->getCellByColumnAndRow(1, 1)->getValue() ?? '');

            // Détection format DEES_BDD : présence de "Matricule PA 1" en col J
            $j1 = (string) ($sheet->getCellByColumnAndRow(10, 1)->getValue() ?? '');
            $i1 = (string) ($sheet->getCellByColumnAndRow(9, 1)->getValue() ?? '');
            if (str_contains(mb_strtolower($j1), 'matricule pa')
                || str_contains(mb_strtolower($i1), 'partenaires associés pa')) {
                return [1, 2, 'dees_bdd'];
            }

            // Sinon : format IDEE — officiel (catégories L1 en majuscules) ou simplifié
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
