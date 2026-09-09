<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->warn('⚠ UsersSeeder désactivé en production. Skipped.');
            return;
        }

        $defaultPassword = Hash::make('Password123!');

        $users = [
            [
                'matricule'         => 'ADM001',
                'first_name'        => 'Super',
                'last_name'         => 'Admin',
                'name'              => 'Super Admin',
                'email'             => 'admin@fmfp.mg',
                'organization_type' => 'internal',
                'role'              => 'admin',
            ],
            [
                'matricule'         => 'DEES001',
                'first_name'        => 'Rivo',
                'last_name'         => 'Rakoto',
                'name'              => 'Rivo Rakoto',
                'email'             => 'charge.projet@fmfp.mg',
                'organization_type' => 'internal',
                'role'              => 'project_manager',
            ],
            [
                'matricule'         => 'EVAL001',
                'first_name'        => 'Hanta',
                'last_name'         => 'Razafy',
                'name'              => 'Hanta Razafy',
                'email'             => 'evaluateur@fmfp.mg',
                'organization_type' => 'internal',
                'role'              => 'evaluateur',
            ],
            [
                'matricule'         => 'DAF001',
                'first_name'        => 'Mialy',
                'last_name'         => 'Andria',
                'name'              => 'Mialy Andria',
                'email'             => 'daf@fmfp.mg',
                'organization_type' => 'internal',
                'role'              => 'daf',
            ],
            [
                'matricule'         => 'DIR001',
                'first_name'        => 'Le',
                'last_name'         => 'Directeur',
                'name'              => 'Le Directeur',
                'email'             => 'direction@fmfp.mg',
                'organization_type' => 'internal',
                'role'              => 'direction',
            ],
        ];

        foreach ($users as $data) {
            $role = $data['role'];
            unset($data['role']);

            $user = User::updateOrCreate(
                ['email' => $data['email']],
                array_merge($data, [
                    'password'           => $defaultPassword,
                    'is_active'          => true,
                    'email_verified_at'  => now(),
                ])
            );

            $user->syncRoles([$role]);
            $this->command->info("✔ {$data['email']} ({$role})");
        }

        $this->command->newLine();
        $this->command->warn('⚠ Mot de passe par défaut : Password123!');
    }
}
