<?php

namespace Tests\Feature;

use App\Imports\ProjetExcelImport;
use App\Models\Guichet;
use App\Models\Vague;
use Database\Seeders\ReferentielsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Les référentiels ouverts ne doivent pas se dédoubler.
 *
 * Vagues et guichets naissent de deux sources qui ne codaient pas pareil :
 *
 *   · l'assistant d'import produit un code en UPPER_SNAKE_CASE  → « AP5_EQ »
 *   · la résolution automatique retirait tous les séparateurs   → « AP5EQ »
 *
 * La recherche portant sur le seul code, les deux ne se reconnaissaient pas.
 * Constaté en base après un import réel : deux vagues distinctes portant le
 * même libellé « AP5_EQ ». La répartition par vague s'en serait trouvée
 * coupée en deux, comme l'avait été celle des secteurs.
 */
class ReferentielsSansDoublonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferentielsSeeder::class);
    }

    private function resoudre(string $methode, string $texte, ProjetExcelImport $import): mixed
    {
        $m = new ReflectionMethod(ProjetExcelImport::class, $methode);
        $m->setAccessible(true);

        return $m->invoke($import, $texte);
    }

    #[Test]
    public function une_vague_deja_creee_par_l_assistant_est_reconnue(): void
    {
        // Ce que l'assistant enregistre quand l'utilisateur choisit « Créer » :
        // code en UPPER_SNAKE_CASE, libellé identique.
        Vague::create(['code' => 'AP5_EQ', 'libelle' => 'AP5_EQ', 'is_active' => true]);

        // Ce que le fichier contient, avec son point final.
        $vague = $this->resoudre('resoudreVague', 'AP5_EQ.', new ProjetExcelImport());

        $this->assertSame('AP5_EQ', $vague->code, 'La vague existante devait être retrouvée.');
        $this->assertSame(1, Vague::count(), 'Aucune seconde vague ne doit être créée.');
    }

    public static function graphiesDeLaMemeVague(): array
    {
        return [
            'point final'  => ['AP5_EQ.'],
            'sans point'   => ['AP5_EQ'],
            'sans tiret'   => ['AP5EQ'],
            'avec espace'  => ['AP5 EQ'],
            'minuscules'   => ['ap5_eq'],
            'avec tiret'   => ['AP5-EQ'],
        ];
    }

    #[Test]
    #[DataProvider('graphiesDeLaMemeVague')]
    public function toutes_les_graphies_menent_a_la_meme_vague(string $saisie): void
    {
        Vague::create(['code' => 'AP5_EQ', 'libelle' => 'AP5_EQ', 'is_active' => true]);

        $vague = $this->resoudre('resoudreVague', $saisie, new ProjetExcelImport());

        $this->assertSame('AP5_EQ', $vague->code, "« {$saisie} » doit retrouver la vague existante.");
        $this->assertSame(1, Vague::count());
    }

    #[Test]
    public function une_vague_reellement_nouvelle_est_bien_creee(): void
    {
        // La liste des vagues reste OUVERTE : ce sont des campagnes numérotées
        // qui apparaissent légitimement au fil du temps.
        $vague = $this->resoudre('resoudreVague', 'AP7_2027', new ProjetExcelImport());

        $this->assertNotNull($vague);
        $this->assertSame(1, Vague::count());
    }

    #[Test]
    public function deux_vagues_differentes_ne_fusionnent_pas(): void
    {
        $import = new ProjetExcelImport();

        $this->resoudre('resoudreVague', 'AP5_EQ', $import);
        $this->resoudre('resoudreVague', 'AP6_EQ', $import);

        $this->assertSame(2, Vague::count());
    }

    #[Test]
    public function un_guichet_deja_present_n_est_pas_recree(): void
    {
        // Le seeder charge les 7 guichets DEES, dont RIE.
        $avant = Guichet::count();

        $guichet = $this->resoudre('resoudreGuichet', 'R.I.E.', new ProjetExcelImport());

        $this->assertSame('RIE', $guichet->code);
        $this->assertSame($avant, Guichet::count(), 'Aucun guichet ne doit être ajouté.');
    }

    #[Test]
    public function le_meme_libelle_repete_ne_cree_qu_une_entree(): void
    {
        $import = new ProjetExcelImport();

        foreach (['AP5_EQ.', 'AP5_EQ', 'ap5 eq', 'AP5-EQ'] as $graphie) {
            $this->resoudre('resoudreVague', $graphie, $import);
        }

        $this->assertSame(1, Vague::count());
    }
}
