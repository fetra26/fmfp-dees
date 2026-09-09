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
 * Génère un fichier Excel template propre à distribuer à l'équipe DEES
 * pour qu'elle remplisse les données des projets à importer.
 */
class GenererTemplateImport extends Command
{
    protected $signature = 'template:generer {--fichier=TEMPLATE_IMPORT_DEES.xlsx : Nom du fichier généré}';
    protected $description = 'Génère le template Excel officiel FMFP-DEES à distribuer à l\'équipe pour remplissage';

    private const COULEURS = [
        'info'        => ['fond' => '1E40AF', 'texte' => 'FFFFFF'],
        'prevu'       => ['fond' => '10B981', 'texte' => 'FFFFFF'],
        'contract'    => ['fond' => '6B7280', 'texte' => 'FFFFFF'],
        'paiement'    => ['fond' => '1F2937', 'texte' => 'FFFFFF'],
        'alerte'      => ['fond' => 'F59E0B', 'texte' => '1F2937'],
        'terrain'     => ['fond' => 'F97316', 'texte' => 'FFFFFF'],
        'rapport'     => ['fond' => '7C3AED', 'texte' => 'FFFFFF'],
        'realisation' => ['fond' => '92400E', 'texte' => 'FFFFFF'],
    ];

    private array $sections = [];

