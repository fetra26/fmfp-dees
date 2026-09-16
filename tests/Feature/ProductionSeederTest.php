<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ProductionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ProductionSeeder crée le compte administrateur initial lors du déploiement.
 * C'est le tout premier accès à l'application : s'il échoue, personne ne peut
 * se connecter, et l'erreur n'apparaît qu'en production.
 */
class ProductionSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    #[Test]
    public function il_cree_un_compte_administrateur_exploitable(): void
    {
        $this->seed(ProductionSeeder::class);

        $admin = User::where('email', 'admin@fmfp.mg')->first();

        $this->assertNotNull($admin, 'Aucun compte administrateur créé.');
        $this->assertTrue($admin->hasRole('admin'), "Le compte n'a pas le rôle admin.");
        $this->assertTrue((bool) $admin->is_active, 'Le compte est inactif.');
        $this->assertNotNull($admin->email_verified_at, "L'e-mail n'est pas marqué comme vérifié.");
    }

    #[Test]
    public function organization_type_respecte_les_valeurs_de_l_enum(): void
    {
        $this->seed(ProductionSeeder::class);

        $admin = User::where('email', 'admin@fmfp.mg')->first();

        // La colonne est un enum('internal','external'). Toute autre valeur est
        // rejetée par MySQL en mode strict — le mode par defaut de Laravel.
        $this->assertContains(
            $admin?->organization_type,
            ['internal', 'external'],
            "organization_type vaut « {$admin?->organization_type} », "
            . "hors des valeurs autorisées par l'enum de la colonne."
        );
    }

    #[Test]
    public function il_honore_les_variables_d_environnement_initial_admin(): void
    {
        config(['app.dummy' => null]);
        putenv('INITIAL_ADMIN_EMAIL=direction@fmfp.mg');

        $this->seed(ProductionSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'direction@fmfp.mg']);

        putenv('INITIAL_ADMIN_EMAIL');
    }

    #[Test]
    public function il_est_idempotent_et_ne_cree_pas_de_second_admin(): void
    {
        $this->seed(ProductionSeeder::class);
        $this->seed(ProductionSeeder::class);

        $this->assertSame(
            1,
            User::role('admin')->count(),
            'Relancer le seeder a créé un administrateur en double.'
        );
    }
}
