<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fokontany', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commune_id')->constrained('commune')->cascadeOnDelete();
            $table->foreignId('district_id')->nullable()->constrained('district')->nullOnDelete();
            $table->foreignId('region_id')->constrained('region')->cascadeOnDelete();
            $table->string('libelle', 150);
            $table->string('libelle_normalise', 150)->index();
            $table->timestamps();

            $table->index(['region_id', 'commune_id'], 'idx_fokontany_region_commune');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fokontany');
    }
};
