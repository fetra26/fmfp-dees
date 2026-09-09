<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mémoire des mappings de valeurs de référentiels lors des imports.
 *
 * À chaque fois qu'un utilisateur résout un cas d'inconnu dans le wizard d'import
 * ("AGRO ALIMENTAIRE" → utiliser "AGROALIMENTAIRE"), on peut mémoriser la décision
 * pour appliquer automatiquement lors des prochains imports.
 *
 * Exemple d'usage :
 *   $mapping = ImportMapping::where('referentiel', 'secteur')
 *       ->where('valeur_saisie_normalisee', 'AGROALIMENTAIRE')
 *       ->first();
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_mapping', function (Blueprint $table) {
            $table->id();
            $table->string('referentiel', 30);
            $table->string('valeur_saisie', 255);
            $table->string('valeur_saisie_normalisee', 255)
                ->comment('Version normalisée (upper + sans espaces/séparateurs) pour matching');
            $table->enum('action', ['map', 'create', 'ignore'])
                ->comment('map = pointe vers ID existant, create = créer, ignore = sauter les lignes');
            $table->unsignedBigInteger('target_id')->nullable()
                ->comment('ID du référentiel cible si action=map');
            $table->string('target_label', 255)->nullable()
                ->comment('Libellé cible ou libellé à créer');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['referentiel', 'valeur_saisie_normalisee'], 'idx_mapping_unique');
            $table->index('referentiel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_mapping');
    }
};
