<?php

namespace Tests\Feature;

use App\Imports\ProjetExcelImport;
use App\Models\Projet;
use App\Models\Secteur;
use Database\Seeders\ReferentielsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Nomenclature officielle FMFP : 11 secteurs, liste fermée.
 *
 * L'import créait auparavant un secteur pour chaque graphie rencontrée, d'où
 * 54 entrées pour 11 secteurs réels. Le graphique de répartition agrégeant par
 * libellé, « THA », « Textile » et « TEXTILE/HABILLEMENT » comptaient comme
 * trois secteurs distincts : le total restait juste, mais la répartition était
 * fausse, sans que rien ne le signale à l'écran.
 */
class NomenclatureSecteursTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferentielsSeeder::class);
    }

    private function resoudre(string $texte, ?ProjetExcelImport $import = null): ?Secteur
    {
        $import ??= new ProjetExcelImport();
        $m = new ReflectionMethod(ProjetExcelImport::class, 'resoudreSecteur');
        $m->setAccessible(true);

        return $m->invoke($import, $texte);
    }

    #[Test]
    public function la_nomenclature_compte_exactement_onze_secteurs(): void
    {
        $this->assertSame(11, Secteur::count());

        $this->assertEqualsCanonicalizing([
            'THA', 'THR', 'TIC', 'BTP_RS', 'DR',
            'MULTI_EDUCATION', 'MULTI_SANTE', 'MULTI_TRANSPORT',
            'MULTI_INDUSTRIE', 'MULTI_COMMERCE', 'MULTI_AUTRE',
        ], Secteur::pluck('code')->all());
    }

    public static function graphiesEquivalentes(): array
    {
        return [
            // Par code, toutes casses et séparateurs confondus
            'code exact'          => ['THA',    'THA'],
            'code minuscule'      => ['tha',    'THA'],
            'code avec slash'     => ['BTP/RS', 'BTP_RS'],
            'code avec tiret'     => ['btp-rs', 'BTP_RS'],
            'code avec espace'    => ['BTP RS', 'BTP_RS'],

            // Par libellé officiel
            'libelle exact'       => ['Multi santé',     'MULTI_SANTE'],
            'libelle sans accent' => ['MULTI SANTE',     'MULTI_SANTE'],
            'libelle underscore'  => ['MULTI_INDUSTRIE', 'MULTI_INDUSTRIE'],

            // Par alias : mot seul vers le « Multi » correspondant
            'mot seul sante'      => ['SANTE',     'MULTI_SANTE'],
            'mot seul autre'      => ['AUTRE',     'MULTI_AUTRE'],
            'pluriel autres'      => ['Autres',    'MULTI_AUTRE'],
            'multi seul'          => ['MULTI',     'MULTI_AUTRE'],
            'multi minuscule'     => ['multi',     'MULTI_AUTRE'],
            'multi autres'        => ['Multi autres', 'MULTI_AUTRE'],
            'mot seul commerce'   => ['Commerce',  'MULTI_COMMERCE'],
            'mot seul education'  => ['Education', 'MULTI_EDUCATION'],
            'mot seul transport'  => ['Transport', 'MULTI_TRANSPORT'],
            'mot seul industrie'  => ['Industrie', 'MULTI_INDUSTRIE'],

            // Par alias : forme abrégée vers secteur complet
            'btp seul'            => ['BTP',         'BTP_RS'],
            'batiment'            => ['Batiment',    'BTP_RS'],
            'textile'             => ['Textile',     'THA'],
            'tourisme'            => ['Tourisme',    'THR'],
            'numerique'           => ['Numerique',   'TIC'],
            'agriculture'         => ['Agriculture', 'DR'],
            'peche'               => ['Peche',       'DR'],
        ];
    }

    #[Test]
    #[DataProvider('graphiesEquivalentes')]
    public function une_graphie_du_fichier_retrouve_son_secteur(string $saisie, string $codeAttendu): void
    {
        $secteur = $this->resoudre($saisie);

        $this->assertNotNull($secteur, "« {$saisie} » devrait être rattaché à {$codeAttendu}.");
        $this->assertSame($codeAttendu, $secteur->code);
    }

    #[Test]
    public function un_secteur_hors_nomenclature_n_est_jamais_cree(): void
    {
        $this->resoudre('Blockchain spatiale');

        // C'est la règle de fond : sans elle, charger les 11 bons secteurs ne
        // servirait à rien, le référentiel se repolluerait au premier fichier.
        $this->assertSame(11, Secteur::count());
    }

    #[Test]
    public function un_secteur_hors_nomenclature_laisse_le_champ_vide(): void
    {
        $this->assertNull($this->resoudre('Blockchain spatiale'));
    }

    #[Test]
    public function un_secteur_hors_nomenclature_est_signale_une_seule_fois(): void
    {
        $import = new ProjetExcelImport();

        $this->resoudre('Blockchain spatiale', $import);
        $this->resoudre('Blockchain spatiale', $import);
        $this->resoudre('Blockchain spatiale', $import);

        // Un fichier de 2 000 lignes ne doit pas produire 2 000 fois le même
        // message : le rapport doit rester lisible.
        $this->assertSame(['Blockchain spatiale'], $import->secteursInconnus);
        $this->assertCount(1, $import->errors);
    }

    #[Test]
    public function une_cellule_vide_ne_signale_rien(): void
    {
        $import = new ProjetExcelImport();

        $this->assertNull($this->resoudre('', $import));
        $this->assertNull($this->resoudre('   ', $import));
        $this->assertSame([], $import->errors);
    }

    #[Test]
    public function a_l_import_un_secteur_inconnu_laisse_le_projet_sans_secteur(): void
    {
        $import = new ProjetExcelImport();
        $import->collection(collect([$this->ligne([
            'A' => 'Blockchain spatiale',
            'D' => 'PROJ-001',
            'F' => 'STELLARIX',
            'O' => 'Formation',
        ])]));

        $projet = Projet::where('reference', 'PROJ-001')->first();

        $this->assertNotNull($projet, 'Le projet doit être importé malgré le secteur inconnu.');
        $this->assertNull($projet->secteur_id, 'La DEES complétera le secteur depuis l’interface.');
        $this->assertSame(11, Secteur::count());
    }

    #[Test]
    public function a_l_import_un_alias_est_correctement_rattache(): void
    {
        $import = new ProjetExcelImport();
        $import->collection(collect([$this->ligne([
            'A' => 'BTP',
            'D' => 'PROJ-001',
            'F' => 'STELLARIX',
            'O' => 'Formation',
        ])]));

        $projet = Projet::where('reference', 'PROJ-001')->first();

        $this->assertSame('BTP_RS', Secteur::find($projet->secteur_id)?->code);
    }

    #[Test]
    public function des_graphies_differentes_alimentent_le_meme_secteur(): void
    {
        // Le cas qui faussait le tableau de bord : trois écritures du textile
        // doivent produire UNE part du camembert, pas trois.
        $import = new ProjetExcelImport();
        $import->collection(collect([
            $this->ligne(['A' => 'THA',                 'D' => 'P-1', 'F' => 'A', 'O' => 'F']),
            $this->ligne(['A' => 'Textile',             'D' => 'P-2', 'F' => 'B', 'O' => 'F']),
            $this->ligne(['A' => 'TEXTILE/HABILLEMENT', 'D' => 'P-3', 'F' => 'C', 'O' => 'F']),
        ]));

        $this->assertSame(3, Projet::count());
        $this->assertSame(
            1,
            Projet::whereNotNull('secteur_id')->distinct()->count('secteur_id'),
            'Les trois projets doivent pointer vers le même secteur.'
        );
    }

    /** @param  array<string, mixed>  $colonnes */
    private function ligne(array $colonnes): Collection
    {
        $ligne = array_fill(0, 60, null);

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
