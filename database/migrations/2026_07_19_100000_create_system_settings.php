<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Table clé/valeur pour la configuration globale de SEER.
 *
 * Usage :
 *   SystemSetting::get('projet.champs_obligatoires', ['porteur_id', 'intitule']);
 *   SystemSetting::set('projet.champs_obligatoires', ['porteur_id', 'intitule', 'secteur_id']);
 *
 * Configurable via /admin/parametres onglet "Système" (Super Admin uniquement).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('cle', 100)->unique();
            $table->json('valeur')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Valeurs par défaut
        DB::table('system_settings')->insert([
            [
                'cle'         => 'projet.champs_obligatoires',
                'valeur'      => json_encode(['porteur_id', 'intitule', 'secteur_id', 'guichet_id']),
                'description' => 'Liste des champs obligatoires lors de la création d\'un nouveau projet',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
