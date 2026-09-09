<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- suivi_terrain (niveau projet) ---
        Schema::create('suivi_terrain', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projet_id')->constrained('projet')->cascadeOnDelete();
            $table->date('date_visite');
            $table->string('lieu', 200)->nullable();
            $table->text('constats')->nullable();
            $table->text('recommandations')->nullable();
            $table->unsignedTinyInteger('taux_execution_observe')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->index('projet_id');
        });

        // --- relance (niveau projet) ---
        Schema::create('relance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projet_id')->constrained('projet')->cascadeOnDelete();
            $table->foreignId('type_relance_id')->nullable()->constrained('type_relance')->nullOnDelete();
            $table->date('date_relance');
            $table->text('contenu')->nullable();
            $table->boolean('is_repondu')->default(false);
            $table->date('date_reponse')->nullable();
            $table->text('reponse')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->index('projet_id');
        });

        // --- rapport_technique (niveau projet) ---
        Schema::create('rapport_technique', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projet_id')->constrained('projet')->cascadeOnDelete();
            $table->string('reference', 100)->nullable();
            $table->date('date_rapport');
            $table->enum('type_rapport', ['mi_parcours', 'final', 'complementaire'])->default('final');
            $table->enum('statut', ['brouillon', 'soumis', 'valide', 'rejete'])->default('brouillon');
            $table->text('synthese')->nullable();
            $table->text('conclusions')->nullable();
            $table->foreignId('evaluateur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date_validation')->nullable();
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->index('projet_id');
        });

        // --- reserve (niveau rapport) ---
        Schema::create('reserve', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rapport_technique_id')->constrained('rapport_technique')->cascadeOnDelete();
            $table->foreignId('projet_id')->constrained('projet')->restrictOnDelete();
            $table->text('description');
            $table->enum('niveau', ['mineure', 'majeure', 'bloquante'])->default('mineure');
            $table->enum('statut', ['ouverte', 'en_cours', 'levee'])->default('ouverte');
            $table->date('echeance')->nullable();
            $table->date('date_levee')->nullable();
            $table->text('reponse_porteur')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->index('projet_id');
        });

        // --- dano (niveau projet) ---
        Schema::create('dano', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projet_id')->constrained('projet')->cascadeOnDelete();
            $table->foreignId('type_dano_id')->nullable()->constrained('type_dano')->nullOnDelete();
            $table->string('reference', 100)->nullable();
            $table->date('date_dano')->nullable();
            $table->text('contenu')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->index('projet_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dano');
        Schema::dropIfExists('reserve');
        Schema::dropIfExists('rapport_technique');
        Schema::dropIfExists('relance');
        Schema::dropIfExists('suivi_terrain');
    }
};
