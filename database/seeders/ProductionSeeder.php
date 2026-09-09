<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder MINIMAL pour la production.
 *
 * Crée uniquement :
 *   - 1 compte Super Admin (à personnaliser IMMÉDIATEMENT après le premier login)
 *
 * NE crée PAS de comptes de test, PAS de données factices, PAS de départements.
 * Tout ça se fait depuis l'UI par le Super Admin après déploiement.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        // Sécurité : n'insère PAS de super admin s'il en existe déjà un
        if (User::role('admin')->exists()) {
            $this->command->info('✔ Un Super Admin existe déjà — aucun compte créé.');
            return;
        }

        $email = env('INITIAL_ADMIN_EMAIL', 'admin@fmfp.mg');
        $password = env('INITIAL_ADMIN_PASSWORD', 'ChangezMoiTresVite!2026');

        $user = User::create([
            'matricule'         => 'SUPERADMIN',
            'first_name'        => 'Super',
            'last_name'         => 'Admin',
            'name'              => 'Super Admin',
            'email'             => $email,
            'password'          => Hash::make($password),
            'organization_type' => 'interne',
            'is_active'         => true,
            'email_verified_at' => now(),
        ]);

        $user->assignRole('admin');

        $this->command->newLine();
        $this->command->info('╔═══════════════════════════════════════════════════════╗');
        $this->command->info('║  🎉 Super Admin créé !                                ║');
        $this->command->info('╠═══════════════════════════════════════════════════════╣');
        $this->command->line("║  Email    : {$email}");
        $this->command->line("║  MDP      : {$password}");
        $this->command->info('╠═══════════════════════════════════════════════════════╣');
        $this->command->warn('║  ⚠️  CHANGEZ CE MOT DE PASSE DÈS LE PREMIER LOGIN     ║');
        $this->command->warn('║      via /admin/utilisateurs                          ║');
        $this->command->info('╚═══════════════════════════════════════════════════════╝');
        $this->command->newLine();
    }
}
