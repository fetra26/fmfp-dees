<?php

namespace Tests\Feature;

use App\Models\PorteurProj;
use App\Services\CategorisationProjets;
use Database\Factories\PorteurProjFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Contrôle de cohérence de la DEES :
 *   soumis = notifié + engagé + refusé + annulé + clôturé
 *
 * Les catégories sont dérivées des faits en base — dates, montants, situation
 * d'allocation — et non d'un libellé de statut saisi à la main. Un projet est
 * classé dans l'état LE PLUS AVANCÉ qu'il a atteint, sans quoi un projet
 * clôturé serait aussi compté comme notifié et la somme dépasserait le total.
 */
class CategorisationProjetsTest extends TestCase
{
    use RefreshDatabase;

    private function projet(array $attributs = []): PorteurProj
    {
        // La factory renseigne montant_total : on part d'un projet nu, pour que
        // chaque test ne porte que le fait qu'il veut éprouver.
        return PorteurProjFactory::new()->create(array_merge([
            'montant_total'       => 0,
            'financement_demande' => 0,
        ], $attributs));
    }

    private function categorie(array $attributs = []): string
    {
        return CategorisationProjets::categorieDe($this->projet($attributs));
    }

    #[Test]
    public function un_projet_sans_aucun_fait_reste_soumis_sans_suite(): void
    {
        // Ni date, ni montant, ni statut : le projet est en base mais échappe
        // à tout suivi. C'est le seul indicateur qui demande une action.
        $this->assertSame('soumis_seul', $this->categorie());
    }

    #[Test]
    public function une_date_de_notification_suffit_a_le_rendre_notifie(): void
    {
        $this->assertSame('notifie', $this->categorie(['date_notification' => '2026-03-01']));
    }

    #[Test]
    public function un_montant_total_le_rend_engage(): void
    {
        $this->assertSame('engage', $this->categorie(['montant_total' => 5_000_000]));
    }

    #[Test]
    public function un_financement_demande_suffit_aussi(): void
    {
        $this->assertSame('engage', $this->categorie(['financement_demande' => 2_000_000]));
    }

    #[Test]
    public function un_montant_a_zero_ne_vaut_pas_engagement(): void
    {
        $this->assertSame('notifie', $this->categorie([
            'date_notification' => '2026-03-01',
            'montant_total'     => 0,
        ]));
    }

    #[Test]
    public function un_statut_refuse_le_rend_refuse(): void
    {
        $this->assertSame('refuse', $this->categorie(['statut_validation' => 'refuse']));
        $this->assertSame('refuse', $this->categorie(['statut_validation' => 'inelig']));
    }

    #[Test]
    public function une_date_de_resiliation_le_rend_annule(): void
    {
        $this->assertSame('annule', $this->categorie(['date_resiliation' => '2026-05-01']));
    }

    #[Test]
    public function une_allocation_annulee_le_rend_annule(): void
    {
        $this->assertSame('annule', $this->categorie(['situation_alloc' => 'annule']));
    }

    #[Test]
    public function un_statut_cloture_le_rend_cloture(): void
    {
        $this->assertSame('cloture', $this->categorie(['statut_validation' => 'cloture']));
        $this->assertSame('cloture', $this->categorie(['statut_validation' => 'fini_cloture']));
    }

    #[Test]
    public function la_cloture_prime_sur_tout_le_reste(): void
    {
        // Un projet clôturé a forcément été notifié et engagé : il ne doit
        // apparaître QUE dans « clôturé ».
        $this->assertSame('cloture', $this->categorie([
            'statut_validation'   => 'cloture',
            'date_notification'   => '2026-01-10',
            'montant_total'       => 8_000_000,
            'date_resiliation'    => '2026-06-01',
        ]));
    }

    #[Test]
    public function l_annulation_prime_sur_l_engagement_et_la_notification(): void
    {
        $this->assertSame('annule', $this->categorie([
            'date_resiliation'  => '2026-06-01',
            'date_notification' => '2026-01-10',
            'montant_total'     => 8_000_000,
        ]));
    }

    #[Test]
    public function l_engagement_prime_sur_la_notification(): void
    {
        $this->assertSame('engage', $this->categorie([
            'date_notification' => '2026-01-10',
            'montant_total'     => 8_000_000,
        ]));
    }

    #[Test]
    public function la_somme_des_categories_vaut_le_total_soumis(): void
    {
        // Le contrôle de cohérence demandé par la DEES, sur un échantillon
        // couvrant tous les cas de figure.
        $this->projet();                                               // sans suite
        $this->projet(['date_notification' => '2026-01-10']);           // notifié
        $this->projet(['date_notification' => '2026-01-11']);           // notifié
        $this->projet(['montant_total' => 3_000_000]);                  // engagé
        $this->projet(['statut_validation' => 'refuse']);               // refusé
        $this->projet(['date_resiliation' => '2026-02-01']);            // annulé
        $this->projet(['statut_validation' => 'cloture']);              // clôturé
        $this->projet(['statut_validation' => 'fini_cloture']);         // clôturé

        $comptes = CategorisationProjets::compter();
        $total   = CategorisationProjets::totalSoumis();

        $this->assertSame(8, $total);
        $this->assertSame(
            $total,
            array_sum($comptes),
            'soumis doit égaler notifié + engagé + refusé + annulé + clôturé + sans suite.'
        );

        $this->assertSame(2, $comptes['notifie']);
        $this->assertSame(1, $comptes['engage']);
        $this->assertSame(1, $comptes['refuse']);
        $this->assertSame(1, $comptes['annule']);
        $this->assertSame(2, $comptes['cloture']);
        $this->assertSame(1, $comptes['soumis_seul']);
    }

    #[Test]
    public function l_egalite_tient_meme_sur_des_donnees_contradictoires(): void
    {
        // La cascade étant exhaustive, aucune combinaison de faits ne peut
        // faire tomber un projet dans deux catégories ni dans aucune.
        foreach ([
            ['statut_validation' => 'cloture', 'date_resiliation' => '2026-01-01'],
            ['statut_validation' => 'refuse',  'montant_total' => 9_000_000],
            ['situation_alloc' => 'annule',    'date_notification' => '2026-01-01'],
            ['statut_validation' => 'valide',  'date_notification' => '2026-01-01'],
        ] as $cas) {
            $this->projet($cas);
        }

        $this->assertSame(
            CategorisationProjets::totalSoumis(),
            array_sum(CategorisationProjets::compter())
        );
    }

    #[Test]
    public function chaque_categorie_est_presente_meme_a_zero(): void
    {
        // Le widget lit ce tableau directement : une clé manquante le ferait
        // planter sur une base vide.
        $comptes = CategorisationProjets::compter();

        $this->assertSame(
            array_keys(CategorisationProjets::CATEGORIES),
            array_keys($comptes)
        );
        $this->assertSame(0, array_sum($comptes));
    }

    #[Test]
    public function le_filtre_retrouve_exactement_les_projets_d_une_categorie(): void
    {
        $this->projet(['date_notification' => '2026-01-10']);
        $this->projet(['date_notification' => '2026-01-11']);
        $this->projet(['statut_validation' => 'cloture']);

        $notifies = CategorisationProjets::filtrer(PorteurProj::query(), 'notifie')->count();

        $this->assertSame(2, $notifies);
    }
}
