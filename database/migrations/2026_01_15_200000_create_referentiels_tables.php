<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guichet', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('libelle', 150);
            $table->string('region', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('vague', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('libelle', 150);
            $table->unsignedSmallInteger('annee')->nullable();
            $table->foreignId('guichet_id')->nullable()->constrained('guichet')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('secteur', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('libelle', 150);
            $table->timestamps();
        });

        Schema::create('region', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('libelle', 150);
            $table->timestamps();
        });

        Schema::create('statut_projet', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('libelle', 100);
            $table->unsignedTinyInteger('ordre')->default(0);
            $table->timestamps();
        });

        Schema::create('type_dano', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('libelle', 150);
            $table->timestamps();
        });

        Schema::create('type_relance', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('libelle', 150);
            $table->timestamps();
        });

        Schema::create('situation_alloc', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('libelle', 150);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('situation_alloc');
        Schema::dropIfExists('type_relance');
        Schema::dropIfExists('type_dano');
        Schema::dropIfExists('statut_projet');
        Schema::dropIfExists('region');
        Schema::dropIfExists('secteur');
        Schema::dropIfExists('vague');
        Schema::dropIfExists('guichet');
    }
};
