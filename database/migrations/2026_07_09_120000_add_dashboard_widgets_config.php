<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuration hybride des widgets du tableau de bord :
 *
 *   - departement.dashboard_widgets      : profil par département (défaut)
 *   - users.dashboard_widgets_override   : override individuel (null = hérite du dept)
 *
 * Priorité d'affichage :
 *   1. Si users.dashboard_widgets_override IS NOT NULL → utiliser cette liste
 *   2. Sinon si l'utilisateur a un département → utiliser departement.dashboard_widgets
 *   3. Sinon → défaut système (tous les widgets)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departement', function (Blueprint $table) {
            $table->json('dashboard_widgets')
                ->nullable()
                ->after('is_active')
                ->comment('Slugs des widgets à afficher pour ce département (null = tous)');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('dashboard_widgets_override')
                ->nullable()
                ->after('departement_id')
                ->comment('Override individuel du profil département (null = hérite)');
        });
    }

    public function down(): void
    {
        Schema::table('departement', function (Blueprint $table) {
            $table->dropColumn('dashboard_widgets');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dashboard_widgets_override');
        });
    }
};
