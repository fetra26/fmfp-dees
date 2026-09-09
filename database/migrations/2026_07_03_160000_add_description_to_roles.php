<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute une colonne `description` à la table Spatie `roles`
 * pour permettre la création de rôles custom via l'UI Filament
 * avec une description persistée en base.
 *
 * Les rôles existants sont backfillés depuis config/roles.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('label', 100)->nullable()->after('name');
            $table->text('description')->nullable()->after('label');
        });

        // Backfill depuis config/roles.php pour les rôles existants
        foreach (config('roles.roles', []) as $name => $data) {
            DB::table('roles')
                ->where('name', $name)
                ->update([
                    'label'       => $data['label'] ?? null,
                    'description' => $data['description'] ?? null,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['label', 'description']);
        });
    }
};
