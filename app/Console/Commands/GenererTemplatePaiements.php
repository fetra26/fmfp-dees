<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Template Excel dédié à la SAISIE DES PAIEMENTS par l'équipe DAF.
 *
 * Contrairement au TEMPLATE_IMPORT_DEES (qui écrase le projet entier),
 * ce template ajoute UNIQUEMENT des paiements — les autres données du projet
 * restent intactes.
 */
class GenererTemplatePaiements extends Command
{
    protected $signature = 'template:generer-paiements
                            {--fichier=TEMPLATE_PAIEMENTS_DAF.xlsx : Nom du fichier généré}';

    protected $description = 'Génère le template Excel de saisie des paiements pour l\'équipe DAF';

    public function handle(): int
    {
        $sp = new Spreadsheet();
        $sh = $sp->getActiveSheet();
        $sh->setTitle('Paiements');

        // ─── Ligne 1 : Bandeau ───
        $sh->mergeCells('A1:K1');
        $sh->setCellValue('A1', 'PAIEMENTS DAF — 1 ligne = 1 projet (avec ses 3 tranches J1/J2/J3)');
        $sh->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2937']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sh->getRowDimension(1)->setRowHeight(28);

        // ─── Lignes 2-3 : En-têtes + descriptions (format WIDE : 1 ligne = 1 projet) ───
        $colonnes = [
            ['Référence projet',      "Référence UNIQUE du projet (ex: STELLARIX_2026_001) — normalisée automatiquement"],
            ['Référence convention',  "Facultatif — utilisé si Ref projet inconnue"],
            ['Porteur (info)',        "Facultatif — juste indicatif, non utilisé à l'import"],
            ['Date paiement J1',      "Date du paiement 1ère tranche (JJ/MM/AAAA)"],
            ['Montant payé J1',       "Montant J1 (Ariary, sans espace)"],
            ['Date paiement J2',      "Date du paiement 2ème tranche"],
            ['Montant payé J2',       "Montant J2 (Ariary)"],
            ['Date paiement J3',      "Date du paiement 3ème tranche"],
            ['Montant payé J3',       "Montant J3 (Ariary)"],
            ['Allocation consommée',  "Total versé J1+J2+J3 (auto-calculé si vide)"],
            ['Situation',             "Encours / Clôturée / Stand by / Annulé / Remboursement (liste déroulante)"],
        ];

        foreach ($colonnes as $i => [$nom, $desc]) {
            $col = Coordinate::stringFromColumnIndex($i + 1);

            $sh->setCellValue("{$col}2", $nom);
            $sh->getStyle("{$col}2")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            ]);