    public function handle(): int
    {
        $this->definirStructure();

        $sp = new Spreadsheet();
        $sh = $sp->getActiveSheet();
        $sh->setTitle('Import DEES');

        $colonneNum = 1;
        $colonneStatut = null;

        foreach ($this->sections as $section) {
            $colDebut = Coordinate::stringFromColumnIndex($colonneNum);
            $nbColonnes = count($section['colonnes']);
            $colFin = Coordinate::stringFromColumnIndex($colonneNum + $nbColonnes - 1);

            // Ligne 1 : titre de section (fusion + couleur)
            $sh->mergeCells("{$colDebut}1:{$colFin}1");
            $sh->setCellValue("{$colDebut}1", $section['titre']);
            $sh->getStyle("{$colDebut}1")->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => $section['couleur']['texte']],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $section['couleur']['fond']],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']],
                ],
            ]);

            // Lignes 2 et 3 : en-têtes + descriptions
            $ci = $colonneNum;
            foreach ($section['colonnes'] as [$nomColonne, $description]) {
                $colLetter = Coordinate::stringFromColumnIndex($ci);

                if ($nomColonne === 'Statut') {
                    $colonneStatut = $colLetter;
                }

                $sh->setCellValue("{$colLetter}2", $nomColonne);
                $sh->getStyle("{$colLetter}2")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
                ]);

                $sh->setCellValue("{$colLetter}3", $description);
                $sh->getStyle("{$colLetter}3")->applyFromArray([
                    'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '6B7280']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FAFAFA']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_TOP,
                        'wrapText' => true,
                    ],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
                ]);

                $largeur = max(15, min(30, strlen($nomColonne) + 5));
                $sh->getColumnDimension($colLetter)->setWidth($largeur);

                $ci++;
            }

            $colonneNum += $nbColonnes;
        }

        $sh->getRowDimension(1)->setRowHeight(28);
        $sh->getRowDimension(2)->setRowHeight(35);
        $sh->getRowDimension(3)->setRowHeight(55);

        // Liste déroulante pour la colonne Statut sur 30 lignes
        if ($colonneStatut !== null) {
            for ($row = 4; $row <= 33; $row++) {
                $validation = $sh->getCell("{$colonneStatut}{$row}")->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                $validation->setAllowBlank(true);
                $validation->setShowInputMessage(true);
                $validation->setShowErrorMessage(true);
                $validation->setShowDropDown(true);
                $validation->setErrorTitle('Statut invalide');
                $validation->setError("Utilisez l'une des valeurs proposées.");
                $validation->setPromptTitle('Choisir un statut');
                $validation->setPrompt('Cliquez sur la flèche pour voir la liste des statuts autorisés.');
                $validation->setFormula1('"STAND BY,ATTENTE_PECES_REGUL,VALIDATION FINANCIERE,FORMATION_ENCOURS,CLOTURE,ANNULE"');
            }
        }

        // Bordures légères pour 30 lignes vides prêtes à remplir
        $totalCols = $colonneNum - 1;
        $derniereColonne = Coordinate::stringFromColumnIndex($totalCols);
        $sh->getStyle("A4:{$derniereColonne}33")->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']],
            ],
        ]);

        // Figer les 3 premières lignes
        $sh->freezePane('A4');

        // Feuille "Partenaires" (illimitée, liée par référence projet/convention)
        $this->ajouterFeuillePartenaires($sp);

        // Feuille "AIDE"
        $this->ajouterFeuilleAide($sp);

        // Sauvegarde
        $nomFichier = $this->option('fichier');
        $chemin = base_path($nomFichier);
        (new Xlsx($sp))->save($chemin);

        $this->newLine();
        $this->info('✔ Template généré avec succès !');
        $this->line("📁 Emplacement : {$chemin}");
        $this->line('📏 Taille      : ' . round(filesize($chemin) / 1024, 1) . ' Ko');
        $this->newLine();
        $this->line('▶ Partagez ce fichier à l\'équipe DEES pour qu\'elle remplisse les projets.');
        $this->line('▶ Une fois rempli, importez-le via Menu Données Projets → Importer.');

        return 0;
    }

    private function definirStructure(): void
    {
        $this->sections = [
            [
                'titre'    => '1. INFORMATION GÉNÉRALE',
                'couleur'  => self::COULEURS['info'],
                'colonnes' => [
                    ['Secteur',                    "Secteur d'activité (ex: BTP, AGRO, EDUCATION...)"],
                    ['Vague',                      "Vague de l'appel projet (ex: AP1, AP2, AP14_EQ)"],
                    ['Guichet',                    'Code guichet DEES (RIE, PIS, PII, INP, EQUITE, ALTERNANCE, APPRENTISSAGE)'],
                    ['Référence du projet',        "Référence unique du projet (ex: EQ_AV_2019_001)"],
                    ['Référence convention',       "Référence de la convention signée"],
                    ['Porteur',                    "Nom de l'entreprise porteuse (OBLIGATOIRE)"],
                    ['CNaPS porteur',              "Numéro CNaPS du porteur"],
                    ['Nombre salariés du porteur', "Nb salariés (chiffre entier)"],
                    ['Nb partenaires',             "Nombre total de partenaires (info seulement — détails dans feuille 'Partenaires')"],
                    ['Contact',                    "Nom du responsable / personne de contact"],
                    ['Téléphone',                  "Numéro de téléphone"],
                    ['Email',                      "Adresse email"],
                    ['Adresse',                    "Adresse physique complète"],
                    ['Région',                     "Région Madagascar (ex: Analamanga, Vakinankaratra...)"],
                    ['Intitulé',                   "Intitulé complet du projet (OBLIGATOIRE)"],
                ],
            ],

            [
                'titre'    => '2. PRÉVISIONNEL',
                'couleur'  => self::COULEURS['prevu'],
                'colonnes' => [
                    ['Nb bénéf total',            "Nombre total de bénéficiaires prévus"],
                    ['H',                         "Nb hommes prévus"],
                    ['F',                         "Nb femmes prévues"],
                    ['Jeunes',                    "Nb jeunes (15-35 ans) prévus"],
                    ['FPE',                       "Nb bénéficiaires en formation pré-emploi"],
                    ['Femmes cadres',             "Nb femmes cadres prévues"],
                    ['Prestataire',               "Nom du prestataire de formation"],
                    ['Modules de formation',      "Liste des modules (séparés par ; )"],
                    ['Volume horaire par module', "Heures par module (chiffre)"],
                    ['Formateur par module',      "Nom du/des formateur(s)"],
                    ['Volume horaire total',      "Total des heures de formation"],
                ],
            ],

            [
                'titre'    => '3. CONTRACTUALISATION & MISE EN ŒUVRE',
                'couleur'  => self::COULEURS['contract'],
                'colonnes' => [
                    ['Montant total',              "Montant total du projet (en Ariary, sans espace)"],
                    ['Financement demandé',        "Montant demandé au FMFP (Ariary)"],
                    ['DT mobilisé',                "Droit de tirage mobilisé (Ariary)"],
                    ['Fonds ADDITIONNELS',         "Fonds additionnels (Ariary)"],
                    ['Fonds mutualisé',            "Fonds mutualisé (Ariary)"],
                    ['Financement (autre)',        "Autre financement (Ariary)"],
                    ['Appréciation évaluateur',    "Commentaire de l'évaluateur"],
                    ['Statut',                     "STAND BY / ATTENTE_PECES_REGUL / VALIDATION FINANCIERE / FORMATION_ENCOURS / CLOTURE / ANNULE"],
                    ['Motifs',                     "Motifs (pour projets non validés)"],
                    ['Date notification',          "Date au format JJ/MM/AAAA"],
                    ['Date envoi convention',      "Date envoi convention"],
                    ['Date réception convention',  "Date réception convention"],
                    ['Date début',                 "Date de démarrage du projet"],
                    ['Date fin',                   "Date de fin prévue"],
                    ['DANO',                       "CHANGEMENT DE DATE / CHANGEMENT FORMATEUR / RÉAMÉNAGEMENT BUDGÉTAIRE (sinon vide)"],
                ],
            ],

            [
                'titre'    => '4. PAIEMENT (rempli par équipe DAF)',
                'couleur'  => self::COULEURS['paiement'],
                'colonnes' => [
                    ['Date paiement J1',     "Date du paiement 1ère tranche"],
                    ['Montant payé J1',      "Montant J1 (Ariary)"],
                    ['Date paiement J2',     "Date du paiement 2ème tranche"],
                    ['Montant payé J2',      "Montant J2 (Ariary)"],
                    ['Date paiement J3',     "Date du paiement 3ème tranche"],
                    ['Montant payé J3',      "Montant J3 (Ariary)"],
                    ['Allocation consommée', "Total versé J1+J2+J3"],
                    ['Situation',            "Encours / Clôturée / Stand by / Annulé / Remboursement"],
                ],
            ],

            [
                'titre'    => '5. ALERTE ROUGE',
                'couleur'  => self::COULEURS['alerte'],
                'colonnes' => [
                    ['Situation alerte',      "Verte / Orange / Rouge (auto-calculée par le système)"],
                    ['Date relance 1',        "1ère relance pour alertes rouges"],
                    ['Date relance 2',        "2ème relance pour alertes rouges"],
                    ['Date mise en demeure',  "Date réception lettre mise en demeure"],
                    ['Date résiliation',      "Date réception lettre résiliation"],
                    ['Observation',           "Observation générale sur les alertes"],
                ],
            ],

            [
                'titre'    => '6. TERRAIN',
                'couleur'  => self::COULEURS['terrain'],
                'colonnes' => [
                    ['Date formation contractants', "Date formation des contractants"],
                    ['Date suivi terrain',          "Date visite terrain"],
                    ['Observation suivi terrain',   "Observations lors du suivi"],
                ],
            ],

            [
                'titre'    => '7. RAPPORT TECHNIQUE',
                'couleur'  => self::COULEURS['rapport'],
                'colonnes' => [
                    ['Date arrivée rapport DEES',  "Date d'arrivée du rapport à la DEES"],
                    ['Évaluateur',                 "Nom de l'évaluateur assigné"],
                    ['Date transfert évaluateur',  "Date de transfert vers évaluateur"],
                    ['Date début traitement',      "Date de début du traitement"],
                    ['Réserve du projet',          "Description des réserves"],
                    ['Date envoi réserve porteur', "Date envoi réserve au porteur"],
                    ['Date 1ère relance réserve',  "Date d'envoi de la 1ère relance"],
                    ['Date 2ème relance réserve',  "Date d'envoi de la 2ème relance"],
                    ['Situation réserves',         "État des réserves"],
                    ['Date validation évaluateur', "Date de validation par l'évaluateur"],
                    ['Date transmission DAF',      "Date de transmission vers équipe DAF"],
                    ['Observations',               "Observations générales"],
                ],
            ],

            [
                'titre'    => '8. RÉALISATION',
                'couleur'  => self::COULEURS['realisation'],
                'colonnes' => [
                    ['Nb bénéf total formé',       "Nb total de bénéficiaires effectivement formés"],
                    ['H (réalisé)',                "Nb hommes effectivement formés"],
                    ['F (réalisé)',                "Nb femmes effectivement formées"],
                    ['Jeunes (réalisé)',           "Nb jeunes effectivement formés"],
                    ['FPE (réalisé)',              "Nb en FPE effectivement formés"],
                    ['Femmes cadres formées',      "Nb femmes cadres effectivement formées"],
                    ['Prestataire (réalisé)',      "Prestataire réel"],
                    ['Modules (réalisé)',          "Modules effectivement réalisés"],
                    ['Vol h par module (réalisé)', "Heures par module réalisé"],
                    ['Formateur (réalisé)',        "Formateur(s) réel(s)"],
                    ['Vol h total (réalisé)',      "Volume horaire total réalisé"],
                ],
            ],
        ];
    }

    /**
     * Feuille "Partenaires" — liste illimitée de partenaires liés aux projets
     * par Référence projet OU Référence convention (stratégie A : match souple).
     */
    private function ajouterFeuillePartenaires(Spreadsheet $sp): void
    {
        $part = $sp->createSheet();
        $part->setTitle('Partenaires');

        $colonnes = [
            ['Référence projet',      "1 SEULE FOIS par projet (ex: STELLARIX_2026_001). Laissez VIDE pour les partenaires suivants du même projet."],
            ['Référence convention',  "1 SEULE FOIS par projet (ex: CONV_2026_001). Laissez VIDE pour les partenaires suivants."],
            ['Intitulé de projet',    "FACULTATIF — pour vous aider à identifier le projet (non importé)"],
            ['Porteur',               "FACULTATIF — nom du porteur du projet (non importé, juste indicatif)"],
            ['Date convention',       "FACULTATIF — date début-fin de la convention (non importé, juste indicatif)"],
            ['Nom partenaire',        "OBLIGATOIRE — Nom complet du partenaire"],
            ['CNaPS partenaire',      "Numéro CNaPS du partenaire"],
            ['Nb salariés',           "Nombre de salariés du partenaire (chiffre entier)"],
        ];

        // Ligne 1 : titre section (fusion)
        $colFin = Coordinate::stringFromColumnIndex(count($colonnes));
        $part->mergeCells("A1:{$colFin}1");
        $part->setCellValue('A1', 'PARTENAIRES DES PROJETS — 1 ligne = 1 partenaire (sans limite)');
        $part->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $part->getRowDimension(1)->setRowHeight(28);

        // Lignes 2 et 3 : en-têtes + descriptions
        foreach ($colonnes as $i => [$nom, $description]) {
            $col = Coordinate::stringFromColumnIndex($i + 1);

            $part->setCellValue("{$col}2", $nom);
            $part->getStyle("{$col}2")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            ]);

            $part->setCellValue("{$col}3", $description);
            $part->getStyle("{$col}3")->applyFromArray([
                'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '6B7280']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FAFAFA']],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'vertical' => Alignment::VERTICAL_TOP,
                    'wrapText' => true,
                ],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);
        }

        // Largeurs
        $part->getColumnDimension('A')->setWidth(24); // Ref projet
        $part->getColumnDimension('B')->setWidth(24); // Ref convention
        $part->getColumnDimension('C')->setWidth(30); // Intitulé
        $part->getColumnDimension('D')->setWidth(22); // Porteur
        $part->getColumnDimension('E')->setWidth(22); // Date convention
        $part->getColumnDimension('F')->setWidth(32); // Nom partenaire
        $part->getColumnDimension('G')->setWidth(18); // CNaPS
        $part->getColumnDimension('H')->setWidth(15); // Nb salariés

        $part->getRowDimension(2)->setRowHeight(30);
        $part->getRowDimension(3)->setRowHeight(60);

        // Bordures pour 100 lignes vides prêtes à remplir
        $part->getStyle("A4:{$colFin}103")->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']],
            ],
        ]);

        // ─── EXEMPLE 1 : STELLARIX avec 2 partenaires (lignes 4-5) ───
        // Les cellules A-E sont FUSIONNÉES verticalement : l'info projet apparaît une seule fois
        // Seules les colonnes F, G, H diffèrent (1 ligne = 1 partenaire)
        $part->mergeCells('A4:A5'); // Ref projet
        $part->mergeCells('B4:B5'); // Ref convention
        $part->mergeCells('C4:C5'); // Intitulé
        $part->mergeCells('D4:D5'); // Porteur
        $part->mergeCells('E4:E5'); // Date convention

        $part->setCellValue('A4', 'STELLARIX_2026_001');
        $part->setCellValue('B4', 'CONV_2026_001');
        $part->setCellValue('C4', 'Formation soudure niveau 2');
        $part->setCellValue('D4', 'STELLARIX SA');
        $part->setCellValue('E4', '15/01/2026 → 15/06/2026');

        // Ligne 4 : premier partenaire
        $part->setCellValue('F4', 'Partenaire A (exemple)');
        $part->setCellValue('G4', '111222333');
        $part->setCellValue('H4', 20);

        // Ligne 5 : deuxième partenaire (info projet vient de la fusion ci-dessus)
        $part->setCellValue('F5', 'Partenaire B (exemple)');
        $part->setCellValue('G5', '444555666');
        $part->setCellValue('H5', 15);

        // ─── EXEMPLE 2 : ORANGE avec 1 partenaire (ligne 6) ───
        $part->setCellValue('A6', 'ORANGE_2026_002');
        $part->setCellValue('B6', 'CONV_2026_002');
        $part->setCellValue('C6', 'Formation télécom');
        $part->setCellValue('D6', 'ORANGE MG');
        $part->setCellValue('E6', '01/03/2026 → 01/09/2026');
        $part->setCellValue('F6', 'Telma (exemple)');
        $part->setCellValue('G6', '777888999');
        $part->setCellValue('H6', 250);

        // ─── EXEMPLE 3 : TELMA avec 3 partenaires (lignes 7-9) — bloc fusionné plus large ───
        $part->mergeCells('A7:A9');
        $part->mergeCells('B7:B9');
        $part->mergeCells('C7:C9');
        $part->mergeCells('D7:D9');
        $part->mergeCells('E7:E9');

        $part->setCellValue('A7', 'TELMA_2026_003');
        $part->setCellValue('B7', 'CONV_2026_003');
        $part->setCellValue('C7', 'Formation fibre optique');
        $part->setCellValue('D7', 'TELMA');
        $part->setCellValue('E7', '01/04/2026 → 30/09/2026');

        $part->setCellValue('F7', 'Partenaire X (exemple)');
        $part->setCellValue('G7', '900');
        $part->setCellValue('H7', 40);

        $part->setCellValue('F8', 'Partenaire Y (exemple)');
        $part->setCellValue('G8', '901');
        $part->setCellValue('H8', 25);

        $part->setCellValue('F9', 'Partenaire Z (exemple)');
        $part->setCellValue('G9', '902');
        $part->setCellValue('H9', 12);

        // Style visuel : lignes exemple grisées avec fond jaune pâle
        $part->getStyle("A4:{$colFin}9")->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '9CA3AF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        // Bannière d'instruction en ligne 10
        $part->mergeCells("A10:{$colFin}10");
        $part->setCellValue('A10', '⚠️ SUPPRIMEZ les lignes exemples (4 à 9) avant import — commencez à remplir à partir de la ligne 11. Pour fusionner : sélectionnez les cellules → clic droit → Fusionner');
        $part->getStyle('A10')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '92400E']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $part->getRowDimension(10)->setRowHeight(35);

        // Figer les 3 premières lignes
        $part->freezePane('A4');
    }

    private function ajouterFeuilleAide(Spreadsheet $sp): void
    {
        $aide = $sp->createSheet();
        $aide->setTitle('AIDE');

        $contenu = [
            ['GUIDE DE REMPLISSAGE DU TEMPLATE FMFP-DEES', ''],
            ['', ''],
            ['STRUCTURE DU FICHIER (3 feuilles)', ''],
            ['Feuille "Import DEES"', 'Les projets (1 ligne = 1 projet, 87 colonnes en 8 sections)'],
            ['Feuille "Partenaires"', 'Les partenaires (1 ligne = 1 partenaire, sans limite)'],
            ['Feuille "AIDE"', 'Ce guide (à ne pas remplir)'],
            ['', ''],
            ['STRUCTURE INTERNE DE CHAQUE FEUILLE', ''],
            ['Ligne 1', 'Titres des sections (fusionnées) — NE PAS MODIFIER'],
            ['Ligne 2', 'Noms des colonnes — NE PAS MODIFIER'],
            ['Ligne 3', 'Description/aide pour chaque colonne — NE PAS MODIFIER'],
            ['Ligne 4 et +', 'VOS DONNÉES'],
            ['', ''],
            ['CHAMPS OBLIGATOIRES (Feuille Import DEES)', ''],
            ['Porteur', "Nom de l'entreprise porteuse"],
            ['Intitulé', 'Intitulé complet du projet'],
            ['Référence projet', 'Recommandée — sert de clé pour lier les partenaires'],
            ['', ''],
            ['GESTION DES PARTENAIRES (Feuille Partenaires)', ''],
            ['Principe', '1 ligne = 1 partenaire. Sans limite (10 000+ possibles)'],
            ['Liaison au projet', 'Référence projet OU Référence convention doit matcher'],
            ['Références souples', 'STELLARIX_2026_001 = STELLARIX-2026-001 = stellarix 2026 001'],
            ['Espaces, tirets, /', "Ignorés automatiquement à l'import (normalisation)"],
            ['Casse', "Ignorée (majuscules/minuscules équivalentes)"],
            ['', ''],
            ['NE PAS RÉPÉTER LES RÉFÉRENCES', ''],
            ['Règle', "Écrivez la Réf projet + Réf convention UNE seule fois par projet"],
            ['Lignes suivantes', 'Laissez ces colonnes VIDES pour les partenaires suivants du même projet'],
            ['Effet à l\'import', 'Les lignes vides héritent automatiquement de la référence de la ligne précédente'],
            ['Exemple 5 partenaires Orange', 'Ligne 1 : Ref + Partenaire A. Lignes 2-5 : seulement le nom du partenaire (Ref vide)'],
            ['Colonnes Info (C, D, E)', 'Intitulé / Porteur / Date convention = FACULTATIVES, non importées'],
            ['Doublons', 'Détectés automatiquement, rapport affiché après import'],
            ['', ''],
            ['STATUTS AUTORISÉS (colonne Statut)', ''],
            ['STAND BY', 'Projet en attente/pause'],
            ['ATTENTE_PECES_REGUL', 'Attente de pièces à régulariser'],
            ['VALIDATION FINANCIERE', 'En cours de validation financière'],
            ['FORMATION_ENCOURS', "Formation en cours d'exécution"],
            ['CLOTURE', 'Projet clôturé/soldé'],
            ['ANNULE', 'Projet annulé'],
            ['', ''],
            ['GUICHETS AUTORISÉS (colonne Guichet)', ''],
            ['RIE', 'Renforcement Insertion Économique'],
            ['PIS', 'Programme Insertion Sectorielle'],
            ['PII', 'Programme Insertion Individuelle'],
            ['INP', 'Insertion Nouveaux Profils'],
            ['EQUITE', 'Guichet Équité'],
            ['ALTERNANCE', 'Formation en alternance'],
            ['APPRENTISSAGE', 'Formation par apprentissage'],
            ['', ''],
            ['TYPES DE DANO (colonne DANO)', ''],
            ['CHANGEMENT DE DATE', 'Modification des dates du projet'],
            ['CHANGEMENT FORMATEUR', 'Changement du formateur'],
            ['RÉAMÉNAGEMENT BUDGÉTAIRE', 'Modification du budget'],
            ['(vide)', 'Aucun DANO (cas normal)'],
            ['', ''],
            ['FORMATS DE DATES', ''],
            ['Format accepté', 'JJ/MM/AAAA (ex: 15/03/2024) ou AAAA-MM-JJ'],
            ['', ''],
            ['MONTANTS FINANCIERS', ''],
            ['Devise', 'Ariary (Ar) — chiffres entiers seulement'],
            ['Séparateur', 'Aucun (ex: 40000000, pas 40 000 000)'],
            ['', ''],
            ['PLUSIEURS PARTENAIRES', ''],
            ['Nom (col Partenaire)', 'Séparez par virgule : "Partenaire A, Partenaire B"'],
            ['CNaPS (col CNaPS partenaire)', 'Même ordre, séparés par virgule'],
            ['', ''],
            ['CELLULES VIDES', ''],
            ['Comportement', 'Les cellules vides restent vides en base — pas de problème'],
            ['', ''],
            ['IMPORT', ''],
            ['Étape 1', 'Enregistrez le fichier en .xlsx'],
            ['Étape 2', 'Ouvrez la plateforme : /admin/porteur-projs'],
            ['Étape 3', 'Cliquez "Importer" → "Importer un fichier"'],
            ['Étape 4', 'Sélectionnez ce fichier → Importer'],
        ];

        foreach ($contenu as $i => $ligne) {
            $rowNum = $i + 1;
            $aide->setCellValue("A{$rowNum}", $ligne[0]);
            $aide->setCellValue("B{$rowNum}", $ligne[1]);

            if ($ligne[1] === '' && $ligne[0] !== '' && preg_match('/^[A-Z0-9\s\p{L}\(\)\-\—]+$/u', $ligne[0])) {
                $aide->getStyle("A{$rowNum}:B{$rowNum}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '1E40AF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFF6FF']],
                ]);
            }
        }

        $aide->getColumnDimension('A')->setWidth(35);
        $aide->getColumnDimension('B')->setWidth(70);
    }
}
