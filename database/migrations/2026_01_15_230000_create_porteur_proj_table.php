<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table centrale : lien Projet × Porteur avec tous les attributs opérationnels
        // Une ligne Excel = un enregistrement porteur_proj
        Schema::create('porteur_proj', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projet_id')->constrained('projet')->cascadeOnDelete();
            $table->foreignId('porteur_id')->constrained('porteur')->restrictOnDelete();

            // Identifiants
            $table->string('reference_convention', 100)->nullable();

            // Financement
            $table->unsignedBigInteger('montant_total')->default(0);
            $table->unsignedBigInteger('financement_demande')->default(0);
            $table->unsignedBigInteger('dt_mobilise')->default(0);
            $table->unsignedBigInteger('fonds_additionnel')->default(0);
            $table->unsignedBigInteger('fonds_mutualise')->default(0);
            $table->unsignedBigInteger('financement_autre')->default(0);

            // Contractualisation
            $table->string('appreciation_evaluateur', 500)->nullable();
            $table->enum('statut_validation', ['en_attente', 'valide', 'refuse', 'en_cours', 'suspendu', 'cloture'])->default('en_attente');
            $table->text('motifs')->nullable();
            $table->date('date_notification')->nullable();
            $table->date('date_envoi_convention')->nullable();
            $table->date('date_reception_convention')->nullable();
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();

            // DANO
            $table->string('dano_type', 200)->nullable();
            $table->text('dano_description')->nullable();

            // Paiements (situation globale)
            $table->enum('situation_alloc', ['non_verse', 'partiel', 'total', 'solde', 'annule'])->default('non_verse');

            // Alertes
            $table->enum('niveau_alerte', ['verte', 'orange', 'rouge'])->default('verte');
            $table->date('date_relance_1')->nullable();
            $table->date('date_relance_2')->nullable();
            $table->date('date_mise_en_demeure')->nullable();
            $table->date('date_resiliation')->nullable();

            // Suivi terrain
            $table->date('date_formation_contractants')->nullable();
            $table->date('date_suivi_terrain')->nullable();
            $table->text('observation_suivi')->nullable();

            // Rapport technique
            $table->date('date_arrivee_rapport')->nullable();
            $table->foreignId('evaluateur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date_transfert_evaluateur')->nullable();
            $table->date('date_debut_traitement')->nullable();
            $table->text('reserve_description')->nullable();
            $table->date('date_envoi_reserve')->nullable();
            $table->date('date_relance_reserve_1')->nullable();
            $table->date('date_relance_reserve_2')->nullable();
            $table->string('situation_reserves', 200)->nullable();
            $table->date('date_validation_evaluateur')->nullable();
            $table->date('date_transmission_daf')->nullable();
            $table->text('observations_evaluation')->nullable();

            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('projet_id');
            $table->index('porteur_id');
            $table->index('niveau_alerte');
            $table->index('statut_validation');
        });

        // --- partenaire (lié à porteur_proj) ---
        Schema::create('partenaire', function (Blueprint $table) {
            $table->id();
            $table->foreignId('porteur_proj_id')->constrained('porteur_proj')->cascadeOnDelete();
            $table->string('nom', 200);
            $table->string('cnaps', 30)->nullable();
            $table->unsignedInteger('nb_salaries')->default(0);
            $table->string('contact', 100)->nullable();
            $table->timestamps();
            $table->index('porteur_proj_id');
        });

        // --- benef (bénéficiaires prévu + réalisé) ---
        Schema::create('benef', function (Blueprint $table) {
            $table->id();
            $table->foreignId('porteur_proj_id')->constrained('porteur_proj')->cascadeOnDelete();
            $table->enum('type', ['prevu', 'realise']);
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('h')->default(0);
            $table->unsignedInteger('f')->default(0);
            $table->unsignedInteger('jeunes')->default(0);
            $table->unsignedInteger('fpe')->default(0);
            $table->unsignedInteger('cadres')->default(0);
            $table->timestamps();

            $table->unique(['porteur_proj_id', 'type']);
        });

        // --- formation (prévu / demandé / réalisé) ---
        Schema::create('formation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('porteur_proj_id')->constrained('porteur_proj')->cascadeOnDelete();
            $table->enum('type', ['prevu', 'demande', 'realise'])->default('prevu');
            $table->unsignedSmallInteger('volume_horaire_total')->default(0);
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->index('porteur_proj_id');
        });

        // --- form_mod (pivot Formation × Module × Formateur) ---
        Schema::create('form_mod', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formation_id')->constrained('formation')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('module')->restrictOnDelete();
            $table->foreignId('formateur_id')->nullable()->constrained('formateur')->nullOnDelete();
            $table->unsignedSmallInteger('volume_horaire')->default(0);
            $table->timestamps();

            $table->unique(['formation_id', 'module_id'], 'uniq_form_mod');
        });

        // --- presta_form (pivot Prestataire × Formation) ---
        Schema::create('presta_form', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestataire_id')->constrained('prestataire')->cascadeOnDelete();
            $table->foreignId('formation_id')->constrained('formation')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['prestataire_id', 'formation_id'], 'uniq_presta_form');
        });

        // --- paiement (lié à porteur_proj) ---
        Schema::create('paiement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('porteur_proj_id')->constrained('porteur_proj')->restrictOnDelete();
            $table->enum('ligne', ['J1', 'J2', 'J3']);
            $table->string('reference_ordre', 100)->nullable();
            $table->date('date_paiement');
            $table->unsignedBigInteger('montant');
            $table->boolean('is_annule')->default(false);
            $table->text('motif_annulation')->nullable();
            $table->date('date_annulation')->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index('porteur_proj_id');
            $table->index('ligne');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiement');
        Schema::dropIfExists('presta_form');
        Schema::dropIfExists('form_mod');
        Schema::dropIfExists('formation');
        Schema::dropIfExists('benef');
        Schema::dropIfExists('partenaire');
        Schema::dropIfExists('porteur_proj');
    }
};
