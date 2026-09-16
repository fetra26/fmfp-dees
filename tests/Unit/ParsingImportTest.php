<?php

namespace Tests\Unit;

use App\Imports\ProjetExcelImport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Conversion des cellules Excel brutes en valeurs exploitables.
 *
 * C'est le point d'entrée de toutes les données de la DEES, et l'endroit où
 * une erreur ne se voit pas : aucune exception n'est levée, la valeur est
 * simplement fausse ou absente dans la base.
 */
class ParsingImportTest extends TestCase
{
    private function appeler(string $methode, mixed ...$args): mixed
    {
        $m = new ReflectionMethod(ProjetExcelImport::class, $methode);
        $m->setAccessible(true);

        return $m->invoke(new ProjetExcelImport(), ...$args);
    }

    public static function datesFrancaises(): array
    {
        return [
            'debut de mois'       => ['01/02/2026', '2026-02-01'],
            'jour superieur a 12' => ['15/06/2026', '2026-06-15'],
            'fin d annee'         => ['31/12/2026', '2026-12-31'],
            'annee bissextile'    => ['29/02/2024', '2024-02-29'],
            'sans zero initial'   => ['1/2/2026',   '2026-02-01'],
        ];
    }

    #[Test]
    #[DataProvider('datesFrancaises')]
    public function une_date_texte_est_lue_au_format_francais(string $saisie, string $attendu): void
    {
        // Les fichiers de la DEES sont en français : « 01/02/2026 » est le
        // 1er février, pas le 2 janvier. Sans traitement dédié, Carbon applique
        // la convention américaine m/d/Y et inverse jour et mois sans rien dire.
        $this->assertSame($attendu, $this->appeler('parseDate', $saisie));
    }

    #[Test]
    public function une_date_au_format_iso_reste_comprise(): void
    {
        $this->assertSame('2026-06-15', $this->appeler('parseDate', '2026-06-15'));
        $this->assertSame('2026-06-15', $this->appeler('parseDate', '15-06-2026'));
    }

    #[Test]
    public function un_numero_de_serie_excel_est_converti(): void
    {
        // Format natif d'Excel pour les dates : le plus fiable des trois.
        $this->assertSame('2023-03-15', $this->appeler('parseDate', '45000'));
    }

    public static function datesInvalides(): array
    {
        return [
            'vide'            => [''],
            'espaces'         => ['   '],
            'texte'           => ['n/a'],
            'jour inexistant' => ['31/02/2026'],
            'mois inexistant' => ['06/15/2026'],
        ];
    }

    #[Test]
    #[DataProvider('datesInvalides')]
    public function une_date_invalide_donne_null_sans_planter(string $saisie): void
    {
        // « 31/02 » n'existe pas : createFromFormat déborderait silencieusement
        // au 3 mars, on refuse plutôt que d'inventer une date.
        $this->assertNull($this->appeler('parseDate', $saisie));
    }

    public static function montants(): array
    {
        return [
            'espaces normaux'       => ['1 000 000',    1000000],
            'sans separateur'       => ['1000000',      1000000],
            'points comme milliers' => ['1.000.000',    1000000],
            'points sur 5 chiffres' => ['25.000',       25000],
            'gros montant a points' => ['250.000.000',  250000000],
            'milliers courts'       => ['1.500',        1500],
            'prefixe devise'        => ['Ar 1 000',     1000],
            'suffixe devise'        => ['1 000 Ar',     1000],
            'decimale a la virgule' => ['12 345,67',    12346],
            'decimale simple'       => ['1,5',          2],
            'points et decimale'    => ['1.234.567,89', 1234568],
            'separateur final'      => ['1000.',        1000],
            'vide'                  => ['',             0],
            'texte'                 => ['neant',        0],
        ];
    }

    #[Test]
    #[DataProvider('montants')]
    public function un_montant_est_converti_en_ariary_entier(string $saisie, int $attendu): void
    {
        // Les montants sont des Ariary entiers (bigint non signé en base) :
        // pas de décimales, pas de valeurs négatives.
        //
        // Le point est un séparateur de MILLIERS, pas un séparateur décimal :
        // « 1.000.000 », « 1 000 000 » et « 1000000 » désignent le même
        // million. C'est la règle confirmée par la DEES. Auparavant
        // « 250.000.000 » était lu comme 250 — une division par un million,
        // sans la moindre alerte.
        $this->assertSame($attendu, $this->appeler('parseMontant', $saisie));
    }

    #[Test]
    public function un_montant_avec_espace_insecable_est_correctement_lu(): void
    {
        // Excel insère des espaces insécables dans les nombres formatés
        // (U+00A0, soit les octets 194 et 160 en UTF-8). Sans traitement,
        // le montant tomberait à 0.
        $nbsp = chr(194) . chr(160);
        $insecable = '1' . $nbsp . '000' . $nbsp . '000';

        $this->assertSame(1000000, $this->appeler('parseMontant', $insecable));
    }

