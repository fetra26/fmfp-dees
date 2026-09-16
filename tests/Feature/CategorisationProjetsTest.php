<?php

namespace Tests\Feature;

use App\Models\PorteurProj;
use App\Services\CategorisationProjets;
use Database\Factories\PaiementFactory;
use Database\Factories\PorteurProjFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Taxonomie DEES des projets soumis.
 *
 *   Soumis
 *   ├── Validé
 *   │   ├── Non notifié
 *   │   └── Notifié
 *   │       ├── Engagé          J1 versé
 *   │       ├── Clôturé         J1 + J2 versés
 *   │       └── Sans convention aucun retour porteur
 *   ├── Refusé
 *   ├── Non éligible
 *   └── Incomplet
 *
 * L'avancement d'un projet notifié se lit sur les VERSEMENTS, pas sur les
 * montants conventionnés : une convention signée n'est pas un paiement.
 */
class CategorisationProjetsTest extends TestCase
{
    use RefreshDatabase;

    private function projet(array $attributs = []): PorteurProj
    {
        // statut_validation est explicité : la colonne vaut 'incomplet' par
        // défaut en base, ce qui classerait tous les projets de test en dossier
        // incomplet et masquerait ce que chaque cas cherche à éprouver.
        return PorteurProjFactory::new()->create(array_merge([
            'statut_validation'         => 'valide',
            'date_notification'         => '2026-01-10',
            'date_reception_convention' => '2026-02-01',
        ], $attributs));
    }

    private function verser(PorteurProj $pp, string ...$tranches): PorteurProj
    {
        foreach ($tranches as $tranche) {
            PaiementFactory::new()
                ->tranche($tranche, 1_000_000)
                ->create(['porteur_proj_id' => $pp->id]);
        }

        return $pp;
    }

    private function categorie(PorteurProj $pp): string
    {
        return CategorisationProjets::categorieDe($pp);
    }

    // ─────────── Premier niveau : le dossier n'a pas passé l'instruction

    #[Test]
    public function un_dossier_refuse_est_classe_refuse(): void
    {
        $this->assertSame('refuse', $this->categorie($this->projet(['statut_validation' => 'refuse'])));
    }

    #[Test]
    public function un_dossier_ineligible_est_distinct_d_un_refus(): void
    {
        // Deux catégories de premier niveau distinctes dans le schéma DEES :
        // les confondre masquerait le motif du rejet.
        $this->assertSame('non_eligible', $this->categorie($this->projet(['statut_validation' => 'inelig'])));
    }

    #[Test]
    public function un_dossier_incomplet_est_classe_incomplet(): void
    {
        $this->assertSame('incomplet', $this->categorie($this->projet(['statut_validation' => 'incomplet'])));
    }

    #[Test]
    public function l_attente_de_pieces_vaut_dossier_incomplet(): void
    {
        // « Attente pièces régul. » est la formulation DEES du dossier
        // incomplet : les deux doivent tomber dans la même catégorie.
        $this->assertSame('incomplet', $this->categorie($this->projet(['statut_validation' => 'attente_pieces_regul'])));
    }

    #[Test]
    public function un_projet_paye_n_est_jamais_classe_incomplet(): void
    {
        // Le piège principal : statut_validation vaut 'incomplet' par défaut en
        // base, et une cellule de statut vide dans le fichier donne la même
        // valeur. Un projet dont les tranches ont été versées serait alors
        // rangé parmi les dossiers incomplets, ce qui fausserait le pilotage.
        $pp = $this->verser($this->projet(['statut_validation' => 'incomplet']), 'J1', 'J2');

        $this->assertSame('cloture', $this->categorie($pp));
    }

    #[Test]
    public function un_statut_laisse_vide_par_defaut_ne_masque_pas_un_engagement(): void
    {
        $pp = PorteurProjFactory::new()->create([
            'date_notification'         => '2026-01-10',
            'date_reception_convention' => '2026-02-01',
        ]); // statut_validation non fourni : la base met 'incomplet'

        $this->verser($pp, 'J1');

        $this->assertSame('engage', $this->categorie($pp));
    }

    // ─────────── Validé : notifié ou non

    #[Test]
    public function un_projet_valide_sans_date_de_notification_est_non_notifie(): void
    {
        $this->assertSame('non_notifie', $this->categorie($this->projet(['date_notification' => null])));
    }

    #[Test]
    public function le_rejet_prime_sur_l_absence_de_notification(): void
    {
        $this->assertSame('refuse', $this->categorie($this->projet([
            'statut_validation' => 'refuse',
            'date_notification' => null,
        ])));
    }

    // ─────────── Notifié : l'avancement se lit sur les versements

    #[Test]
    public function un_projet_notifie_sans_retour_porteur_est_sans_convention(): void
    {
        $this->assertSame('sans_convention', $this->categorie($this->projet([
            'date_reception_convention' => null,
        ])));
    }

    #[Test]
    public function le_versement_de_j1_rend_le_projet_engage(): void
    {
        $pp = $this->verser($this->projet(), 'J1');

        $this->assertSame('engage', $this->categorie($pp));
    }

    #[Test]
    public function j1_et_j2_verses_cloturent_le_projet(): void
    {
        $pp = $this->verser($this->projet(), 'J1', 'J2');

        $this->assertSame('cloture', $this->categorie($pp));
    }

