<?php

namespace Tests\Feature;

use App\Filament\Pages\ImportWizard;
use App\Models\Guichet;
use App\Models\Region;
use App\Models\Secteur;
use App\Models\StatutProjet;
use App\Models\Vague;
use Database\Seeders\ReferentielsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Création de référentiels depuis l'assistant d'import.
 *
 * Les cinq tables de référentiel portent une colonne code NOT NULL, UNIQUE et
 * sans valeur par défaut. L'assistant ne la renseignait que pour les statuts :
 * créer un secteur, une vague ou un guichet échouait donc sur
 * « SQLSTATE[HY000] Field 'code' doesn't have a default value ».
 */
class ImportWizardReferentielsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferentielsSeeder::class);
    }

    private function genererCode(string $modelClass, string $libelle, int $longueurMax): string
    {
        $m = new ReflectionMethod(ImportWizard::class, 'genererCodeReferentiel');
        $m->setAccessible(true);

        return $m->invoke(new ImportWizard(), $modelClass, $libelle, $longueurMax);
    }

    public static function referentielsCreables(): array
    {
        return [
            'vague'   => [Vague::class,         'VAGUE_2027',        20],
            'guichet' => [Guichet::class,        'GUICHET_SPECIAL',   20],
            'statut'  => [StatutProjet::class,   'VALIDATION_FINANCIERE_BIS', 30],
        ];
    }

    #[Test]
    #[DataProvider('referentielsCreables')]
    public function un_referentiel_peut_etre_cree_avec_un_code_valide(string $modelClass, string $libelle, int $max): void
    {
        $code = $this->genererCode($modelClass, $libelle, $max);

        $this->assertNotSame('', $code);
        $this->assertLessThanOrEqual($max, strlen($code), "Le code dépasse la longueur de colonne.");

        // C'est l'insertion réelle qui échouait : on la rejoue.
        $cree = $modelClass::create(['libelle' => $libelle, 'code' => $code]);

        $this->assertTrue($cree->exists);
    }

    #[Test]
    public function le_code_est_derive_du_libelle(): void
    {
        $this->assertSame('VAGUE_2027', $this->genererCode(Vague::class, 'Vague 2027', 20));
        $this->assertSame('GUICHET_SPECIAL', $this->genererCode(Guichet::class, 'Guichet Spécial', 20));
    }

    #[Test]
    public function un_code_deja_pris_recoit_un_suffixe(): void
    {
        Vague::create(['libelle' => 'Vague 2027', 'code' => 'VAGUE_2027']);

        $code = $this->genererCode(Vague::class, 'Vague 2027', 20);

        // La colonne est UNIQUE : sans suffixe, MySQL rejetterait l'insertion.
        $this->assertNotSame('VAGUE_2027', $code);
        $this->assertSame('VAGUE_2027_2', $code);
    }

    #[Test]
    public function un_libelle_trop_long_est_tronque_a_la_taille_de_colonne(): void
    {
        $code = $this->genererCode(Vague::class, str_repeat('A', 60), 20);

        $this->assertSame(20, strlen($code));
    }

    #[Test]
    public function la_troncature_laisse_place_au_suffixe(): void
    {
        $libelle = str_repeat('A', 60);
        Vague::create(['libelle' => 'occupant', 'code' => $this->genererCode(Vague::class, $libelle, 20)]);

        $code = $this->genererCode(Vague::class, $libelle, 20);

        // Le suffixe ne doit pas faire déborder la colonne.
        $this->assertLessThanOrEqual(20, strlen($code));
        $this->assertStringEndsWith('_2', $code);
    }

    #[Test]
    public function la_nomenclature_des_secteurs_reste_fermee(): void
    {
        // Le seeder charge les 11 secteurs officiels FMFP. L'assistant ne doit
        // jamais en créer un douzième depuis un fichier : sinon le référentiel
        // se repollue et la répartition du tableau de bord se fragmente.
        $this->assertSame(11, Secteur::count());
    }

    #[Test]
    public function les_regions_officielles_restent_au_nombre_de_vingt_trois(): void
    {
        $this->assertSame(23, Region::count());
    }
}
