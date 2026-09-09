<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Mise à jour de l'enum statut_validation avec les vrais statuts DEES
        DB::statement("ALTER TABLE porteur_proj MODIFY statut_validation ENUM(
            'incomplet', 'annule', 'inelig', 'valide', 'refuse', 'resilie', 'en_attente'
        ) DEFAULT 'en_attente'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE porteur_proj MODIFY statut_validation ENUM(
            'en_attente', 'valide', 'refuse', 'en_cours', 'suspendu', 'cloture'
        ) DEFAULT 'en_attente'");
    }
};