    #[Test]
    public function un_troisieme_jalon_ne_change_rien_a_la_cloture(): void
    {
        // Les cas équité comportent un J3 : sa présence ne doit ni empêcher
        // ni conditionner la clôture, acquise dès J1 + J2.
        $pp = $this->verser($this->projet(), 'J1', 'J2', 'J3');

        $this->assertSame('cloture', $this->categorie($pp));
    }

    #[Test]
    public function un_paiement_annule_ne_compte_pas_comme_versement(): void
    {
        $pp = $this->projet();
        PaiementFactory::new()->tranche('J1', 1_000_000)->annule()->create(['porteur_proj_id' => $pp->id]);

        // Une tranche annulée reste en base pour la traçabilité, mais ne fait
        // pas avancer le projet.
        $this->assertSame('notifie_sans_versement', $this->categorie($pp));
    }

    #[Test]
    public function un_paiement_supprime_ne_compte_pas_non_plus(): void
    {
        $pp = $this->verser($this->projet(), 'J1');
        $pp->paiements()->first()->delete();

        $this->assertSame('notifie_sans_versement', $this->categorie($pp));
    }

    #[Test]
    public function un_versement_prime_sur_l_absence_de_convention(): void
    {
        // Incohérence possible dans les données : de l'argent versé sans date
        // de retour de convention. Le fait le plus avancé l'emporte.
        $pp = $this->verser($this->projet(['date_reception_convention' => null]), 'J1');

        $this->assertSame('engage', $this->categorie($pp));
    }

    #[Test]
    public function un_projet_notifie_avec_convention_mais_sans_versement_est_isole(): void
    {
        // Ce cas ne figure dans aucune branche du schéma DEES : plutôt que de
        // le ranger d'office ailleurs, il est identifié pour être qualifié.
        $this->assertSame('notifie_sans_versement', $this->categorie($this->projet()));
    }

    // ─────────── Cohérence de l'arbre

    #[Test]
    public function la_somme_des_feuilles_vaut_le_total_soumis(): void
    {
        $this->verser($this->projet(), 'J1');                          // engagé
        $this->verser($this->projet(), 'J1', 'J2');                    // clôturé
        $this->projet(['date_reception_convention' => null]);           // sans convention
        $this->projet(['date_notification' => null]);                   // non notifié
        $this->projet(['statut_validation' => 'refuse']);               // refusé
        $this->projet(['statut_validation' => 'inelig']);               // non éligible
        $this->projet(['statut_validation' => 'attente_pieces_regul']); // incomplet
        $this->projet();                                                // notifié sans versement

        $comptes = CategorisationProjets::compter();
        $total   = CategorisationProjets::totalSoumis();

        $this->assertSame(8, $total);
        $this->assertSame($total, array_sum($comptes), 'Chaque projet doit tomber dans exactement une feuille.');

        $this->assertSame(1, $comptes['engage']);
        $this->assertSame(1, $comptes['cloture']);
        $this->assertSame(1, $comptes['sans_convention']);
        $this->assertSame(1, $comptes['non_notifie']);
        $this->assertSame(1, $comptes['refuse']);
        $this->assertSame(1, $comptes['non_eligible']);
        $this->assertSame(1, $comptes['incomplet']);
        $this->assertSame(1, $comptes['notifie_sans_versement']);
    }

    #[Test]
    public function notifie_est_la_somme_de_ses_branches(): void
    {
        $this->verser($this->projet(), 'J1');                // engagé
        $this->verser($this->projet(), 'J1', 'J2');          // clôturé
        $this->projet(['date_reception_convention' => null]); // sans convention
        $this->projet(['date_notification' => null]);         // non notifié — exclu
        $this->projet(['statut_validation' => 'refuse']);     // refusé — exclu

        $comptes = CategorisationProjets::compter();

        $this->assertSame(3, CategorisationProjets::agregat($comptes, 'notifie'));
    }

    #[Test]
    public function valide_englobe_les_notifies_et_les_non_notifies(): void
    {
        $this->verser($this->projet(), 'J1');            // notifié
        $this->projet(['date_notification' => null]);     // non notifié
        $this->projet(['statut_validation' => 'refuse']); // hors validé

        $comptes = CategorisationProjets::compter();

        $this->assertSame(2, CategorisationProjets::agregat($comptes, 'valide'));
    }

    #[Test]
    public function l_egalite_tient_sur_des_donnees_contradictoires(): void
    {
        foreach ([
            ['statut_validation' => 'refuse', 'date_notification' => '2026-01-01'],
            ['statut_validation' => 'inelig', 'date_reception_convention' => null],
            ['date_notification' => null, 'date_reception_convention' => null],
            ['statut_validation' => 'valide'],
        ] as $cas) {
            $this->projet($cas);
        }

        $this->assertSame(
            CategorisationProjets::totalSoumis(),
            array_sum(CategorisationProjets::compter())
        );
    }

    #[Test]
    public function chaque_feuille_est_presente_meme_a_zero(): void
    {
        $comptes = CategorisationProjets::compter();

        $this->assertSame(array_keys(CategorisationProjets::CATEGORIES), array_keys($comptes));
        $this->assertSame(0, array_sum($comptes));
    }

    #[Test]
    public function le_filtre_retrouve_exactement_les_projets_d_une_feuille(): void
    {
        $this->verser($this->projet(), 'J1');
        $this->verser($this->projet(), 'J1');
        $this->verser($this->projet(), 'J1', 'J2');

        $this->assertSame(2, CategorisationProjets::filtrer(PorteurProj::query(), 'engage')->count());
        $this->assertSame(1, CategorisationProjets::filtrer(PorteurProj::query(), 'cloture')->count());
    }
}