            $sh->setCellValue("{$col}3", $desc);
            $sh->getStyle("{$col}3")->applyFromArray([
                'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '6B7280']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FAFAFA']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);
        }

        // Largeurs de colonnes (format WIDE)
        $sh->getColumnDimension('A')->setWidth(24); // Ref projet
        $sh->getColumnDimension('B')->setWidth(22); // Ref conv
        $sh->getColumnDimension('C')->setWidth(28); // Porteur
        $sh->getColumnDimension('D')->setWidth(16); // Date J1
        $sh->getColumnDimension('E')->setWidth(18); // Montant J1
        $sh->getColumnDimension('F')->setWidth(16); // Date J2
        $sh->getColumnDimension('G')->setWidth(18); // Montant J2
        $sh->getColumnDimension('H')->setWidth(16); // Date J3
        $sh->getColumnDimension('I')->setWidth(18); // Montant J3
        $sh->getColumnDimension('J')->setWidth(22); // Alloc consommée
        $sh->getColumnDimension('K')->setWidth(20); // Situation

        $sh->getRowDimension(2)->setRowHeight(35);
        $sh->getRowDimension(3)->setRowHeight(60);

        // ─── Liste déroulante sur la colonne Situation (K) ───
        for ($row = 4; $row <= 103; $row++) {
            $validation = $sh->getCell("K{$row}")->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setPromptTitle('Choisir la situation');
            $validation->setPrompt('Encours / Clôturée / Stand by / Annulé / Remboursement');
            $validation->setFormula1('"Encours,Clôturée,Stand by,Annulé,Remboursement"');
        }

        // ─── Exemples pré-remplis (1 ligne = 1 projet, avec ses 3 tranches) ───
        // Ligne 4 : projet à 3 tranches
        // Ligne 5 : projet à 1 tranche (seul J1 rempli)
        // Ligne 6 : projet clôturé (3 tranches + situation)
        $exemples = [
            ['STELLARIX_2026_001', 'CONV_2026_001', 'STELLARIX SA',      '15/02/2026', 20000000, '15/04/2026', 15000000, '15/06/2026', 5000000, 40000000, 'Clôturée'],
            ['ORANGE_2026_002',    'CONV_2026_002', 'ORANGE MADAGASCAR', '01/03/2026', 45000000, '',            '',        '',            '',       45000000, 'Encours'],
            ['TELMA_2026_003',     'CONV_2026_003', 'TELMA',              '01/04/2026', 10000000, '01/06/2026', 8000000,  '',            '',       18000000, 'Encours'],
        ];

        foreach ($exemples as $i => $ligne) {
            $row = 4 + $i;
            foreach ($ligne as $j => $val) {
                $col = Coordinate::stringFromColumnIndex($j + 1);
                $sh->setCellValue("{$col}{$row}", $val);
            }
        }

        // Style visuel : lignes exemple grisées
        $sh->getStyle('A4:K6')->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '9CA3AF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']],
        ]);

        // Bannière d'instruction en ligne 7
        $sh->mergeCells('A7:K7');
        $sh->setCellValue('A7', '⚠️ SUPPRIMEZ les lignes exemples (4, 5, 6) avant import — commencez à remplir à partir de la ligne 8');
        $sh->getStyle('A7')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '92400E']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sh->getRowDimension(7)->setRowHeight(22);

        // Bordures pour 100 lignes vides prêtes à remplir
        $sh->getStyle('A8:K107')->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
        ]);

        // Formater les colonnes Montant en devise (E, G, I, J)
        foreach (['E', 'G', 'I', 'J'] as $col) {
            $sh->getStyle("{$col}4:{$col}107")->getNumberFormat()->setFormatCode('#,##0 "Ar"');
        }

        // Formater les colonnes Date en date (D, F, H)
        foreach (['D', 'F', 'H'] as $col) {
            $sh->getStyle("{$col}4:{$col}107")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        }

        $sh->freezePane('A4');

        // ─── Feuille "AIDE" ───
        $this->ajouterFeuilleAide($sp);

        // ─── Sauvegarde ───
        $nomFichier = $this->option('fichier');
        $chemin = base_path($nomFichier);
        (new Xlsx($sp))->save($chemin);

        $this->newLine();
        $this->info('✔ Template paiements généré avec succès !');
        $this->line("📁 Emplacement : {$chemin}");
        $this->line('📏 Taille      : ' . round(filesize($chemin) / 1024, 1) . ' Ko');
        $this->newLine();
        $this->line('▶ Le DAF le remplit avec les tranches versées.');
        $this->line('▶ Import via : /admin/paiements → bouton "Importer paiements"');

        return 0;
    }

    private function ajouterFeuilleAide(Spreadsheet $sp): void
    {
        $aide = $sp->createSheet();
        $aide->setTitle('AIDE');

        $contenu = [
            ['GUIDE DE REMPLISSAGE — TEMPLATE PAIEMENTS DAF', ''],
            ['', ''],
            ['OBJECTIF', ''],
            ['Ce fichier', 'Ajouter UNIQUEMENT des paiements. Ne touche pas aux autres données du projet.'],
            ['Différence avec TEMPLATE_IMPORT_DEES', 'L\'autre template écrase le projet entier. Celui-ci ajoute juste des tranches.'],
            ['', ''],
            ['CHAMPS OBLIGATOIRES', ''],
            ['Référence projet', 'La référence unique du projet — sera normalisée automatiquement'],
            ['Au moins une tranche', 'Remplir au moins la Date + Montant d\'une tranche (J1, J2 ou J3)'],
            ['Format dates', 'JJ/MM/AAAA'],
            ['Format montants', 'En Ariary, chiffres uniquement (ex: 12000000)'],
            ['', ''],
            ['CHAMPS OPTIONNELS', ''],
            ['Référence convention', 'Utile si Référence projet inconnue'],
            ['Porteur (info)', 'Purement indicatif, non importé'],
            ['Allocation consommée', 'Auto-calculée = somme J1+J2+J3 si laissée vide'],
            ['Situation', 'Encours / Clôturée / Stand by / Annulé / Remboursement'],
            ['', ''],
            ['STRUCTURE DU FICHIER (format WIDE)', ''],
            ['Principe', '1 ligne = 1 projet avec ses 3 tranches maximum (J1, J2, J3)'],
            ['Colonnes vides', 'Si un projet n\'a que 1 ou 2 tranches, laissez les autres colonnes vides'],
            ['Idempotent', 'Ré-importer met à jour au lieu de dupliquer'],
            ['', ''],
            ['RÉFÉRENCES SOUPLES', ''],
            ['Formats acceptés', 'STELLARIX_2026_001 = STELLARIX-2026-001 = stellarix 2026 001'],
            ['Casse ignorée', 'MAJUSCULES ou minuscules équivalentes'],
            ['Séparateurs ignorés', 'Espaces, tirets, underscores, slashes → tous équivalents'],
            ['', ''],
            ['SI LA REF PROJET EST INCONNUE', ''],
            ['Solution', 'Laisser vide et remplir la Référence convention à la place'],
            ['', ''],
            ['DOUBLONS', ''],
            ['Détection', 'Si un paiement identique (même projet, même tranche) existe déjà, il sera mis à jour au lieu d\'être dupliqué'],
            ['', ''],
            ['IMPORT', ''],
            ['Étape 1', 'Enregistrez le fichier en .xlsx'],
            ['Étape 2', 'Ouvrez /admin/paiements'],
            ['Étape 3', 'Cliquez "Importer paiements"'],
            ['Étape 4', 'Sélectionnez ce fichier → Importer'],
            ['', ''],
            ['QUI PEUT UTILISER CE FICHIER ?', ''],
            ['Import', 'Équipe DAF uniquement (rôle "daf" ou "admin")'],
            ['Lecture des paiements', 'DEES, Direction, Évaluateurs peuvent VOIR mais pas modifier'],
        ];

        foreach ($contenu as $i => $ligne) {
            $rowNum = $i + 1;
            $aide->setCellValue("A{$rowNum}", $ligne[0]);
            $aide->setCellValue("B{$rowNum}", $ligne[1]);

            if ($ligne[1] === '' && $ligne[0] !== '' && preg_match('/^[A-Z0-9\s\p{L}\(\?\)\-\—\']+$/u', $ligne[0])) {
                $aide->getStyle("A{$rowNum}:B{$rowNum}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '1F2937']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']],
                ]);
            }
        }

        $aide->getColumnDimension('A')->setWidth(35);
        $aide->getColumnDimension('B')->setWidth(70);
    }
}
