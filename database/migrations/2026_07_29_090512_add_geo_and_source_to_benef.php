<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('benef', function (Blueprint $table) {
            // Localisation des bénéficiaires (permet stat fiable par région)
            $table->foreignId('region_id')->nullable()->after('type')
                ->constrained('region')->nullOnDelete();
            $table->foreignId('commune_id')->nullable()->after('region_id')
                ->constrained('commune')->nullOnDelete();

            // Provenance de la donnée
            // 'excel_precis'       = valeurs saisies par lieu dans Excel ("100 / 100 / 100")
            // 'excel_reparti_auto' = total unique dans Excel, réparti automatiquement par SEER
            // 'saisie_manuelle'    = complété/modifié à la main dans l'app
            $table->enum('source', ['excel_precis', 'excel_reparti_auto', 'saisie_manuelle'])
                ->default('saisie_manuelle')
                ->after('cadres');

            $table->index('region_id', 'idx_benef_region');
        });
    }

    public function down(): void
    {
        Schema::table('benef', function (Blueprint $table) {
            $table->dropIndex('idx_benef_region');
            $table->dropForeign(['region_id']);
            $table->dropForeign(['commune_id']);
            $table->dropColumn(['region_id', 'commune_id', 'source']);
        });
    }
};
