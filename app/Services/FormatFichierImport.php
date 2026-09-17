<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Détecte la disposition d'un classeur d'import : où sont les en-têtes, où
 * commencent les données, et à quel format de colonnes on a affaire.
 *
 * Trois dispositions coexistent dans les fichiers de la DEES :
 *
 *   · dees_bdd          en-têtes ligne 1, données ligne 2  (74 colonnes)
 *   · idee « officiel » ligne 1 = catégories en majuscules,
 *                       en-têtes ligne 2, données ligne 4
 *   · idee simplifié    en-têtes ligne 1, données ligne 2
 *
 * L'assistant d'import codait en dur « en-têtes ligne 2, données ligne 3 ».
 * Sur un fichier dont les en-têtes sont en ligne 1, la première ligne de
 * données était donc prise pour un en-tête et PERDUE — sans aucun message,
 * puisqu'elle n'était même pas comptée comme ignorée. Constaté sur un fichier
 * de 18 projets dont 17 seulement étaient proposés à l'import.
 */
class FormatFichierImport
{
    /**
     * @return array{0: int, 1: int, 2: string}  [ligne d'en-têtes, 1re ligne de données, format]
     */
    public static function detecter(string $chemin): array
    {
        try {
            $sp = IOFactory::load($chemin);
            $sheet = $sp->getSheetByName('Import DEES') ?? $sp->getActiveSheet();

            $a1 = (string) ($sheet->getCell('A1')->getValue() ?? '');
            $i1 = (string) ($sheet->getCell('I1')->getValue() ?? '');
            $j1 = (string) ($sheet->getCell('J1')->getValue() ?? '');

            // Format DEES_BDD : reconnaissable à ses colonnes de partenaires.
            if (str_contains(mb_strtolower($j1), 'matricule pa')
                || str_contains(mb_strtolower($i1), 'partenaires associés pa')) {
                return [1, 2, 'dees_bdd'];
            }

            // Format « officiel » : la ligne 1 porte des CATÉGORIES en
            // majuscules (« IDENTIFICATION », « FINANCEMENT »…), pas des noms
            // de colonnes. Les en-têtes sont alors en ligne 2.
            $estOfficiel = mb_strtoupper($a1) === $a1
                && strlen(trim($a1)) > 3
                && ! str_contains(mb_strtolower($a1), 'porteur')
                && ! str_contains(mb_strtolower($a1), 'secteur')
                && ! str_contains(mb_strtolower($a1), 'intitul');

            return $estOfficiel ? [2, 4, 'idee'] : [1, 2, 'idee'];
        } catch (\Throwable) {
            // En cas de doute, la disposition la plus courante.
            return [1, 2, 'idee'];
        }
    }

    /** Libellé lisible d'un format, pour les rapports et les journaux. */
    public static function libelle(string $format): string
    {
        return match ($format) {
            'dees_bdd' => 'DEES_BDD (74 colonnes, 17 partenaires)',
            'idee'     => 'IDEE_DE_COLONNES_BASE',
            default    => $format,
        };
    }
}
