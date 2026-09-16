<?php

namespace Tests\Unit;

use App\Models\ImportMapping;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Normalisation des valeurs de référentiels à l'import Excel.
 *
 * normaliser() sert au RAPPROCHEMENT : deux graphies d'un même secteur doivent
 * converger, sinon le wizard demande à l'utilisateur de trancher une valeur
 * qu'il a déjà traitée dix fois.
 *
 * formaterLibelle() sert à la CRÉATION : c'est la forme officielle d'un nouveau
 * référentiel dans SEER (UPPER_SNAKE_CASE).
 */
class ImportNormalisationTest extends TestCase
{
    public static function exemplesDocumentes(): array
    {
        // Les exemples figurant dans la docblock de normaliser().
        return [
            'espaces'          => ['Agro Alimentaire',  'AGROALIMENTAIRE'],
            'tiret et chiffre' => ['AP-1',              'AP1'],
            'apostrophe'       => ["MULTI'EDUCATION",   'MULTIEDUCATION'],
            'accent'           => ['MULTI-ÉDUCATION',   'MULTIEDUCATION'],
            'parentheses'      => ['MULTI (EDUCATION)', 'MULTIEDUCATION'],
            'esperluette'      => ["L'ÉLEVAGE & PÊCHE", 'LELEVAGEPECHE'],
        ];
    }

    #[Test]
    #[DataProvider('exemplesDocumentes')]
    public function normaliser_respecte_les_exemples_documentes(string $saisie, string $attendu): void
    {
        $this->assertSame($attendu, ImportMapping::normaliser($saisie));
    }

    #[Test]
    public function toutes_les_variantes_d_un_meme_secteur_convergent(): void
    {
        // C'est ce regroupement qui évite de redemander 4 fois la même chose
        // à l'utilisateur dans le wizard d'import.
        $variantes = [
            'MULTI-EDUCATION',
            'MULTI EDUCATION',
            "MULTI'EDUCATION",
            'Multi Éducation',
            'multi  education',
            'MULTI(EDUCATION)',
        ];

        $normalisees = array_unique(array_map(
            fn (string $v) => ImportMapping::normaliser($v),
            $variantes
        ));

        $this->assertCount(1, $normalisees, 'Ces graphies doivent former un seul groupe.');
        $this->assertSame('MULTIEDUCATION', reset($normalisees));
    }

    public static function valeursSansContenu(): array
    {
        return [
            'null'              => [null],
            'chaine vide'       => [''],
            'espaces'           => ['    '],
            'ponctuation seule' => ['---'],
            'parentheses vides' => ['()'],
            'symboles'          => ['&&& ... ///'],
        ];
    }

    #[Test]
    #[DataProvider('valeursSansContenu')]
    public function une_valeur_sans_caractere_utile_donne_null(?string $saisie): void
    {
        // Renvoyer null et non '' : resoudre() ignore les null, alors qu'une
        // chaîne vide créerait un groupe fourre-tout mélangeant des valeurs
        // sans rapport entre elles.
        $this->assertNull(ImportMapping::normaliser($saisie));
    }

    #[Test]
    public function les_chiffres_sont_conserves(): void
    {
        $this->assertSame('VAGUE2026', ImportMapping::normaliser('Vague 2026'));
        $this->assertSame('AP1', ImportMapping::normaliser('AP - 1'));
    }

    #[Test]
    public function des_valeurs_reellement_differentes_ne_fusionnent_pas(): void
    {
        $this->assertNotSame(
            ImportMapping::normaliser('AGRICULTURE'),
            ImportMapping::normaliser('AGROALIMENTAIRE')
        );
    }

    public static function exemplesFormatage(): array
    {
        // Les exemples figurant dans la docblock de formaterLibelle().
        return [
            'accent et espace'    => ['Multi Éducation',    'MULTI_EDUCATION'],
            'deux mots'           => ['Artisanat DR',       'ARTISANAT_DR'],
            'apostrophe et et'    => ["L'Élevage & Pêche",  'L_ELEVAGE_PECHE'],
            'deja en snake case'  => ['STELLARIX_2026_001', 'STELLARIX_2026_001'],
            'sans separateur'     => ['MULTIEDUCATION',     'MULTIEDUCATION'],
            'espaces autour'      => ['  BTP-RS  ',         'BTP_RS'],
        ];
    }

    #[Test]
    #[DataProvider('exemplesFormatage')]
    public function formater_libelle_respecte_les_exemples_documentes(string $saisie, string $attendu): void
    {
        $this->assertSame($attendu, ImportMapping::formaterLibelle($saisie));
    }

    #[Test]
    public function formater_libelle_ne_laisse_pas_d_underscore_aux_extremites(): void
    {
        $this->assertSame('BTP_RS', ImportMapping::formaterLibelle('-BTP/RS-'));
        $this->assertSame('SECTEUR', ImportMapping::formaterLibelle('   (secteur)   '));
    }

    #[Test]
    public function les_deux_fonctions_sont_coherentes_entre_elles(): void
    {
        // Un libellé créé par formaterLibelle() doit se normaliser exactement
        // comme la valeur d'origine, sinon le référentiel tout juste créé
        // ressortirait « inconnu » au prochain import.
        foreach (['Multi Éducation', "L'Élevage & Pêche", 'BTP-RS', 'Artisanat DR'] as $saisie) {
            $libelleCree = ImportMapping::formaterLibelle($saisie);

            $this->assertSame(
                ImportMapping::normaliser($saisie),
                ImportMapping::normaliser($libelleCree),
                "« {$saisie} » créé comme « {$libelleCree} » doit être reconnu au prochain import."
            );
        }
    }
}
