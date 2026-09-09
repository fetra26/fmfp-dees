<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index pour optimiser les requêtes fréquentes :
 *  - Filtres dashboard (niveau_alerte, statut_validation)
 *  - Joins fréquents (porteur_proj_id, projet_id)
 *  - Recherches (porteur.raison_sociale)
 */
return new class extends Migration
{
    public function up(): void
    {
        // PORTEUR_PROJ : colonnes filtrées dans les tabs et widgets
        Schema::table('porteur_proj', function (Blueprint $table) {
            $table->index('statut_validation', 'idx_pp_statut_validation');
            $table->index('situation_alloc',  'idx_pp_situation_alloc');
            $table->index('date_fin',         'idx_pp_date_fin');
            $table->index(['niveau_alerte', 'statut_validation'], 'idx_pp_alerte_statut');
        });

        // BENEF : group by type très fréquent
        Schema::table('benef', function (Blueprint $table) {
            $table->index('type', 'idx_benef_type');
        });

        // PAIEMENT : sum by ligne, where is_annule
        Schema::table('paiement', function (Blueprint $table) {
            $table->index(['ligne', 'is_annule'], 'idx_paiement_ligne_annule');
        });

        // PORTEUR : recherche par nom
        Schema::table('porteur', function (Blueprint $table) {
            $table->index('raison_sociale', 'idx_porteur_raison_sociale');
        });

        // PROJET : tri/recherche par référence + jointures par statut/guichet/vague
        Schema::table('projet', function (Blueprint $table) {
            $table->index('statut_projet_id', 'idx_projet_statut');
            $table->index('guichet_id', 'idx_projet_guichet');
        });
    }

    public function down(): void
    {
        Schema::table('porteur_proj', function (Blueprint $table) {
            $table->dropIndex('idx_pp_statut_validation');
            $table->dropIndex('idx_pp_situation_alloc');
            $table->dropIndex('idx_pp_date_fin');
            $table->dropIndex('idx_pp_alerte_statut');
        });

        Schema::table('benef', function (Blueprint $table) {
            $table->dropIndex('idx_benef_type');
        });

        Schema::table('paiement', function (Blueprint $table) {
            $table->dropIndex('idx_paiement_ligne_annule');
        });

        Schema::table('porteur', function (Blueprint $table) {
            $table->dropIndex('idx_porteur_raison_sociale');
        });

        Schema::table('projet', function (Blueprint $table) {
            $table->dropIndex('idx_projet_statut');
            $table->dropIndex('idx_projet_guichet');
        });
    }
};
