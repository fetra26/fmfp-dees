<?php

namespace Tests\Feature;

use App\Imports\ProjetExcelImport;
use App\Models\Benef;
use App\Models\Projet;
use Database\Seeders\ReferentielsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Cellules contenant une FORMULE Excel non évaluée.
 *
 * Les classeurs de la DEES calculent « Nb bénéf total » par formule :
 *   =SUM(Tableau23[[#This Row],[Homme]],Tableau23[[#This Row],[Femme]])
 *
 * Le lecteur renvoie le texte de la formule, pas son résultat. Deux dégâts
 * s'ensuivaient, tous deux silencieux :
 *
 *   · parseInt en extrayait les chiffres du nom de table — « Tableau23 »
 *     devenait 23 — et ce 23 atterrissait comme nombre de bénéficiaires ;
 *   · splitMultiValue découpait la formule sur ses virgules et la prenait
 *     pour une liste de lieux multiples.
 *
 * Résultat observé sur un import réel : les 349 lignes de bénéficiaires
 * portaient un total incohérent, et le tableau de bord affichait des
 * répartitions par sexe supérieures au total.
 */
class ImportFormulesExcelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferentielsSeeder::class);
    }

    private const FORMULE = '=SUM(Tableau23[[#This Row],[Homme]],Tableau23[[#This Row],[Femme]])';

    private function appeler(string $methode, mixed ...$args): mixed
    {
        $m = new ReflectionMethod(ProjetExcelImport::class, $methode);
        $m->setAccessible(true);

        return $m->invoke(new ProjetExcelImport(), ...$args);
    }

    #[Test]
    public function une_formule_est_reconnue_comme_telle(): void
    {
        $this->assertTrue($this->appeler('estFormule', self::FORMULE));
        $this->assertTrue($this->appeler('estFormule', '  =A1+B1'));
        $this->assertFalse($this->appeler('estFormule', '104'));
        $this->assertFalse($this->appeler('estFormule', 104));
        $this->assertFalse($this->appeler('estFormule', null));
    }

    #[Test]
    public function une_formule_ne_produit_plus_un_effectif_fantaisiste(): void
    {
        // C'est le bug exact : « Tableau23 » donnait 23 bénéficiaires.
        $this->assertSame(0, $this->appeler('parseInt', self::FORMULE));
    }

    #[Test]
    public function une_formule_ne_produit_plus_un_montant_fantaisiste(): void
    {
        $this->assertSame(0, $this->appeler('parseMontant', '=SUM(A1:A9)'));
    }

    #[Test]
    public function un_nombre_normal_reste_correctement_lu(): void
    {
        $this->assertSame(104, $this->appeler('parseInt', '104'));
        $this->assertSame(1000000, $this->appeler('parseMontant', '1 000 000'));
    }

    #[Test]
    public function le_total_issu_d_une_formule_est_recalcule_depuis_h_et_f(): void
    {
        $import = new ProjetExcelImport();
        $import->collection(collect([$this->ligne([
            'D' => 'PROJ-001',
            'F' => 'STELLARIX',
            'O' => 'Formation',
            'P' => self::FORMULE,  // Nb bénéf total : formule
            'Q' => '68',           // Hommes
            'R' => '36',           // Femmes
        ])]));

        $benef = Benef::where('type', 'prevu')->first();

        $this->assertNotNull($benef, 'Les bénéficiaires devraient être enregistrés.');
        // La formule vaut SUM(Homme, Femme) : on la recalcule nous-mêmes.
        $this->assertSame(104, (int) $benef->total);
        $this->assertSame(68, (int) $benef->h);
        $this->assertSame(36, (int) $benef->f);
    }

    #[Test]
    public function le_total_recalcule_est_coherent_avec_la_repartition(): void
    {
        // Le symptôme visible au tableau de bord : hommes + femmes dépassait
        // le total affiché. L'égalité doit désormais tenir.
        $import = new ProjetExcelImport();
        $import->collection(collect([$this->ligne([
            'D' => 'PROJ-001', 'F' => 'STELLARIX', 'O' => 'Formation',
            'P' => self::FORMULE, 'Q' => '975', 'R' => '1393',
        ])]));

        $benef = Benef::where('type', 'prevu')->first();

        $this->assertSame((int) $benef->h + (int) $benef->f, (int) $benef->total);
    }

    #[Test]
    public function un_total_explicite_n_est_pas_ecrase_par_le_repli(): void
    {
        // Quand le total est un vrai nombre, il fait foi — même s'il diffère
        // de H + F, ce qui arrive si le sexe de certains n'est pas renseigné.
        $import = new ProjetExcelImport();
        $import->collection(collect([$this->ligne([
            'D' => 'PROJ-001', 'F' => 'STELLARIX', 'O' => 'Formation',
            'P' => '120', 'Q' => '68', 'R' => '36',
        ])]));

        $this->assertSame(120, (int) Benef::where('type', 'prevu')->first()->total);
    }

    #[Test]
    public function la_presence_d_une_formule_est_signalee_dans_le_rapport(): void
    {
        $import = new ProjetExcelImport();
        $import->collection(collect([$this->ligne([
            'D' => 'PROJ-001', 'F' => 'STELLARIX', 'O' => 'Formation',
            'P' => self::FORMULE, 'Q' => '68', 'R' => '36',
        ])]));

        // La DEES doit savoir que son classeur contient des formules : sans
        // message, la correction resterait invisible et le fichier source
        // continuerait de poser problème.
        $this->assertNotEmpty($import->errors);
        $this->assertStringContainsString('formule', implode(' ', $import->errors));
    }

    #[Test]
    public function une_formule_n_est_pas_prise_pour_une_liste_de_lieux(): void
    {
        // splitMultiValue découpait la formule sur ses virgules et croyait
        // voir quatre lieux distincts.
        $import = new ProjetExcelImport();
        $import->collection(collect([$this->ligne([
            'D' => 'PROJ-001', 'F' => 'STELLARIX', 'O' => 'Formation',
            'N' => 'ANALAMANGA',
            'P' => self::FORMULE, 'Q' => '68', 'R' => '36',
        ])]));

        $this->assertSame(1, Benef::where('type', 'prevu')->count(), 'Un seul lieu, donc une seule ligne.');
        $this->assertSame(1, Projet::count());
    }

    /** @param  array<string, mixed>  $colonnes */
    private function ligne(array $colonnes): Collection
    {
        $ligne = array_fill(0, 80, null);

        foreach ($colonnes as $lettre => $valeur) {
            $index = 0;
            foreach (str_split(strtoupper($lettre)) as $c) {
                $index = $index * 26 + (ord($c) - ord('A') + 1);
            }
            $ligne[$index - 1] = $valeur;
        }

        return collect($ligne);
    }
}
