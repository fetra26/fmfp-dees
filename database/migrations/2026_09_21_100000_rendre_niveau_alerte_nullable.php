<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rend niveau_alerte nullable : null signifie « aucune alerte ».
 *
 * La colonne était enum('verte','orange','rouge') NOT NULL, défaut 'verte'.
 * Il n'existait donc aucune façon de dire qu'un projet n'est PAS en alerte, et
 * « verte » recouvrait trois situations sans rapport :
 *
 *   · l'échéance n'est pas encore passée ;
 *   · elle est dépassée de 0 à 29 jours, ce qui n'appelle rien ;
 *   · elle est dépassée de 30 à 59 jours — le seul cas qui justifie une
 *     relance préventive.
 *
 * Les écrans devaient donc recroiser niveau_alerte avec la date de fin pour
 * distinguer les trois, et le compteur « alertes vertes » du tableau de bord
 * affichait en réalité tous les projets à l'heure.
 *
 * Tous les niveaux sont remis à null : ils seront recalculés par
 * ClassifyAlertsJob selon la nouvelle règle, qui exclut désormais les projets
 * clôturés. Sans cette remise à zéro, un projet passé au rouge puis clôturé
 * garderait son rouge indéfiniment — l'ancien job écartait ces projets de sa
 * requête au lieu de réinitialiser leur niveau.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Passage par du SQL brut : la colonne est un enum, que le Schema
        // Builder de Laravel ne sait pas modifier sans doctrine/dbal.
        DB::statement("
            ALTER TABLE porteur_proj
            MODIFY niveau_alerte ENUM('verte', 'orange', 'rouge') NULL DEFAULT NULL
        ");

        DB::table('porteur_proj')->update(['niveau_alerte' => null]);
    }

    public function down(): void
    {
        DB::table('porteur_proj')->whereNull('niveau_alerte')->update(['niveau_alerte' => 'verte']);

        DB::statement("
            ALTER TABLE porteur_proj
            MODIFY niveau_alerte ENUM('verte', 'orange', 'rouge') NOT NULL DEFAULT 'verte'
        ");
    }
};
