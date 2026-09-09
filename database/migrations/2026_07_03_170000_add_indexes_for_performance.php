<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index de performance pour les tables les plus consultées.
 *
 * Ciblés sur les colonnes utilisées dans les filtres, tris et jointures
 * de la table Filament et du journal d'audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── activity_log : le journal d'audit est très consulté ───
        Schema::table('activity_log', function (Blueprint $table) {
            if (! $this->hasIndex('activity_log', 'activity_log_created_at_index')) {
                $table->index('created_at');
            }
            if (! $this->hasIndex('activity_log', 'activity_log_causer_id_index')) {
                $table->index(['causer_type', 'causer_id']);
            }
            if (! $this->hasIndex('activity_log', 'activity_log_event_index')) {
                $table->index('event');
            }
        });

        // ─── porteur_proj : table centrale, filtrée par statut/alerte ───
        Schema::table('porteur_proj', function (Blueprint $table) {
            if (! $this->hasIndex('porteur_proj', 'porteur_proj_statut_validation_index')) {
                $table->index('statut_validation');
            }
            if (! $this->hasIndex('porteur_proj', 'porteur_proj_niveau_alerte_index')) {
                $table->index('niveau_alerte');
            }
            if (! $this->hasIndex('porteur_proj', 'porteur_proj_created_at_index')) {
                $table->index('created_at');
            }
        });

        // ─── partenaire : filtré par porteur_proj_id, nom ───
        Schema::table('partenaire', function (Blueprint $table) {
            if (! $this->hasIndex('partenaire', 'partenaire_nom_index')) {
                $table->index('nom');
            }
        });

        // ─── users : login + filtres département/actif ───
        Schema::table('users', function (Blueprint $table) {
            if (! $this->hasIndex('users', 'users_is_active_index')) {
                $table->index('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex('activity_log_created_at_index');
            $table->dropIndex(['causer_type', 'causer_id']);
            $table->dropIndex('activity_log_event_index');
        });

        Schema::table('porteur_proj', function (Blueprint $table) {
            $table->dropIndex('porteur_proj_statut_validation_index');
            $table->dropIndex('porteur_proj_niveau_alerte_index');
            $table->dropIndex('porteur_proj_created_at_index');
        });

        Schema::table('partenaire', function (Blueprint $table) {
            $table->dropIndex('partenaire_nom_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_is_active_index');
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $rows = \DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        return count($rows) > 0;
    }
};