    public static function entiers(): array
    {
        return [
            'nombre seul'   => ['12',          12],
            'avec du texte' => ['12 salaries', 12],
            'vide'          => ['',            0],
            'texte seul'    => ['aucun',       0],
        ];
    }

    #[Test]
    #[DataProvider('entiers')]
    public function un_effectif_est_converti_en_entier(string $saisie, int $attendu): void
    {
        $this->assertSame($attendu, $this->appeler('parseInt', $saisie));
    }

    public static function colonnes(): array
    {
        return [
            'A'  => ['A',  0],
            'B'  => ['B',  1],
            'Z'  => ['Z',  25],
            'AA' => ['AA', 26],
            'AH' => ['AH', 33],
            'BO' => ['BO', 66],
        ];
    }

    #[Test]
    #[DataProvider('colonnes')]
    public function une_lettre_de_colonne_designe_le_bon_index(string $lettre, int $index): void
    {
        // Le mapping du template va jusqu'à BV : une erreur sur les colonnes à
        // deux lettres décalerait des dizaines de champs d'un cran.
        $ligne = range(0, 80);

        $this->assertSame($index, $this->appeler('cell', $ligne, $lettre));
    }

    #[Test]
    public function une_colonne_absente_de_la_ligne_donne_null(): void
    {
        $this->assertNull($this->appeler('cell', ['a', 'b'], 'Z'));
    }

    #[Test]
    public function la_lettre_de_colonne_est_insensible_a_la_casse(): void
    {
        $ligne = range(0, 40);

        $this->assertSame(33, $this->appeler('cell', $ligne, 'ah'));
    }

    public static function nomsPorteurs(): array
    {
        return [
            'forme juridique en fin' => ['Stellarix SARL',     'STELLARIX'],
            'avec virgule'           => ['STELLARIX, SA',      'STELLARIX'],
            'sans forme juridique'   => ['Stellarix',          'STELLARIX'],
            'espaces autour'         => ['  stellarix sarl  ', 'STELLARIX'],
        ];
    }

    #[Test]
    #[DataProvider('nomsPorteurs')]
    public function le_nom_du_porteur_est_normalise(string $saisie, string $attendu): void
    {
        // Retirer la forme juridique évite de créer deux porteurs distincts
        // pour « STELLARIX » et « STELLARIX SARL ».
        $this->assertSame($attendu, $this->appeler('normaliserNom', $saisie));
    }

    public static function alertes(): array
    {
        return [
            'rouge'           => ['Alerte rouge', 'rouge'],
            'orange'          => ['ORANGE',       'orange'],
            'verte'           => ['Verte',        'verte'],
            'valeur inconnue' => ['bleu',         'verte'],
            'vide'            => ['',             'verte'],
        ];
    }

    #[Test]
    #[DataProvider('alertes')]
    public function le_niveau_d_alerte_est_normalise(string $saisie, string $attendu): void
    {
        // Par défaut « verte » : en cas de doute, on ne déclenche pas une
        // procédure de résiliation à tort.
        $this->assertSame($attendu, $this->appeler('normaliserAlerte', $saisie));
    }

    public static function situations(): array
    {
        return [
            'cloture avec accent' => ['Clôturé',  'solde'],
            'cloture sans accent' => ['cloture',  'solde'],
            'annule'              => ['Annulé',   'annule'],
            'en cours'            => ['En cours', 'partiel'],
            'stand by'            => ['Stand-by', 'partiel'],
            'inconnu'             => ['autre',    'non_verse'],
        ];
    }

    #[Test]
    #[DataProvider('situations')]
    public function la_situation_d_allocation_est_normalisee(string $saisie, string $attendu): void
    {
        $this->assertSame($attendu, $this->appeler('normaliserSituationAlloc', $saisie));
    }

    #[Test]
    public function une_cellule_multi_valeurs_est_eclatee(): void
    {
        [$parts, $sep] = $this->appeler('splitMultiValue', 'ANALAMANGA / ITASY / DIANA');

        $this->assertSame(['ANALAMANGA', 'ITASY', 'DIANA'], $parts);
        $this->assertSame('/', $sep);
    }

    #[Test]
    public function une_cellule_simple_reste_entiere(): void
    {
        [$parts, $sep] = $this->appeler('splitMultiValue', 'ANALAMANGA');

        $this->assertSame(['ANALAMANGA'], $parts);
        $this->assertNull($sep);
    }

    #[Test]
    public function les_fragments_vides_sont_ecartes(): void
    {
        [$parts] = $this->appeler('splitMultiValue', 'ANALAMANGA / / ITASY');

        $this->assertSame(['ANALAMANGA', 'ITASY'], $parts);
    }

    #[Test]
    public function une_cellule_vide_ne_produit_aucun_fragment(): void
    {
        [$parts, $sep] = $this->appeler('splitMultiValue', '   ');

        $this->assertSame([], $parts);
        $this->assertNull($sep);
    }
}
