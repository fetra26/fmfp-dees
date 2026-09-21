<?php

namespace Tests\Feature;

use App\Jobs\ClassifyAlertsJob;
use App\Models\PorteurProj;
use Database\Factories\PaiementFactory;
use Database\Factories\PorteurProjFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Règle de suivi des alertes.
 *
 * Le suivi poursuit les conventions dont l'échéance est passée SANS que le
 * projet soit clos. Un dossier clôturé, annulé, résilié ou intégralement
 * versé n'a plus à être relancé : il en sort.
 *
 *   verte   30 à 59 jours   première relance préventive
 *   orange  60 à 89 jours   deuxième relance et mise en demeure
 *   rouge   90 jours et +   procédure de résiliation
 *
 * niveau_alerte vaut null hors de ces cas. Auparavant la colonne était NOT
 * NULL avec 'verte' par défaut : « verte » désignait donc aussi bien un projet
 * à l'heure qu'un retard appelant une relance, et le compteur « alertes
 * vertes » du tableau de bord affichait en réalité tout le portefeuille.
 */
class SuiviDesAlertesTest extends TestCase
{
    use RefreshDatabase;

    private function projet(array $attributs = []): PorteurProj
    {
        return PorteurProjFactory::new()->create(array_merge([
            'statut_validation' => 'valide',
            'date_fin'          => now()->subDays(95),
        ], $attributs));
    }

    private function classer(): ClassifyAlertsJob
    {
        $job = new ClassifyAlertsJob();
        $job->handle();

        return $job;
    }

    // ─────────── Seuils

    public static function seuils(): array
    {
        return [
            'echeance a venir'   => [-10, null],
            'echeance du jour'   => [0,   null],
            '29 jours de retard' => [29,  null],
            '30 jours'           => [30,  'verte'],
            '59 jours'           => [59,  'verte'],
            '60 jours'           => [60,  'orange'],
            '89 jours'           => [89,  'orange'],
            '90 jours'           => [90,  'rouge'],
            '200 jours'          => [200, 'rouge'],
        ];
    }

    #[Test]
    #[DataProvider('seuils')]
    public function le_niveau_suit_les_jours_de_retard(int $joursRetard, ?string $attendu): void
    {
        $pp = $this->projet(['date_fin' => now()->subDays($joursRetard)]);

        $this->classer();

        $this->assertSame($attendu, $pp->fresh()->niveau_alerte);
    }

    #[Test]
    public function un_retard_de_moins_de_30_jours_n_est_pas_une_alerte(): void
    {
        // Le cœur de la correction : 'verte' ne doit plus désigner un projet
        // qui n'appelle aucune action.
        $pp = $this->projet(['date_fin' => now()->subDays(10)]);

        $this->classer();

        $this->assertNull($pp->fresh()->niveau_alerte);
    }

    // ─────────── Sortie du suivi

    public static function statutsHorsSuivi(): array
    {
        return [
            'cloture'      => ['cloture'],
            'fini cloture' => ['fini_cloture'],
            'annule'       => ['annule'],
            'resilie'      => ['resilie'],
        ];
    }

    #[Test]
    #[DataProvider('statutsHorsSuivi')]
    public function un_projet_clos_sort_du_suivi(string $statut): void
    {
        // C'est la demande de la DEES : l'utilité de ce suivi est de poursuivre
        // les conventions échues qui ne sont PAS closes.
        $pp = $this->projet(['statut_validation' => $statut, 'date_fin' => now()->subDays(200)]);

        $this->classer();

        $this->assertNull($pp->fresh()->niveau_alerte);
    }

    #[Test]
    public function un_projet_dont_j1_et_j2_sont_verses_sort_du_suivi(): void
    {
        // Même si le statut n'a pas été mis à jour : le porteur est soldé,
        // le relancer n'aurait aucun sens.
        $pp = $this->projet(['date_fin' => now()->subDays(200)]);
        PaiementFactory::new()->tranche('J1', 1_000_000)->create(['porteur_proj_id' => $pp->id]);
        PaiementFactory::new()->tranche('J2', 1_000_000)->create(['porteur_proj_id' => $pp->id]);

        $this->classer();

        $this->assertNull($pp->fresh()->niveau_alerte);
    }

    #[Test]
    public function un_projet_dont_seul_j1_est_verse_reste_sous_suivi(): void
    {
        $pp = $this->projet(['date_fin' => now()->subDays(95)]);
        PaiementFactory::new()->tranche('J1', 1_000_000)->create(['porteur_proj_id' => $pp->id]);

        $this->classer();

        $this->assertSame('rouge', $pp->fresh()->niveau_alerte);
    }

    #[Test]
    public function une_tranche_annulee_ne_solde_pas_le_projet(): void
    {
        $pp = $this->projet(['date_fin' => now()->subDays(95)]);
        PaiementFactory::new()->tranche('J1', 1_000_000)->create(['porteur_proj_id' => $pp->id]);
        PaiementFactory::new()->tranche('J2', 1_000_000)->annule()->create(['porteur_proj_id' => $pp->id]);

        $this->classer();

        $this->assertSame('rouge', $pp->fresh()->niveau_alerte);
    }

    #[Test]
    public function un_projet_sans_date_de_fin_n_est_jamais_en_alerte(): void
    {
        $pp = $this->projet(['date_fin' => null]);

        $this->classer();

        $this->assertNull($pp->fresh()->niveau_alerte);
    }

    // ─────────── Remise à zéro des niveaux devenus caducs

    #[Test]
    public function un_projet_passe_au_rouge_puis_cloture_perd_son_alerte(): void
    {
        // Le défaut le plus sournois de l'ancienne version : les projets clos
        // étaient ÉCARTÉS de la requête, donc jamais réexaminés. Leur dernier
        // niveau restait figé en base — un rouge fantôme, indéfiniment.
        $pp = $this->projet(['date_fin' => now()->subDays(120)]);
        $this->classer();
        $this->assertSame('rouge', $pp->fresh()->niveau_alerte);

        $pp->update(['statut_validation' => 'cloture']);
        $this->classer();

        $this->assertNull($pp->fresh()->niveau_alerte);
    }

    #[Test]
    public function la_sortie_du_suivi_est_comptabilisee(): void
    {
        $pp = $this->projet(['date_fin' => now()->subDays(120)]);
        $this->classer();

        $pp->update(['statut_validation' => 'cloture']);
        $job = $this->classer();

        $this->assertSame(1, $job->sortiesDuSuivi);
    }

    #[Test]
    public function seuls_les_projets_reellement_en_alerte_sont_comptes(): void
    {
        $this->projet(['date_fin' => now()->subDays(95)]);                            // rouge
        $this->projet(['date_fin' => now()->subDays(70)]);                            // orange
        $this->projet(['date_fin' => now()->subDays(40)]);                            // verte
        $this->projet(['date_fin' => now()->addDays(30)]);                            // a l'heure
        $this->projet(['date_fin' => now()->subDays(200), 'statut_validation' => 'cloture']);

        $this->classer();

        $this->assertSame(3, PorteurProj::whereNotNull('niveau_alerte')->count());
        $this->assertSame(2, PorteurProj::whereNull('niveau_alerte')->count());
    }

    #[Test]
    public function relancer_le_job_ne_change_rien_si_rien_n_a_bouge(): void
    {
        $this->projet(['date_fin' => now()->subDays(95)]);
        $this->classer();

        $second = $this->classer();

        $this->assertSame(0, $second->totalReclassifies, 'Un second passage ne doit rien reclasser.');
    }
}
