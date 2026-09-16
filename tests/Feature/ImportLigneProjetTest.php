<?php

namespace Tests\Feature;

use App\Imports\ProjetExcelImport;
use App\Models\Convention;
use App\Models\ImportConflict;
use App\Models\Porteur;
use App\Models\PorteurProj;
use App\Models\Projet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Import d'une ligne Excel vers les entités métier.
 *
 * collection() accepte une Collection de lignes : on peut donc éprouver tout
 * le mapping des colonnes sans fabriquer de classeur, en passant directement
 * les valeurs brutes telles que PhpSpreadsheet les livrerait.
 *
 * Le risque couvert ici est le décalage de colonne : le template va de A à BF,
 * et une erreur d'un seul cran ferait atterrir le téléphone dans l'adresse ou
 * la date de fin dans la date de début — sans la moindre erreur visible.
 */
class ImportLigneProjetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Les référentiels officiels doivent exister, comme en exploitation :
        // la nomenclature des secteurs étant fermée, un import sur une base
        // sans référentiels ne rattacherait aucun secteur.
        $this->seed(\Database\Seeders\ReferentielsSeeder::class);
    }

    /**
     * Construit une ligne Excel à partir d'une carte « lettre de colonne => valeur ».
     *
     * @param  array<string, mixed>  $colonnes
     */
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

    private function importer(array ...$lignes): ProjetExcelImport
    {
        $import = new ProjetExcelImport();
        $import->collection(collect(array_map(fn ($l) => $this->ligne($l), $lignes)));

        return $import;
    }

    /** Une ligne représentative, avec une valeur distincte par colonne. */
    private function ligneComplete(): array
    {
        return [
            'A'  => 'THA',
            'B'  => 'VAGUE 1',
            'C'  => 'GUICHET A',
            'D'  => 'PROJ-001',
            'E'  => 'CONV-2026-014',
            'F'  => 'STELLARIX SARL',
            'G'  => '1234567890',
            'H'  => '42',
            'J'  => 'RAKOTO Jean',
            'K'  => '0340000000',
            'L'  => 'contact@stellarix.mg',
            'M'  => 'Lot II A 15 Antananarivo',
            'N'  => 'ANALAMANGA',
            'O'  => 'Formation en soudure industrielle',
            'AH' => 'En cours',
            'AM' => '01/02/2026',
            'AN' => '15/06/2026',
        ];
    }

    #[Test]
    public function une_ligne_complete_cree_le_projet_et_le_porteur(): void
    {
        $import = $this->importer($this->ligneComplete());

        $this->assertSame([], $import->errors);

        $projet = Projet::where('reference', 'PROJ-001')->first();
        $this->assertNotNull($projet, 'Le projet devrait être créé.');
        $this->assertSame('Formation en soudure industrielle', $projet->intitule);

        $porteur = Porteur::first();
        $this->assertNotNull($porteur);
        // La forme juridique est retirée : « STELLARIX SARL » devient « STELLARIX ».
        $this->assertSame('STELLARIX', $porteur->raison_sociale_normalisee);
    }

    #[Test]
    public function chaque_colonne_atterrit_dans_le_bon_champ(): void
    {
        // Le cœur du test : un décalage d'une seule colonne ferait échouer
        // au moins une de ces assertions.
        $this->importer($this->ligneComplete());

        $porteur = Porteur::first();

        $this->assertSame('1234567890',               $porteur->cnaps,           'colonne G');
        $this->assertSame(42,                         (int) $porteur->nb_salaries, 'colonne H');
        $this->assertSame('RAKOTO Jean',              $porteur->responsable_nom, 'colonne J');
        $this->assertSame('0340000000',               $porteur->telephone,       'colonne K');
        $this->assertSame('contact@stellarix.mg',     $porteur->email,           'colonne L');
        $this->assertSame('Lot II A 15 Antananarivo', $porteur->adresse,         'colonne M');
    }

    #[Test]
    public function les_dates_francaises_arrivent_correctement_en_base(): void
    {
        // Colonne AM = début (1er février), AN = fin (15 juin). Avant la
        // correction de parseDate, la première devenait le 2 janvier et la
        // seconde disparaissait purement et simplement.
        $this->importer($this->ligneComplete());

        $projet = Projet::where('reference', 'PROJ-001')->first();

        $this->assertSame('2026-02-01', $projet->date_debut?->toDateString(), 'colonne AM');
        $this->assertSame('2026-06-15', $projet->date_fin?->toDateString(), 'colonne AN');
    }

    #[Test]
    public function les_referentiels_sont_rattaches_au_projet(): void
    {
        $this->importer($this->ligneComplete());

        $projet = Projet::where('reference', 'PROJ-001')->first();

        // Le secteur vient de la nomenclature FMFP, fermée : « THA » y existe.
        $this->assertNotNull($projet->secteur_id, 'colonne A');

        // Vague, guichet et statut restent créés à la volée : les vagues sont
        // des campagnes numérotées qui apparaissent légitimement au fil du
        // temps, une liste fermée les bloquerait.
        $this->assertNotNull($projet->vague_id,   'colonne B');
        $this->assertNotNull($projet->guichet_id, 'colonne C');
        $this->assertNotNull($projet->statut_projet_id, 'colonne AH');
    }

    #[Test]
    public function la_convention_est_rattachee_au_projet(): void
    {
        $this->importer($this->ligneComplete());

        $projet = Projet::where('reference', 'PROJ-001')->first();

        $this->assertNotNull($projet->convention_id, 'colonne E');
        $this->assertSame('CONV-2026-014', Convention::find($projet->convention_id)?->reference);
    }

    #[Test]
    public function une_ligne_entierement_vide_est_ignoree_sans_bruit(): void
    {
        // Les classeurs de la DEES se terminent souvent par des lignes blanches.
        $import = $this->importer([], []);

        $this->assertSame(2, $import->skipped);
        $this->assertSame(0, $import->imported);
        $this->assertSame(0, Projet::count());
    }

    #[Test]
    public function sans_reference_projet_elle_est_derivee_de_la_convention(): void
    {
        $import = $this->importer([
            'E' => 'CONV-2026-014',
            'F' => 'STELLARIX',
            'O' => 'Formation',
        ]);

        $this->assertNotNull(Projet::where('reference', 'CONV_CONV-2026-014')->first());
        // La ligne est importée mais signalée : la DEES devra compléter.
        $this->assertNotEmpty($import->errors);
    }

    #[Test]
    public function sans_reference_ni_convention_la_ligne_est_marquee_a_completer(): void
    {
        $import = $this->importer(['F' => 'STELLARIX', 'O' => 'Formation']);

        $projet = Projet::first();

        $this->assertNotNull($projet);
        $this->assertStringStartsWith('A_REMPLIR_L', $projet->reference);
        $this->assertNotEmpty($import->errors);
    }

    #[Test]
    public function un_porteur_manquant_est_marque_a_completer(): void
    {
        $this->importer(['D' => 'PROJ-001', 'O' => 'Formation']);

        $this->assertStringStartsWith('A_REMPLIR_L', Porteur::first()?->raison_sociale ?? '');
    }

    #[Test]
    public function reimporter_la_meme_reference_ne_cree_pas_de_doublon(): void
    {
        $this->importer($this->ligneComplete());
        $import = $this->importer($this->ligneComplete());

        $this->assertSame(1, Projet::count(), 'Le projet ne doit pas être dupliqué.');
        $this->assertNotEmpty($import->doublonsProjets, 'La fusion doit être signalée.');
    }

    #[Test]
    public function reimporter_le_meme_porteur_ne_cree_pas_de_doublon(): void
    {
        $this->importer($this->ligneComplete());
        $this->importer($this->ligneComplete());

        // Le rapprochement se fait sur raison_sociale_normalisee. Si ce champ
        // n'est pas persisté, la recherche ne trouve jamais rien et chaque
        // import recrée l'entreprise.
        $this->assertSame(1, Porteur::count(), "L'entreprise ne doit pas être dupliquée.");
    }

    #[Test]
    public function un_porteur_ecrit_autrement_est_reconnu_comme_le_meme(): void
    {
        $a = $this->ligneComplete();
        $a['F'] = 'ACM-NET';

        $b = $this->ligneComplete();
        $b['D'] = 'PROJ-002';
        $b['F'] = 'Acm Net';

        $this->importer($a, $b);

        // « ACM-NET » et « Acm Net » normalisent tous deux en ACMNET.
        $this->assertSame(1, Porteur::count());
        $this->assertSame(2, Projet::count());
    }

    #[Test]
    public function une_reference_ecrite_autrement_designe_le_meme_projet(): void
    {
        $this->importer($this->ligneComplete());

        $autre = $this->ligneComplete();
        $autre['D'] = 'proj 001'; // même référence normalisée : PROJ001
        $this->importer($autre);

        $this->assertSame(1, Projet::count());
    }

    #[Test]
    public function une_valeur_deja_en_base_n_est_jamais_ecrasee_par_l_excel(): void
    {
        $this->importer($this->ligneComplete());

        // Deuxième passage avec un intitulé différent : la base fait foi.
        $modifie = $this->ligneComplete();
        $modifie['O'] = 'Intitulé different venant du fichier';
        $this->importer($modifie);

        $this->assertSame(
            'Formation en soudure industrielle',
            Projet::first()->intitule,
            "L'import ne doit jamais écraser une donnée saisie par la DEES."
        );
    }

    #[Test]
    public function une_divergence_entre_base_et_fichier_est_journalisee(): void
    {
        $this->importer($this->ligneComplete());

        $modifie = $this->ligneComplete();
        $modifie['O'] = 'Intitulé different venant du fichier';
        $this->importer($modifie);

        // Le conflit est tracé pour être arbitré plus tard, plutôt que perdu.
        $this->assertGreaterThan(0, ImportConflict::count());
    }

    #[Test]
    public function un_champ_vide_dans_le_fichier_ne_vide_pas_la_base(): void
    {
        $this->importer($this->ligneComplete());

        $sansIntitule = $this->ligneComplete();
        $sansIntitule['O'] = '';
        $this->importer($sansIntitule);

        $this->assertSame('Formation en soudure industrielle', Projet::first()->intitule);
    }

    #[Test]
    public function plusieurs_lignes_sont_traitees_en_un_passage(): void
    {
        $a = $this->ligneComplete();
        $b = $this->ligneComplete();
        $b['D'] = 'PROJ-002';
        $b['F'] = 'AUTRE PORTEUR';

        $this->importer($a, $b);

        $this->assertSame(2, Projet::count());
        $this->assertSame(2, Porteur::count());
    }

    #[Test]
    public function le_lien_porteur_projet_est_cree(): void
    {
        $this->importer($this->ligneComplete());

        $lien = PorteurProj::first();

        $this->assertNotNull($lien, 'Le rattachement porteur/projet doit exister.');
        $this->assertSame(Projet::first()->id, $lien->projet_id);
        $this->assertSame(Porteur::first()->id, $lien->porteur_id);
    }
}
