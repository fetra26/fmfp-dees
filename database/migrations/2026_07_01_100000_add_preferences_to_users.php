<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute une colonne JSON `preferences` dans users pour stocker
 * les paramètres de personnalisation par utilisateur :
 *   - colonnes visibles par défaut
 *   - colonnes figées à gauche
 *   - pagination
 *   - tri par défaut
 *   - widgets dashboard actifs
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('preferences')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('preferences');
        });
    }
};
