<?php

namespace Tests\Unit;

use App\Models\PorteurProj;
use App\Models\Projet;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * La normalisation des références pilote le rapprochement des projets à
 * l'import Excel : une référence saisie « STELLARIX-2026-001 » doit retrouver
 * le projet enregistré « STELLARIX_2026_001 ».
 *
 * Une régression ici ne lève aucune erreur — elle fait simplement échouer des
 * correspondances, et les lignes concernées atterrissent en rejet.
 */
class NormalisationReferenceTest extends TestCase
{
    public static function variantesEquivalentes(): array
    {
        return [
            'underscores'          => ['STELLARIX_2026_001'],
            'tirets'               => ['STELLARIX-2026-001'],
            'espaces'              => ['STELLARIX 2026 001'],
            'minuscules'           => ['stellarix_2026_001'],
            'casse mixte'          => ['Stellarix-2026-001'],
            'points'               => ['STELLARIX.2026.001'],
            'slashs'               => ['STELLARIX/2026/001'],
            'deja normalisee'      => ['STELLARIX2026001'],
            'espaces en trop'      => ['  STELLARIX  2026  001  '],
            'separateurs melanges' => ['stellarix -_/. 2026 _ 001'],
        ];
    }

    #[Test]
    #[DataProvider('variantesEquivalentes')]
    public function toutes_les_graphies_convergent_vers_la_meme_reference(string $saisie): void
    {
        $this->assertSame(
            'STELLARIX2026001',
            Projet::normaliserReference($saisie),
            "« {$saisie} » doit se normaliser en STELLARIX2026001."
        );
    }

    public static function valeursVides(): array
    {
        return [
            'null'                  => [null],
            'chaine vide'           => [''],
            'espaces seuls'         => ['   '],
            'separateurs seuls'     => ['---'],
            'melange de separateurs'=> [' _ - / . '],
        ];
    }

    #[Test]
    #[DataProvider('valeursVides')]
    public function une_reference_vide_ou_sans_contenu_donne_null(?string $saisie): void
    {
        // Important : renvoyer null et non '' — sinon deux projets sans référence
        // se normaliseraient tous deux en chaîne vide et seraient considérés
        // comme le même projet lors du rapprochement à l'import.
        $this->assertNull(Projet::normaliserReference($saisie));
    }

    #[Test]
    public function le_trait_est_partage_par_projet_et_porteur_proj(): void
    {
        // Les deux modèles doivent normaliser à l'identique, sinon une référence
        // de convention ne retrouverait pas son projet.
        $saisie = 'Conv-2026/014';

        $this->assertSame(
            Projet::normaliserReference($saisie),
            PorteurProj::normaliserReference($saisie)
        );
        $this->assertSame('CONV2026014', Projet::normaliserReference($saisie));
    }

    #[Test]
    public function les_references_reellement_differentes_ne_sont_pas_confondues(): void
    {
        $a = Projet::normaliserReference('STELLARIX-2026-001');
        $b = Projet::normaliserReference('STELLARIX-2026-010');

        $this->assertNotSame($a, $b, 'Deux références distinctes ne doivent pas fusionner.');
    }

    #[Test]
    public function les_accents_sont_conserves_et_ne_sont_pas_des_separateurs(): void
    {
        // Comportement constaté : mb_strtoupper met en majuscules sans retirer
        // les accents. « Éval » et « Eval » restent donc DISTINCTS.
        // C'est un piège à connaître si des références accentuées apparaissent
        // un jour dans les fichiers de la DEES.
        $this->assertSame('ÉVAL2026', Projet::normaliserReference('éval-2026'));
        $this->assertNotSame(
            Projet::normaliserReference('eval-2026'),
            Projet::normaliserReference('éval-2026')
        );
    }
}
