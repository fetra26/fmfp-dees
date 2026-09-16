<?php

namespace Tests\Unit;

use App\Services\PreflightScanner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Détection des cellules « région » contenant en réalité plusieurs lieux
 * ou une description libre, plutôt qu'une région unique.
 *
 * L'enjeu : une cellule multi-lieu ne doit pas être proposée à l'utilisateur
 * comme une région inconnue à créer — sinon la base se remplit de faux
 * référentiels du genre « VAKINANKARATRA / AMBANJA / MANAKARA ».
 */
class DetectionMultiLieuTest extends TestCase
{
    private function estMultiLieu(string $texte): bool
    {
        $m = new ReflectionMethod(PreflightScanner::class, 'estMultiLieu');
        $m->setAccessible(true);

        return $m->invoke(new PreflightScanner(), $texte);
    }

    public static function casMultiLieu(): array
    {
        return [
            'slash'            => ['VAKINANKARATRA / AMBANJA'],
            'point-virgule'    => ['ANALAMANGA;ITASY'],
            'barre verticale'  => ['ANALAMANGA|ITASY'],
            'retour a la ligne' => ['ANALAMANGA' . "\n" . 'ITASY'],
            'enumeration longue' => ['ANALAMANGA, VAKINANKARATRA, ITASY'],
            'texte tres long'  => ['ANALAMANGAVAKINANKARATRAITASYBONGOLAVA'],
            'region repetee'   => ['region nord region sud'],
            'district repete'  => ['district A district B'],
        ];
    }

    #[Test]
    #[DataProvider('casMultiLieu')]
    public function il_repere_les_cellules_a_eclater(string $texte): void
    {
        $this->assertTrue(
            $this->estMultiLieu($texte),
            "« {$texte} » doit être vu comme multi-lieu."
        );
    }

    public static function casRegionUnique(): array
    {
        return [
            'region simple'     => ['VAKINANKARATRA'],
            'region courte'     => ['ITASY'],
            'region composee'   => ['ATSIMO ANDREFANA'],
            'avec un seul mot-cle' => ['region Diana'],
        ];
    }

    #[Test]
    #[DataProvider('casRegionUnique')]
    public function il_laisse_passer_une_region_unique(string $texte): void
    {
        $this->assertFalse(
            $this->estMultiLieu($texte),
            "« {$texte} » est une région unique et ne doit pas être éclatée."
        );
    }

    #[Test]
    public function une_region_composee_avec_virgule_reste_une_region(): void
    {
        // Cas explicitement prévu dans le code : « ATSIMO, ANDREFANA » porte une
        // virgule mais désigne UNE région. La règle ne déclenche qu'au-delà de
        // 20 caractères, ce qui épargne ce cas (17 caractères).
        $this->assertFalse($this->estMultiLieu('ATSIMO, ANDREFANA'));
    }

    #[Test]
    public function le_seuil_de_longueur_est_bien_a_30_caracteres(): void
    {
        // Sans séparateur ni mot-clé, seule la longueur décide.
        $trente     = str_repeat('A', 30);
        $trenteEtUn = str_repeat('A', 31);

        $this->assertFalse($this->estMultiLieu($trente), '30 caractères : encore accepté.');
        $this->assertTrue($this->estMultiLieu($trenteEtUn), '31 caractères : considéré multi-lieu.');
    }

    #[Test]
    public function un_mot_cle_geographique_isole_ne_suffit_pas(): void
    {
        // Le code exige DEUX occurrences : « commune » seul reste une valeur
        // plausible, alors que le répéter signale une description libre.
        $this->assertFalse($this->estMultiLieu('commune Ambanja'));
        $this->assertTrue($this->estMultiLieu('commune X commune Y'));
    }
}
