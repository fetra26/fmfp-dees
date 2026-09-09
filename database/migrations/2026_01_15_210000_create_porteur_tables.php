<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- porteur (= entreprise) ---
        Schema::create('porteur', function (Blueprint $table) {
            $table->id();
            $table->string('raison_sociale', 200);
            $table->string('sigle', 50)->nullable();
            $table->string('nif', 30)->nullable()->unique();
            $table->string('cnaps', 30)->nullable();
            $table->string('forme_juridique', 50)->nullable();
            $table->foreignId('secteur_id')->nullable()->constrained('secteur')->nullOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('region')->nullOnDelete();
            $table->string('adresse', 300)->nullable();
            $table->string('ville', 100)->nullable();
            $table->string('telephone', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('responsable_nom', 150)->nullable();
            $table->string('responsable_fonction', 100)->nullable();
            $table->unsignedInteger('nb_salaries')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->index('raison_sociale');
        });

        // --- formateur ---
        Schema::create('formateur', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 200);
            $table->string('prenom', 100)->nullable();
            $table->string('contact', 100)->nullable();
            $table->string('specialite', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        // --- prestataire ---
        Schema::create('prestataire', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 200);
            $table->string('type', 100)->nullable();
            $table->string('contact', 100)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        // --- module ---
        Schema::create('module', function (Blueprint $table) {
            $table->id();
            $table->string('intitule', 300);
            $table->string('code', 50)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module');
        Schema::dropIfExists('prestataire');
        Schema::dropIfExists('formateur');
        Schema::dropIfExists('porteur');
    }
};
