<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Structure de gestion des utilisateurs par département.
 *
 * - `departement` : services de FMFP-DEES (DEES, DAF, Direction Générale, etc.)
 * - `users.departement_id` : rattachement d'un utilisateur à son service
 *
 * L'isolation des données est PAR PERMISSION (Spatie), pas par département.
 * Le département sert d'organisation logique (annuaire, filtrage, statistiques).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departement', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->string('code', 20)->unique(); // ex: DEES, DAF, DG
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('responsable_id')->nullable()
                ->constrained('users')->nullOnDelete()
                ->comment('Chef/responsable du département');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['is_active', 'code']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('departement_id')->nullable()
                ->after('external_organization')
                ->constrained('departement')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['departement_id']);
            $table->dropColumn('departement_id');
        });

        Schema::dropIfExists('departement');
    }
};
