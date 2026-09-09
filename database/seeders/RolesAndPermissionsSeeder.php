<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crée tous les rôles et permissions définis dans config/roles.php.
 *
 * Idempotent : peut être ré-exécuté sans dupliquer les données.
 * À lancer après chaque modification de config/roles.php :
 *   php artisan db:seed --class=RolesAndPermissionsSeeder
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Vider le cache des permissions pour éviter les incohérences
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $config = config('roles');

        // --- 1. Créer toutes les permissions ---
        foreach ($config['permissions'] as $permission) {
            Permission::firstOrCreate([
                'name'       => $permission,
                'guard_name' => 'web',
            ]);
        }

        $this->command->info(sprintf(
            '✔ %d permissions créées/vérifiées.',
            count($config['permissions'])
        ));

        // --- 2. Créer les rôles et leur attacher les permissions ---
        foreach ($config['roles'] as $roleName => $roleData) {
            $role = Role::firstOrCreate([
                'name'       => $roleName,
                'guard_name' => 'web',
            ]);

            // Le rôle admin reçoit toutes les permissions ('*')
            if (in_array('*', $roleData['permissions'], true)) {
                $role->syncPermissions(Permission::all());
            } else {
                $role->syncPermissions($roleData['permissions']);
            }

            $this->command->info(sprintf(
                '✔ Rôle "%s" : %d permissions attachées.',
                $roleName,
                count($role->permissions)
            ));
        }

        // --- 3. Vider le cache une nouvelle fois ---
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
