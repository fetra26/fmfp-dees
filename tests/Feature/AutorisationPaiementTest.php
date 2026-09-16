<?php

namespace Tests\Feature;

use App\Models\Paiement;
use App\Models\User;
use App\Policies\PaiementPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Règle métier critique du cahier des charges : SEUL le DAF (et l'admin)
 * peut saisir un paiement. Les autres rôles y ont accès en lecture seule.
 *
 * Les noms de rôles existent en deux conventions (l'UI permet de les renommer),
 * d'où les doublets 'daf'/'equipe_daf' : les deux doivent être reconnus.
 */
class AutorisationPaiementTest extends TestCase
{
    use RefreshDatabase;

    private function utilisateurAvecRole(?string $role): User
    {
        if ($role !== null) {
            Role::findOrCreate($role, 'web');
        }

        $user = User::factory()->create();

        if ($role !== null) {
            $user->assignRole($role);
        }

        // Spatie met les permissions en cache : sans reset, un rôle créé après
        // le premier appel n'est pas vu et le test passe pour de mauvaises raisons.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    public static function rolesAutorisesAEditer(): array
    {
        return [
            'admin'       => ['admin'],
            'super_admin' => ['super_admin'],
            'daf'         => ['daf'],
            'equipe_daf'  => ['equipe_daf'],
        ];
    }

    public static function rolesInterditsDEditer(): array
    {
        return [
            'chargé de projet'    => ['project_manager'],
            'équipe DEES'         => ['equipe_dees'],
            'responsable DEES'    => ['responsable_dees'],
            'évaluateur'          => ['evaluateur'],
            'direction'           => ['direction'],
            'direction DEES'      => ['direction_dees'],
            'contrôleur interne'  => ['controleur_interne'],
            'sans aucun rôle'     => [null],
        ];
    }

    #[Test]
    #[DataProvider('rolesAutorisesAEditer')]
    public function le_daf_et_l_admin_peuvent_saisir_un_paiement(string $role): void
    {
        $user = $this->utilisateurAvecRole($role);

        $this->assertTrue(
            $user->peutEditerPaiement(),
            "Le rôle « {$role} » doit pouvoir saisir un paiement."
        );
    }

    #[Test]
    #[DataProvider('rolesInterditsDEditer')]
    public function les_autres_roles_ne_peuvent_pas_saisir_un_paiement(?string $role): void
    {
        $user = $this->utilisateurAvecRole($role);

        $this->assertFalse(
            $user->peutEditerPaiement(),
            "Le rôle « " . ($role ?? 'aucun') . " » ne doit PAS pouvoir saisir un paiement : "
            . "c'est la règle de séparation des tâches exigée par la DEES."
        );
    }

    #[Test]
    public function la_policy_refuse_la_creation_aux_roles_non_daf(): void
    {
        $policy = new PaiementPolicy();

        $daf  = $this->utilisateurAvecRole('daf');
        $dees = $this->utilisateurAvecRole('equipe_dees');

        $this->assertTrue($policy->create($daf));
        $this->assertFalse($policy->create($dees));
    }

    #[Test]
    public function la_policy_refuse_aussi_la_modification_et_la_suppression(): void
    {
        $policy   = new PaiementPolicy();
        $dees     = $this->utilisateurAvecRole('equipe_dees');
        $paiement = new Paiement();

        // update et delete doivent suivre la même règle que create : un accès en
        // lecture ne doit jamais ouvrir une porte d'écriture détournée.
        $this->assertFalse($policy->update($dees, $paiement));
        $this->assertFalse($policy->delete($dees, $paiement));
    }

    #[Test]
    public function tous_les_roles_internes_peuvent_consulter_les_paiements(): void
    {
        foreach (['admin', 'daf', 'equipe_dees', 'evaluateur', 'direction', 'controleur_interne'] as $role) {
            $user = $this->utilisateurAvecRole($role);

            $this->assertTrue(
                $user->peutVoirPaiement(),
                "Le rôle « {$role} » doit pouvoir consulter les paiements."
            );
        }
    }

    #[Test]
    public function un_utilisateur_sans_role_ne_voit_pas_les_paiements(): void
    {
        $this->assertFalse($this->utilisateurAvecRole(null)->peutVoirPaiement());
    }

    #[Test]
    public function seul_le_super_admin_peut_restaurer_ou_purger_un_paiement(): void
    {
        $policy   = new PaiementPolicy();
        $paiement = new Paiement();

        $this->assertTrue($policy->restore($this->utilisateurAvecRole('admin'), $paiement));
        $this->assertFalse($policy->restore($this->utilisateurAvecRole('daf'), $paiement));
        $this->assertFalse($policy->forceDelete($this->utilisateurAvecRole('daf'), $paiement));
    }
}
