<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_conflicts', function (Blueprint $table) {
            $table->id();
            // Entité concernée (projet, porteur, porteur_proj, convention...)
            $table->string('entite_type', 50);
            $table->unsignedBigInteger('entite_id')->nullable();
            // Champ divergent
            $table->string('champ', 100);
            // Valeurs en conflit
            $table->text('valeur_db')->nullable();
            $table->text('valeur_excel')->nullable();
            // Traçabilité
            $table->unsignedInteger('ligne_excel')->nullable();
            $table->string('fichier', 255)->nullable();
            // Statut de résolution
            $table->enum('statut', ['en_attente', 'garde_db', 'pris_excel', 'ignore'])->default('en_attente');
            $table->text('note_resolution')->nullable();
            $table->unsignedBigInteger('resolu_par')->nullable();
            $table->timestamp('resolu_le')->nullable();

            $table->timestamps();

            $table->index(['entite_type', 'entite_id'], 'idx_conflit_entite');
            $table->index('statut', 'idx_conflit_statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_conflicts');
    }
};
