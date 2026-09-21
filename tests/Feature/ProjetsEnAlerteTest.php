<?php

namespace Tests\Feature;

use App\Filament\Pages\ProjetsEnAlerte;
use App\Models\PorteurProj;
use Database\Factories\PorteurProjFactory;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Écran « Projets en alerte ».
 *
 * Le suivi des retards est la raison d'être de l'application, et il n'existait
 * jusqu'ici que sous forme de quatre compteurs sur le tableau de bord : aucun
 * moyen de savoir QUELS projets relancer.
 */
class ProjetsEnAlerteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = \App\Models\User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
    }

    private function projet(array $attributs): PorteurProj
    {
        return PorteurProjFactory::new()->create($attributs);
    }

    #[Test]
    public function la_page_s_affiche(): void
    {
        Livewire::test(ProjetsEnAlerte::class)->assertOk();
    }

    #[Test]
    public function elle_liste_les_projets_dont_l_echeance_est_depassee(): void
    {
        $enRetard = $this->projet(['date_fin' => now()->subDays(95), 'niveau_alerte' => 'rouge']);

        Livewire::test(ProjetsEnAlerte::class)
            ->assertCanSeeTableRecords([$enRetard]);
    }

    #[Test]
    public function elle_ecarte_par_defaut_les_projets_encore_dans_les_temps(): void
    {
        // « Verte » est aussi la valeur par d\u00e9faut du job pour un projet \u00e0
        // l'heure : sans le filtre, la liste m\u00ealerait dossiers \u00e0 relancer et
        // dossiers qui vont tr\u00e8s bien.
        $aLHeure = $this->projet(['date_fin' => now()->addDays(30), 'niveau_alerte' => 'verte']);

        Livewire::test(ProjetsEnAlerte::class)
            ->assertCanNotSeeTableRecords([$aLHeure]);
    }

    #[Test]
    public function l_onglet_rouge_ne_montre_que_les_alertes_rouges(): void
    {
        $rouge  = $this->projet(['date_fin' => now()->subDays(95), 'niveau_alerte' => 'rouge']);
        $orange = $this->projet(['date_fin' => now()->subDays(70), 'niveau_alerte' => 'orange']);

        Livewire::test(ProjetsEnAlerte::class)
            ->call('changerNiveau', 'rouge')
            ->assertCanSeeTableRecords([$rouge])
            ->assertCanNotSeeTableRecords([$orange]);
    }

    #[Test]
    public function l_onglet_toutes_rassemble_les_niveaux(): void
    {
        $rouge  = $this->projet(['date_fin' => now()->subDays(95), 'niveau_alerte' => 'rouge']);
        $orange = $this->projet(['date_fin' => now()->subDays(70), 'niveau_alerte' => 'orange']);
        $verte  = $this->projet(['date_fin' => now()->subDays(40), 'niveau_alerte' => 'verte']);

        Livewire::test(ProjetsEnAlerte::class)
            ->call('changerNiveau', 'rouge')
            ->call('changerNiveau', null)
            ->assertCanSeeTableRecords([$rouge, $orange, $verte]);
    }

    #[Test]
    public function chaque_onglet_porte_une_icone_distincte(): void
    {
        // Les icônes doublent la couleur plutôt que de s'y substituer : environ
        // un homme sur douze distingue mal le rouge du vert, et la seule teinte
        // ne dirait pas lequel des onglets appelle une résiliation.
        $icones = collect(ProjetsEnAlerte::NIVEAUX)->map(fn ($n) => $n[2]);

        $this->assertCount(3, $icones->unique(), 'Chaque niveau doit avoir sa propre icône.');

        foreach ($icones as $icone) {
            $this->assertStringStartsWith('heroicon-', $icone);
        }
    }

    #[Test]
    public function les_icones_des_onglets_sont_rendues_dans_la_page(): void
    {
        $this->projet(['date_fin' => now()->subDays(95), 'niveau_alerte' => 'rouge']);

        Livewire::test(ProjetsEnAlerte::class)
            ->assertOk()
            ->assertSee('Rouge')
            ->assertSee('Orange')
            ->assertSee('Verte')
            ->assertSee('Toutes');
    }

    #[Test]
    public function chaque_onglet_annonce_son_effectif(): void
    {
        $this->projet(['date_fin' => now()->subDays(95),  'niveau_alerte' => 'rouge']);
        $this->projet(['date_fin' => now()->subDays(100), 'niveau_alerte' => 'rouge']);
        $this->projet(['date_fin' => now()->subDays(70),  'niveau_alerte' => 'orange']);
        $this->projet(['date_fin' => now()->subDays(40),  'niveau_alerte' => 'verte']);

        $comptes = (new ProjetsEnAlerte())->comptesParNiveau();

        $this->assertSame(2, $comptes['rouge']);
        $this->assertSame(1, $comptes['orange']);
        $this->assertSame(1, $comptes['verte']);
        $this->assertSame(4, $comptes['tous'], 'Le total doit être la somme des niveaux.');
    }

    #[Test]
    public function les_compteurs_d_onglets_excluent_les_projets_dans_les_temps(): void
    {
        // « Verte » est aussi la valeur attribuée à un projet à l'heure : un
        // comptage brut gonflerait cet onglet de dossiers qui vont très bien.
        $this->projet(['date_fin' => now()->addDays(30), 'niveau_alerte' => 'verte']);
        $this->projet(['date_fin' => now()->subDays(40), 'niveau_alerte' => 'verte']);

        $comptes = (new ProjetsEnAlerte())->comptesParNiveau();

        $this->assertSame(1, $comptes['verte']);
    }

    #[Test]
    public function la_pastille_compte_les_dossiers_a_traiter(): void
    {
        $this->projet(['date_fin' => now()->subDays(95), 'niveau_alerte' => 'rouge']);
        $this->projet(['date_fin' => now()->subDays(70), 'niveau_alerte' => 'orange']);
        // Une verte n'appelle pas d'action imm\u00e9diate : elle ne compte pas.
        $this->projet(['date_fin' => now()->subDays(40), 'niveau_alerte' => 'verte']);

        $this->assertSame('2', ProjetsEnAlerte::getNavigationBadge());
    }

    #[Test]
    public function un_dossier_resilie_ne_compte_plus_dans_la_pastille(): void
    {
        // La proc\u00e9dure est all\u00e9e \u00e0 son terme : il n'y a plus rien \u00e0 relancer.
        $this->projet([
            'date_fin'         => now()->subDays(120),
            'niveau_alerte'    => 'rouge',
            'date_resiliation' => now()->subDays(5),
        ]);

        $this->assertNull(ProjetsEnAlerte::getNavigationBadge());
    }

    #[Test]
    public function la_pastille_vire_au_rouge_des_qu_un_dossier_l_est(): void
    {
        $this->projet(['date_fin' => now()->subDays(70), 'niveau_alerte' => 'orange']);
        $this->assertSame('warning', ProjetsEnAlerte::getNavigationBadgeColor());

        $this->projet(['date_fin' => now()->subDays(95), 'niveau_alerte' => 'rouge']);
        $this->assertSame('danger', ProjetsEnAlerte::getNavigationBadgeColor());
    }

    #[Test]
    public function sans_projet_en_alerte_la_pastille_disparait(): void
    {
        $this->assertNull(ProjetsEnAlerte::getNavigationBadge());
    }
}
