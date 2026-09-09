<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Passer statut_validation de ENUM à VARCHAR pour supporter tous
        // les statuts personnalisés du fichier DEES (FINI ET ATTENTE RAPPORT, etc.)
        DB::statement("ALTER TABLE porteur_proj MODIFY statut_validation VARCHAR(50) DEFAULT 'incomplet'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE porteur_proj MODIFY statut_validation ENUM(
            'incomplet', 'annule', 'inelig', 'valide', 'refuse', 'resilie', 'en_attente'
        ) DEFAULT 'en_attente'");
    }
};
