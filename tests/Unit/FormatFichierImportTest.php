<?php

namespace Tests\Unit;

use App\Services\FormatFichierImport;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Détection de la disposition d'un classeur d'import.
 *
 * L'assistant codait en dur « en-têtes ligne 2, données ligne 3 ». Sur un
 * fichier dont les en-têtes sont en ligne 1, la première ligne de données
 * était donc prise pour un en-tête et PERDUE — sans le moindre message,
 * puisqu'elle n'était même pas comptée comme ignorée.
 *
 * Constaté sur un fichier de 18 projets dont 17 seulement étaient proposés.
 */
class FormatFichierImportTest extends TestCase
{
    /** @var string[] */
    private array $fichiersTemporaires = [];

    protected function tearDown(): void
    {
        foreach ($this->fichiersTemporaires as $f) {
            if (file_exists($f)) {
                unlink($f);
            }
        }

        parent::tearDown();
    }

    /**
     * Fabrique un classeur minimal à partir de ses lignes.
     *
     * @param  array<int, array<int, string>>  $lignes
     */
    private function classeur(array $lignes): string
    {
        $sp = new Spreadsheet();
        $sheet = $sp->getActiveSheet();

        foreach ($lignes as $i => $ligne) {
            foreach ($ligne as $j => $valeur) {
                $sheet->setCellValue([$j + 1, $i + 1], $valeur);
            }
        }

        $chemin = tempnam(sys_get_temp_dir(), 'fmfp_') . '.xlsx';
        (new Xlsx($sp))->save($chemin);
        $this->fichiersTemporaires[] = $chemin;

        return $chemin;
    }

    #[Test]
    public function un_classeur_avec_entetes_en_ligne_1_demarre_les_donnees_en_ligne_2(): void
    {
        // La disposition du fichier qui perdait un projet.
        $chemin = $this->classeur([
            ['Secteur', 'Vague', 'Guichet', 'Référence du projet', 'Référence convention', 'Porteur'],
            ['BTP/RS', 'AP1', '', 'AP5_EQUITE_2021_139', 'FMFP_CF', 'SOALALA'],
        ]);

        [$entetes, $donnees, $format] = FormatFichierImport::detecter($chemin);

        $this->assertSame(1, $entetes);
        $this->assertSame(2, $donnees, 'La première ligne de données ne doit pas être sautée.');
        $this->assertSame('idee', $format);
    }

    #[Test]
    public function un_classeur_avec_categories_en_ligne_1_demarre_les_donnees_en_ligne_4(): void
    {
        // Format « officiel » : la ligne 1 porte des catégories en majuscules,
        // les vrais en-têtes sont en ligne 2.
        $chemin = $this->classeur([
            ['IDENTIFICATION', '', '', '', '', ''],
            ['Secteur', 'Vague', 'Guichet', 'Référence du projet', 'Référence convention', 'Porteur'],
            ['', '', '', '', '', ''],
            ['BTP/RS', 'AP1', '', 'PROJ-1', 'CONV-1', 'STELLARIX'],
        ]);

        [$entetes, $donnees, $format] = FormatFichierImport::detecter($chemin);

        $this->assertSame(2, $entetes);
        $this->assertSame(4, $donnees);
        $this->assertSame('idee', $format);
    }

    #[Test]
    public function le_format_dees_bdd_est_reconnu_a_ses_colonnes_de_partenaires(): void
    {
        $chemin = $this->classeur([
            ['Secteur', 'Vague', 'Guichet', 'Réf projet', 'Réf conv', 'Porteur', 'CNaPS', 'Nb sal',
             'Partenaires associés PA 1', 'Matricule PA 1'],
            ['BTP/RS', 'AP1', '', 'PROJ-1', 'CONV-1', 'STELLARIX', '', '0', '', ''],
        ]);

        [$entetes, $donnees, $format] = FormatFichierImport::detecter($chemin);

        $this->assertSame('dees_bdd', $format);
        $this->assertSame(1, $entetes);
        $this->assertSame(2, $donnees);
    }

    #[Test]
    public function un_entete_contenant_secteur_ou_porteur_n_est_pas_pris_pour_une_categorie(): void
    {
        // Le piège : « SECTEUR » en majuscules ressemble à une catégorie. Les
        // mots-clés de colonnes lèvent l'ambiguïté.
        foreach (['SECTEUR', 'PORTEUR', 'INTITULÉ'] as $premier) {
            $chemin = $this->classeur([
                [$premier, 'Vague', 'Guichet'],
                ['BTP/RS', 'AP1', ''],
            ]);

            [, $donnees] = FormatFichierImport::detecter($chemin);

            $this->assertSame(2, $donnees, "« {$premier} » est un en-tête de colonne, pas une catégorie.");
        }
    }

    #[Test]
    public function un_fichier_illisible_retombe_sur_la_disposition_la_plus_courante(): void
    {
        [$entetes, $donnees, $format] = FormatFichierImport::detecter('/chemin/qui/nexiste/pas.xlsx');

        $this->assertSame(1, $entetes);
        $this->assertSame(2, $donnees);
        $this->assertSame('idee', $format);
    }

    #[Test]
    public function chaque_format_a_un_libelle_lisible(): void
    {
        $this->assertStringContainsString('74 colonnes', FormatFichierImport::libelle('dees_bdd'));
        $this->assertStringContainsString('IDEE', FormatFichierImport::libelle('idee'));
    }
}
