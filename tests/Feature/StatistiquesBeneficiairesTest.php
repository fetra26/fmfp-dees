<?php

namespace Tests\Feature;

use App\Models\Benef;
use App\Models\PorteurProj;
use App\Models\Region;
use App\Models\Secteur;
use App\Services\StatistiquesBeneficiaires;
use Database\Factories\PorteurProjFactory;
use Database\Seeders\ReferentielsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Agrégation des bénéficiaires par secteur et par région.
 *
 * Deux pièges guettent ce type de requête, et les tests les visent :
 *   · la jointure des bénéficiaires multiplie les lignes — un projet portant
 *     une ligne prévue ET une ligne réalisée serait compté deux fois si le
 *     décompte des projets n'était pas distinct ;
 *   · un secteur sans projet doit apparaître à zéro, pas disparaître.
 */
class StatistiquesBeneficiairesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferentielsSeeder::class);
    }

    private function projetDans(string $codeSecteur, ?string $codeRegion = null): PorteurProj
    {
        $secteur = Secteur::where('code', $codeSecteur)->firstOrFail();
        $region = $codeRegion ? Region::where('code', $codeRegion)->firstOrFail() : null;

        $pp = PorteurProjFactory::new()->create();
        $pp->projet->update([
            'secteur_id' => $secteur->id,
            'region_id'  => $region?->id,
        ]);

        return $pp;
    }

    private function beneficiaires(PorteurProj $pp, string $type, array $chiffres, ?string $codeRegion = null): void
    {
        Benef::create(array_merge([
            'porteur_proj_id' => $pp->id,
            'type'            => $type,
            'region_id'       => $codeRegion ? Region::where('code', $codeRegion)->value('id') : null,
            'total'  => 0, 'h' => 0, 'f' => 0,
            'jeunes' => 0, 'fpe' => 0, 'cadres' => 0,
            'source' => 'saisie_manuelle',
        ], $chiffres));
    }

    private function ligneSecteur(string $code): ?object
    {
        return StatistiquesBeneficiaires::parSecteur()->get()->firstWhere('code', $code);
    }

    #[Test]
    public function les_onze_secteurs_apparaissent_meme_sans_projet(): void
    {
        // Un secteur vide doit se voir : c'est une information en soi.
        $this->assertCount(11, StatistiquesBeneficiaires::parSecteur()->get());
    }

    #[Test]
    public function un_secteur_sans_projet_affiche_des_zeros(): void
    {
        $ligne = $this->ligneSecteur('THA');

        $this->assertSame(0, (int) $ligne->nb_projets);
        $this->assertSame(0, (int) $ligne->total_realise);
    }

    #[Test]
    public function les_beneficiaires_sont_ventiles_par_phase(): void
    {
        $pp = $this->projetDans('THA');
        $this->beneficiaires($pp, 'prevu',   ['total' => 100, 'h' => 60, 'f' => 40]);
        $this->beneficiaires($pp, 'realise', ['total' => 80,  'h' => 45, 'f' => 35]);

        $ligne = $this->ligneSecteur('THA');

        $this->assertSame(100, (int) $ligne->total_prevu);
        $this->assertSame(80,  (int) $ligne->total_realise);
        $this->assertSame(60,  (int) $ligne->h_prevu);
        $this->assertSame(35,  (int) $ligne->f_realise);
    }

    #[Test]
    public function un_projet_avec_prevu_et_realise_n_est_compte_qu_une_fois(): void
    {
        // La jointure produit deux lignes pour ce projet : sans COUNT DISTINCT,
        // le tableau afficherait deux projets là où il n'y en a qu'un.
        $pp = $this->projetDans('THA');
        $this->beneficiaires($pp, 'prevu',   ['total' => 50]);
        $this->beneficiaires($pp, 'realise', ['total' => 45]);

        $this->assertSame(1, (int) $this->ligneSecteur('THA')->nb_projets);
    }

    #[Test]
    public function les_jeunes_les_cadres_et_la_fpe_sont_agreges(): void
    {
        $pp = $this->projetDans('DR');
        $this->beneficiaires($pp, 'realise', [
            'total' => 200, 'h' => 120, 'f' => 80,
            'jeunes' => 90, 'fpe' => 30, 'cadres' => 12,
        ]);

        $ligne = $this->ligneSecteur('DR');

        $this->assertSame(90, (int) $ligne->jeunes_realise);
        $this->assertSame(30, (int) $ligne->fpe_realise);
        // « cadres » vaut « femmes cadres » dans le modèle d'import : ce chiffre
        // ne compte que des femmes, et aucune ventilation n'est possible.
        $this->assertSame(12, (int) $ligne->cadres_realise);
    }

    #[Test]
    public function les_secteurs_ne_se_melangent_pas(): void
    {
        $this->beneficiaires($this->projetDans('THA'), 'realise', ['total' => 100]);
        $this->beneficiaires($this->projetDans('TIC'), 'realise', ['total' => 250]);

        $this->assertSame(100, (int) $this->ligneSecteur('THA')->total_realise);
        $this->assertSame(250, (int) $this->ligneSecteur('TIC')->total_realise);
        $this->assertSame(0,   (int) $this->ligneSecteur('DR')->total_realise);
    }

    #[Test]
    public function le_filtre_region_transforme_le_tableau_en_croisement(): void
    {
        $analamanga = Region::where('code', 'R01')->firstOrFail();

        $this->beneficiaires($this->projetDans('THA', 'R01'), 'realise', ['total' => 100]);
        $this->beneficiaires($this->projetDans('THA', 'R02'), 'realise', ['total' => 700]);

        $ligne = StatistiquesBeneficiaires::parSecteur($analamanga->id)->get()->firstWhere('code', 'THA');

        $this->assertSame(100, (int) $ligne->total_realise, 'Seule Analamanga doit être comptée.');
    }

    #[Test]
    public function la_region_du_beneficiaire_prime_sur_celle_du_projet(): void
    {
        $itasy = Region::where('code', 'R03')->firstOrFail();

        // Projet rattaché à Analamanga, mais formation tenue dans l'Itasy.
        $pp = $this->projetDans('TIC', 'R01');
        $this->beneficiaires($pp, 'realise', ['total' => 60], 'R03');

        $ligne = StatistiquesBeneficiaires::parSecteur($itasy->id)->get()->firstWhere('code', 'TIC');

        $this->assertSame(60, (int) $ligne->total_realise, 'La région du bénéficiaire doit primer.');
    }

    #[Test]
    public function la_region_du_projet_sert_de_repli(): void
    {
        $analamanga = Region::where('code', 'R01')->firstOrFail();

        $pp = $this->projetDans('TIC', 'R01');
        $this->beneficiaires($pp, 'realise', ['total' => 60]); // sans région propre

        $ligne = StatistiquesBeneficiaires::parSecteur($analamanga->id)->get()->firstWhere('code', 'TIC');

        $this->assertSame(60, (int) $ligne->total_realise);
    }

    #[Test]
    public function le_classement_des_regions_place_la_plus_fournie_en_tete(): void
    {
        $this->beneficiaires($this->projetDans('THA', 'R02'), 'realise', ['total' => 10]);
        $this->beneficiaires($this->projetDans('TIC', 'R02'), 'realise', ['total' => 20]);
        $this->beneficiaires($this->projetDans('DR',  'R01'), 'realise', ['total' => 900]);

        $classement = StatistiquesBeneficiaires::parRegion()->get();

        // R02 porte deux projets, R01 un seul : le classement suit le nombre
        // de projets, pas le volume de bénéficiaires.
        $this->assertSame('R02', $classement->first()->code);
        $this->assertSame(2, (int) $classement->first()->nb_projets);
    }

    #[Test]
    public function les_totaux_globaux_recoupent_la_somme_des_secteurs(): void
    {
        $this->beneficiaires($this->projetDans('THA'), 'realise', ['total' => 100, 'f' => 60]);
        $this->beneficiaires($this->projetDans('TIC'), 'realise', ['total' => 250, 'f' => 90]);
        $this->beneficiaires($this->projetDans('DR'),  'prevu',   ['total' => 400]);

        $global = StatistiquesBeneficiaires::global();
        $parSecteur = StatistiquesBeneficiaires::parSecteur()->get();

        $this->assertSame(350, $global['total_realise']);
        $this->assertSame(150, $global['f_realise']);
        $this->assertSame(400, $global['total_prevu']);
        $this->assertSame(3, $global['nb_projets']);

        $this->assertSame(
            $global['total_realise'],
            (int) $parSecteur->sum(fn ($l) => (int) $l->total_realise),
            'Le global doit recouper la somme des secteurs.'
        );
    }

    #[Test]
    public function le_taux_d_atteinte_est_un_pourcentage_entier(): void
    {
        $this->assertSame(80, StatistiquesBeneficiaires::tauxAtteinte(100, 80));
        $this->assertSame(50, StatistiquesBeneficiaires::tauxAtteinte(200, 100));
        $this->assertSame(120, StatistiquesBeneficiaires::tauxAtteinte(100, 120));
    }

    #[Test]
    public function sans_objectif_le_taux_est_indefini_et_non_nul(): void
    {
        // Afficher 0 % laisserait croire à un échec, alors qu'il n'y avait
        // simplement rien de prévu.
        $this->assertNull(StatistiquesBeneficiaires::tauxAtteinte(0, 50));
        $this->assertNull(StatistiquesBeneficiaires::tauxAtteinte(null, 50));
    }
}
